<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

// index.php - Point d'entrée principal (Front Controller)

$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Routes simples, style manuel
switch (true) {
    case $requestUri === '/' || str_starts_with($requestUri, '/?'):
        require __DIR__ . '/src/pages/index.php';
        break;

    case str_starts_with($requestUri, '/admin'):
        require __DIR__ . '/src/pages/admin.php';
        break;

    case $requestUri === '/vote-gallery':
        require __DIR__ . '/src/pages/vote-gallery.php';
        break;

    case $requestUri === '/upload':
        require __DIR__ . '/upload.php';
        break;

    case $requestUri === '/delete':
        require __DIR__ . '/delete.php';
        break;

    case $requestUri === '/vote':
        require __DIR__ . '/vote.php';
        break;

    case str_starts_with($requestUri, '/public/'):
        // Laisse le serveur web servir les fichiers statiques normalement
        return false;

    default:
        http_response_code(404);
        echo '404 Not Found';
        break;
}
