-- =============================================================
--  SEARAH Travel — Skema Database Backend (SQLite)  [v2 FINAL]
--  Stack target: PHP native + SQLite (hosting gratis / shared)
--  Model: DEMAND-DRIVEN (pesanan -> Smart Match -> admin tinjau -> Trip)
--  Tarif: PER RUTE (asal-tujuan) per paket
--  Pembayaran: QRIS via DUITKU (+ rekam transfer manual)
--  Ketersediaan: KALENDER TANGGAL + JAM utk mobil & supir
--  Admin: SUPER ADMIN (semua wilayah) + KOORDINATOR WILAYAH (terbatas)
--  Invoice: entitas sendiri
--
--  CATATAN SQLITE:
--   - Jalankan `PRAGMA foreign_keys = ON;` di SETIAP koneksi PHP.
--   - Waktu = TEXT ISO-8601 (UTC). Jam = TEXT 'HH:MM'. Uang = INTEGER (Rp).
--   - Boolean = INTEGER 0/1. Koordinat = REAL.
-- =============================================================

PRAGMA foreign_keys = ON;

-- ---------- 1. DATA REFERENSI WILAYAH & LOKASI ----------

-- Wilayah operasional (I-IV dari dokumen perencanaan)
CREATE TABLE IF NOT EXISTS regions (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  code          TEXT    NOT NULL UNIQUE,           -- 'I','II','III','IV'
  name          TEXT    NOT NULL                   -- 'Gerbangkertosusila & Madura', dst.
);

-- Kota layanan (terhubung ke wilayah)
CREATE TABLE IF NOT EXISTS cities (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT    NOT NULL UNIQUE,
  region_id     INTEGER NOT NULL REFERENCES regions(id) ON DELETE RESTRICT,
  lat           REAL,
  lng           REAL,
  is_active     INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at    TEXT
);
CREATE INDEX IF NOT EXISTS idx_cities_region ON cities(region_id);

-- Garasi / pool resmi (CRUD oleh admin; wilayah diturunkan dari kota)
CREATE TABLE IF NOT EXISTS garages (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT    NOT NULL,
  city_id       INTEGER NOT NULL REFERENCES cities(id) ON DELETE RESTRICT,
  address       TEXT    NOT NULL,
  lat           REAL,
  lng           REAL,
  phone         TEXT,
  operating_hours TEXT,
  is_active     INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at    TEXT
);
CREATE INDEX IF NOT EXISTS idx_garages_city ON garages(city_id);

-- Landmark rekomendasi titik jemput/antar (opsional, CRUD admin)
CREATE TABLE IF NOT EXISTS landmarks (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  city_id       INTEGER NOT NULL REFERENCES cities(id) ON DELETE CASCADE,
  name          TEXT    NOT NULL,
  address       TEXT,
  lat           REAL,
  lng           REAL,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_landmarks_city ON landmarks(city_id);

-- ---------- 2. IDENTITAS & AKUN ----------

CREATE TABLE IF NOT EXISTS users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT    NOT NULL,
  email         TEXT    NOT NULL UNIQUE,
  phone         TEXT,
  password_hash TEXT    NOT NULL,          -- password_hash() PHP (bcrypt/argon2)
  role          TEXT    NOT NULL CHECK (role IN ('penumpang','pengemudi','admin')),
  status        TEXT    NOT NULL DEFAULT 'aktif'
                  CHECK (status IN ('aktif','menunggu_verifikasi','suspend','ban','dinonaktifkan')),
  ban_reason    TEXT,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at    TEXT
);
CREATE INDEX IF NOT EXISTS idx_users_role_status ON users(role, status);

-- Scope wilayah utk admin koordinator. Admin TANPA baris = SUPER ADMIN (semua wilayah).
CREATE TABLE IF NOT EXISTS user_region_scopes (
  user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  region_id     INTEGER NOT NULL REFERENCES regions(id) ON DELETE CASCADE,
  PRIMARY KEY (user_id, region_id)
);

CREATE TABLE IF NOT EXISTS passengers (
  user_id       INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  username      TEXT,
  is_identity_complete INTEGER NOT NULL DEFAULT 0 CHECK (is_identity_complete IN (0,1)),
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at    TEXT
);

