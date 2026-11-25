# SeArah — Status Akhir Penyempurnaan UI/UX

Penyempurnaan menyeluruh terhadap seluruh antarmuka web **SeArah Travel Shuttle** berdasarkan perpaduan komprehensif dari:
1. **Dokumen Perencanaan Travel Shuttle (Versi 1.1)**: Logika peran pengguna, paket layanan, aturan berbagi mobil searah, verifikasi driver 3-tahap, logika checkout 5.5, pembatalan ala Gojek 5.6, invoice otomatis 5.7, identitas driver & rating 5.8, dan penundaan tarif resmi ke tahap berikutnya.
2. **Mockup Figma (`MOCKUP seARAH.fig`)**: Aset visual asli, warna design tokens, logo resmi (`logo.png`), banner hero beresolusi tinggi, foto paket (`paket-reguler.png`, `paket-d2d.png`, `paket-private.png`), foto profil driver (`driver-avatar.png`), grafik pembayaran QRIS (`qris.png`), serta ikon kontak WhatsApp, Instagram, TikTok, dan CS resmi.
3. **Prototipe NusaShuttle**: Standar interaksi modern (toast system, simulasi transisi status, sorting prioritas mobil terisi di atas, chips filter rute dan kota).

---

## 1. Halaman & Fitur Utama

- `index.html` — Landing page visual Traveloka-style:
  - Hero banner menggunakan aset resolusi tinggi dari Figma dan gradien elegan.
  - Widget pencarian lengkap: Tab paket (Reguler, Door to Door, Private), dropdown kota asal & tujuan Jawa Timur lengkap berdasarkan 4 wilayah dokumen perencanaan:
    - *Wilayah III (Malang Raya & Mataraman):* Kepanjen, Malang Kota, Batu, Blitar, Kediri, Tulungagung, Madiun, Ponorogo, Nganjuk, Trenggalek, Magetan, Ngawi.
    - *Wilayah I (Gerbangkertosusila & Madura):* Surabaya, Sidoarjo, Pasuruan, Mojokerto, Gresik, Madura, Sampang, Pamekasan, Sumenep.
    - *Wilayah II (Tapal Kuda & Pantura Timur):* Probolinggo, Lumajang, Jember, Situbondo, Bondowoso, Banyuwangi.
    - *Wilayah IV (Jombang & Pantura Barat):* Jombang, Lamongan, Bojonegoro, Tuban.
  - Input tanggal keberangkatan, jam, dan jumlah kursi (1–7 orang).
  - Tiga kartu paket perjalanan lengkap dengan foto Figma, badge keunggulan, daftar fitur checkmark, estimasi tarif draf, dan tombol aksi langsung.
  - Seksi 6 keunggulan *"Kenapa SeArah?"* (Asuransi perjalanan, Driver terverifikasi, Identitas driver di HP, Rating & ulasan, Berbagi mobil searah, Invoice otomatis).
  - 4 Ikon kontak mengambang pojok kanan bawah: WhatsApp (hijau), Instagram (gradien ungu-oranye), TikTok (hitam), dan Customer Service 24/7 (navy berikon headset SVG vektor resmi).

- `pages/booking.html` — Pilihan perjalanan & kustomisasi:
  - **Bilah Pencarian & Pengubah Rute/Jadwal Interaktif (In-Place Ticket Search & Edit Bar):** Dropdown kota asal lengkap Jawa Timur, tombol tukar rute (⇄), kota tujuan, input tanggal, input jam, dan dropdown jumlah penumpang yang dapat diubah langsung di halaman booking tanpa perlu kembali ke beranda.
  - Wrapper tombol paket mandiri (*card buttons*) untuk **Reguler**, **Door to Door**, dan **Private** dengan ikon, judul, subteks, dan status aktif.
  - **Paket Reguler:** Dilengkapi kartu informasi garasi dan tombol interaktif *"🗺️ Lihat Titik Garasi Asal di Peta"* & *"🗺️ Lihat Titik Garasi Tujuan di Peta"* untuk melihat peta visual lokasi pool garasi, fasilitas garasi (AC lounge, charger station, toilet, musholla, parkir inap aman), dan jam operasional.
  - **Door to Door & Private:** Pemilih titik jemput dan drop off di peta ala Gojek (*modal map picker*) dengan pin dinamis yang bergerak mulus saat mengeklik area peta atau memilih landmark rekomendasi per kota, serta menyimpan titik ke input alamat.
  - Rangkuman rute & jadwal dinamis.
  - Pemilihan jenis jalan: *Jalan Biasa (Default)* vs *Jalan Tol (+ Tambahan)*.
  - Titik keberangkatan garasi untuk Reguler, atau input penjemputan & drop off khusus untuk Door to Door dan Private.
  - Pemilihan armada & driver: Sistem prioritas terisi di atas untuk Door to Door (`3/7 terisi`, `2/9 terisi`), dan profil driver berfoto untuk Private.
  - Penjelasan aturan berbagi mobil searah (Dokumen 4.1).
  - Checkbox proteksi asuransi perjalanan resmi.

