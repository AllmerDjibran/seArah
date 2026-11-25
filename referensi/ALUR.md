# SeArah — Peta Alur (sesuai Dokumen Perencanaan Travel Shuttle)

## Peran
- **Penumpang**: daftar (2 hal), pilih paket, booking, checkout, batalkan, invoice, identitas driver, rating.
- **Pengemudi**: daftar (email+password → unggah KTP/SIM), status verifikasi, unggah ulang bila ditolak, dashboard perjalanan.
- **Admin**: login (admin@searah.id / admin123), verifikasi driver (setujui/tolak + catatan), pantau pesanan.

## Alur singkat
```
Landing (index.html)
  ├─ Daftar → daftar.html → daftar-penumpang.html (2 langkah)
  │                       → daftar-pengemudi.html (2 langkah + dokumen)
  │                              → status-verifikasi.html (menunggu/ditolak/disetujui)
  ├─ Masuk → masuk.html → (role) penumpang→index / pengemudi→status/dashboard / admin→admin-verifikasi
  └─ Cari → booking.html (jenis jalan, asuransi, titik jemput/antar)
              → checkout.html (logika 5.5: tamu daftar / isi data diri / langsung)
              → pembayaran.html (pesanan dikonfirmasi, tanpa payment)
              → invoice.html (unduh PDF, detail perjalanan, batalkan)
                   ├─ detail-perjalanan.html (identitas driver + rating)
                   └─ pembatalan.html (ala Gojek)
```

## Logika checkout (dokumen 5.5)
| Kondisi | Alur |
|---|---|
| Belum punya akun | Daftar/masuk dulu, lalu order dibuat |
| Punya akun, data diri kosong | Isi data diri, order dibuat |
| Punya akun, data diri terisi | Langsung order dibuat |

Tanpa payment — order langsung `dikonfirmasi`.

## Status order
dibuat → menunggu driver → dikonfirmasi → driver menuju lokasi → berlangsung → selesai / dibatalkan.

## Pembatalan (5.6)
- Sebelum driver menerima: dibatalkan langsung.
- Setelah driver menerima: pilih alasan, driver diberi tahu.
- Driver dekat/tiba: pembatalan terlambat (sanksi ditunda).
- Saat berlangsung: hubungi CS.

## Ditunda
Harga & pembayaran (QRIS) — disisipkan nanti antara checkout dan konfirmasi.

## Akun admin demo
email: admin@searah.id · password: admin123