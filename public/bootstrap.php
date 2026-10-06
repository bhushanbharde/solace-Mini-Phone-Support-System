<?php
declare(strict_types=1);

$config = require __DIR__ . '/../config/config.php';
date_default_timezone_set($config['app']['timezone']);

require_once __DIR__ . '/../src/Database.php';
require_once __DIR__ . '/../src/Response.php';
require_once __DIR__ . '/../src/Request.php';
require_once __DIR__ . '/../src/Logger.php';
require_once __DIR__ . '/../src/PhoneNormalizer.php';
require_once __DIR__ . '/../src/CallSessionService.php';
require_once __DIR__ . '/../src/OrderService.php';
require_once __DIR__ . '/../src/IvrService.php';

$db = Database::connection();
$callSessions = new CallSessionService($db, $config);
$orderService = new OrderService($db);
$ivrService = new IvrService($db, $callSessions, $orderService, $config);