- `pages/checkout.html` — Implementasi ketat Logika Dokumen 5.5:
  - **Kondisi 1 (Tamu/Belum Login):** Formulir pendaftaran akun & data diri lengkap (akun dan order dibuat otomatis).
  - **Kondisi 2 (Sudah Punya Akun, Data Diri Belum Diisi):** Form melengkapi nama & kontak WhatsApp sebelum order dibuat.
  - **Kondisi 3 (Sudah Punya Akun, Data Lengkap):** Konfirmasi data diri instan tanpa form tambahan.
  - Ringkasan rincian seluruh pesanan (rute, jadwal, paket, jalan, asuransi, titik jemput/antar, armada).

- `pages/pembayaran.html` — Hub konfirmasi, QRIS & status pesanan:
  - **SEARAH TripSync™ Multi-Stop Live Journey & Map Tracker:** Transparansi rute bersama di mana seluruh penumpang di dalam mobil dapat melihat lokasi pengemudi secara live (mobil bergerak dengan plat & nama), lokasi titik jemput penumpang sendiri (`⭐ JEMPUT ANDA`), lokasi titik jemput penumpang lain (`🧍 Jemput Penumpang 2/3`), lokasi titik drop off penumpang lain (`🏁 Drop Off Penumpang 2/3`), dan lokasi drop off penumpang sendiri (`🎯 DROP ANDA`).
  - **Manifest Multi-Stop Stop-by-Stop Itinerary:** Tabel urutan pemberhentian lengkap dengan nomor urut, jenis titik, nama penumpang, alamat/titik, dan status real-time (`✓ Terlewati`, `🚗 Sedang Dituju`, `⏳ Menunggu Antrean`).
  - **Simulasi Interaktif Posisi Armada:** Tombol *Simulasikan Gerak Driver* untuk mendemonstrasikan pergerakan armada sepanjang jalur tol dan jalan arteri dari titik ke titik.
  - Untuk Paket Reguler: Otomatis memetakan rute dari Pool Garasi Asal ke Garasi Transit hingga Garasi Tujuan.
  - Untuk Paket Private: Menampilkan rute privat langsung (Direct Point-to-Point) tanpa penjemputan/penurunan penumpang lain.
  - Tampilan QRIS dummy dinamis mandiri berstandar nasional (NMID, GPN, merchant PT SeArah Travel Indonesia, logo di tengah) dengan tombol **🔀 Acak QR Baru** untuk menghasilkan pola QR baru kapan saja.
  - Countdown timer batas waktu bayar (15:00) dan petunjuk e-wallet / m-Banking lengkap.
  - Opsi *Lewati Pembayaran (Langsung Dikonfirmasi)* sesuai Dokumen 5.5 & 9.
  - Timeline status 6 tahap: `Dibuat` → `Menunggu driver` → `Dikonfirmasi` → `Driver menuju lokasi` → `Berlangsung` → `Selesai`.
  - Tombol simulasi status untuk kemudahan demonstrasi pengujian alur.
  - Kartu identitas driver berfoto (`driver-avatar.png`), plat nomor, armada, dan bintang rating.
  - Pengunduhan otomatis invoice `.txt` serta tautan ke lembar invoice resmi.
  - Widget penilaian (Rating) 5-bintang interaktif saat status `Selesai`.
  - Sistem pembatalan ala Gojek (Dokumen 5.6) dengan alasan pembatalan dan peringatan pembatalan terlambat.

