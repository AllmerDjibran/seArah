/* =========================================================
   SeArah — Mock API (sesuai Dokumen Perencanaan Travel Shuttle)
   Entitas: users, passenger_profiles, driver_profiles,
   driver_documents, verification_notes, bookings,
   cancellations, ratings, trips.
   ========================================================= */

const DB = {
  users: JSON.parse(localStorage.getItem("searah_users") || "[]"),
  session: JSON.parse(localStorage.getItem("searah_session") || "null"),
  passengers: JSON.parse(localStorage.getItem("searah_passengers") || "[]"),
  drivers: JSON.parse(localStorage.getItem("searah_drivers") || "[]"),
  documents: JSON.parse(localStorage.getItem("searah_documents") || "[]"),
  vnotes: JSON.parse(localStorage.getItem("searah_vnotes") || "[]"),
  bookings: JSON.parse(localStorage.getItem("searah_bookings") || "[]"),
  cancellations: JSON.parse(localStorage.getItem("searah_cancellations") || "[]"),
  ratings: JSON.parse(localStorage.getItem("searah_ratings") || "[]"),
  reports_driver: JSON.parse(localStorage.getItem("searah_reports_driver") || "[]"),
  reports_passenger: JSON.parse(localStorage.getItem("searah_reports_passenger") || "[]"),
};

function save() {
  localStorage.setItem("searah_users", JSON.stringify(DB.users));
  localStorage.setItem("searah_session", JSON.stringify(DB.session));
  localStorage.setItem("searah_passengers", JSON.stringify(DB.passengers));
  localStorage.setItem("searah_drivers", JSON.stringify(DB.drivers));
  localStorage.setItem("searah_documents", JSON.stringify(DB.documents));
  localStorage.setItem("searah_vnotes", JSON.stringify(DB.vnotes));
  localStorage.setItem("searah_bookings", JSON.stringify(DB.bookings));
  localStorage.setItem("searah_cancellations", JSON.stringify(DB.cancellations));
  localStorage.setItem("searah_ratings", JSON.stringify(DB.ratings));
  localStorage.setItem("searah_reports_driver", JSON.stringify(DB.reports_driver));
  localStorage.setItem("searah_reports_passenger", JSON.stringify(DB.reports_passenger));
}

/* Seed / Update akun admin (username: admin, password: 12345678) */
let adminUser = DB.users.find((u) => u.role === "admin" || u.id === "admin" || u.email === "admin@searah.id");
if (!adminUser) {
  DB.users.push({ id: "admin", email: "admin@searah.id", password: "12345678", role: "admin", name: "Administrator SeArah" });
  save();
} else {
  adminUser.password = "12345678";
  save();
}

/* Seed daftar mitra pengemudi untuk manajemen admin jika belum ada */
if (!DB.users.some((u) => u.email === "ahmad.driver@searah.id")) {
  const driverUserId = "u_driver_demo";
  DB.users.push({
    id: driverUserId,
    email: "ahmad.driver@searah.id",
    password: "driver123",
    role: "pengemudi",
    name: "Ahmad Fauzi"
  });
  DB.drivers.push({
    user_id: driverUserId,
    status: "disetujui",
    mobil: "Toyota HiAce Premio",
    plat: "N 7421 KP",
    tahun: "2023",
    seat: 7,
    rating: 4.9,
    trips_count: 24,
    phone: "0812-3456-7890"
  });
  DB.documents.push(
    { id: "doc_ktp_1", driver_id: driverUserId, jenis: "KTP", file: "ktp-ahmad-fauzi.jpg", versi: 1, waktu: new Date().toISOString() },
    { id: "doc_sim_1", driver_id: driverUserId, jenis: "SIM", file: "sim-b-ahmad-fauzi.jpg", versi: 1, waktu: new Date().toISOString() }
  );
  save();
}

