<?php
declare(strict_types=1);

$requestedPath = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
$requestedPath = str_replace('\\', '/', $requestedPath);

if (str_contains($requestedPath, "\0") || preg_match('~(?:^|/)(?:runtime|\.git|tests|classes)(?:/|$)|(?:^|/)config\.(?:local|example)\.php$|(?:^|/)biblioteca\.sql$|(?:^|/)\.env(?:\.|$)~i', $requestedPath)) {
    http_response_code(404);
    exit;
}

$filePath = realpath(__DIR__ . $requestedPath);
if ($filePath !== false && str_starts_with($filePath, __DIR__ . DIRECTORY_SEPARATOR) && is_file($filePath)) {
    if (strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) === 'php' && basename($filePath) !== 'index.php') {
        http_response_code(404);
        exit;
    }
    return false;
}

if ($requestedPath === '/' || $requestedPath === '/index.php') {
    require __DIR__ . '/index.php';
    return true;
}

http_response_code(404);
echo 'No se encontró la página solicitada.';
