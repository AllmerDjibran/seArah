<?php
/**
 * SEARAH — Helper respons JSON + baca body request.
 */

/** Keluar JSON lalu hentikan eksekusi. */
function json_out($data, int $code = 200): void
{
    if (!empty($GLOBALS['api_transaction'])) {
        db()->exec($code < 400 ? 'COMMIT' : 'ROLLBACK');
        $GLOBALS['api_transaction'] = false;
    }
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Respons sukses standar. */
function json_ok($data = [], int $code = 200): void
{
    json_out(['ok' => true] + (is_array($data) ? $data : ['data' => $data]), $code);
}

/** Respons error standar. */
function json_error(string $message, int $code = 400, array $extra = []): void
{
    json_out(['ok' => false, 'error' => $message] + $extra, $code);
}

/** Baca body request (JSON atau form). */
function request_body(): array
{
    if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 1048576) throw new AppException('Body terlalu besar.', 413);
    $raw = file_get_contents('php://input');
    if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json')) {
        $decoded = json_decode($raw ?: '', true);
        if (!is_array($decoded) || !str_starts_with(ltrim($raw ?: ''), '{')) throw new AppException('Body JSON object wajib.', 422);
        return $decoded;
    }
    if ($raw !== false && $raw !== '') {
        $j = json_decode($raw, true);
        if (is_array($j)) {
            return $j;
        }
    }
    return $_POST ?: [];
}

/** Header CORS ( utk prototype statis yang jalan di origin berbeda ). */
function cors_headers(array $cfg): void
{
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($cfg['cors']['allow_all'] ?? false) {
        header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
    } elseif ($origin && in_array($origin, $cfg['cors']['allowed_origins'] ?? [], true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Vary: Origin');
    }
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Token');
    header('Access-Control-Max-Age: 86400');
}

/** Exception aplikasi dengan kode HTTP (dipakai modul Pricing/Booking/dst.). */
class AppException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