CREATE TABLE IF NOT EXISTS drivers (
  user_id       INTEGER PRIMARY KEY REFERENCES users(id) ON DELETE CASCADE,
  status        TEXT    NOT NULL DEFAULT 'menunggu'
                  CHECK (status IN ('menunggu','disetujui','ditolak','peringatan','suspen')),
  is_online     INTEGER NOT NULL DEFAULT 0 CHECK (is_online IN (0,1)),
  home_garage_id INTEGER REFERENCES garages(id) ON DELETE SET NULL,
  region_id     INTEGER REFERENCES regions(id) ON DELETE SET NULL,  -- wilayah operasi supir
  current_garage_id INTEGER REFERENCES garages(id) ON DELETE SET NULL,  -- lokasi supir SEKARANG (di-update saat request relokasi disetujui)
  current_region_id INTEGER REFERENCES regions(id) ON DELETE SET NULL,
  location_updated_at TEXT,
  assign_radius_km REAL,                       -- NULL = ikut settings.default_assign_radius_km (global); boleh override per supir
  reject_count    INTEGER NOT NULL DEFAULT 0,   -- akumulasi tolak order -> peringatan lalu penalti
  rating        REAL    NOT NULL DEFAULT 0,
  rating_count  INTEGER NOT NULL DEFAULT 0,
  trips_count   INTEGER NOT NULL DEFAULT 0,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at    TEXT
);
CREATE INDEX IF NOT EXISTS idx_drivers_status ON drivers(status);

CREATE TABLE IF NOT EXISTS driver_documents (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  driver_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  jenis         TEXT    NOT NULL CHECK (jenis IN ('KTP','SIM','STNK','KIR')),
  file_path     TEXT    NOT NULL,
  file_name     TEXT,
  version       INTEGER NOT NULL DEFAULT 1,
  status        TEXT    NOT NULL DEFAULT 'menunggu'
                  CHECK (status IN ('menunggu','disetujui','ditolak')),
  uploaded_at   TEXT    NOT NULL DEFAULT (datetime('now')),
  reviewed_at   TEXT,
  reviewed_by   INTEGER REFERENCES users(id) ON DELETE SET NULL,
  review_note   TEXT
);
CREATE INDEX IF NOT EXISTS idx_docs_driver ON driver_documents(driver_user_id);
CREATE INDEX IF NOT EXISTS idx_docs_status ON driver_documents(status);

