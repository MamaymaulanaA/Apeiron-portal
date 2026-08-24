# Frontend — Apeiron Portal

Source code antarmuka portal (Vue 3 + Vite). Sebelumnya repo ini hanya berisi
hasil build ter-*minify* tanpa source; folder ini adalah sumbernya.

## Menjalankan

```bash
npm install
npm run dev
```

Dev server berjalan di `http://localhost:5173` dan mem-*proxy* `/api` serta
`/storage` ke backend PHP. Arahkan targetnya lewat variabel lingkungan:

```bash
VITE_API_TARGET=https://portal-apeiron.web.id npm run dev
```

### Tanpa backend

Ada mock API untuk memeriksa tampilan tanpa PHP + database:

```bash
node mock/server.mjs            # sesi sebagai user biasa
ROLE=admin node mock/server.mjs # sesi sebagai admin
```

Mock mendengarkan di `:8000` — sama dengan target proxy bawaan. Mock **hanya**
untuk pengembangan lokal dan tidak ikut memengaruhi build.

## Build & deploy

Server produksi tidak menjalankan proses build, jadi hasil build ikut
di-*commit* ke root repo:

```bash
npm run deploy
```

Perintah itu menjalankan `vite build` lalu menyalin `dist/` ke root repo
(`index.html` + `assets/`), mengikuti tata letak yang dibaca `.htaccess`.

`scripts/deploy.mjs` mencatat berkas yang disalinnya di
`assets/.build-manifest.json`, dan pada deploy berikutnya hanya menghapus
berkas dari catatan itu. Jadi file yang dirawat manual (`assets/css/`,
`assets/img/`, dll.) tidak pernah ikut terhapus.

Setelah `npm run deploy`, *commit* perubahan di `index.html` dan `assets/`.

## Struktur

```
src/
  lib/          klien axios (session cookie + CSRF) dan formatter
  stores/       state reaktif: auth, notifikasi, toast
  router/       definisi route + guard akses
  styles/       tokens.css (palet & skala) dan base.css (primitif)
  components/
    layout/     shell aplikasi, sidebar, topbar, dropdown notifikasi
    ui/         kartu statistik, badge, modal, paginasi, toast, dsb.
  views/        satu berkas per halaman; views/admin/ untuk area admin
```

## Aturan yang dijaga

- **Kontrak backend tidak diubah.** Semua endpoint, bentuk payload, aturan
  akses, dan alur auth mengikuti `api/` apa adanya.
- **Path route dipertahankan** persis seperti aplikasi sebelumnya, sehingga URL
  yang sudah dipakai/di-*bookmark* tetap valid.
- **Warna hanya lewat token.** Jangan menulis nilai hex langsung di komponen —
  tambahkan atau pakai token di `styles/tokens.css`.
- **Ikon di-import eksplisit** dari `lucide-vue-next`
  (`import { Bell } from 'lucide-vue-next'`). Jangan memakai
  `import * as icons` — itu menarik seluruh pustaka (±770 kB) ke dalam bundle.
- **Tidak ada kontrol kosong.** Jangan menambah UI yang tidak didukung backend
  (mis. pencarian global — tidak ada endpointnya).

## Catatan tampilan

Aplikasi ini *light-only*, mengikuti perilaku sebelumnya (bundle lama mengunci
`data-theme=light` dan tidak punya pengalih tema).
