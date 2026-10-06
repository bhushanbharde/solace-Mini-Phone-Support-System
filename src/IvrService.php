<?php
declare(strict_types=1);

final class IvrService
{
    private const MAIN_MENU = [
        '1' => 'view_orders',
        '2' => 'place_hold',
        '3' => 'voicemail',
        '4' => 'support',
    ];

    public function __construct(
        private PDO $db,
        private CallSessionService $sessions,
        private OrderService $orders,
        private array $config
    ) {
    }

    public function handle(int $callId, string $digit, array $input): array
    {
        $session = $this->sessions->getById($callId);
        $digit = trim($digit);

        if ($digit === '') {
            return $this->invalid($session, 'No DTMF digit supplied.');
        }

        Logger::call(
            $this->db,
            $callId,
            'menu_selection',
            "DTMF digit received: {$digit}",
            null,
            json_encode(['digit' => $digit, 'state' => $session['state']])
        );

        return match ($session['state']) {
            'main_menu' => $this->mainMenu($session, $digit),
            'select_order_view' => $this->selectOrder($session, $digit, 'view'),
            'select_order_hold' => $this->selectOrder($session, $digit, 'hold'),
            'voicemail' => $this->saveVoicemail($session, $input),
            'support' => $this->routeSupport($session),
            default => $this->invalid($session, "Unsupported IVR state: {$session['state']}"),
        };
    }

    private function mainMenu(array $session, string $digit): array
    {
        if (!isset(self::MAIN_MENU[$digit])) {
            return $this->invalid($session, 'Invalid main menu option. Please choose 1, 2, 3, or 4.');
        }

        $callId = (int)$session['id'];

        switch (self::MAIN_MENU[$digit]) {
            case 'view_orders':
                $this->sessions->resetInvalidAttempts($callId);
                $this->sessions->updateState($callId, 'select_order_view');

                $orders = $this->orders->recentOrdersForCaller(
                    $session['customer_id'] !== null ? (int)$session['customer_id'] : null,
                    $session['caller_phone']
                );

                Logger::call(
                    $this->db,
                    $callId,
                    'order_selection_prompt',
                    'Prompted caller to select a recent order for viewing.'
                );

                return [
                    'success' => true,
                    'state' => 'select_order_view',
                    'message' => 'Select one of the recent orders by number.',
                    'orders' => $this->numberOrders($orders),
                ];

            case 'place_hold':
                $this->sessions->resetInvalidAttempts($callId);
                $this->sessions->updateState($callId, 'select_order_hold');

                $orders = $this->orders->recentOrdersForCaller(
                    $session['customer_id'] !== null ? (int)$session['customer_id'] : null,
                    $session['caller_phone']
                );

                Logger::call(
                    $this->db,
                    $callId,
                    'order_hold_prompt',
                    'Prompted caller to select an order to place on hold.'
                );

                return [
                    'success' => true,
                    'state' => 'select_order_hold',
                    'message' => 'Select the order you want to place on hold.',
                    'orders' => $this->numberOrders($orders),
                ];

            case 'voicemail':
                $this->sessions->resetInvalidAttempts($callId);
                $this->sessions->updateState($callId, 'voicemail');
                Logger::call($this->db, $callId, 'voicemail_prompt', 'Caller selected voicemail.');
                return [
                    'success' => true,
                    'state' => 'voicemail',
                    'message' => 'Provide recording_url or recording_id in the next request.',
                ];

            case 'support':
                $this->sessions->resetInvalidAttempts($callId);
                return $this->routeSupport($session);
        }

        return $this->invalid($session, 'Unable to process menu option.');
    }

