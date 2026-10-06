<?php
declare(strict_types=1);

final class CallSessionService
{
    public function __construct(private PDO $db, private array $config)
    {
    }

    public function start(string $callSid, string $from, ?string $to): array
    {
        $from = PhoneNormalizer::normalize($from);
        $to = $to !== null && $to !== '' ? PhoneNormalizer::normalize($to) : null;

        $existing = $this->db->prepare(
            'SELECT * FROM call_sessions WHERE call_sid = :call_sid LIMIT 1'
        );
        $existing->execute([':call_sid' => $callSid]);
        $session = $existing->fetch();

        if ($session) {
            return $session;
        }

        $customerStmt = $this->db->prepare(
            'SELECT id, name, phone
             FROM customers
             WHERE phone = :phone
             LIMIT 1'
        );
        $customerStmt->execute([':phone' => $from]);
        $customer = $customerStmt->fetch();

        $stmt = $this->db->prepare(
            'INSERT INTO call_sessions
                (call_sid, caller_phone, called_phone, customer_id, state, started_at)
             VALUES
                (:call_sid, :caller_phone, :called_phone, :customer_id, :state, NOW())'
        );

        $stmt->execute([
            ':call_sid' => $callSid,
            ':caller_phone' => $from,
            ':called_phone' => $to,
            ':customer_id' => $customer['id'] ?? null,
            ':state' => 'main_menu',
        ]);

        $id = (int)$this->db->lastInsertId();
        Logger::call(
            $this->db,
            $id,
            'call_started',
            $customer
                ? "Known caller identified: {$customer['name']}"
                : 'Unknown caller',
            null,
            json_encode(['customer_id' => $customer['id'] ?? null])
        );

        return $this->getById($id);
    }

    public function getById(int $id): array
    {
        $stmt = $this->db->prepare(
            'SELECT cs.*, c.name AS customer_name, c.phone AS customer_phone
             FROM call_sessions cs
             LEFT JOIN customers c ON c.id = cs.customer_id
             WHERE cs.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $session = $stmt->fetch();

        if (!$session) {
            throw new RuntimeException('Call session not found.');
        }

        return $session;
    }

    public function updateState(int $id, string $state): void
    {
        $stmt = $this->db->prepare(
            'UPDATE call_sessions SET state = :state, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':state' => $state, ':id' => $id]);
    }

    public function setSelectedOrder(int $id, ?int $orderId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE call_sessions
             SET selected_order_id = :order_id, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([':order_id' => $orderId, ':id' => $id]);
    }

    public function incrementInvalidAttempts(int $id): int
    {
        $stmt = $this->db->prepare(
            'UPDATE call_sessions
             SET invalid_attempts = invalid_attempts + 1, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);

        $session = $this->getById($id);
        return (int)$session['invalid_attempts'];
    }

    public function resetInvalidAttempts(int $id): void
    {
        $stmt = $this->db->prepare(
            'UPDATE call_sessions SET invalid_attempts = 0, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public function finish(int $id): void
    {
        $stmt = $this->db->prepare(
            "UPDATE call_sessions
             SET state = 'completed', ended_at = NOW(), updated_at = NOW()
             WHERE id = :id"
        );
        $stmt->execute([':id' => $id]);
    }
}
