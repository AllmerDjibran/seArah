<?php
/**
 * SEARAH — Autentikasi (register / login / token / session).
 * ------------------------------------------------------------------
 * - Password di-hash pakai password_hash() (bcrypt via PASSWORD_DEFAULT).
 * - Token acak 32 byte dikirim ke klien; yang disimpan di DB hanya hash-nya
 *   (SHA-256) supaya kebocoran DB tidak membocorkan token aktif.
 * - Role publik: 'penumpang' & 'pengemudi'. 'admin' tidak bisa didaftarkan publik.
 */

require_once __DIR__ . '/../db.php';

final class Auth
{
    /** Validasi + buat akun. Mengembalikan array user (tanpa hash). */
    public static function register(array $in): array
    {
        $name     = trim($in['name'] ?? '');
        $email    = strtolower(trim($in['email'] ?? ''));
        $phone    = trim($in['phone'] ?? '');
        $password = (string)($in['password'] ?? '');
        $role     = strtolower(trim($in['role'] ?? 'penumpang'));

        // --- validasi ---
        if ($name === '')            self::fail('Nama wajib diisi.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) self::fail('Email tidak valid.');
        if (!in_array($role, ['penumpang', 'pengemudi'], true)) self::fail('Role tidak valid.');
        $cfg = require __DIR__ . '/../config.php';
        if (strlen($password) > 72) self::fail('Password maksimal 72 byte.', 422);
        if (strlen($password) < $cfg['auth']['min_password_length']) {
            self::fail('Password minimal ' . $cfg['auth']['min_password_length'] . ' karakter.');
        }
        if ($phone !== '' && !preg_match('/^(\+62|62|0)8[1-9][0-9]{6,11}$/', preg_replace('/[\s-]/', '', $phone))) {
            self::fail('Nomor HP tidak valid.');
        }

        $pdo = db();

        // email unik
        $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) self::fail('Email ini sudah terdaftar.', 409);

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $localTransaction = empty($GLOBALS['api_transaction']);
        if ($localTransaction) $pdo->beginTransaction();
        try {
            $st = $pdo->prepare(
                'INSERT INTO users (name, email, phone, password_hash, role, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $st->execute([$name, $email, $phone ?: null, $hash, $role, 'aktif']);
            $userId = (int)$pdo->lastInsertId();

            if ($role === 'penumpang') {
                $pdo->prepare('INSERT INTO passengers (user_id, username, is_identity_complete) VALUES (?,?,0)')
                    ->execute([$userId, $in['username'] ?? null]);
            } else { // pengemudi -> perlu verifikasi dokumen
                $pdo->prepare('INSERT INTO drivers (user_id, status) VALUES (?, "menunggu")')
                    ->execute([$userId]);
            }

            if ($localTransaction) $pdo->commit();
        } catch (Throwable $e) {
            if ($localTransaction) $pdo->rollBack();
            throw $e;
        }

        return self::userById($userId);
    }

    /** Login -> [token, user]. */
    public static function login(string $email, string $password): array
    {
        $email = strtolower(trim($email));
        $st = db()->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute([$email]);
        $user = $st->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            self::fail('Email atau password salah.', 401);
        }
        if ($user['status'] !== 'aktif') {
            self::fail('Akun diblokir. Ajukan banding untuk membuka kembali.', 403);
        }

        $token = self::issueToken((int)$user['id']);
        unset($user['password_hash']);
        return ['token' => $token, 'user' => $user];
    }

    /** Buat & simpan token (hash). Return token mentah ke klien. */
    public static function issueToken(int $userId): string
    {
        $cfg   = require __DIR__ . '/../config.php';
        $raw   = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $raw);
        $ttl   = (int)$cfg['auth']['token_ttl'];
        db()->prepare(
            "INSERT INTO auth_tokens (user_id, token_hash, expires_at)
             VALUES (?, ?, datetime('now', '+' || ? || ' seconds'))"
        )->execute([$userId, $hash, $ttl]);
        return $raw;
    }

    /** Ambil user dari token (Bearer / X-Token / ?token). null jika invalid/expired. */
    public static function user(): ?array
    {
        $raw = self::bearer();
        if ($raw === '') return null;
        $hash = hash('sha256', $raw);
        $st = db()->prepare(
            "SELECT u.*, t.expires_at
             FROM auth_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.expires_at > datetime('now')"
        );
        $st->execute([$hash]);
        $u = $st->fetch();
        if (!$u || $u['status'] !== 'aktif') return null;
        unset($u['password_hash'], $u['token_hash'], $u['expires_at']);
        return $u;
    }

    /** Wajib login; balikin user atau hentikan dgn 401. */
    public static function requireUser(?string $role = null): array
    {
        $u = self::user();
        if (!$u) self::fail('Harus login terlebih dahulu.', 401);
        if ($role !== null && $u['role'] !== $role) self::fail('Akses ditolak untuk role ini.', 403);
        return $u;
    }

    /** Hapus token aktif (logout). */
    public static function logout(): void
    {
        $raw = self::bearer();
        if ($raw !== '') {
            db()->prepare('DELETE FROM auth_tokens WHERE token_hash = ?')
                ->execute([hash('sha256', $raw)]);
        }
    }

    /** Ambil user by id (sanitized). */
    public static function userById(int $id): array
    {
        $st = db()->prepare('SELECT id, name, email, phone, role, status, created_at FROM users WHERE id = ?');
        $st->execute([$id]);
        $u = $st->fetch();
        if (!$u) self::fail('Pengguna tidak ditemukan.', 404);
        return $u;
    }

    /** Bersihkan token kedaluwarsa (dipanggil berkala/cron). */
    public static function purgeExpiredTokens(): int
    {
        $st = db()->prepare("DELETE FROM auth_tokens WHERE expires_at <= datetime('now')");
        $st->execute();
        return $st->rowCount();
    }

    /** Baca token dari Authorization: Bearer / X-Token / ?token. */
    private static function bearer(): string
    {
        $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
        if ($h === '' && function_exists('apache_request_headers')) {
            $hs = apache_request_headers();
            $h  = $hs['Authorization'] ?? $hs['authorization'] ?? '';
        }
        if (preg_match('/^Bearer ([a-f0-9]{64})$/i', $h, $m)) return $m[1];
        return '';
    }

    /** Helper error (lempar agar endpoint memutuskan format). */
    private static function fail(string $msg, int $code = 400): void
    {
        throw new AuthException($msg, $code);
    }
}

class AuthException extends RuntimeException
{
    public function __construct(string $message = '', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
