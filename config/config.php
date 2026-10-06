<?php

declare(strict_types=1);

return [
    'db' => [
        'socket' => '/Applications/MAMP/tmp/mysql/mysql.sock',
        'database' => 'phone_support',
        'username' => 'root',
        'password' => 'root',
        'charset' => 'utf8mb4',
    ],
    'app' => [
        'timezone' => 'Asia/Kolkata',
        'max_invalid_attempts' => 3,
    ],
];
