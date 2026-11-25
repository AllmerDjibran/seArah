/* =========================================================
   SeArah — Komponen UI bersama
   Navbar, Footer, Contact Corner, Toast, Modal, Stepper
   ========================================================= */

const ROOT = (function () {
  const p = location.pathname.replace(/\\/g, "/");
  return p.includes("/pages/") ? "../" : "";
})();

const UI = {
  cities: [
    "Kepanjen", "Malang", "Batu", "Blitar", "Kediri", "Tulungagung", "Madiun", "Ponorogo", "Nganjuk", "Trenggalek", "Magetan", "Ngawi",
    "Surabaya", "Sidoarjo", "Pasuruan", "Mojokerto", "Gresik", "Madura", "Sampang", "Pamekasan", "Sumenep",
    "Probolinggo", "Lumajang", "Jember", "Situbondo", "Bondowoso", "Banyuwangi",
    "Jombang", "Lamongan", "Bojonegoro", "Tuban"
  ],

  /* Navbar: mode tamu | login */
  navbar(mode = "auto") {
    const el = document.getElementById("navbar-slot");
    if (!el) return;

    const u = api.getSession();
    const isLogin = mode === "login" || (mode === "auto" && !!u);

    if (isLogin && u) {
      const initial = (u.name || u.email || "?").charAt(0).toUpperCase();
      const roleName = u.role === "pengemudi" ? "Pengemudi" : u.role === "admin" ? "Admin" : "Penumpang";
      const isDriver = u.role === "pengemudi";
      const isAdmin = u.role === "admin";

      el.innerHTML = `
        <header class="navbar">
          <div class="wrap">
            <a class="navbar__brand" href="${ROOT}index.html">
              <img src="${ROOT}assets/logo.png" alt="Logo SeArah" class="navbar__logo-img">
              <span class="navbar__logo">SeArah</span>
            </a>
            <div class="navbar__user">
              <div class="navbar__avatar">${initial}</div>
              <div class="navbar__user-info" style="cursor:pointer" id="dd-toggle" role="button" aria-expanded="false" tabindex="0">
                <span class="navbar__name">${u.name || u.email.split("@")[0]}</span>
                <span class="navbar__role-badge">${roleName} ▾</span>
              </div>
              <div class="dropdown" id="dd" hidden>
                <div class="dropdown__header">
                  Masuk sebagai <b>${u.email}</b>
                </div>
                ${isAdmin ? `
                  <a href="${ROOT}pages/admin-panel.html">🛡️ Admin Management &amp; Laporan</a>
                  <a href="${ROOT}pages/admin-verifikasi.html">📋 Verifikasi Driver</a>
                  <a href="${ROOT}pages/booking.html">🚗 Cari Perjalanan</a>
                ` : isDriver ? `
                  <a href="${ROOT}pages/dashboard-pengemudi.html">🚗 Dashboard Pengemudi</a>
                  <a href="${ROOT}pages/order-masuk-driver.html">📥 Pesanan Masuk</a>
                  <a href="${ROOT}pages/perjalanan-aktif-driver.html">🗺️ Perjalanan Aktif</a>
                  <a href="${ROOT}pages/edit-akun-driver.html">👤 Edit Akun Driver</a>
                  <a href="${ROOT}pages/status-verifikasi.html">📋 Status Verifikasi</a>
                ` : `
                  <a href="${ROOT}pages/profil.html">👤 Profil Saya</a>
                  <a href="${ROOT}pages/booking.html">🚗 Cari Perjalanan</a>
                  <a href="${ROOT}pages/pembayaran.html">🧾 Riwayat Pesanan</a>
                `}
                <div class="divider"></div>
                <button class="logout" id="dd-logout">🚪 Keluar</button>
              </div>
            </div>
          </div>
        </header>`;

      const dd = document.getElementById("dd");
      const tog = document.getElementById("dd-toggle");
      tog.addEventListener("click", (e) => {
        e.stopPropagation();
        const open = dd.hidden;
        dd.hidden = !open;
        tog.setAttribute("aria-expanded", String(open));
      });

      document.addEventListener("click", (e) => {
        if (!dd.contains(e.target) && !tog.contains(e.target)) {
          dd.hidden = true;
          tog.setAttribute("aria-expanded", "false");
        }
      });

      document.getElementById("dd-logout").addEventListener("click", () => {
        api.logout();
        UI.toast("Berhasil keluar dari akun.", "info");
        setTimeout(() => {
          location.href = ROOT + "index.html";
        }, 300);
      });
    } else {
      el.innerHTML = `
        <header class="navbar">
          <div class="wrap">
            <a class="navbar__brand" href="${ROOT}index.html">
              <img src="${ROOT}assets/logo.png" alt="Logo SeArah" class="navbar__logo-img">
              <span class="navbar__logo">SeArah</span>
            </a>
            <nav class="navbar__btns">
              <button class="btn btn--ghost btn--nav" onclick="location.href='${ROOT}pages/masuk.html'">Masuk</button>
              <button class="btn btn--accent btn--nav" onclick="location.href='${ROOT}pages/daftar.html'">Daftar</button>
            </nav>
          </div>
        </header>`;
    }
  },

  /* Footer resmi SeArah */
  footer() {
    const el = document.getElementById("footer-slot");
    if (!el) return;
    el.innerHTML = `
      <footer class="footer">
        <div class="wrap">
          <div class="footer__grid">
            <div>
              <div class="footer__brand">
                <img src="${ROOT}assets/logo.png" alt="Logo SeArah">
                <span class="footer__brand-name">SeArah</span>
              </div>
              <p style="max-width:340px;line-height:1.6;margin-bottom:16px">
                Layanan travel & shuttle antar kota terpercaya. Menghubungkan penumpang searah dengan aman, nyaman, armada terawat, dan pengemudi terverifikasi.
              </p>
              <div style="display:flex;gap:10px;font-size:12px;color:#cbd5e1">
                <span>🛡️ Dilindungi Asuransi</span> • <span>✅ Driver Terverifikasi</span>
              </div>
            </div>
            <div>
              <h4>Paket Layanan</h4>
              <ul>
                <li><a href="${ROOT}pages/booking.html?paket=reguler">Reguler (Garasi ke garasi)</a></li>
                <li><a href="${ROOT}pages/booking.html?paket=door-to-door">Door to Door</a></li>
                <li><a href="${ROOT}pages/booking.html?paket=private">Private Shuttle</a></li>
              </ul>
            </div>
            <div>
              <h4>Pusat Akun</h4>
              <ul>
                <li><a href="${ROOT}pages/masuk.html">Masuk ke Akun</a></li>
                <li><a href="${ROOT}pages/daftar.html">Daftar Pengguna Baru</a></li>
                <li><a href="${ROOT}pages/daftar-pengemudi.html">Daftar Mitra Pengemudi</a></li>
                <li><a href="${ROOT}pages/admin-verifikasi.html">Login Admin Verifikasi</a></li>
              </ul>
            </div>
            <div>
              <h4>Bantuan & Kontak</h4>
              <ul>
                <li><a href="javascript:void(0)" onclick="UI.toast('Menghubungkan ke WhatsApp CS SeArah...', 'info')">WhatsApp 24/7</a></li>
                <li><a href="javascript:void(0)" onclick="UI.toast('Membuka Instagram resmi @searah.id...', 'info')">Instagram @searah.id</a></li>
                <li><a href="javascript:void(0)" onclick="UI.toast('Membuka TikTok resmi @searah.id...', 'info')">TikTok @searah.id</a></li>
              </ul>
            </div>
          </div>
          <div class="footer__bottom">
            © 2026 SeArah Travel Shuttle · Prototype UI/UX · Sesuai Dokumen Perencanaan & Figma Mockup
          </div>
        </div>
      </footer>`;
  },

  /* Kontak Pojok Web Menggunakan Aset Ikon Resmi Figma */
  contactCorner() {
    if (document.getElementById("contact-corner")) return;
    const div = document.createElement("div");
    div.id = "contact-corner";
    div.className = "contact-corner no-print";
    div.innerHTML = `
      <a class="contact-btn wa" data-tooltip="WhatsApp Customer Service" onclick="UI.openContact('wa')">
        <img src="${ROOT}assets/icon-wa.png" alt="WhatsApp" onerror="this.parentElement.textContent='WA'">
      </a>
      <a class="contact-btn ig" data-tooltip="Instagram @searah.id" onclick="UI.openContact('ig')">
        <img src="${ROOT}assets/icon-ig.png" alt="Instagram" onerror="this.parentElement.textContent='IG'">
      </a>
      <a class="contact-btn tt" data-tooltip="TikTok @searah.id" onclick="UI.openContact('tt')">
        <img src="${ROOT}assets/icon-tiktok.png" alt="TikTok" onerror="this.parentElement.textContent='TT'">
      </a>
      <a class="contact-btn cs" data-tooltip="Customer Service 24/7" onclick="UI.openContact('cs')">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="display:block;margin:auto">
          <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
          <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
          <path d="M14 19a2 2 0 0 1-2 2H9"></path>
        </svg>
      </a>
    `;
    document.body.appendChild(div);
  },

  openContact(type) {
    if (type === "wa") {
      UI.toast("Membuka layanan WhatsApp resmi SeArah (+62 812-3456-7890)", "success");
    } else if (type === "ig") {
      UI.toast("Membuka profil Instagram resmi @searah.id", "info");
    } else if (type === "tt") {
      UI.toast("Membuka profil TikTok resmi @searah.id", "info");
    } else {
      UI.toast("Menghubungkan ke Customer Support SeArah 24 Jam...", "info");
    }
  },

  /* Toast Notification */
  toast(message, type = "info", duration = 3000) {
    let container = document.getElementById("toast-container");
    if (!container) {
      container = document.createElement("div");
      container.id = "toast-container";
      container.className = "toast-container";
      document.body.appendChild(container);
    }

    const t = document.createElement("div");
    t.className = `toast toast--${type}`;
    const icon = type === "success" ? "✅" : type === "error" ? "❌" : type === "warning" ? "⚠️" : "ℹ️";
    t.innerHTML = `<span>${icon}</span> <span>${message}</span>`;
    container.appendChild(t);

    setTimeout(() => {
      t.style.opacity = "0";
      t.style.transform = "translateY(10px)";
      setTimeout(() => t.remove(), 250);
    }, duration);
  },

  /* Modal Dialog */
  modal({ title, content, confirmText = "Lanjut", cancelText = "Batal", onConfirm, onCancel }) {
    const backdrop = document.createElement("div");
    backdrop.className = "modal-backdrop";
    backdrop.innerHTML = `
      <div class="modal-box">
        <h2 style="margin-top:0;font-size:var(--text-xl);color:var(--color-navy)">${title}</h2>
        <div style="margin:16px 0;font-size:var(--text-sm);color:#334155;line-height:1.6">${content}</div>
        <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px">
          ${cancelText ? `<button class="btn btn--ghost" id="m-cancel">${cancelText}</button>` : ""}
          <button class="btn btn--primary" id="m-confirm">${confirmText}</button>
        </div>
      </div>
    `;
    document.body.appendChild(backdrop);

    const close = () => backdrop.remove();

    backdrop.querySelector("#m-confirm")?.addEventListener("click", () => {
      close();
      if (onConfirm) onConfirm();
    });
    backdrop.querySelector("#m-cancel")?.addEventListener("click", () => {
      close();
      if (onCancel) onCancel();
    });
    backdrop.addEventListener("click", (e) => {
      if (e.target === backdrop) close();
    });
  },

  /* Guard: halaman butuh login */
  requireAuth() {
    if (!api.getSession()) {
      location.href = ROOT + "pages/masuk.html?next=" + encodeURIComponent(location.pathname);
      return false;
    }
    return true;
  },

  /* Stepper */
  stepper(steps, current) {
    return `<div class="stepper">` + steps.map((s, i) => {
      const cls = i < current ? "stepper__item--done" : i === current ? "stepper__item--active" : "";
      const line = i < steps.length - 1 ? `<span class="stepper__line"></span>` : "";
      const dotContent = i < current ? "✓" : (i + 1);
      return `<span class="stepper__item ${cls}"><span class="stepper__dot">${dotContent}</span><span>${s}</span></span>${line}`;
    }).join("") + `</div>`;
  },

  /* Validasi */
  validators: {
    email: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v),
    phone: (v) => /^(\+62|62|0)8[1-9][0-9]{6,11}$/.test(v.replace(/[\s-]/g, "")),
    password: (v) => (v || "").length >= 6,
  },

  fieldError(input, msg) {
    const field = input.closest(".field");
    if (!field) return;
    field.classList.add("field--invalid");
    let err = field.querySelector(".field__error");
    if (!err) {
      err = document.createElement("span");
      err.className = "field__error";
      field.appendChild(err);
    }
    err.textContent = msg;
  },
  fieldClear(input) {
    const field = input.closest(".field");
    if (!field) return;
    field.classList.remove("field--invalid");
    const err = field.querySelector(".field__error");
    if (err) err.textContent = "";
  },

  passwordStrength(v) {
    let s = 0;
    if (v.length >= 8) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    return s;
  },
};

/* Inisialisasi otomatis kontak pojok saat DOM siap */
document.addEventListener("DOMContentLoaded", () => {
  UI.contactCorner();
});
