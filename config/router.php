<?php
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if (str_starts_with($uri, '/public/')) {
    return false;
}

$routes = [
    '/' => __DIR__ . '/../src/pages/index.php',
    '/admin' => __DIR__ . '/../src/pages/admin/index.php',
    '/vote-gallery' => __DIR__ . '/../src/pages/vote-gallery.php',
    '/api/news/list' => __DIR__ . '/../src/services/news/list.php',
    '/api/news/upload' => __DIR__ . '/../src/services/news/upload.php',
    '/api/news/delete' => __DIR__ . '/../src/services/news/delete.php',
    '/api/news/vote' => __DIR__ . '/../src/services/news/vote.php',
    '/api/auth/ip' => __DIR__ . '/../src/services/auth/ip.php',
];

if (isset($routes[$uri])) {
    require $routes[$uri];
    return;
}

http_response_code(404);
echo 'Not Found';
