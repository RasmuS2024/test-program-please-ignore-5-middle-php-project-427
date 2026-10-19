<?php

require __DIR__ . '/../vendor/autoload.php';

if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $file = __DIR__ . $path;

    if ($path !== '/' && is_file($file)) {
        return false;
    }

    if ($path !== '/api' && !str_starts_with($path, '/api/')) {
        header('Content-Type: text/html; charset=utf-8');
        readfile(__DIR__ . '/index.html');
        exit;
    }
}

$app = createApp();
$app->run();