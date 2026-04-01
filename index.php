<?php
// Front Controller — routes all requests to appropriate handlers

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch (true) {
    case $path === '/' || $path === '':
        require __DIR__ . '/src/pages/index.php';
        break;

    case str_starts_with($path, '/admin'):
        require __DIR__ . '/src/pages/admin.php';
        break;

    case $path === '/upload':
        require __DIR__ . '/upload.php';
        break;

    case $path === '/delete':
        require __DIR__ . '/delete.php';
        break;

    case $path === '/regenerate-thumbnails':
        require __DIR__ . '/regenerate-thumbnails.php';
        break;

    case $path === '/vote':
        require __DIR__ . '/vote.php';
        break;

    case $path === '/list':
        require __DIR__ . '/list.php';
        break;

    case $path === '/submit':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require __DIR__ . '/handle-submit.php';
        } else {
            require __DIR__ . '/src/pages/submit.php';
        }
        break;

    case $path === '/moderate':
        require __DIR__ . '/handle-moderate.php';
        break;

    case str_starts_with($path, '/public/'):
        // Static files served by the web server via .htaccess
        return false;

    default:
        http_response_code(404);
        require __DIR__ . '/src/pages/404.php';
        break;
}
