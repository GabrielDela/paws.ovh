<?php

declare(strict_types=1);

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

require_once __DIR__ . '/../app/bootstrap.php';

$endpoint = $_GET['endpoint'] ?? '';
$apiController->dispatch($endpoint);
