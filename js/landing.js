/* =========================================================
   SeArah — Landing logic (Fase 2)
   ========================================================= */
UI.navbar(api.getSession() ? "login" : "guest");
UI.footer();

const tabs = document.querySelectorAll(".tab");
function pilihPaket(p) {
  tabs.forEach((t) => t.setAttribute("aria-selected", t.dataset.paket === p));
}
tabs.forEach((t) => t.addEventListener("click", () => pilihPaket(t.dataset.paket)));
document.querySelectorAll(".btn-pilih").forEach((b) =>
  b.addEventListener("click", () => {
    pilihPaket(b.dataset.paket);
    document.getElementById("search").scrollIntoView({ behavior: "smooth", block: "center" });
  })
);
document.getElementById("search").addEventListener("submit", (e) => {
  e.preventDefault();
  const d = Object.fromEntries(new FormData(e.target));
  d.paket = document.querySelector('.tab[aria-selected="true"]').dataset.paket;
  location.href = "pages/booking.html?" + new URLSearchParams(d);
});