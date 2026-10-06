<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

try {
    $db->query('SELECT 1');
    Response::json([
        'success' => true,
        'status' => 'ok',
        'database' => 'connected',
    ]);
} catch (Throwable $e) {
    Response::error('Database connection failed.', 500);
}