    private function selectOrder(array $session, string $selection, string $action): array
    {
        if (!ctype_digit($selection) || (int)$selection < 1) {
            return $this->invalid($session, 'Invalid order selection. Please select a listed order number.');
        }

        $orders = $this->orders->recentOrdersForCaller(
            $session['customer_id'] !== null ? (int)$session['customer_id'] : null,
            $session['caller_phone']
        );

        $index = (int)$selection - 1;
        if (!isset($orders[$index])) {
            return $this->invalid($session, 'Selected order is not available for this caller.');
        }

        $order = $orders[$index];

        if ($session['customer_id'] === null) {
            return $this->invalid($session, 'Unknown callers cannot access order information.');
        }

        $ownedOrder = $this->orders->findOwnedOrder(
            (int)$order['id'],
            (int)$session['customer_id']
        );

        if (!$ownedOrder) {
            Logger::call(
                $this->db,
                (int)$session['id'],
                'error',
                'Order ownership validation failed.',
                (int)$order['id']
            );
            return $this->invalid($session, 'Order validation failed.');
        }

        $callId = (int)$session['id'];
        $this->sessions->setSelectedOrder($callId, (int)$ownedOrder['id']);

        Logger::call(
            $this->db,
            $callId,
            'order_selected',
            "Order selected: {$ownedOrder['order_number']}",
            (int)$ownedOrder['id']
        );

        if ($action === 'view') {
            $this->sessions->updateState($callId, 'main_menu');
            return [
                'success' => true,
                'state' => 'main_menu',
                'message' => 'Order details retrieved.',
                'order' => $ownedOrder,
                'next' => 'main_menu',
            ];
        }

        try {
            $updated = $this->orders->holdOrder(
                (int)$ownedOrder['id'],
                (int)$session['customer_id']
            );

            Logger::call(
                $this->db,
                $callId,
                'order_hold',
                "Order {$updated['order_number']} placed on hold.",
                (int)$updated['id']
            );

            $this->sessions->updateState($callId, 'main_menu');

            return [
                'success' => true,
                'state' => 'main_menu',
                'message' => 'Order has been placed on hold.',
                'order' => $updated,
                'next' => 'main_menu',
            ];
        } catch (Throwable $e) {
            Logger::call(
                $this->db,
                $callId,
                'error',
                'Order hold failed: ' . $e->getMessage(),
                (int)$ownedOrder['id']
            );

            return $this->invalid($session, 'Unable to place the order on hold.');
        }
    }

    private function saveVoicemail(array $session, array $input): array
    {
        $recordingUrl = trim((string)($input['recording_url'] ?? ''));
        $recordingId = trim((string)($input['recording_id'] ?? ''));

        if ($recordingUrl === '' && $recordingId === '') {
            return $this->invalid($session, 'Provide recording_url or recording_id.');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO voicemail_logs
                (call_session_id, recording_url, recording_identifier)
             VALUES
                (:call_id, :recording_url, :recording_identifier)'
        );

        $stmt->execute([
            ':call_id' => $session['id'],
            ':recording_url' => $recordingUrl !== '' ? $recordingUrl : null,
            ':recording_identifier' => $recordingId !== '' ? $recordingId : null,
        ]);

        Logger::call(
            $this->db,
            (int)$session['id'],
            'voicemail_saved',
            'Voicemail recording information stored.',
            null,
            json_encode([
                'has_recording_url' => $recordingUrl !== '',
                'has_recording_id' => $recordingId !== '',
            ])
        );

        $this->sessions->updateState((int)$session['id'], 'main_menu');

        return [
            'success' => true,
            'state' => 'main_menu',
            'message' => 'Voicemail saved successfully.',
            'next' => 'main_menu',
        ];
    }

    private function routeSupport(array $session): array
    {
        $queue = 'support';
        $decision = 'routed_to_support_queue';

        $stmt = $this->db->prepare(
            'INSERT INTO support_routes
                (call_session_id, queue_name, routing_decision)
             VALUES
                (:call_id, :queue_name, :decision)'
        );

        $stmt->execute([
            ':call_id' => $session['id'],
            ':queue_name' => $queue,
            ':decision' => $decision,
        ]);

        Logger::call(
            $this->db,
            (int)$session['id'],
            'support_transfer',
            'Caller routed to support queue.',
            null,
            json_encode(['queue' => $queue, 'decision' => $decision])
        );

        $this->sessions->updateState((int)$session['id'], 'completed');
        $this->sessions->finish((int)$session['id']);

        return [
            'success' => true,
            'state' => 'completed',
            'message' => 'Call routed to support queue.',
            'queue' => $queue,
            'routing_decision' => $decision,
        ];
    }

    private function invalid(array $session, string $message): array
    {
        $callId = (int)$session['id'];
        $attempts = $this->sessions->incrementInvalidAttempts($callId);

        Logger::call(
            $this->db,
            $callId,
            'invalid_input',
            $message,
            null,
            json_encode(['attempt' => $attempts])
        );

        $max = (int)$this->config['app']['max_invalid_attempts'];

        if ($attempts >= $max) {
            $this->sessions->finish($callId);
            Logger::call(
                $this->db,
                $callId,
                'fallback',
                'Maximum invalid attempts reached; call ended by fallback.'
            );

            return [
                'success' => false,
                'state' => 'completed',
                'message' => 'Too many invalid attempts. Call ended by fallback.',
                'invalid_attempts' => $attempts,
            ];
        }

        return [
            'success' => false,
            'state' => $session['state'],
            'message' => $message,
            'invalid_attempts' => $attempts,
            'retry_remaining' => $max - $attempts,
        ];
    }

    private function numberOrders(array $orders): array
    {
        $result = [];
        foreach ($orders as $index => $order) {
            $result[] = [
                'selection' => $index + 1,
                'id' => (int)$order['id'],
                'order_number' => $order['order_number'],
                'total_amount' => $order['total_amount'],
                'status' => $order['status'],
                'created_at' => $order['created_at'],
            ];
        }
        return $result;
    }
}
