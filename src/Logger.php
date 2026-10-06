<?php
declare(strict_types=1);

final class Logger
{
    public static function call(
        PDO $db,
        int $callId,
        string $eventType,
        ?string $message = null,
        ?int $orderId = null,
        ?string $metadata = null
    ): void {
        $stmt = $db->prepare(
            'INSERT INTO call_logs
                (call_session_id, order_id, event_type, message, metadata)
             VALUES
                (:call_id, :order_id, :event_type, :message, :metadata)'
        );

        $stmt->execute([
            ':call_id' => $callId,
            ':order_id' => $orderId,
            ':event_type' => $eventType,
            ':message' => $message,
            ':metadata' => $metadata,
        ]);
    }
}
