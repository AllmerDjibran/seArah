# Deployment SeArah PHP SQLite

Paket berisi frontend, assets, PHP API, schema dan router development. Tidak termasuk executable PHP: hosting wajib menyediakan PHP 8.2+, pdo_sqlite, sqlite3/PDO, curl, mbstring dan GD (JPEG/PNG identity sanitization). Tidak membutuhkan npm atau Node saat runtime.

## Development demo eksplisit

Dari root aplikasi jalankan php -S 127.0.0.1:8080 server.php. Sebelum menjalankan, set environment SEARAH_ENV=development, SEARAH_DEMO=1, SEARAH_SUPER_ADMIN_IDS=1, SEARAH_DB_PATH ke file BARU di direktori privat writable di luar webroot. SEARAH_DOCUMENT_DIR wajib direktori privat existing di luar webroot untuk upload identitas. Jangan memakai database pengguna atau mengaktifkan SEARAH_MIGRATE pada data existing tanpa backup/review.

Demo accounts: admin@searah.id, pengemudi@searah.id, penumpang@searah.id; password 12345678. Backend demo seed tidak otomatis menyediakan semua mobil/garasi driver. Admin membuat mobil linked garage dan menetapkan garasi driver sebelum booking Private. Admin baru wajib scope region; tidak otomatis menjadi superadmin.

## Production hosting

Gunakan HTTPS dan PHP-FPM/Apache/Nginx, bukan built-in server.php. Same origin /api/* harus diteruskan ke backend/api/index.php dengan PATH/REQUEST_URI yang mempertahankan rute API. Alternatif API direct backend/api/index.php memerlukan konfigurasi rewrite yang cocok; frontend menggunakan /api/*.

Hanya index.html, pages/*.html, css, js dan assets boleh disajikan statis. Deny akses HTTP ke backend/config*, lib, data, schema, backup, outputs, dotfiles, database, log, dokumen privat dan konfigurasi lokal. Entry backend/api/index.php dijalankan hanya melalui lokasi API. Tidak cukup sekadar mengekstrak ZIP pada shared hosting tanpa rewrite dan deny rules.

Set SEARAH_ENV=production, SEARAH_DEMO=0, SEARAH_DEBUG=0, SEARAH_DB_PATH di luar webroot, SEARAH_DOCUMENT_DIR privat, SEARAH_ADMIN_PASSWORD kuat pada inisialisasi database baru serta SEARAH_SUPER_ADMIN_IDS yang dipercaya secara eksplisit. Pastikan directory dan SQLite writable oleh akun PHP (termasuk sidecar WAL/SHM); jangan chmod world-writable. Backup SQLite dan private identity teruji diperlukan.

Duitku: konfigurasi DUITKU_MERCHANT_CODE, DUITKU_API_KEY, DUITKU_MODE, DUITKU_PAYMENT_METHOD, DUITKU_CALLBACK_URL, DUITKU_RETURN_URL. Jangan menyimpan secret di JS atau ZIP. Callback wajib HTTPS server-to-server. Redirect bukan bukti lunas. Refund manual memerlukan penyelesaian operator, bukan transfer otomatis.

Peta menggunakan Leaflet CDN dan OpenStreetMap tiles; font memakai Google Fonts. Internet dibutuhkan untuk peta/font; koordinat manual tetap tersedia. GPS browser membutuhkan HTTPS atau localhost. Token bearer per-tab di sessionStorage, bukan cookie HttpOnly.

## Checks dan keterbatasan aktual

Browser Edge headless terhadap fresh isolated SQLite: availability reguler/D2D/private, tarif server, booking, location null, cancel, admin create/read/edit/soft-disable, laporan admin/review, banding ban sukses dan duplicate weekly rejection, Sunday-only review rejection pada Kamis, upload/baca PNG di-reencode JPEG privat. Tidak ada pageerror; halaman diuji pada mobile 390x844 tanpa overflow. Seed resource tambahan hanya dibuat melalui API pada DB demo disposable.

Belum dibuktikan browser penuh: GPS driver ke passenger dengan trip aktif, pembayaran settlement nyata, callback Duitku nyata, positive Sunday unban review, user-report untuk booking assigned (UI tersedia), seluruh tiga-package trip lifecycle dan scope regional edge cases. Pembayaran tidak mock/fallback. Profile/password self-service belum memiliki endpoint. Backend masih partial hardening: rate limiting, antivirus/encryption-at-rest, retention cleanup, aggregate detour stop optimization, overnight trip dan strict state machine belum production certified. Lihat backend/README.md bagian Continuation Update yang mengoverride daftar lama.

Tidak ada publish QW Pages. Tidak ada SQLite atau secret di paket. Runtime server lokal demo terpisah dari paket.
