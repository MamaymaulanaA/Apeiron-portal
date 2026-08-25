# Apeiron Portal — Ringkasan Konteks

Dokumen ini untuk melanjutkan pekerjaan di sesi baru. Tempel isinya sebagai
pesan pertama.

---

## 1. Apa ini

Portal lisensi untuk plugin WordPress **Apeiron Kit** dan katalog **template
undangan digital**. User mengunggah bukti transaksi, admin memverifikasi, lalu
lisensi terbit dan plugin bisa diunduh.

| | |
|---|---|
| Live | https://portal-apeiron.web.id |
| GitHub | https://github.com/MamaymaulanaA/Apeiron-portal (branch `main`) |
| Repo lokal | `C:\Users\mamay\Documents\Apeiron-portal` |

---

## 2. Arsitektur

- **Backend**: PHP vanilla (bukan framework). REST API di `api/index.php` —
  43 endpoint, router sederhana. Auth memakai session cookie PHP + header
  `X-CSRF-Token`.
- **Frontend**: Vue 3 + Vite + vue-router + axios, source di `frontend/`.
- **Deploy**: hosting terhubung Git. **Push ke `main` memicu deploy otomatis**
  (~1 menit). Tidak ada GitHub Actions.

### Sejarah penting
Repo ini semula **hanya berisi hasil build ter-minify tanpa source Vue**.
Frontend-nya dibangun ulang dari nol di `frontend/` dengan mempertahankan
seluruh kontrak backend. Sisa arsitektur lama (`src/**/Views/*.php`) masih ada
tapi **tidak pernah disajikan** — `.htaccess` melempar semua request ke
`index.html`.

---

## 3. Aturan yang WAJIB dijaga

### Backend tidak disentuh
Semua endpoint, payload, aturan akses, dan alur auth mengikuti `api/` apa
adanya. Batas validasi di klien hanya mencerminkan backend (5MB dokumen, 2MB
thumbnail, 50MB ZIP, password 8 karakter) — backend tetap penentu akhir.

### Tidak ada data karangan
Setiap angka, daftar, dan label harus berasal dari endpoint nyata. Bagian yang
belum ada datanya memakai **empty state jujur**, bukan contoh.

### Design system
- **Satu tinggi kontrol**: `--h-control` (40px). Semua tombol, input, select,
  dan segmented control sebaris otomatis sejajar.
- **Radius 10px** (`--r`) untuk semua persegi-membulat. Lingkaran hanya untuk
  avatar dan titik status.
- **Field tanpa `box-shadow`**. Hover/fokus/aktif dibedakan hanya lewat border
  1px — tanpa glow, tanpa outline ganda.
- **Bayangan sangat tipis** (`--sh-card` opasitas 0.022).
- **Outline netral**. Warna hanya di ikon dan badge status. Jangan memberi
  garis berwarna pada kartu — itu yang membuatnya terasa "AI slop".
- **Font Ubuntu**. Ubuntu tidak punya bobot 600, jadi `--fw-semibold` = 500.
- Warna hanya lewat token di `styles/tokens.css`. Jangan tulis hex di komponen.
- Ikon: `import { Bell } from 'lucide-vue-next'` — **jangan** `import * as`
  (menarik seluruh pustaka ±770 kB).

---

## 4. Alur aplikasi

**Dashboard selalu terbuka.** Pengajuan bukti transaksi dan status menunggu
ada di halaman **Transaksi** (`SubmissionPanel.vue`), bukan menutup dashboard.

| Keadaan | Halaman Transaksi | Tombol banner dashboard |
|---|---|---|
| Belum unggah | Panel unggah | Unggah Bukti Transaksi → /transactions |
| Menunggu | Panel "Menunggu verifikasi" | Lihat Status Pengajuan |
| Ditolak | Panel + catatan admin | Lihat Status Pengajuan |
| Disetujui | Panel hilang, tinggal riwayat | Unduh Plugin |

---

## 5. Sumber data tiap bagian

| Bagian | Sumber |
|---|---|
| Statistik, template, lisensi, plugin | `GET /user/dashboard` |
| Aktivitas & notifikasi | `GET /notifications` |
| Riwayat transaksi | `GET /user/transactions` |
| Anggota | `GET /admin/users` — **hanya admin** |
| Forum & Group | **Tidak ada** — empty state |

---

## 6. Batasan yang sudah dipastikan (jangan diulang menyelidikinya)

1. **`GET /admin/users` dilindungi `requireAdmin()`.** User biasa dapat 403.
   Karena itu seksi Anggota hanya tampil untuk admin.
2. **Tidak ada tabel forum maupun group.** Tabel yang ada hanya: `users`,
   `transactions`, `templates`, `plugins`, `user_licenses`, `notifications`,
   `admin_logs`, `download_logs`.