// Seed driver kedua dan ketiga jika belum ada
if (!DB.users.some((u) => u.email === "budi.driver@searah.id")) {
  DB.users.push(
    { id: "u_drv_budi", email: "budi.driver@searah.id", password: "driver123", role: "pengemudi", name: "Budi Santoso" },
    { id: "u_drv_dimas", email: "dimas.driver@searah.id", password: "driver123", role: "pengemudi", name: "Dimas Pratama" },
    { id: "u_drv_joko", email: "joko.driver@searah.id", password: "driver123", role: "pengemudi", name: "Joko Susilo" }
  );
  DB.drivers.push(
    { user_id: "u_drv_budi", status: "disetujui", mobil: "Isuzu Elf Long", plat: "N 1180 AB", tahun: "2022", seat: 9, rating: 4.8, trips_count: 19, phone: "0813-9988-7766" },
    { user_id: "u_drv_dimas", status: "peringatan", mobil: "Toyota Innova Zenix", plat: "N 9033 CD", tahun: "2023", seat: 6, rating: 4.3, trips_count: 8, phone: "0857-1122-3344", note: "Diberi SP-1 karena keterlambatan penjemputan" },
    { user_id: "u_drv_joko", status: "suspen", mobil: "Daihatsu GranMax", plat: "N 5521 OP", tahun: "2020", seat: 7, rating: 3.7, trips_count: 14, phone: "0821-4455-6677", note: "Dibekukan sementara karena komplain berkendara ugal-ugalan" }
  );
  save();
}

/* Seed Laporan Awal (Penumpang vs Driver & Driver vs Penumpang) jika kosong */
if (!DB.reports_driver.length) {
  DB.reports_driver = [
    {
      id: "RPT-D-101",
      pelapor: "Rizky Ramadhan",
      pelapor_kontak: "0812-9988-1122",
      driver_name: "Joko Susilo",
      driver_plat: "N 5521 OP",
      booking_id: "SRH-782194",
      rute: "Kepanjen → Surabaya",
      kategori: "Ugal-ugalan / Melebihi Batas Kecepatan",
      kronologi: "Driver melajukan mobil di atas 120 km/jam saat hujan di Tol Pandaan-Malang dan sering menyalip zig-zag. Penumpang merasa sangat was-was dan mual.",
      tanggal: "25 Nov 2026, 14:20 WIB",
      status: "Menunggu Tindakan",
      tindakan_admin: null
    },
    {
      id: "RPT-D-102",
      pelapor: "Dina Rahmawati",
      pelapor_kontak: "0813-7766-3344",
      driver_name: "Dimas Pratama",
      driver_plat: "N 9033 CD",
      booking_id: "SRH-441209",
      rute: "Malang → Surabaya",
      kategori: "Armada Kotor & Bau Asap Rokok",
      kronologi: "Kabin mobil berbau asap rokok yang menyengat dan lantai kotor berpasir. AC kurang dingin selama perjalanan siang hari.",
      tanggal: "24 Nov 2026, 11:15 WIB",
      status: "Diproses",
      tindakan_admin: "Diberi Surat Peringatan SP-1 & diwajibkan salon interior armada."
    },
    {
      id: "RPT-D-103",
      pelapor: "Bambang Haryanto",
      pelapor_kontak: "0857-4433-2211",
      driver_name: "Agus Wijaya",
      driver_plat: "N 1180 AB",
      booking_id: "SRH-339182",
      rute: "Surabaya → Kepanjen",
      kategori: "Meminta Uang di Luar Aplikasi",
      kronologi: "Driver menagih tambahan tunai Rp 20.000 untuk uang tol, padahal di tiket aplikasi sudah termasuk biaya tol.",
      tanggal: "23 Nov 2026, 17:00 WIB",
      status: "Selesai",
      tindakan_admin: "Kompensasi refund Rp 20.000 ke penumpang & teguran keras kepada driver."
    }
  ];
  save();
}

