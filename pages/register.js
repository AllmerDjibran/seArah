/* =========================================================
   SeArah — Logika form daftar bertahap (Dokumen 5.2)
   Penumpang: Akun -> Data Diri
   Pengemudi: Akun -> Dokumen (KTP/SIM) -> Status Verifikasi
   ========================================================= */

const Register = {
  cfg: null,
  step: 0,
  data: {},

  init(cfg) {
    this.cfg = cfg;
    UI.navbar("guest");
    UI.footer();
    this.render();
    document.getElementById("btn-next").addEventListener("click", () => this.next());
    document.getElementById("btn-back").addEventListener("click", () => this.prev());
    this.collect();
  },

  collect() {
    const panel = document.querySelector(`.reg-panel[data-step="${this.step}"]`);
    if (!panel) return;
    panel.querySelectorAll("input").forEach((i) => {
      if (i.type === "file") this.data[i.name] = i.files[0] || null;
      else this.data[i.name] = i.value.trim();
    });
  },

  render() {
    document.getElementById("stepper").innerHTML = UI.stepper(this.cfg.steps, this.step);
    document.querySelectorAll(".reg-panel").forEach((p) =>
      p.classList.toggle("reg-panel--active", +p.dataset.step === this.step)
    );
    document.getElementById("btn-back").hidden = this.step === 0;
    const last = this.step === this.cfg.steps.length - 1;
    document.getElementById("btn-next").textContent = last ? "Selesaikan Pendaftaran" : "Lanjut ›";
  },

  validateStep() {
    const panel = document.querySelector(`.reg-panel[data-step="${this.step}"]`);
    if (!panel) return true;
    let ok = true;
    panel.querySelectorAll("input").forEach((i) => {
      UI.fieldClear(i);
      if (i.type === "file") {
        if (!i.files[0]) {
          UI.fieldError(i, "Dokumen wajib dipilih.");
          ok = false;
        }
        return;
      }
      if (i.name === "email" && !UI.validators.email(i.value)) {
        UI.fieldError(i, "Format email tidak valid.");
        ok = false;
      }
      if (i.name === "password" && !UI.validators.password(i.value)) {
        UI.fieldError(i, "Password minimal 6 karakter.");
        ok = false;
      }
      if (i.name === "telepon" && !UI.validators.phone(i.value)) {
        UI.fieldError(i, "Nomor WhatsApp/HP tidak valid (contoh: 081234567890).");
        ok = false;
      }
      if ((i.name === "nama" || i.name === "username") && !i.value.trim()) {
        UI.fieldError(i, "Bagian ini wajib diisi.");
        ok = false;
      }
    });
    return ok;
  },

  async next() {
    this.collect();
    if (!this.validateStep()) {
      UI.toast("Mohon periksa kembali formulir yang belum lengkap.", "warning");
      return;
    }
    if (this.step < this.cfg.steps.length - 1) {
      this.step++;
      this.render();
      return;
    }

    const nextBtn = document.getElementById("btn-next");
    nextBtn.disabled = true;
    nextBtn.textContent = "Mendaftarkan...";

    try {
      const { ktp, sim, ...rest } = this.data;
      await api.register({ ...rest, role: this.cfg.role, name: this.data.nama || this.data.username });
      await api.login({ email: this.data.email, password: this.data.password });

      if (this.cfg.role === "pengemudi") {
        if (ktp) await api.uploadDocument({ jenis: "KTP", fileName: ktp.name });
        if (sim) await api.uploadDocument({ jenis: "SIM", fileName: sim.name });
        UI.toast("Pendaftaran mitra berhasil! Silakan menunggu verifikasi admin.", "success");
        setTimeout(() => {
          location.href = "status-verifikasi.html";
        }, 350);
      } else {
        await api.savePassenger({
          username: this.data.username,
          nama: this.data.nama,
          telepon: this.data.telepon
        });
        UI.toast("Selamat datang di SeArah! Akun berhasil dibuat.", "success");
        setTimeout(() => {
          location.href = "../index.html";
        }, 350);
      }
    } catch (err) {
      UI.toast(err.message, "error");
      nextBtn.disabled = false;
      nextBtn.textContent = "Selesaikan Pendaftaran";
    }
  },

  prev() {
    this.collect();
    if (this.step > 0) {
      this.step--;
      this.render();
    }
  },
};