3. **Masa berlaku lisensi tidak tersedia.** `user_licenses` tidak punya kolom
   kedaluwarsa. Server lisensi (`server-apeiron.web.id/api`, v2.0.0) hanya
   punya dua endpoint: `health.php` dan `create_license.php` — **tidak ada**
   endpoint cek status. `getUserLicensesWithStatus()` meng-hardcode
   `status: 'active'`.
   → Langkah berikutnya: cek `grep "Server response" error_log` untuk melihat
   apakah `create_license.php` mengembalikan `expires_at` yang selama ini
   dibuang. Kalau tidak ada, butuh endpoint baru di repo server lisensi.

---

## 7. Alur kerja deploy

```bash
cd frontend
npm run deploy          # vite build + salin dist/ ke root repo
cd ..
git add -A && git commit -m "..." && git push origin main
```

`frontend/scripts/deploy.mjs` mencatat berkas yang disalinnya di
`assets/.build-manifest.json` dan hanya menghapus berkas dari catatan itu —
jadi file yang dirawat manual tidak pernah ikut terhapus.

Verifikasi deploy: bandingkan hash di `index.html` lokal dengan yang live.

---

## 8. Pengembangan lokal

```bash
cd frontend
node mock/server.mjs              # mock API di :8000, sesi user biasa
ROLE=admin node mock/server.mjs   # sesi admin
npm run dev -- --port 5174
```

Mock meniru bentuk response `api/` supaya tampilan bisa diperiksa tanpa
PHP + database. **Hanya untuk lokal**, tidak ikut build.

---

## 9. Skrip verifikasi

Ada di scratchpad sesi (perlu dibuat ulang bila hilang). Playwright memakai
`playwright-core` dari `node_modules` repo plugin WordPress, dengan Chromium di
`%LOCALAPPDATA%/ms-playwright/chromium-1237`.

- `audit.mjs` — tinggi kontrol sebaris, radius, border 1px, bayangan pada
  field, overflow horizontal. Dijalankan di 375/768/1440.
- `authctl.mjs` — tinggi input vs tombol pada /login dan /register, pemusatan
  ikon, CLS, overflow.

**Prinsip yang dipakai sepanjang sesi: ukur, jangan menebak.** Beberapa
"masalah" ternyata tidak ada setelah diukur, dan beberapa bug baru ketahuan
justru karena diukur.

---

## 10. Bug yang pernah ditemukan (jangan terulang)

| Bug | Sebab | Perbaikan |
|---|---|---|
| Chunk 772 kB | `import * as icons from 'lucide-vue-next'` | Impor eksplisit per ikon |
| Halaman meluber di mobile | Grid `1fr` = `minmax(auto, 1fr)` tidak mau menyusut | `minmax(min(Npx, 100%), 1fr)` |
| Grid item memaksa lebar | Grid item bawaannya `min-width: auto` | Beri `min-width: 0` |
| Fokus field tak terlihat | `.input:hover:not(:disabled)` (0,3,0) menang atas `.input:focus` (0,2,0) | Samakan spesifisitas |
| Panel `opacity: 0` | Komentar HTML di akar template membuat komponen multi-root; `<Transition>` butuh satu anak | Bungkus satu akar |
| Isi kartu menempel tepi | `.rail-body` tidak punya aturan padding | Tambah padding + prop `flush` |
| Gambar 271 KB | Ukuran 1536px padahal tampil ~320px | WebP 768px → 56 KB |

---

## 11. Yang masih terbuka

1. **Masa berlaku lisensi** — lihat bagian 6 poin 3.
2. **Forum & Group** — butuh tabel + endpoint di backend bila mau data nyata.
3. **Anggota untuk user biasa** — butuh endpoint baru (yang ada admin-only).
4. **Belum diuji dengan data produksi bervolume besar** — verifikasi memakai
   mock; nama template panjang sudah ditiru, tapi belum diuji dengan banyak
   template atau nama user sangat panjang.

---

## 12. Peringatan penting

Repo **plugin WordPress** di `C:\Users\mamay\Local Sites\...\plugins\Apeiron`
ber-git-root di **`C:/Users/mamay`** — seluruh home directory. Ada 324 file
untracked termasuk `.ssh/`, `.claude.json`, `.clasprc.json`, dan `NTUSER.DAT`.

**Jangan pernah `git add -A` di repo itu.** Pakai `git add -u` (hanya file
yang sudah tracked). Repo itu belum punya remote dan file sensitifnya belum
tracked, jadi belum ada yang bocor — tapi ini bom waktu yang belum dibereskan.

Repo `Apeiron-portal` sendiri aman: root-nya benar di folder project.
