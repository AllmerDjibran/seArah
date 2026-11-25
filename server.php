<?php
/**
 * SEARAH — Router utk PHP built-in server (dev).
 *   php -S 127.0.0.1:8080 server.php
 * Menyajikan:
 *   - /api/...  -> backend/api/index.php (API PHP + SQLite)
 *   - sisanya   -> file statis prototype (index.html, pages/, css/, js/, assets/)
 */
$root = __DIR__;
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri  = str_replace('\\', '/', $uri);

// --- API ---
if (preg_match('#^/api(/.*)?$#', $uri, $m)) {
    $_SERVER['SCRIPT_NAME'] = '/api/index.php';
    require $root . '/backend/api/index.php';
    return true;
}

// --- index.html utk '/' ---
if ($uri === '/' || $uri === '') {
    readfile($root . '/index.html');
    return true;
}

// --- file statis (cegah directory traversal) ---
$file = realpath($root . $uri);
$relative = $file === false ? '' : str_replace('\\', '/', substr($file, strlen($root) + 1));
if ($file !== false && strncmp($file, $root . DIRECTORY_SEPARATOR, strlen($root) + 1) === 0
    && is_file($file) && preg_match('#^(index\.html|(?:pages|css|js|assets)/[^.].*\.(?:html|css|js|png|jpg|jpeg|svg|webp|ico|woff2?))$#i', $relative)) {
    return false; // serahkan ke server bawaan (serve dg mime benar)
}

http_response_code(404);
echo "404 Not Found: {$uri}";
