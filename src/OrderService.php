<?php
declare(strict_types=1);

final class OrderService
{
    public function __construct(private PDO $db)
    {
    }

    public function recentOrdersForCaller(?int $customerId, string $phone, int $limit = 3): array
    {
        if ($customerId !== null) {
            $stmt = $this->db->prepare(
                'SELECT id, order_number, customer_id, total_amount, status, created_at
                 FROM orders
                 WHERE customer_id = :customer_id
                 ORDER BY created_at DESC
                 LIMIT 3'
            );
            $stmt->execute([':customer_id' => $customerId]);
            return $stmt->fetchAll();
        }

        // Unknown callers have no associated orders.
        return [];
    }

    public function findOwnedOrder(int $orderId, int $customerId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, order_number, customer_id, total_amount, status, created_at
             FROM orders
             WHERE id = :id AND customer_id = :customer_id
             LIMIT 1'
        );
        $stmt->execute([
            ':id' => $orderId,
            ':customer_id' => $customerId,
        ]);

        $order = $stmt->fetch();
        return $order ?: null;
    }

    public function holdOrder(int $orderId, int $customerId): array
    {
        $this->db->beginTransaction();

        try {
            $order = $this->findOwnedOrder($orderId, $customerId);

            if (!$order) {
                throw new RuntimeException('Order does not exist or does not belong to caller.');
            }

            if ($order['status'] === 'on_hold') {
                $this->db->commit();
                return $order;
            }

            $stmt = $this->db->prepare(
                "UPDATE orders
                 SET status = 'on_hold', updated_at = NOW()
                 WHERE id = :id AND customer_id = :customer_id"
            );
            $stmt->execute([
                ':id' => $orderId,
                ':customer_id' => $customerId,
            ]);

            $log = $this->db->prepare(
                'INSERT INTO order_logs (order_id, action, comment)
                 VALUES (:order_id, :action, :comment)'
            );
            $log->execute([
                ':order_id' => $orderId,
                ':action' => 'hold',
                ':comment' => 'Order placed on hold through phone support IVR.',
            ]);

            $this->db->commit();

            return $this->findOwnedOrder($orderId, $customerId);
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
