# Frontend SeArah: deployment dan status aktual

Frontend memakai fetch same-origin ke /api/*, PHP dan SQLite backend yang ada. Backend tidak diubah. Tidak memakai localStorage. sessionStorage hanya menyimpan bearer token per tab; akun, booking, tarif, pembayaran, armada dan relokasi berasal dari server. /auth/me memvalidasi token dan role sebelum halaman operasional dibuka. Authorization tetap diputuskan backend, bukan guard JavaScript.

## Menjalankan dan hosting

Jalankan dari root proyek: `php -S 127.0.0.1:8080 server.php`, lalu buka http://127.0.0.1:8080/. Jangan buka file HTML dengan file://. Router server.php hanya untuk development, bukan server produksi.

Production: konfigurasi webserver same-origin agar /api/* diteruskan ke backend/api/index.php; public frontend hanya index.html, pages HTML, js, css dan assets. Tolak akses publik backend/config, backend/lib, schema, data, dotfiles, backup dan konfigurasi lokal. SQLite wajib berada di luar webroot pada direktori writable oleh PHP. Ekstensi PHP: pdo_sqlite, curl, mbstring. Gunakan HTTPS untuk autentikasi dan GPS browser. Lihat backend/README.md untuk konfigurasi deployment, scope admin dan Duitku. Tidak ada kredensial gateway dalam frontend.

Demo hanya untuk database BARU terisolasi: SEARAH_ENV=development, SEARAH_DEMO=1, SEARAH_SUPER_ADMIN_IDS=1 dan SEARAH_DB_PATH menuju file database baru di luar webroot. Akun admin@searah.id, pengemudi@searah.id, penumpang@searah.id, password 12345678. Jangan aktifkan demo pada production atau menimpa database yang ada.

## Yang diimplementasikan

- Login tiga role, registrasi penumpang/driver, validasi sesi server dan logout server. Registrasi admin publik tidak ditawarkan.
- Urutan tab admin: Akun Penumpang, Akun Driver, Akun Admin. Status akun penumpang/driver mengirim mutation backend; tab admin menjelaskan endpoint yang belum tersedia.
- Garasi create/edit/soft-disable: nama, kota, alamat, koordinat manual, GPS dan klik peta; tanpa template. Mobil create/edit/soft-disable dengan garasi aktif wajib. Search dan filter wilayah serta monitoring driver dan keputusan relokasi.
- Booking reguler/D2D/private, pilihan katalog mobil dan pilihan driver Private terpisah. Tarif backend, alamat/empat koordinat wajib D2D/private, booking dan invoice dibuat server. Detail dan daftar pesanan menggunakan ID URL, bukan draft browser.
- Pembayaran create/read/check server, tautan redirect_url HTTPS resmi; tidak membuat QR atau status lunas lokal. Rating booking selesai mengirim server.
- Admin draft trip, attach booking lunas, konfirmasi, detail manifest/GPS, run/list/accept/reject Smart Match.
- Driver daftar penugasan, terima/tolak, update status server, GPS watch nyata dikirim server maksimal tiap 10 detik; stop pada navigasi/error. Pengajuan relokasi confirm/GPS/manual map dan konfirmasi usulan otomatis. Garasi berubah setelah keputusan admin.
- Verifikasi driver dan review unban menggunakan endpoint server, pengaturan ditampilkan read-only.

## Verifikasi aktual

Node memeriksa sintaks seluruh JS dan inline script halaman. Playwright dengan Microsoft Edge headless berjalan melawan PHP HTTP dan SQLite demo terisolasi di direktori temp, bukan database proyek. Lulus: login admin/driver/passenger, registrasi kedua role, create/update/soft-disable garasi, create mobil linked garage, urutan/tab akun, tarif dan booking ketiga paket, error pembayaran tanpa sukses palsu, pengajuan relokasi driver dan penolakan role driver di halaman admin. Tidak ada pageerror pada dua rangkaian browser tersebut. Halaman driver pada viewport 390x844 tidak overflow. Navbar landing mobile ditemukan overflow dan diperbaiki dengan flex-wrap.

## Belum selesai / tidak boleh dianggap production-certified

- Tidak ada endpoint pembacaan GPS trip untuk penumpang. Detail penumpang tidak menampilkan posisi driver buatan.
- Backend tidak menyediakan CRUD akun admin, profile/password update, upload/download identitas aman, pembatalan penumpang, atau pengajuan banding; halaman terkait menampilkan unsupported tanpa klaim tersimpan.
- Gateway nyata belum dikonfigurasi/dibuktikan; pengujian pembayaran menghasilkan respons error server. Belum diuji callback, QR yang valid, pembayaran sukses, refund, atau seluruh lifecycle ketiga paket sampai selesai.
- Tombol driver GPS/status, admin approval relokasi, attach/confirm trip dan Smart Match telah dihubungkan, tetapi belum seluruhnya diuji browser end-to-end pada rangkaian ini.
- Manajemen laporan admin lama belum dipasang kembali pada controller server-backed. Data mock laporan lama tidak digunakan.
- Token bearer masih disimpan di sessionStorage sesuai kontrak backend; belum sesi cookie HttpOnly. Kontak perusahaan masih metadata statis, bukan pengaturan SQLite editable.
- Struktur operasional di dalam halaman disederhanakan memakai design tokens/components, font dan navbar yang ada. Bukan preservasi pixel-identical layout lama. Belum audit visual semua halaman atau pengujian aksesibilitas menyeluruh.

Backup original frontend sebelum perubahan (termasuk dirty/untracked originals): C:/Users/allme/AppData/Local/Temp/qwenwork/searah-frontend-backup-1795631536209/. Script dan database pengujian hanya di direktori temp qwenwork. Tidak ada publish, commit, penghapusan file user, atau perubahan backend.