CREATE TABLE IF NOT EXISTS verification_notes (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  driver_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  admin_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  note          TEXT    NOT NULL,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- ---------- 3. ARMADA (milik mitra pemilik kendaraan) ----------

CREATE TABLE IF NOT EXISTS partners (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  name          TEXT    NOT NULL,
  phone         TEXT,
  email         TEXT,
  address       TEXT,
  is_active     INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at    TEXT
);

CREATE TABLE IF NOT EXISTS vehicles (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  partner_id    INTEGER REFERENCES partners(id) ON DELETE SET NULL,
  model         TEXT    NOT NULL,                  -- 'Toyota Calya','Avanza','Innova Reborn', dst.
  serves_package TEXT   NOT NULL CHECK (serves_package IN ('reguler','door_to_door','private')),
  plate         TEXT    NOT NULL UNIQUE,
  seat_capacity INTEGER NOT NULL,
  year          INTEGER,
  current_garage_id INTEGER REFERENCES garages(id) ON DELETE SET NULL,  -- monitoring "mobil di garasi mana"
  status        TEXT    NOT NULL DEFAULT 'aktif'
                  CHECK (status IN ('aktif','suspended','maintenance','non_aktif')),
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at    TEXT
);
CREATE INDEX IF NOT EXISTS idx_vehicles_pkg_status ON vehicles(serves_package, status);
CREATE INDEX IF NOT EXISTS idx_vehicles_garage ON vehicles(current_garage_id);

CREATE TABLE IF NOT EXISTS vehicle_movements (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  vehicle_id    INTEGER NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
  from_garage_id INTEGER REFERENCES garages(id) ON DELETE SET NULL,
  to_garage_id   INTEGER REFERENCES garages(id) ON DELETE SET NULL,
  note          TEXT,
  moved_at      TEXT    NOT NULL DEFAULT (datetime('now')),
  moved_by      INTEGER REFERENCES users(id) ON DELETE SET NULL
);
CREATE INDEX IF NOT EXISTS idx_moves_vehicle ON vehicle_movements(vehicle_id);

-- KALENDER KETERSEDIAAN (tanggal + jam) — mencegah bentrok jadwal utk D2D/Private.
CREATE TABLE IF NOT EXISTS vehicle_availability (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  vehicle_id    INTEGER NOT NULL REFERENCES vehicles(id) ON DELETE CASCADE,
  work_date     TEXT    NOT NULL,                  -- ISO date
  start_time    TEXT    NOT NULL,                  -- 'HH:MM'
  end_time      TEXT    NOT NULL,                  -- 'HH:MM'
  garage_id     INTEGER REFERENCES garages(id) ON DELETE SET NULL,
  status        TEXT    NOT NULL DEFAULT 'available'
                  CHECK (status IN ('available','reserved','on_trip','service','off')),
  trip_id       INTEGER REFERENCES trips(id) ON DELETE SET NULL,   -- pengikat saat terpakai (FK ditambahkan setelah trips)
  note          TEXT,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at    TEXT,
  UNIQUE (vehicle_id, work_date, start_time)
);
CREATE INDEX IF NOT EXISTS idx_veh_avail ON vehicle_availability(vehicle_id, work_date, status);

CREATE TABLE IF NOT EXISTS driver_availability (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  driver_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  work_date     TEXT    NOT NULL,
  start_time    TEXT    NOT NULL,
  end_time      TEXT    NOT NULL,
  garage_id     INTEGER REFERENCES garages(id) ON DELETE SET NULL,
  status        TEXT    NOT NULL DEFAULT 'available'
                  CHECK (status IN ('available','reserved','on_trip','off','cuti')),
  trip_id       INTEGER REFERENCES trips(id) ON DELETE SET NULL,
  note          TEXT,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at    TEXT,
  UNIQUE (driver_user_id, work_date, start_time)
);
CREATE INDEX IF NOT EXISTS idx_drv_avail ON driver_availability(driver_user_id, work_date, status);

-- MEKANISME RELOKASI: supir mengajukan ke admin agar basis/ketersediaannya dipindah
-- ke wilayah/garasi yang baru saja dia datangi. Admin (sesuai scope wilayah) menyetujui
-- -> sistem update drivers.current_garage_id/current_region_id + baris driver_availability.
-- Ini mencegah supir dapat order dari Malang padahal fisiknya sudah di Pasuruan.
CREATE TABLE IF NOT EXISTS driver_availability_requests (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  driver_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  origin        TEXT    NOT NULL DEFAULT 'driver_manual'
                  CHECK (origin IN ('driver_manual','auto_trip')),   -- auto_trip dibuat sistem saat trip selesai
  source_trip_id INTEGER REFERENCES trips(id) ON DELETE SET NULL,    -- trip pemicu ( utk origin=auto_trip)
  type          TEXT    NOT NULL DEFAULT 'relocate'
                  CHECK (type IN ('relocate','isi_slot','libur','selesai_tugas')),
  from_garage_id INTEGER REFERENCES garages(id) ON DELETE SET NULL,  -- basis sebelumnya
  proposed_garage_id INTEGER REFERENCES garages(id) ON DELETE SET NULL, -- usulan sistem (dest trip)
  requested_garage_id INTEGER REFERENCES garages(id) ON DELETE SET NULL, -- hasil final (dikonfirmasi/dikoreksi driver)
  work_date     TEXT,
  start_time    TEXT,
  end_time      TEXT,
  verify_method TEXT    CHECK (verify_method IN ('confirm','gps_scan','manual_map')),
  reported_lat  REAL,                              -- titik hasil scan GPS / pilih manual
  reported_lng  REAL,
  driver_confirmed INTEGER NOT NULL DEFAULT 0 CHECK (driver_confirmed IN (0,1)),
  verified_at   TEXT,
  note          TEXT,
  status        TEXT    NOT NULL DEFAULT 'await_admin'
                  CHECK (status IN ('await_driver_verify','await_admin','approved','rejected','cancelled')),
  reviewed_by   INTEGER REFERENCES users(id) ON DELETE SET NULL,  -- admin wilayah
  reviewed_at   TEXT,
  review_note   TEXT,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_dar_status ON driver_availability_requests(status);
CREATE INDEX IF NOT EXISTS idx_dar_driver ON driver_availability_requests(driver_user_id);

-- Respons supir thd trip yang ditugaskan ("terima jadi"): terima / tolak + alasan (anti no-show).
CREATE TABLE IF NOT EXISTS driver_order_responses (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  trip_id       INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
  driver_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  response      TEXT    NOT NULL CHECK (response IN ('terima','tolak')),
  reason        TEXT,
  responded_at  TEXT    NOT NULL DEFAULT (datetime('now')),
  UNIQUE (trip_id)
);
CREATE INDEX IF NOT EXISTS idx_dor_driver ON driver_order_responses(driver_user_id);

-- ---------- 4. TARIF PER RUTE ----------

CREATE TABLE IF NOT EXISTS routes (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  origin_city_id INTEGER NOT NULL REFERENCES cities(id) ON DELETE RESTRICT,
  dest_city_id   INTEGER NOT NULL REFERENCES cities(id) ON DELETE RESTRICT,
  distance_km   REAL,
  is_active     INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  UNIQUE (origin_city_id, dest_city_id)
);

CREATE TABLE IF NOT EXISTS fares (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  route_id      INTEGER NOT NULL REFERENCES routes(id) ON DELETE CASCADE,
  package_type  TEXT    NOT NULL CHECK (package_type IN ('reguler','door_to_door','private')),
  base_price    INTEGER NOT NULL,
  toll_price    INTEGER NOT NULL DEFAULT 0,
  protection_price INTEGER NOT NULL DEFAULT 0,
  price_unit    TEXT    NOT NULL DEFAULT 'per_orang' CHECK (price_unit IN ('per_orang','per_kendaraan')),
  is_active     INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
  updated_at    TEXT,
  UNIQUE (route_id, package_type)
);

-- ---------- 5. PESANAN & PERJALANAN ----------

CREATE TABLE IF NOT EXISTS bookings (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  code          TEXT    NOT NULL UNIQUE,           -- 'SRH-######'
  passenger_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  package_type  TEXT    NOT NULL CHECK (package_type IN ('reguler','door_to_door','private')),
  route_id      INTEGER REFERENCES routes(id) ON DELETE SET NULL,
  origin_city   TEXT    NOT NULL,
  dest_city     TEXT    NOT NULL,
  trip_date     TEXT    NOT NULL,
  preferred_time TEXT,
  pax_count     INTEGER NOT NULL DEFAULT 1,
  road_type     TEXT    NOT NULL DEFAULT 'biasa' CHECK (road_type IN ('biasa','tol')),
  insurance     INTEGER NOT NULL DEFAULT 0 CHECK (insurance IN (0,1)),

  pickup_type   TEXT    NOT NULL DEFAULT 'garasi' CHECK (pickup_type IN ('garasi','alamat')),
  pickup_address TEXT, pickup_lat REAL, pickup_lng REAL,
  drop_type     TEXT    NOT NULL DEFAULT 'garasi' CHECK (drop_type IN ('garasi','alamat')),
  drop_address  TEXT,   drop_lat  REAL, drop_lng  REAL,

  selected_vehicle_id INTEGER REFERENCES vehicles(id) ON DELETE SET NULL,
  selected_driver_id  INTEGER REFERENCES users(id)     ON DELETE SET NULL,

  fare_base     INTEGER, fare_toll INTEGER, fare_protection INTEGER, fare_total INTEGER,

  status        TEXT    NOT NULL DEFAULT 'menunggu_pembayaran'
                  CHECK (status IN (
                    'draft','menunggu_pembayaran','dibayar',
                    'menunggu_pencocokan','menunggu_konfirmasi_admin','dikonfirmasi',
                    'berlangsung','selesai','dibatalkan')),
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at    TEXT
);
CREATE INDEX IF NOT EXISTS idx_bookings_status ON bookings(status);
CREATE INDEX IF NOT EXISTS idx_bookings_route_date ON bookings(route_id, trip_date);
CREATE INDEX IF NOT EXISTS idx_bookings_passenger ON bookings(passenger_user_id);

CREATE TABLE IF NOT EXISTS trips (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  code          TEXT    NOT NULL UNIQUE,           -- 'SRH-TRIP-######'
  route_id      INTEGER REFERENCES routes(id) ON DELETE SET NULL,
  package_type  TEXT    NOT NULL CHECK (package_type IN ('reguler','door_to_door','private')),
  trip_date     TEXT    NOT NULL,
  departure_time TEXT,
  origin_garage_id INTEGER REFERENCES garages(id) ON DELETE SET NULL,
  dest_garage_id   INTEGER REFERENCES garages(id) ON DELETE SET NULL,
  road_type     TEXT    NOT NULL DEFAULT 'biasa' CHECK (road_type IN ('biasa','tol')),
  vehicle_id    INTEGER REFERENCES vehicles(id) ON DELETE SET NULL,
  driver_id     INTEGER REFERENCES users(id)     ON DELETE SET NULL,
  capacity      INTEGER,
  seats_booked  INTEGER NOT NULL DEFAULT 0,
  status        TEXT    NOT NULL DEFAULT 'draft'
                  CHECK (status IN ('draft','dikonfirmasi','berlangsung','selesai','dibatalkan')),
  confirmed_by  INTEGER REFERENCES users(id) ON DELETE SET NULL,
  confirmed_at  TEXT,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at    TEXT
);
CREATE INDEX IF NOT EXISTS idx_trips_status ON trips(status);
CREATE INDEX IF NOT EXISTS idx_trips_date ON trips(trip_date);
CREATE INDEX IF NOT EXISTS idx_trips_vehicle ON trips(vehicle_id);
CREATE INDEX IF NOT EXISTS idx_trips_driver ON trips(driver_id);

CREATE TABLE IF NOT EXISTS trip_bookings (
  trip_id       INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
  booking_id    INTEGER NOT NULL REFERENCES bookings(id) ON DELETE RESTRICT,
  seat_no       INTEGER,
  pickup_stop_order INTEGER,
  drop_stop_order   INTEGER,
  stop_status   TEXT    NOT NULL DEFAULT 'menunggu'
                  CHECK (stop_status IN ('menunggu','sedang_dituju','terlewati','selesai')),
  PRIMARY KEY (trip_id, booking_id)
);
CREATE INDEX IF NOT EXISTS idx_tb_booking ON trip_bookings(booking_id);

CREATE TABLE IF NOT EXISTS match_suggestions (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  booking_id    INTEGER NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
  target_trip_id INTEGER REFERENCES trips(id) ON DELETE CASCADE,
  batch_key     TEXT,
  score         REAL,
  factors_json  TEXT,
  status        TEXT    NOT NULL DEFAULT 'pending'
                  CHECK (status IN ('pending','diterima','ditolak')),
  reviewed_by   INTEGER REFERENCES users(id) ON DELETE SET NULL,
  reviewed_at   TEXT,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_match_booking ON match_suggestions(booking_id);
CREATE INDEX IF NOT EXISTS idx_match_status ON match_suggestions(status);

CREATE TABLE IF NOT EXISTS driver_locations (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  trip_id       INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
  driver_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  lat           REAL NOT NULL,
  lng           REAL NOT NULL,
  accuracy      REAL,
  recorded_at   TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_dloc_trip ON driver_locations(trip_id, recorded_at);

-- Log peristiwa perjalanan utk timeline/audit (berangkat, tiba, selesai, dst.)
CREATE TABLE IF NOT EXISTS trip_events (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  trip_id       INTEGER NOT NULL REFERENCES trips(id) ON DELETE CASCADE,
  event         TEXT    NOT NULL CHECK (event IN (
                  'ditetapkan','dikonfirmasi','diterima','ditolak','berangkat','tiba_jemput',
                  'berlangsung','tiba_drop','selesai','dibatalkan')),
  actor_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,  -- supir/admin/penumpang
  note          TEXT,
  lat           REAL,
  lng           REAL,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_te_trip ON trip_events(trip_id, created_at);

-- ---------- 6. INVOICE & PEMBAYARAN (QRIS DUITKU) ----------

CREATE TABLE IF NOT EXISTS invoices (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  code          TEXT    NOT NULL UNIQUE,           -- 'INV-######'
  booking_id    INTEGER NOT NULL REFERENCES bookings(id) ON DELETE RESTRICT,
  trip_id       INTEGER REFERENCES trips(id) ON DELETE SET NULL,
  passenger_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE RESTRICT,
  subtotal_base INTEGER NOT NULL DEFAULT 0,
  subtotal_toll INTEGER NOT NULL DEFAULT 0,
  subtotal_protection INTEGER NOT NULL DEFAULT 0,
  discount      INTEGER NOT NULL DEFAULT 0,
  total         INTEGER NOT NULL,
  status        TEXT    NOT NULL DEFAULT 'draft'
                  CHECK (status IN ('draft','issued','paid','void')),
  issued_at     TEXT,
  paid_at       TEXT,
  pdf_path      TEXT,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at    TEXT
);
CREATE INDEX IF NOT EXISTS idx_inv_booking ON invoices(booking_id);
CREATE INDEX IF NOT EXISTS idx_inv_status ON invoices(status);

-- Pembayaran: QRIS via Duitku (merchantCode, refId, paymentCode, channelCode, resultCode)
CREATE TABLE IF NOT EXISTS payments (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  code          TEXT    NOT NULL UNIQUE,           -- ref internal / order id utk Duitku
  booking_id    INTEGER NOT NULL REFERENCES bookings(id) ON DELETE RESTRICT,
  invoice_id    INTEGER REFERENCES invoices(id) ON DELETE SET NULL,
  amount        INTEGER NOT NULL,
  currency      TEXT    NOT NULL DEFAULT 'IDR',
  payment_method TEXT   NOT NULL DEFAULT 'qris'
                  CHECK (payment_method IN ('qris','transfer','ewallet','tunai')),
  gateway       TEXT    NOT NULL DEFAULT 'duitku',
  merchant_code TEXT,                              -- MerchantCode Duitku
  duitku_ref_id TEXT,                              -- RefId dari Duitku
  duitku_payment_code TEXT,                        -- PaymentCode (mis. QRIS / VA)
  duitku_channel_code TEXT,                        -- ChannelCode
  duitku_result_code  TEXT,                        -- ResultCode (00 = sukses)
  qr_string     TEXT,                              -- string QRIS (EMVCo) dari Duitku
  redirect_url  TEXT,                              -- paymentUrl/checkout Duitku
  status        TEXT    NOT NULL DEFAULT 'pending'
                  CHECK (status IN ('pending','paid','expired','failed','refunded')),
  expired_at    TEXT,
  paid_at       TEXT,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_at    TEXT
);
CREATE INDEX IF NOT EXISTS idx_pay_booking ON payments(booking_id);
CREATE INDEX IF NOT EXISTS idx_pay_invoice ON payments(invoice_id);
CREATE INDEX IF NOT EXISTS idx_pay_status ON payments(status);
CREATE INDEX IF NOT EXISTS idx_pay_duitku_ref ON payments(duitku_ref_id);

-- Log callback/webhook Duitku (rekonsiliasi; verifikasi signature di lapisan PHP)
CREATE TABLE IF NOT EXISTS payment_logs (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  payment_id    INTEGER REFERENCES payments(id) ON DELETE CASCADE,
  event         TEXT    NOT NULL,                  -- 'create','callback','webhook','expire'
  payload_json  TEXT,                              -- body POST Duitku mentah
  signature_ok  INTEGER CHECK (signature_ok IN (0,1)),
  received_at   TEXT    NOT NULL DEFAULT (datetime('now'))
);

-- Refund (Duitku sandbox: eksekusi kemungkinan manual via support, status tetap dicatat).
CREATE TABLE IF NOT EXISTS refunds (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  code          TEXT    NOT NULL UNIQUE,           -- 'RFD-######'
  payment_id    INTEGER NOT NULL REFERENCES payments(id) ON DELETE RESTRICT,
  booking_id    INTEGER REFERENCES bookings(id) ON DELETE SET NULL,
  amount        INTEGER NOT NULL,
  method        TEXT    NOT NULL DEFAULT 'duitku' CHECK (method IN ('duitku','manual')),
  reason        TEXT,
  status        TEXT    NOT NULL DEFAULT 'pending'
                  CHECK (status IN ('pending','processed','completed','failed')),
  duitku_ref    TEXT,
  processed_by  INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  processed_at  TEXT
);
CREATE INDEX IF NOT EXISTS idx_refund_payment ON refunds(payment_id);
CREATE INDEX IF NOT EXISTS idx_refund_status ON refunds(status);

-- ---------- 7. ULASAN, PEMBATALAN, LAPORAN, BAN/UNBAN ----------

CREATE TABLE IF NOT EXISTS ratings (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  booking_id    INTEGER REFERENCES bookings(id) ON DELETE SET NULL,
  trip_id       INTEGER REFERENCES trips(id) ON DELETE SET NULL,
  passenger_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  driver_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  score         INTEGER NOT NULL CHECK (score BETWEEN 1 AND 5),
  comment       TEXT,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  UNIQUE (booking_id)
);
CREATE INDEX IF NOT EXISTS idx_rating_driver ON ratings(driver_user_id);

CREATE TABLE IF NOT EXISTS cancellations (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  booking_id    INTEGER NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
  cancelled_by  TEXT    NOT NULL CHECK (cancelled_by IN ('penumpang','pengemudi','admin')),
  reason        TEXT,
  stage         TEXT,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE TABLE IF NOT EXISTS reports (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  code          TEXT    NOT NULL UNIQUE,           -- 'RPT-...'
  type          TEXT    NOT NULL CHECK (type IN ('terhadap_driver','terhadap_penumpang')),
  reporter_user_id INTEGER REFERENCES users(id) ON DELETE SET NULL,
  subject_user_id  INTEGER REFERENCES users(id) ON DELETE SET NULL,
  booking_id    INTEGER REFERENCES bookings(id) ON DELETE SET NULL,
  region_id     INTEGER REFERENCES regions(id) ON DELETE SET NULL,  -- utk filter koordinator wilayah
  category      TEXT,
  description   TEXT,
  evidence_path TEXT,
  status        TEXT    NOT NULL DEFAULT 'menunggu'
                  CHECK (status IN ('menunggu','diproses','selesai')),
  admin_action  TEXT,
  handled_by    INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  handled_at    TEXT
);
CREATE INDEX IF NOT EXISTS idx_reports_status ON reports(status);
CREATE INDEX IF NOT EXISTS idx_reports_subject ON reports(subject_user_id);
CREATE INDEX IF NOT EXISTS idx_reports_region ON reports(region_id);

CREATE TABLE IF NOT EXISTS account_actions (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  target_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  action        TEXT    NOT NULL CHECK (action IN ('peringatan','suspend','ban','aktifkan_kembali')),
  reason        TEXT,
  related_report_id INTEGER REFERENCES reports(id) ON DELETE SET NULL,
  by_admin_id   INTEGER REFERENCES users(id) ON DELETE SET NULL,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_acct_target ON account_actions(target_user_id);

-- Penalti supir — SELALU didahului peringatan. Alur: peringatan -> SP/suspend (penalti).
CREATE TABLE IF NOT EXISTS driver_penalties (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  driver_user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  level         TEXT    NOT NULL CHECK (level IN ('peringatan','sp1','sp2','sp3','suspend')),
  reason        TEXT,
  reject_count_at INTEGER,                              -- jumlah tolak saat dijatuhkan
  related_response_id INTEGER REFERENCES driver_order_responses(id) ON DELETE SET NULL,
  issued_by     INTEGER REFERENCES users(id) ON DELETE SET NULL,
  is_active     INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0,1)),
  created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_pen_driver ON driver_penalties(driver_user_id);

CREATE TABLE IF NOT EXISTS unban_requests (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  reason        TEXT,
  status        TEXT    NOT NULL DEFAULT 'menunggu'
                  CHECK (status IN ('menunggu','disetujui','ditolak')),
  reviewed_by   INTEGER REFERENCES users(id) ON DELETE SET NULL,
  review_note   TEXT,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  reviewed_at   TEXT
);
CREATE INDEX IF NOT EXISTS idx_unban_status ON unban_requests(status);

-- ---------- 8. SESSION TOKEN (API PHP) ----------

CREATE TABLE IF NOT EXISTS auth_tokens (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id       INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
  token_hash    TEXT    NOT NULL,
  expires_at    TEXT    NOT NULL,
  created_at    TEXT    NOT NULL DEFAULT (datetime('now'))
);
CREATE INDEX IF NOT EXISTS idx_token ON auth_tokens(token_hash);

-- ---------- 9. SETTINGS / KONFIG PERUSAHAAN ----------
-- Konfigurasi non-rahasia. Secret Duitku (apiKey) TETAP di config PHP, bukan di sini.
CREATE TABLE IF NOT EXISTS settings (
  key           TEXT    PRIMARY KEY,
  value         TEXT,
  description   TEXT,
  updated_at    TEXT    NOT NULL DEFAULT (datetime('now')),
  updated_by    INTEGER REFERENCES users(id) ON DELETE SET NULL
);

-- =============================================================
--  SEED DATA REFERENSI (contoh awal)
-- =============================================================
INSERT OR IGNORE INTO regions (id, code, name) VALUES
  (1,'I','Gerbangkertosusila & Madura'),
  (2,'II','Tapal Kuda & Pantura Timur'),
  (3,'III','Malang Raya & Mataraman'),
  (4,'IV','Jombang & Pantura Barat');

INSERT OR IGNORE INTO cities (name, region_id, lat, lng) VALUES
  ('Kepanjen',3,-8.1333,112.5714),('Malang',3,-7.9826,112.6308),
  ('Batu',3,-7.8671,112.5239),('Blitar',3,-8.0983,112.1681),
  ('Surabaya',1,-7.2575,112.7521),('Sidoarjo',1,-7.4478,112.7183),
  ('Jember',2,-8.1724,113.7007),('Jombang',4,-7.5459,112.2331);

INSERT OR IGNORE INTO garages (name, city_id, address, lat, lng, operating_hours) VALUES
  ('Pool SeArah Kepanjen',(SELECT id FROM cities WHERE name='Kepanjen'),'Jl. Raya Kepanjen',-8.1333,112.5714,'24 Jam'),
  ('Pool SeArah Surabaya',(SELECT id FROM cities WHERE name='Surabaya'),'Jl. Raya Surabaya',-7.2575,112.7521,'24 Jam');

INSERT OR IGNORE INTO routes (origin_city_id, dest_city_id, distance_km) VALUES
  ((SELECT id FROM cities WHERE name='Malang'),(SELECT id FROM cities WHERE name='Surabaya'),95);
INSERT OR IGNORE INTO fares (route_id, package_type, base_price, toll_price, protection_price, price_unit) VALUES
  ((SELECT id FROM routes WHERE origin_city_id=(SELECT id FROM cities WHERE name='Malang') AND dest_city_id=(SELECT id FROM cities WHERE name='Surabaya')),'reguler',150000,15000,10000,'per_orang'),
  ((SELECT id FROM routes WHERE origin_city_id=(SELECT id FROM cities WHERE name='Malang') AND dest_city_id=(SELECT id FROM cities WHERE name='Surabaya')),'door_to_door',300000,20000,10000,'per_orang'),
  ((SELECT id FROM routes WHERE origin_city_id=(SELECT id FROM cities WHERE name='Malang') AND dest_city_id=(SELECT id FROM cities WHERE name='Surabaya')),'private',650000,100000,25000,'per_kendaraan');

-- Super admin (tanpa scope wilayah). Ganti hash asli via:
--   php -r "echo password_hash('12345678', PASSWORD_DEFAULT);"
INSERT OR IGNORE INTO users (id, name, email, password_hash, role, status) VALUES
  (1,'Administrator SeArah','admin@searah.id','__REPLACE_WITH_PASSWORD_HASH__','admin','aktif');

INSERT OR IGNORE INTO settings (key, value, description) VALUES
  ('company_name','PT SeArah Travel Indonesia','Nama perusahaan'),
  ('currency','IDR','Mata uang'),
  ('default_assign_radius_km','30','Radius default penugasan supir dari garasi wilayahnya (km); override per supir bila assign_radius_km diisi'),
  ('relocation_auto_on_trip_complete','1','Auto-buat request relokasi saat trip selesai'),
  ('reject_warn_threshold','3','Jumlah tolak order -> peringatan (belum penalti)'),
  ('reject_penalty_threshold','5','Jumlah tolak order -> penalti (SP/suspend)'),
  ('duitku_mode','sandbox','Mode Duitku (apiKey/merchantCode rahasia di config PHP)');
