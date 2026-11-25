<?php
/**
 * SEARAH — Koneksi SQLite (PDO) + inisialisasi skema.
 * ------------------------------------------------------------------
 * - PRAGMA foreign_keys = ON dijalankan di SETIAP koneksi (wajib utk SQLite).
 * - Skema (schema.sql) dijalankan otomatis saat file DB pertama kali dibuat.
 * - Rekap: jalankan ulang lewat db_migrate() atau set env SEARAH_MIGRATE=1.
 */

/**
 * Ambil (atau buat) koneksi PDO singleton.
 *
 * @return PDO
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    /** @var array $cfg */
    $cfg = require __DIR__ . '/config.php';

    date_default_timezone_set($cfg['app']['timezone']);

    // pastikan folder data ada
    if (!is_dir($cfg['paths']['data'])) {
        @mkdir($cfg['paths']['data'], 0775, true);
    }

    $isNew = !is_file($cfg['paths']['db']);

    $pdo = new PDO('sqlite:' . $cfg['paths']['db'], null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // --- PRAGMA wajib ---
    $pdo->exec('PRAGMA foreign_keys = ON');          // aktifkan FK constraint
    $pdo->exec('PRAGMA busy_timeout = 5000');        // tahan lock sesaat (shared hosting)
    // WAL bagus utk konkurensi tapi bisa gagal di filesystem jaringan.
    // Aman bila dibungkus try/catch (fallback ke jurnal default).
    try {
        $pdo->exec('PRAGMA journal_mode = WAL');
    } catch (Throwable $e) {
        // abaikan; pakai mode default
    }

    // migrasi saat DB baru atau diminta eksplisit
    if ($isNew || getenv('SEARAH_MIGRATE') === '1') {
        db_migrate($pdo, $cfg);
    }

    return $pdo;
}

/**
 * Jalankan schema.sql. Placeholder hash admin diganti hash asli.
 */
function db_migrate(?PDO $pdo = null, ?array $cfg = null): void
{
    $pdo = $pdo ?? db();
    $cfg = $cfg ?? require __DIR__ . '/config.php';

    $sql = file_get_contents($cfg['paths']['schema']);
    if ($sql === false) {
        throw new RuntimeException('schema.sql tidak ditemukan: ' . $cfg['paths']['schema']);
    }

    // isi hash password admin seed
    $password = $cfg['app']['demo'] ? '12345678' : $cfg['auth']['admin_default_password'];
    $hash = password_hash($password !== '' ? $password : bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $sql = str_replace('__REPLACE_WITH_PASSWORD_HASH__', $hash, $sql);

    // PDO SQLite bisa mengeksekusi multi-statement
    $pdo->exec($sql);
    if (!$cfg['app']['demo'] && $password === '') {
        $pdo->exec("UPDATE users SET status='dinonaktifkan' WHERE email='admin@searah.id'");
    }
    if ($cfg['app']['demo']) {
        foreach (['pengemudi', 'penumpang'] as $role) {
            $pdo->prepare('INSERT OR IGNORE INTO users (name,email,password_hash,role,status) VALUES (?,?,?,?,?)')
                ->execute(['Demo ' . $role, $role . '@searah.id', password_hash('12345678', PASSWORD_DEFAULT), $role, 'aktif']);
            $st = $pdo->prepare('SELECT id FROM users WHERE email=?');
            $st->execute([$role . '@searah.id']);
            $id = $st->fetchColumn();
            $pdo->prepare($role === 'pengemudi' ? "INSERT OR IGNORE INTO drivers (user_id,status) VALUES (?,'disetujui')" : 'INSERT OR IGNORE INTO passengers (user_id) VALUES (?)')->execute([$id]);
        }
    }
}

/** Helper query cepat dengan prepared statement. */
function db_run(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}