if (!DB.reports_passenger.length) {
  DB.reports_passenger = [
    {
      id: "RPT-P-201",
      driver_name: "Ahmad Fauzi",
      driver_kontak: "0812-3456-7890",
      penumpang_name: "Dimas Anggara",
      penumpang_kontak: "0822-5566-7788",
      booking_id: "SRH-559124",
      rute: "Kepanjen → Surabaya",
      kategori: "Penumpang Tidak Muncul (No-Show)",
      kronologi: "Armada sudah menunggu di titik penjemputan Jl. Panglima Sudirman selama lebih dari 30 menit. Ditelepon dan di-WA tidak ada respon sama sekali, menghambat jadwal 2 penumpang lain di mobil.",
      tanggal: "25 Nov 2026, 08:35 WIB",
      status: "Menunggu Tindakan",
      tindakan_admin: null
    },
    {
      id: "RPT-P-202",
      driver_name: "Budi Santoso",
      driver_kontak: "0813-9988-7766",
      penumpang_name: "Kevin Sanjaya",
      penumpang_kontak: "0819-3344-5566",
      booking_id: "SRH-992144",
      rute: "Malang → Surabaya",
      kategori: "Mengotori / Merusak Fasilitas Mobil",
      kronologi: "Penumpang menumpahkan kopi manis berkapasitas besar di atas jok bludru baris kedua dan tidak mau bertanggung jawab saat ditegur.",
      tanggal: "24 Nov 2026, 19:10 WIB",
      status: "Diproses",
      tindakan_admin: "Klaim biaya pembersihan jok Rp 100.000 dibebankan ke akun pemesan."
    },
    {
      id: "RPT-P-203",
      driver_name: "Ahmad Fauzi",
      driver_kontak: "0812-3456-7890",
      penumpang_name: "Hendro Wibowo",
      penumpang_kontak: "0811-2233-4455",
      booking_id: "SRH-118832",
      rute: "Blitar → Surabaya",
      kategori: "Membawa Barang Berbau Menyengat / Terlarang",
      kronologi: "Penumpang memaksa membawa buah durian tanpa wadah kedap udara ke dalam kabin ber-AC dan memicu komplain dari penumpang lain.",
      tanggal: "22 Nov 2026, 09:00 WIB",
      status: "Selesai",
      tindakan_admin: "Akun penumpang diberikan catatan peringatan larangan barang berbau tajam."
    }
  ];
  save();
}

const wait = (ms = 300) => new Promise((r) => setTimeout(r, ms));

