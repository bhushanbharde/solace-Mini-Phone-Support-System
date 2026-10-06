<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $input = Request::input();
    Request::required($input, ['CallSid', 'From']);

    $session = $callSessions->start(
        (string)$input['CallSid'],
        (string)$input['From'],
        isset($input['To']) ? (string)$input['To'] : null
    );

    $orders = $orderService->recentOrdersForCaller(
        $session['customer_id'] !== null ? (int)$session['customer_id'] : null,
        $session['caller_phone']
    );

    Response::json([
        'success' => true,
        'message' => 'Inbound call session created.',
        'call' => [
            'id' => (int)$session['id'],
            'call_sid' => $session['call_sid'],
            'caller_phone' => $session['caller_phone'],
            'called_phone' => $session['called_phone'],
            'started_at' => $session['started_at'],
            'state' => $session['state'],
        ],
        'caller' => $session['customer_id'] !== null
            ? [
                'identified' => true,
                'customer_id' => (int)$session['customer_id'],
                'name' => $session['customer_name'],
            ]
            : [
                'identified' => false,
                'message' => 'Unknown caller.',
            ],
        'recent_orders' => array_map(
            static fn(array $order): array => [
                'id' => (int)$order['id'],
                'order_number' => $order['order_number'],
                'total_amount' => $order['total_amount'],
                'status' => $order['status'],
                'created_at' => $order['created_at'],
            ],
            $orders
        ),
        'menu' => [
            '1' => 'View recent orders',
            '2' => 'Place an order on hold',
            '3' => 'Leave a voicemail',
            '4' => 'Talk to support',
        ],
        'next_endpoint' => 'menu_handler.php?call_id=' . (int)$session['id'] . '&digit=1',
    ]);
} catch (InvalidArgumentException $e) {
    Response::error($e->getMessage(), 422);
} catch (Throwable $e) {
    Response::error('Internal server error.', 500, [
        'detail' => $e->getMessage(),
    ]);
}
