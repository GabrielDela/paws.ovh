<?php
// config/config.php

define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_FOLDER', BASE_PATH . '/public');
define('ADMIN_TOKEN', getenv('ADMIN_TOKEN') ?: '');
define('MAX_FILE_SIZE', 10 * 1024 * 1024);
define('MAX_IMAGE_WIDTH', 5000); // in pixels
define('MAX_IMAGE_HEIGHT', 5000); // in pixels

// Start session for CSRF protection
session_start();

function getCsrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(string $token): bool
{
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function getAlphabet(): array
{
    return array_merge(range('A', 'Z'), ['*', 'TOP']);
}

function getClientIp(): string {
    return $_SERVER['HTTP_CLIENT_IP']
        ?? $_SERVER['HTTP_X_FORWARDED_FOR']
        ?? $_SERVER['REMOTE_ADDR']
        ?? 'unknown';
}

function getIpToken(): string {
    return hash('sha256', 'paws-ip-token-salt-' . getClientIp());
}