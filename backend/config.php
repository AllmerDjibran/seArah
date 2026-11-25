<?php
/**
 * SEARAH — Konfigurasi aplikasi (PHP native + SQLite)
 * ------------------------------------------------------------------
 * Mengembalikan array konfigurasi. Nilai rahasia (Duitku, password admin)
 * dibaca dari config.local.php (TIDAK di-commit) atau environment variable.
 *
 * Cara pakai:  $CFG = require __DIR__ . '/config.php';
 */

$base = __DIR__;

// muat secret lokal bila ada
$secrets = [];
if (is_file($base . '/config.local.php')) {
    $secrets = require $base . '/config.local.php';
}

$env = static function (string $k, $d = null) {
    $v = getenv($k);
    return ($v !== false && $v !== '') ? $v : $d;
};

$duitkuMode = $secrets['duitku']['mode'] ?? $env('DUITKU_MODE', 'sandbox');

return [
    'app' => [
        'name'     => 'SEARAH',
        'timezone' => 'Asia/Jakarta',
        'debug'    => $env('SEARAH_DEBUG', '0') === '1',
        'environment' => $env('SEARAH_ENV', 'production'),
        'demo' => $env('SEARAH_ENV', 'production') === 'development' && $env('SEARAH_DEMO', '0') === '1',
    ],

    'paths' => [
        'base'   => $base,
        'data'   => $base . '/data',
        'db'     => $env('SEARAH_DB_PATH', $base . '/data/searah.sqlite'),
        'schema' => $base . '/schema.sql',
    ],

    'auth' => [
        // umur token (detik). default 7 hari
        'token_ttl' => 7 * 24 * 3600,
        // password admin seed bila DB baru (ganti di production)
        'admin_default_password' => $secrets['admin_default_password'] ?? $env('SEARAH_ADMIN_PASSWORD', ''),
        'min_password_length'    => 8,
    ],

    'duitku' => [
        'mode'          => $duitkuMode, // 'sandbox' | 'production'
        'merchant_code' => $secrets['duitku']['merchant_code'] ?? $env('DUITKU_MERCHANT_CODE', ''),
        'api_key'       => $secrets['duitku']['api_key'] ?? $env('DUITKU_API_KEY', ''),
        // Base resmi (v2). Sandbox=sandbox.duitku.com, Production=passport.duitku.com
        'base_url'      => $duitkuMode === 'production' ? 'https://passport.duitku.com' : 'https://sandbox.duitku.com',
        'endpoints' => [
            'inquiry' => '/webapi/api/merchant/v2/inquiry',
            'status'  => '/webapi/api/merchant/transactionStatus',
        ],
        // QRIS memakai kode channel: SP (ShopeePay), NQ (Nobu), SQ (Nusapay)
        'payment_method' => $secrets['duitku']['payment_method'] ?? $env('DUITKU_PAYMENT_METHOD', 'SQ'),
        'expiry_period'  => (int)($secrets['duitku']['expiry_period'] ?? $env('DUITKU_EXPIRY_MIN', '15')), // menit
        // callbackUrl = notifikasi server-to-server (POST, ada signature). return_url = redirect user (GET).
        'callback_url'   => $secrets['duitku']['callback_url'] ?? $env('DUITKU_CALLBACK_URL', ''),
        'return_url'     => $secrets['duitku']['return_url'] ?? $env('DUITKU_RETURN_URL', ''),
    ],

    'cors' => [
        // dev: izinkan origin prototype. production: persempit.
        'allow_all'      => false,
        'allowed_origins' => ['http://localhost:5500', 'http://127.0.0.1:5500', 'null'],
    ],
];