- `pages/invoice.html` — Lembar invoice resmi:
  - Tampilan resmi berlogo SeArah, nomor order `#SRH-...`, tanggal terbit, dan rincian lengkap perjalanan.
  - Fitur cetak bersih ke format PDF (`window.print()`) dengan `@media print` rapi.

- `pages/detail-perjalanan.html` & `pages/pembatalan.html`:
  - Detail perjalanan: Menampilkan foto driver, plat mobil, rute jemput/drop off, status terkini, dan tombol hubungi WhatsApp driver.
  - Pembatalan: Pilihan alasan pembatalan, validasi tahapan perjalanan, dan integrasi modal dialog konfirmasi.

- `pages/daftar.html`, `daftar-penumpang.html`, `daftar-pengemudi.html`, `masuk.html`:
  - Pemilihan peran ramah pengguna (Penumpang vs Pengemudi).
  - Formulir pendaftaran multi-langkah (*stepper progress*).
  - Unggah foto KTP dan SIM untuk mitra pengemudi dengan pratinjau nama file.
  - Halaman login dengan tombol instan *Akun Demo Pengujian* (`admin@searah.id` & `ahmad.driver@searah.id`).

- `pages/status-verifikasi.html` & `admin-verifikasi.html`:
  - Status verifikasi 3-tahap bagi driver: Menunggu / Disetujui / Ditolak (lengkap dengan catatan alasan admin dan form unggah ulang).
  - Panel admin verifikasi untuk memeriksa dokumen, menyetujui, atau menolak driver dengan modal catatan alasan.
  - Tombol simulasi status untuk kemudahan uji coba mandiri tanpa harus login ulang.

- `pages/dashboard-pengemudi.html`:
  - Dashboard mitra driver terverifikasi untuk menerima/menolak order dan memantau riwayat rating.

- `pages/profil.html`, `edit-profil.html`, `ubah-password.html`:
  - Manajemen akun penumpang/driver, pengubahan data nama, email, no. HP, dan password.

- `pages/komponen.html`:
  - Showcase komponen UI SeArah (Buttons, Pills, Cards, Steppers, Toasts, Modals, Contact Icons).

---

## 2. Aset & Konsistensi Brand

- `assets/logo.png` — Logo resmi SeArah dari Figma Mockup.
- `assets/hero.png` — Banner utama beresolusi tinggi dari Figma.
- `assets/paket-reguler.png`, `paket-d2d.png`, `paket-private.png` — Gambar paket resmi dari Figma.
- `assets/driver-avatar.png` — Foto profil driver terverifikasi dari Figma.
- `assets/qris.png` — Grafik QRIS resmi SeArah dari Figma.
- `assets/icon-wa.png`, `icon-ig.png`, `icon-tiktok.png`, `icon-cs.png` — Ikon kontak resmi (WhatsApp, Instagram, TikTok berlatar hitam, dan Customer Service berlatar biru navy dengan simbol headset + mic).
- Dropdown profil pengguna (`js/ui.js`): Menyediakan menu *Profil Saya*, *Cari Perjalanan*, *Riwayat Pesanan* (menuju `pages/pembayaran.html`), dan *Keluar*.

---

## 3. Akun Pengujian Demo

