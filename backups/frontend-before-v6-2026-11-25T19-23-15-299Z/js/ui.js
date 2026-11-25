/* =========================================================
   SeArah — Komponen UI bersama (design: seArah-anantha)
   Navbar per-role, Footer, Contact Corner, Toast, Modal, Stepper, Guard
   ========================================================= */

const ROOT = (function () {
  const p = location.pathname.replace(/\\/g, "/");
  return p.includes("/pages/") ? "../" : "";
})();

const LOGO = `${ROOT}assets/logofix.png`;
const LOGO_FB = `${ROOT}assets/logofix.jpg`;

const UI = {
  cities: [
    "Kepanjen", "Malang", "Batu", "Blitar", "Kediri", "Tulungagung", "Madiun", "Ponorogo", "Nganjuk", "Trenggalek", "Magetan", "Ngawi",
    "Surabaya", "Sidoarjo", "Pasuruan", "Mojokerto", "Gresik", "Madura", "Sampang", "Pamekasan", "Sumenep",
    "Probolinggo", "Lumajang", "Jember", "Situbondo", "Bondowoso", "Banyuwangi",
    "Jombang", "Lamongan", "Bojonegoro", "Tuban"
  ],

  homeFor(role) {
    return role === "admin" ? ROOT + "pages/admin-verifikasi.html"
      : role === "pengemudi" ? ROOT + "pages/dashboard-pengemudi.html"
      : ROOT + "index.html";
  },

  /* Tautan navbar sesuai role. Tamu & penumpang = nav publik; driver/admin = nav alurnya sendiri. */
  navLinks(role) {
    const cur = (location.pathname.replace(/\\/g, "/") + (location.search || "")).toLowerCase();
    const match = (href) => {
      const full = (href.startsWith("javascript") ? "" : ROOT + href).toLowerCase();
      const pathOnly = full.split("?")[0];
      if (href.includes("?tab=")) return cur === full; // bedakan antar tab admin-panel
      return cur.split("?")[0] === pathOnly;
    };
    const dot = (isActive) => (isActive ? '<span class="navbar__dot">•</span>' : "");
    const link = (href, label, onclick) => {
      const target = href.startsWith("javascript") ? "javascript:void(0)" : ROOT + href;
      const act = href.startsWith("javascript") ? (onclick || "void(0)") : "";
      const isActive = !href.startsWith("javascript") && match(href);
      const attr = href.startsWith("javascript") ? `onclick="${act}"` : `href="${target}"`;
      return `<a class="navbar__link ${isActive ? "is-active" : ""}" ${attr}>${dot(isActive)}${label}</a>`;
    };

    if (role === "admin") {
      return [
        link("pages/admin-verifikasi.html", "📋 Verifikasi"),
        link("pages/admin-panel.html", "🛡️ Manajemen Akun &amp; Laporan"),
        link("pages/admin-armada.html", "🚗 Mobil &amp; Driver"),
        link("pages/admin-unban.html", "🔓 Unban"),
        link("pages/admin-setting.html", "⚙️ Pengaturan"),
      ].join("");
    }
    if (role === "pengemudi") {
      return [
        link("pages/dashboard-pengemudi.html", "🚗 Dashboard"),
        link("pages/order-masuk-driver.html", "📥 Pesanan Masuk"),
        link("pages/perjalanan-aktif-driver.html", "🗺️ Perjalanan Aktif"),
        link("pages/edit-akun-driver.html", "👤 Akun Driver"),
      ].join("");
    }
    // tamu & penumpang
    return [
      link("index.html", "🏠 Beranda"),
      link("pages/booking.html", "🔎 Cari Travel"),
      link("index.html#paket", "📦 Paket"),
      link("index.html#kenapa", "⭐ Keunggulan"),
      link("javascript:void(0)", "📞 Kontak", "UI.toast('Hubungi CS SeArah 24/7 di WhatsApp: 0812-3456-7890', 'info')"),
    ].join("");
  },

  navbar(mode = "auto") {
    const el = document.getElementById("navbar-slot");
    if (!el) return;

    const u = api.getSession();
    const isLogin = mode === "login" || (mode === "auto" && !!u);
    const navHtml = `<nav class="navbar__nav">${this.navLinks(isLogin && u ? u.role : "guest")}</nav>`;
    const brandHref = isLogin && u ? this.homeFor(u.role) : ROOT + "index.html";

    if (isLogin && u) {
      const initial = (u.name || u.email || "?").charAt(0).toUpperCase();
      const roleName = u.role === "pengemudi" ? "Pengemudi" : u.role === "admin" ? "Admin" : "Penumpang";
      const ddAccount =
        u.role === "penumpang"
          ? `<a href="${ROOT}pages/profil.html">👤 Profil Saya</a>`
          : u.role === "pengemudi"
          ? `<a href="${ROOT}pages/edit-akun-driver.html">👤 Akun Saya</a>`
          : "";

      el.innerHTML = `
        <header class="navbar">
          <div class="wrap">
            <a class="navbar__brand" href="${brandHref}">
              <img src="${LOGO}" onerror="this.onerror=null;this.src='${LOGO_FB}'" alt="Logo SeArah" class="navbar__logo-img">
              <span class="navbar__logo">SeArah</span>
            </a>
            ${navHtml}
            <div class="navbar__user">
              <div class="navbar__avatar">${initial}</div>
              <div class="navbar__user-info" style="cursor:pointer" id="dd-toggle" role="button" aria-expanded="false" tabindex="0">
                <span class="navbar__name">${u.name || u.email.split("@")[0]}</span>
                <span class="navbar__role-badge">${roleName} ▾</span>
              </div>
              <div class="dropdown" id="dd" hidden>
                <div class="dropdown__header">Masuk sebagai <b>${u.email}</b></div>
                ${ddAccount}
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
        if (!dd.contains(e.target) && !tog.contains(e.target)) { dd.hidden = true; tog.setAttribute("aria-expanded", "false"); }
      });
      document.getElementById("dd-logout").addEventListener("click", () => {
        api.logout().catch(err => UI.toast(err.message, "error"));
      });
    } else {
      el.innerHTML = `
        <header class="navbar">
          <div class="wrap">
            <a class="navbar__brand" href="${brandHref}">
              <img src="${LOGO}" onerror="this.onerror=null;this.src='${LOGO_FB}'" alt="Logo SeArah" class="navbar__logo-img">
              <span class="navbar__logo">SeArah</span>
            </a>
            ${navHtml}
            <div class="navbar__actions">
              <button class="navbar__btn-outline" onclick="location.href='${ROOT}pages/masuk.html'">Masuk</button>
              <button class="navbar__btn-accent" onclick="location.href='${ROOT}pages/daftar.html'">Daftar</button>
            </div>
          </div>
        </header>`;
    }

    const updateNavbarScroll = () => {
      const header = el.querySelector(".navbar");
      if (!header) return;
      if (window.scrollY > 30) { el.classList.add("has-scrolled"); header.classList.add("is-scrolled"); }
      else { el.classList.remove("has-scrolled"); header.classList.remove("is-scrolled"); }
    };
    window.addEventListener("scroll", updateNavbarScroll, { passive: true });
    updateNavbarScroll();
  },

  footer() {
    const el = document.getElementById("footer-slot");
    if (!el) return;
    const c = (typeof api !== "undefined" && api.getContact) ? api.getContact() : { wa: "0812-3456-7890", ig: "@searah.id", tiktok: "@searah.id" };
    el.innerHTML = `
      <footer class="footer">
        <div class="wrap">
          <div class="footer__grid">
            <div>
              <div class="footer__brand">
                <img src="${LOGO}" onerror="this.onerror=null;this.src='${LOGO_FB}'" alt="Logo SeArah">
                <span class="footer__brand-name">SeArah</span>
              </div>
              <p style="max-width:340px;line-height:1.6;margin-bottom:16px">
                Layanan travel &amp; shuttle antar kota terpercaya. Menghubungkan penumpang searah dengan aman, nyaman, armada terawat, dan pengemudi terverifikasi.
              </p>
              <div style="display:flex;gap:10px;font-size:12px;color:#cbd5e1"><span>🛡️ Dilindungi Asuransi</span> • <span>✅ Driver Terverifikasi</span></div>
            </div>
            <div><h4>Paket Layanan</h4><ul>
              <li><a href="${ROOT}pages/booking.html?paket=reguler">Reguler (Garasi ke garasi)</a></li>
              <li><a href="${ROOT}pages/booking.html?paket=door-to-door">Door to Door</a></li>
              <li><a href="${ROOT}pages/booking.html?paket=private">Private Shuttle</a></li>
            </ul></div>
            <div><h4>Pusat Akun</h4><ul>
              <li><a href="${ROOT}pages/masuk.html">Masuk ke Akun</a></li>
              <li><a href="${ROOT}pages/daftar.html">Daftar Pengguna Baru</a></li>
              <li><a href="${ROOT}pages/daftar-pengemudi.html">Daftar Mitra Pengemudi</a></li>
            </ul></div>
            <div><h4>Bantuan &amp; Kontak</h4><ul>
              <li><a href="javascript:void(0)" onclick="UI.openContact('wa')">WhatsApp: ${c.wa}</a></li>
              <li><a href="javascript:void(0)" onclick="UI.openContact('ig')">Instagram ${c.ig}</a></li>
              <li><a href="javascript:void(0)" onclick="UI.openContact('tt')">TikTok ${c.tiktok}</a></li>
              <li><a href="javascript:void(0)" onclick="UI.openContact('cs')">Customer Service</a></li>
            </ul></div>
          </div>
          <div class="footer__bottom">© 2026 SeArah Travel Shuttle · Prototype UI/UX</div>
        </div>
      </footer>`;
  },

  contactCorner() {
    if (document.getElementById("contact-corner")) return;
    const s = api.getSession();
    if (s && s.role === "admin") return; // widget kontak hanya utk tamu/penumpang/pengemudi, bukan admin
    const div = document.createElement("div");
    div.id = "contact-corner";
    div.className = "contact-corner no-print";
    div.innerHTML = `
      <a class="contact-btn wa" data-tooltip="WhatsApp Customer Service" onclick="UI.openContact('wa')"><img src="${ROOT}assets/icon-wa.png" alt="WhatsApp" onerror="this.parentElement.textContent='WA'"></a>
      <a class="contact-btn ig" data-tooltip="Instagram @searah.id" onclick="UI.openContact('ig')"><img src="${ROOT}assets/icon-ig.png" alt="Instagram" onerror="this.parentElement.textContent='IG'"></a>
      <a class="contact-btn tt" data-tooltip="TikTok @searah.id" onclick="UI.openContact('tt')"><img src="${ROOT}assets/icon-tiktok.png" alt="TikTok" onerror="this.parentElement.textContent='TT'"></a>
      <a class="contact-btn cs" data-tooltip="Customer Service 24/7" onclick="UI.openContact('cs')">
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" style="display:block;margin:auto">
          <path d="M3 18v-6a9 9 0 0 1 18 0v6"></path>
          <path d="M21 19a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3zM3 19a2 2 0 0 0 2 2h1a2 2 0 0 0 2-2v-3a2 2 0 0 0-2-2H3z"></path>
          <path d="M14 19a2 2 0 0 1-2 2H9"></path>
        </svg>
      </a>`;
    document.body.appendChild(div);
  },

  openContact(type) {
    const c = (typeof api !== "undefined" && api.getContact) ? api.getContact() : {};
    if (type === "wa") UI.toast("Membuka WhatsApp resmi SeArah: " + (c.wa || "-"), "success");
    else if (type === "ig") UI.toast("Membuka Instagram resmi " + (c.ig || "-"), "info");
    else if (type === "tt") UI.toast("Membuka TikTok resmi " + (c.tiktok || "-"), "info");
    else UI.toast("Menghubungkan ke " + (c.cs || "Customer Support SeArah") + "...", "info");
  },

  toast(message, type = "info", duration = 3000) {
    let container = document.getElementById("toast-container");
    if (!container) { container = document.createElement("div"); container.id = "toast-container"; container.className = "toast-container"; document.body.appendChild(container); }
    const t = document.createElement("div");
    t.className = `toast toast--${type}`;
    const icon = type === "success" ? "✅" : type === "error" ? "❌" : type === "warning" ? "⚠️" : "ℹ️";
    t.textContent = message;
    container.appendChild(t);
    setTimeout(() => { t.style.opacity = "0"; t.style.transform = "translateY(10px)"; setTimeout(() => t.remove(), 250); }, duration);
  },

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
      </div>`;
    document.body.appendChild(backdrop);
    const close = () => backdrop.remove();
    backdrop.querySelector("#m-confirm")?.addEventListener("click", () => { close(); if (onConfirm) onConfirm(); });
    backdrop.querySelector("#m-cancel")?.addEventListener("click", () => { close(); if (onCancel) onCancel(); });
    backdrop.addEventListener("click", (e) => { if (e.target === backdrop) close(); });
  },

  /* Guard: butuh login */
  requireAuth() {
    if (!api.getSession()) { location.href = ROOT + "pages/masuk.html?next=" + encodeURIComponent(location.pathname); return false; }
    return true;
  },

  /* Guard: halaman khusus role. Role lain dialihkan ke beranda role-nya.
     opts.allowGuest=true -> tamu boleh lewat; hanya role lain yang diblokir. */
  requireRole(roles, opts = {}) {
    const u = api.getSession();
    if (!u) {
      if (opts.allowGuest) return true;
      location.href = ROOT + "pages/masuk.html?next=" + encodeURIComponent(location.pathname);
      return false;
    }
    const allowed = Array.isArray(roles) ? roles : [roles];
    if (!allowed.includes(u.role)) {
      UI.toast("Halaman ini khusus " + allowed.join("/") + ".", "warning");
      setTimeout(() => { location.href = UI.homeFor(u.role); }, 700);
      return false;
    }
    return true;
  },

  requirePassenger(opts = {}) { return UI.requireRole("penumpang", opts); },
  requireDriver(opts = {}) { return UI.requireRole("pengemudi", opts); },
  requireAdmin(opts = {}) { return UI.requireRole("admin", opts); },

  stepper(steps, current) {
    return `<div class="stepper">` + steps.map((s, i) => {
      const cls = i < current ? "stepper__item--done" : i === current ? "stepper__item--active" : "";
      const line = i < steps.length - 1 ? `<span class="stepper__line"></span>` : "";
      const dotContent = i < current ? "✓" : (i + 1);
      return `<span class="stepper__item ${cls}"><span class="stepper__dot">${dotContent}</span><span>${s}</span></span>${line}`;
    }).join("") + `</div>`;
  },

  validators: {
    email: (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v),
    phone: (v) => /^(\+62|62|0)8[1-9][0-9]{6,11}$/.test(v.replace(/[\s-]/g, "")),
    password: (v) => new TextEncoder().encode(v || "").length >= 8 && new TextEncoder().encode(v || "").length <= 72,
  },

  fieldError(input, msg) {
    const field = input.closest(".field");
    if (!field) return;
    field.classList.add("field--invalid");
    let err = field.querySelector(".field__error");
    if (!err) { err = document.createElement("span"); err.className = "field__error"; field.appendChild(err); }
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

document.addEventListener("DOMContentLoaded", async () => {
  try { await api.ready; } catch (err) { UI.toast(err.message, "error"); }
  UI.navbar("auto"); UI.footer(); UI.contactCorner();
});