const api = {
  /* ---------- Auth ---------- */
  async login({ email, password }) {
    await wait();
    const cleanId = (email || "").trim().toLowerCase();
    const user = DB.users.find((u) => {
      const matchId = (u.email.toLowerCase() === cleanId || u.id.toLowerCase() === cleanId || (cleanId === "admin" && u.role === "admin"));
      const matchPass = (u.password === password || (cleanId === "admin" && password === "12345678"));
      return matchId && matchPass;
    });
    if (!user) throw new Error("Email/username atau password tidak sesuai.");
    DB.session = { id: user.id, name: user.name, role: user.role, email: user.email };
    save();
    return DB.session;
  },

  async register(payload) {
    await wait();
    if (DB.users.some((u) => u.email.toLowerCase() === payload.email.toLowerCase())) {
      throw new Error("Email ini sudah terdaftar.");
    }
    const user = { id: "u" + Date.now(), ...payload };
    DB.users.push(user);
    if (payload.role === "pengemudi") {
      DB.drivers.push({ user_id: user.id, status: "menunggu" });
    }
    save();
    return { ok: true, user };
  },

  logout() {
    DB.session = null;
    save();
  },

  getSession() {
    return DB.session;
  },

  /* ---------- Profil penumpang ---------- */
  async savePassenger({ username, nama, telepon }) {
    await wait();
    let p = DB.passengers.find((x) => x.user_id === DB.session?.id);
    if (p) Object.assign(p, { username, nama, telepon });
    else DB.passengers.push({ user_id: DB.session.id, username, nama, telepon });
    if (DB.session) DB.session.name = nama || username;
    save();
    return p || DB.passengers.find((x) => x.user_id === DB.session.id);
  },

  getPassenger() {
    return DB.passengers.find((x) => x.user_id === DB.session?.id) || null;
  },

  /* ---------- Dokumen pengemudi ---------- */
  async uploadDocument({ jenis, fileName }) {
    await wait();
    const driver = DB.drivers.find((d) => d.user_id === DB.session?.id);
    if (!driver) throw new Error("Akun pengemudi tidak ditemukan.");
    const doc = {
      id: "doc" + Date.now(),
      driver_id: driver.user_id,
      jenis,
      file: fileName,
      versi: (DB.documents.filter((d) => d.driver_id === driver.user_id && d.jenis === jenis).length) + 1,
      waktu: new Date().toISOString(),
    };
    DB.documents.push(doc);
    driver.status = "menunggu";
    save();
    return doc;
  },

  getDriverStatus() {
    const driver = DB.drivers.find((d) => d.user_id === DB.session?.id);
    if (!driver) return null;
    const notes = DB.vnotes.filter((n) => n.driver_id === driver.user_id);
    return {
      status: driver.status,
      notes,
      documents: DB.documents.filter((d) => d.driver_id === driver.user_id)
    };
  },

  /* Helper demo untuk simulasi status driver */
  setDriverStatusDemo(status, note = "") {
    const driver = DB.drivers.find((d) => d.user_id === DB.session?.id);
    if (driver) {
      driver.status = status;
      if (note && status === "ditolak") {
        DB.vnotes.push({ id: "vn" + Date.now(), driver_id: driver.user_id, admin_id: "admin", catatan: note, waktu: new Date().toISOString() });
      }
      save();
    }
  },

  /* ---------- Admin verifikasi ---------- */
  async listPendingDrivers() {
    await wait(150);
    return DB.drivers.map((d) => {
      const u = DB.users.find((x) => x.id === d.user_id) || {};
      return {
        ...d,
        email: u.email,
        name: u.name,
        documents: DB.documents.filter((doc) => doc.driver_id === d.user_id)
      };
    });
  },

  /* ---------- Admin Driver Management & Reports ---------- */
  async getAllDriversForAdmin() {
    await wait(100);
    return DB.drivers.map((d) => {
      const u = DB.users.find((x) => x.id === d.user_id) || {};
      return {
        ...d,
        name: u.name || "Mitra Driver",
        email: u.email || "",
        phone: d.phone || "0812-3456-7890",
        mobil: d.mobil || "Toyota HiAce Premio",
        plat: d.plat || "N 7421 KP",
        rating: d.rating || 4.8,
        trips_count: d.trips_count || 12,
        status: d.status || "disetujui",
        note: d.note || "",
        documents: DB.documents.filter((doc) => doc.driver_id === d.user_id)
      };
    });
  },

  async updateDriverStatusByAdmin(userId, newStatus, note = "") {
    await wait(150);
    let d = DB.drivers.find((x) => x.user_id === userId);
    if (!d) {
      d = { user_id: userId, status: newStatus };
      DB.drivers.push(d);
    } else {
      d.status = newStatus;
      if (note) d.note = note;
    }
    save();
    return d;
  },

  async getReportsAgainstDriver() {
    await wait(100);
    return [...DB.reports_driver];
  },

  async getReportsAgainstPassenger() {
    await wait(100);
    return [...DB.reports_passenger];
  },

  async updateReportStatus(type, reportId, newStatus, actionNote = "") {
    await wait(150);
    const list = type === "driver" ? DB.reports_driver : DB.reports_passenger;
    const rpt = list.find((r) => r.id === reportId);
    if (rpt) {
      rpt.status = newStatus;
      if (actionNote) rpt.tindakan_admin = actionNote;
      save();
    }
    return rpt;
  },

  async addReportAgainstDriver(data) {
    await wait(150);
    const newReport = {
      id: "RPT-D-" + Math.floor(100 + Math.random() * 900),
      status: "Menunggu Tindakan",
      tanggal: new Date().toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short" }) + " WIB",
      tindakan_admin: null,
      ...data
    };
    DB.reports_driver.unshift(newReport);
    save();
    return newReport;
  },

  async addReportAgainstPassenger(data) {
    await wait(150);
    const newReport = {
      id: "RPT-P-" + Math.floor(100 + Math.random() * 900),
      status: "Menunggu Tindakan",
      tanggal: new Date().toLocaleString("id-ID", { dateStyle: "medium", timeStyle: "short" }) + " WIB",
      tindakan_admin: null,
      ...data
    };
    DB.reports_passenger.unshift(newReport);
    save();
    return newReport;
  },

  async reviewDriver({ driverId, approve, note }) {
    await wait(200);
    const driver = DB.drivers.find((d) => d.user_id === driverId);
    if (!driver) throw new Error("Driver tidak ditemukan.");
    driver.status = approve ? "disetujui" : "ditolak";
    if (!approve && note) {
      DB.vnotes.push({
        id: "vn" + Date.now(),
        driver_id: driverId,
        admin_id: DB.session?.id || "admin",
        catatan: note,
        waktu: new Date().toISOString()
      });
    }
    save();
    return driver;
  },

  /* ---------- Trip & pencarian ---------- */
  async search(params = {}) {
    await wait(350);
    /* Data armada nyata (Toyota HiAce Premio, Isuzu Elf Long, Toyota Innova Zenix) */
    return [
      {
        id: "t1",
        mobil: "Toyota HiAce Premio",
        plat: "N 7421 KP",
        fill: 3,
        seat: 7,
        driver: "Budi Santoso",
        foto: "assets/driver-avatar.png",
        rating: 4.9,
        dir: `${params.asal || "Kepanjen"} → ${params.tujuan || "Surabaya"}`,
        jam: params.jam || "08:00",
        paket: params.paket || "reguler",
        fasilitas: "AC, Reclining Seat, USB Charger, Asuransi",
      },
      {
        id: "t2",
        mobil: "Isuzu Elf Long",
        plat: "N 1180 AB",
        fill: 2,
        seat: 9,
        driver: "Agus Wijaya",
        foto: "assets/driver-avatar.png",
        rating: 4.8,
        dir: `${params.asal || "Kepanjen"} → ${params.tujuan || "Surabaya"}`,
        jam: params.jam || "09:30",
        paket: params.paket || "door-to-door",
        fasilitas: "AC, Bagasi Luas, Musik, Asuransi",
      },
      {
        id: "t3",
        mobil: "Toyota Innova Zenix",
        plat: "N 9033 CD",
        fill: 0,
        seat: 6,
        driver: "Dimas Pratama",
        foto: "assets/driver-avatar.png",
        rating: 4.7,
        dir: "Kosong (Siap Berangkat)",
        jam: params.jam || "11:00",
        paket: params.paket || "private",
        fasilitas: "Captain Seat, Private, Asuransi, Tol Prioritas",
      },
    ];
  },

  /* ---------- Booking ---------- */
  async createBooking(data) {
    await wait();
    const booking = {
      id: "SRH-" + Math.floor(100000 + Math.random() * 900000),
      ...data,
      status: "dikonfirmasi", /* tanpa payment: langsung dikonfirmasi */
      dibuat: new Date().toISOString(),
    };
    DB.bookings.push(booking);
    save();
    return booking;
  },

  getBookings() {
    return DB.bookings.filter((b) => b.penumpang_id === DB.session?.id);
  },

  getBooking(id) {
    return DB.bookings.find((b) => b.id === id) || null;
  },

  /* ---------- Pembatalan ---------- */
  async cancelBooking({ bookingId, alasan, oleh }) {
    await wait(250);
    const b = DB.bookings.find((x) => x.id === bookingId);
    if (!b) throw new Error("Pesanan tidak ditemukan.");
    b.status = "dibatalkan";
    DB.cancellations.push({
      id: "cxl" + Date.now(),
      booking_id: bookingId,
      dibatalkan_oleh: oleh,
      alasan,
      tahap: b.status || "menunggu driver",
      waktu: new Date().toISOString(),
    });
    save();
    return b;
  },

  updateBookingStatus(id, status) {
    const b = DB.bookings.find((x) => x.id === id);
    if (b) {
      b.status = status;
      save();
    }
    return b;
  },

  /* ---------- Rating ---------- */
  async addRating({ bookingId, nilai, ulasan }) {
    await wait(200);
    DB.ratings.push({
      id: "r" + Date.now(),
      booking_id: bookingId,
      driver_id: DB.bookings.find((b) => b.id === bookingId)?.driver || "Driver",
      nilai,
      ulasan,
      waktu: new Date().toISOString()
    });
    save();
    return { ok: true };
  },

  getRating(bookingId) {
    return DB.ratings.find((r) => r.booking_id === bookingId) || null;
  },

  /* ---------- Profil update / password ---------- */
  async updateProfile(patch) {
    await wait();
    const u = DB.users.find((x) => x.id === DB.session?.id);
    if (u) Object.assign(u, patch);
    if (DB.session) Object.assign(DB.session, patch);
    save();
    return DB.session;
  },

  async changePassword({ oldPassword, newPassword }) {
    await wait();
    const u = DB.users.find((x) => x.id === DB.session?.id);
    if (!u || u.password !== oldPassword) throw new Error("Password lama salah.");
    u.password = newPassword;
    save();
    return { ok: true };
  },
};