- **Administrator (Portal Utama & Khusus Link):**
  - Username: `admin` (atau email: `admin@searah.id`)
  - Password: `12345678`
  - URL Akses Khusus: [`pages/admin-panel.html`](file:///f:/ProgrammingProjects/SeArah/pages/admin-panel.html) (dilengkapi gerbang otentikasi kunci)
- **Mitra Driver:** `ahmad.driver@searah.id` · Password: `driver123`
- **Penumpang:** Bebas mendaftar langsung atau melalui alur checkout instan

---

### Update Log: Kalibrasi Presisi Map Picker & Routing Jalur Riil (Leaflet)
1. **Kalibrasi Zero-Offset Pin Picker (`initPickerMap`)**:
   - Didesain ulang marker SVG Teardrop needle pin (`36x50px`) dengan `iconAnchor: [18, 50]`.
   - Menghapus pergeseran visual transform yang sebelumnya menyebabkan marker terangkat di atas titik klik.
   - Ujung jarum pin merah kini mendarat tepat 100% pada koordinat pixel yang diklik atau digeser oleh pengguna, dilengkapi crosshair pulse dot pada titik klik dan bubble nama lokasi dinamis.
2. **Peningkatan Akurasi Geometri Rute TripSync (`initTripSyncMap`)**:
   - Mengganti garis lurus kasar (*straight chords*) dengan integrasi rute jalan riil.
   - Ditambahkan database koridor jalan tol & arteri Jawa Timur (`HIGHWAY_CORRIDORS` untuk rute Kepanjen - Malang - Tol Pandaan-Malang - Tol Gempol-Surabaya) yang menghasilkan lekukan jalan halus dan presisi.
   - Terintegrasi secara asinkron dengan OSRM Driving Engine (`router.project-osrm.org`) untuk mengambil geometri jalan OpenStreetMap turn-by-turn jika online, dengan fallback kurva spline koridor lokal beresolusi tinggi jika offline.
   - Menghilangkan `transform: translateY(-100%)` pada marker stop agar lingkaran nomor stop menempel presisi di atas garis jalan.
3. **Dukungan Gesture Peta Multi-Perangkat (Scrollwheel & Pinch/Stretch)**:
   - Mengaktifkan `scrollWheelZoom: true`, `wheelDebounceTime: 40`, dan `wheelPxPerZoomLevel: 60` untuk scroll mouse dan scrolling dua jari yang mulus.
   - Mengaktifkan `touchZoom: true`, `doubleClickZoom: true`, serta `touch-action: none` pada seluruh container Leaflet (Map Picker, Garage Pool Map, TripSync Map) sehingga gesture *pinch and stretch* (mencubit/meregangkan layar) berjalan responsif di smartphone dan trackpad laptop tanpa terhalang gesture bawaan browser.
4. **Modul Pelacakan Lokasi Real-Time & Ekosistem Driver (`location-tracker.js`, `pages/dashboard-pengemudi.html`, dll.)**:
   - `js/location-tracker.js`: Layanan pelacak posisi GPS browser + simulasi live untuk pengemudi dan penumpang, lengkap dengan marker pulsing dot, akurasi radius, dan sinkronisasi status.
   - `pages/dashboard-pengemudi.html`: Dashboard lengkap pengemudi dengan toggle Online/Offline, live map tracking GPS, menu cepat, preview pesanan masuk siap terima, dan riwayat ulasan rating.
   - `pages/order-masuk-driver.html`: Halaman manajemen pesanan masuk dengan peta titik jemput, filter paket layanan (Reguler, Door to Door, Private), detail rute & penumpang, estimasi pendapatan, serta aksi Terima / Tolak.
   - `pages/perjalanan-aktif-driver.html`: Tampilan navigasi aktif pengemudi dengan peta Leaflet multi-stop real-time, kontrol pembaruan stop berikutnya, dan daftar penumpang di armada beserta kontak WhatsApp.
   - `pages/edit-akun-driver.html`: Pengelolaan profil identitas pengemudi, spesifikasi armada kendaraan (HiAce, Elf, dll.), dan status verifikasi dokumen (KTP, SIM, STNK, KIR).
   - `pages/pembayaran.html` & `pages/detail-perjalanan.html`: Ditambahkan tombol pelacak GPS langsung untuk penumpang di peta TripSync.


5. **Portal Rahasia Administrator Khusus Link (`pages/admin-panel.html`)**:
   - Akses via link langsung saja dengan otentikasi: Username `admin`, Password `12345678`.
   - Gatekeeper Screen: Layar gerbang terkunci otomatis jika belum diautentikasi sebagai admin.
   - Tab 1: **Manajemen Akun Driver** (tabel seluruh driver, status Aktif/Suspend/SP/Menunggu, armada, rating, aksi Suspend/Bekukan Akun, Beri SP, Aktifkan Kembali, Cek Dokumen).
   - Tab 2: **Laporan Penumpang thd Driver** (kategori ugal-ugalan, kabin kotor/bau rokok, tarif di luar aplikasi, kronologi & bukti, aksi SP-1, Suspend Sementara, Refund Penumpang, Selesaikan).
   - Tab 3: **Laporan Driver thd Penumpang** (kategori no-show, mengotori/merusak jok, tolak bayar tol, kronologi, aksi Peringatkan Penumpang, Blokir Akun Penumpang, Kompensasi Cuci Driver, Selesaikan).
   - Fitur Buat Laporan Pengaduan Baru secara manual untuk pengujian sistem.
