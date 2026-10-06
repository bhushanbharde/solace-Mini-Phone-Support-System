<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $input = Request::input();
    Request::required($input, ['call_id']);

    $session = $callSessions->getById((int)$input['call_id']);

    $logs = $db->prepare(
        'SELECT id, event_type, order_id, message, metadata, created_at
         FROM call_logs
         WHERE call_session_id = :call_id
         ORDER BY id ASC'
    );
    $logs->execute([':call_id' => $session['id']]);

    Response::json([
        'success' => true,
        'call' => $session,
        'logs' => $logs->fetchAll(),
    ]);
} catch (RuntimeException $e) {
    Response::error($e->getMessage(), 404);
} catch (Throwable $e) {
    Response::error('Internal server error.', 500, [
        'detail' => $e->getMessage(),
    ]);
}
