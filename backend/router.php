<?php
/**
 * Router utk PHP built-in server (dev saja):
 *   php -S 127.0.0.1:8080 router.php
 * Menyimulasikan rewrite: /api/... -> api/index.php.
 * Di hosting Apache produksi, .htaccess melakukan hal yang sama.
 */
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = str_replace('\\', '/', $uri);

// Backend files are never public static resources.

if (preg_match('#^/api(/.*)?$#', $uri, $m)) {
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    require __DIR__ . '/api/index.php';
    return true;
}

http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['ok' => false, 'error' => 'Not found']);
