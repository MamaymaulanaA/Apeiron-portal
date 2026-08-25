/**
 * Mock API — KHUSUS PENGEMBANGAN LOKAL.
 *
 * Meniru bentuk response api/ (PHP) supaya tampilan bisa diperiksa tanpa
 * menjalankan backend + database. Tidak dipakai saat build/produksi dan tidak
 * memengaruhi backend sungguhan sama sekali.
 *
 *   node mock/server.mjs          → user biasa
 *   ROLE=admin node mock/server.mjs → admin
 */
import { createServer } from 'node:http'

const PORT = 8000
const ROLE = process.env.ROLE === 'admin' ? 'admin' : 'user'

/**
 * Keadaan gerbang grup, untuk memeriksa keempat cabang tanpa menyentuh
 * database: none | pending | rejected | granted (bawaan).
 *   ACCESS=pending node mock/server.mjs
 */
const ACCESS = ['none', 'pending', 'rejected', 'granted'].includes(process.env.ACCESS)
  ? process.env.ACCESS
  : 'granted'

const USER = {
  id: 7,
  name: ROLE === 'admin' ? 'Rangga Pratama' : 'Sinta Dewi',
  email: ROLE === 'admin' ? 'admin@apeiron.id' : 'sinta@example.com',
  role: ROLE,
}

const TEMPLATES = [
  { id: 1, name: '217+ Template Undangan Digital Premium', description: 'Ingin bikin undangan digital yang unik dan elegan tanpa ribet? Paket ini isinya lengkap.', thumbnail_path: null, checkout_url: 'https://example.com/a', is_standard: 0, is_recommended: 1 },
  { id: 2, name: 'Apeiron V2 Series Undangan Digital', description: 'Seri kedua dari template Apeiron dengan tata letak baru.', thumbnail_path: null, checkout_url: 'https://example.com/b', is_standard: 0, is_recommended: 0 },
  { id: 3, name: 'Sales Page Apeiron.ID', description: 'Sales page Apeiron.ID dirancang khusus buat kamu yang ingin jualan lebih rapi.', thumbnail_path: null, checkout_url: 'https://example.com/c', is_standard: 0, is_recommended: 0 },
  { id: 4, name: 'Apeiron V1 Templora', description: 'Isi Paket: 6 Template Undangan Digital Format terbaru siap pakai.', thumbnail_path: null, checkout_url: 'https://example.com/d', is_standard: 0, is_recommended: 0 },
  { id: 5, name: 'Apeiron V3 Sakura Series', description: 'Apeiron V3 adalah seri ketiga dari template Apeiron, dibuat dengan nuansa lembut.', thumbnail_path: null, checkout_url: 'https://example.com/e', is_standard: 0, is_recommended: 0 },
]

const PLUGINS = [
  { id: 3, version: '1.0.3', filename: 'apeiron-kit-2.4.1.zip', changelog: 'Perbaikan kompatibilitas Elementor 3.25 dan penambahan widget Countdown.', is_active: 1, created_at: '2026-08-18 04:12:00' },
  { id: 2, version: '2.3.0', filename: 'apeiron-kit-2.3.0.zip', changelog: 'Widget Soundscape Player dan perbaikan performa.', is_active: 0, created_at: '2026-07-02 09:40:00' },
]

const TX = [
  { id: 41, user_id: 7, user_name: 'Sinta Dewi', user_email: 'sinta@example.com', status: 'approved', document_path: 'doc_7_1.png', document_original_name: 'bukti-transfer.png', admin_notes: null, created_at: '2026-08-23 02:15:00', approved_at: '2026-05-19 01:21:00', rejected_at: null },
  { id: 40, user_id: 9, user_name: 'Bagus Herlambang', user_email: 'bagus@example.com', status: 'approved', document_path: 'doc_9_1.jpg', document_original_name: 'transfer-bca.jpg', admin_notes: null, created_at: '2026-08-21 11:02:00', approved_at: '2026-08-21 12:30:00', rejected_at: null },
  { id: 39, user_id: 12, user_name: 'Ayu Lestari', user_email: 'ayu@example.com', status: 'rejected', document_path: 'doc_12_1.pdf', document_original_name: 'struk.pdf', admin_notes: 'Nominal tidak sesuai, mohon unggah bukti yang benar.', created_at: '2026-08-20 08:45:00', approved_at: null, rejected_at: '2026-08-20 10:10:00' },
  { id: 38, user_id: 15, user_name: 'Dimas Anggara', user_email: 'dimas@example.com', status: 'pending_license', document_path: 'doc_15_1.png', document_original_name: 'bukti.png', admin_notes: null, created_at: '2026-08-19 15:20:00', approved_at: '2026-08-19 16:00:00', rejected_at: null },
  // Transaction::findRecent(10) — dashboard admin memang menerima 10 baris.
  // Mock ikut mengisi 10 supaya tinggi kolom utama yang diperiksa sama dengan
  // yang dilihat admin di produksi.
  { id: 37, user_id: 18, user_name: 'Haritsa Tri Maulana', user_email: 'haritsa.majid@example.com', status: 'waiting_verification', document_path: 'doc_18_1.jpg', document_original_name: 'bukti-mandiri.jpg', admin_notes: null, created_at: '2026-08-18 07:05:00', approved_at: null, rejected_at: null },
  { id: 36, user_id: 21, user_name: 'Iradigit Official', user_email: 'officialiradigit@example.com', status: 'approved', document_path: 'doc_21_1.png', document_original_name: 'transfer.png', admin_notes: null, created_at: '2026-08-17 18:30:00', approved_at: '2026-08-17 19:15:00', rejected_at: null },
  { id: 35, user_id: 24, user_name: 'Babali', user_email: 'solusiparafrase@example.com', status: 'waiting_verification', document_path: 'doc_24_1.pdf', document_original_name: 'bukti-pembayaran.pdf', admin_notes: null, created_at: '2026-08-16 10:45:00', approved_at: null, rejected_at: null },
  { id: 34, user_id: 30, user_name: 'Muhamad Fauzi Ramadhan', user_email: 'fauzi.imaji@example.com', status: 'approved', document_path: 'doc_30_1.jpg', document_original_name: 'bukti-bri.jpg', admin_notes: null, created_at: '2026-08-15 06:20:00', approved_at: '2026-08-15 08:00:00', rejected_at: null },
  { id: 33, user_id: 33, user_name: 'Adian Syahsidiq Pratama', user_email: 'adiansyahshidiqpratama@example.com', status: 'rejected', document_path: 'doc_33_1.png', document_original_name: 'screenshot.png', admin_notes: 'Tangkapan layar terpotong, nominal tidak terlihat.', created_at: '2026-08-14 15:55:00', approved_at: null, rejected_at: '2026-08-14 17:30:00' },
  { id: 32, user_id: 36, user_name: 'Ilham Maulana', user_email: 'borneaninvitation@example.com', status: 'approved', document_path: 'doc_36_1.jpg', document_original_name: 'transfer-bni.jpg', admin_notes: null, created_at: '2026-08-13 09:40:00', approved_at: '2026-08-13 10:25:00', rejected_at: null },
]

const USERS = [
  { id: 1, username: 'rangga', name: 'Rangga Pratama', email: 'admin@apeiron.id', role: 'admin', is_active: 1, created_at: '2025-11-02 03:00:00', last_login_at: '2026-08-24 01:20:00' },
  { id: 7, username: 'sinta', name: 'Sinta Dewi', email: 'sinta@example.com', role: 'user', is_active: 1, created_at: '2026-03-14 07:30:00', last_login_at: '2026-08-23 22:10:00' },
  { id: 9, username: 'bagus_h', name: 'Bagus Herlambang', email: 'bagus@example.com', role: 'user', is_active: 1, created_at: '2026-05-01 04:15:00', last_login_at: '2026-08-22 09:00:00' },
  { id: 12, username: null, name: 'Ayu Lestari', email: 'ayu@example.com', role: 'user', is_active: 0, created_at: '2026-06-20 02:45:00', last_login_at: null },
  { id: 15, username: 'dimas', name: 'Dimas Anggara', email: 'dimas@example.com', role: 'user', is_active: 1, created_at: '2026-07-11 06:00:00', last_login_at: '2026-08-19 14:50:00' },
  // Halaman /admin/users berisi 15 baris. Mock ikut mengisi 15 supaya kartu
  // Anggota di dashboard diuji pada volume yang sebenarnya, bukan pada lima
  // baris yang selalu muat. Beberapa nama dan email sengaja panjang untuk
  // memastikan pemotongan teks bekerja.
  { id: 18, username: 'haritsa', name: 'Haritsa Tri Maulana', email: 'haritsa.majid@example.com', role: 'user', is_active: 1, created_at: '2026-07-14 03:10:00', last_login_at: '2026-08-18 08:05:00' },
  { id: 21, username: 'iradigit', name: 'Iradigit Official', email: 'officialiradigit@example.com', role: 'user', is_active: 1, created_at: '2026-07-16 09:25:00', last_login_at: '2026-08-17 19:40:00' },
  { id: 24, username: 'babali', name: 'Babali', email: 'solusiparafrase@example.com', role: 'user', is_active: 1, created_at: '2026-07-19 01:55:00', last_login_at: '2026-08-16 11:15:00' },
  { id: 27, username: 'arika', name: 'Arika', email: 'duniatanpahulufr@example.com', role: 'user', is_active: 0, created_at: '2026-07-22 05:40:00', last_login_at: null },
  { id: 30, username: 'fauzi', name: 'Muhamad Fauzi Ramadhan', email: 'fauzi.imaji@example.com', role: 'user', is_active: 1, created_at: '2026-07-25 12:00:00', last_login_at: '2026-08-15 07:30:00' },
  { id: 33, username: 'adian', name: 'Adian Syahsidiq Pratama', email: 'adiansyahshidiqpratama@example.com', role: 'user', is_active: 1, created_at: '2026-07-28 04:20:00', last_login_at: '2026-08-14 16:45:00' },
  { id: 36, username: 'ilham', name: 'Ilham Maulana', email: 'borneaninvitation@example.com', role: 'user', is_active: 1, created_at: '2026-08-01 08:10:00', last_login_at: '2026-08-13 10:00:00' },
  { id: 39, username: 'nadia', name: 'Nadia Rahmawati', email: 'nadia@example.com', role: 'user', is_active: 1, created_at: '2026-08-04 02:35:00', last_login_at: '2026-08-12 21:25:00' },
  { id: 42, username: 'yoga', name: 'Yoga Prasetyo', email: 'yoga@example.com', role: 'user', is_active: 1, created_at: '2026-08-07 06:50:00', last_login_at: '2026-08-11 13:05:00' },
  { id: 45, username: null, name: 'Putri Handayani', email: 'putri@example.com', role: 'user', is_active: 0, created_at: '2026-08-10 11:05:00', last_login_at: null },
]

const GROUPS = [
  { id: 1, name: 'Komunitas Apeiron Kit', description: 'Diskusi harian, tanya jawab pemasangan, dan pengumuman rilis.', link: 'https://chat.whatsapp.com/ABCdefGHIjkl', platform: 'whatsapp', is_active: 1, sort_order: 1, created_at: '2026-08-01 09:00:00', updated_at: '2026-08-01 09:00:00' },
  { id: 2, name: 'Kanal Pengumuman', description: 'Kanal satu arah untuk kabar rilis dan pemeliharaan.', link: 'https://t.me/apeironkit', platform: 'telegram', is_active: 1, sort_order: 2, created_at: '2026-08-02 09:00:00', updated_at: '2026-08-02 09:00:00' },
  { id: 3, name: 'Server Discord Kreator', description: null, link: 'https://discord.gg/apeiron', platform: 'discord', is_active: 1, sort_order: 3, created_at: '2026-08-03 09:00:00', updated_at: '2026-08-03 09:00:00' },
  { id: 4, name: 'Grup Reseller (nonaktif)', description: 'Sedang ditutup sementara.', link: 'https://chat.whatsapp.com/XYZ', platform: 'whatsapp', is_active: 0, sort_order: 4, created_at: '2026-08-04 09:00:00', updated_at: '2026-08-04 09:00:00' },
]

const TUTORIALS = [
  { id: 1, title: 'Cara Install Template Undangan', excerpt: 'Langkah memasang template ke WordPress, dari unggah berkas sampai undangan pertama tayang.', content: 'Masuk ke dashboard WordPress Anda, lalu buka menu Appearance > Themes.\n\nKlik Add New, kemudian Upload Theme, dan pilih berkas ZIP template yang sudah diunduh dari portal.\n\nSetelah proses unggah selesai, klik Activate. Template siap dipakai dan Anda bisa mulai mengisi data undangan lewat menu Apeiron Kit.', category: 'Template', icon: 'LayoutTemplate', external_url: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', is_published: 1, sort_order: 1, created_at: '2026-08-05 09:00:00', updated_at: '2026-08-05 09:00:00' },
  { id: 2, title: 'Cara Install Plugin Apeiron Kit', excerpt: 'Memasang plugin dari berkas ZIP dan memastikan versinya sudah yang terbaru.', content: 'Unduh berkas plugin dari halaman Plugin di portal ini.\n\nDi WordPress, buka Plugins > Add New > Upload Plugin, pilih berkas ZIP tadi, lalu klik Install Now.\n\nAktifkan plugin. Menu Apeiron Kit akan muncul di sidebar WordPress.', category: 'Plugin', icon: 'Package', external_url: 'https://youtu.be/dQw4w9WgXcQ', is_published: 1, sort_order: 2, created_at: '2026-08-06 09:00:00', updated_at: '2026-08-06 09:00:00' },
  { id: 3, title: 'Panduan Aktivasi Lisensi', excerpt: 'Menyalin license key dari portal dan memasukkannya ke plugin.', content: 'Buka halaman Plugin di portal, lalu salin License Key dan API Key Anda.\n\nDi WordPress, buka Apeiron Kit > Lisensi, tempelkan kedua kunci itu, lalu klik Aktifkan.\n\nBila muncul pesan gagal, pastikan tidak ada spasi ikut tersalin di awal atau akhir kunci.', category: 'Lisensi', icon: 'KeyRound', external_url: null, is_published: 1, sort_order: 3, created_at: '2026-08-07 09:00:00', updated_at: '2026-08-07 09:00:00' },
  { id: 4, title: 'Cara Menggunakan Plugin', excerpt: 'Mengenal menu utama plugin dan alur membuat undangan pertama.', content: 'Setelah plugin aktif, buka menu Apeiron Kit di sidebar WordPress.\n\nIsi data mempelai, tanggal acara, dan lokasi pada tab Informasi. Semua perubahan tersimpan otomatis.\n\nGunakan tab Tampilan untuk memilih tema warna dan musik latar.', category: 'Plugin', icon: 'Settings', external_url: 'https://docs.example.com/apeiron', is_published: 1, sort_order: 4, created_at: '2026-08-08 09:00:00', updated_at: '2026-08-08 09:00:00' },
  { id: 5, title: 'Draf: Cara Kustom CSS', excerpt: 'Belum terbit - hanya tampil di panel admin.', content: 'Isi sedang disusun.', category: 'Lanjutan', icon: 'Wrench', external_url: null, is_published: 0, sort_order: 5, created_at: '2026-08-09 09:00:00', updated_at: '2026-08-09 09:00:00' },
]

const TUTORIAL_ICONS = ['BookOpen','Download','KeyRound','Package','LayoutTemplate','Rocket','Settings','CircleHelp','Video','FileText','Wrench','Zap']

/** Meniru Tutorial::youtubeId() di backend. */
function youtubeId(url) {
  if (!url) return null
  try {
    const u = new URL(url)
    const host = u.hostname.replace(/^www\./, '')
    if (host === 'youtu.be') return u.pathname.slice(1) || null
    if (host === 'youtube.com' || host === 'm.youtube.com') {
      const v = u.searchParams.get('v')
      if (v) return v
      const m = u.pathname.match(/^\/(embed|shorts)\/([A-Za-z0-9_-]{6,})/)
      if (m) return m[2]
    }
  } catch { /* URL tidak valid */ }
  return null
}

const toCard = (t) => ({
  id: t.id, title: t.title, excerpt: t.excerpt, category: t.category, icon: t.icon,
  external_url: t.external_url, youtube_id: youtubeId(t.external_url),
  has_content: !!(t.content && t.content.trim()), is_published: t.is_published,
  sort_order: t.sort_order, created_at: t.created_at, updated_at: t.updated_at,
})

const NOTIFS = [
  { id: 5, user_id: 7, type: 'plugin_update', title: 'Update Plugin Tersedia!', message: 'Plugin Apeiron Kit versi 2.4.1 telah tersedia. Silakan download versi terbaru dari dashboard Anda.', reference_id: 3, is_read: 0, created_at: '2026-08-23 23:40:00' },
  { id: 4, user_id: 7, type: 'transaction_uploaded', title: 'Bukti Transaksi Baru', message: 'Sinta Dewi telah mengupload bukti transaksi dan menunggu verifikasi.', reference_id: 41, is_read: 0, created_at: '2026-08-23 02:16:00' },
  { id: 3, user_id: 7, type: 'transaction_approved', title: 'Transaksi Disetujui', message: 'Bukti transaksi Anda telah disetujui dan lisensi sudah diterbitkan.', reference_id: 40, is_read: 1, created_at: '2026-08-21 12:31:00' },
  // Lebih dari tiga, supaya batas tinggi dan gulir pada kartu Aktivitas benar
  // benar teruji — dengan tepat tiga baris capnya tidak pernah kena.
  { id: 2, user_id: 7, type: 'transaction_uploaded', title: 'Bukti Transaksi Baru', message: 'Haritsa Tri Maulana telah mengupload bukti transaksi dan menunggu verifikasi.', reference_id: 37, is_read: 1, created_at: '2026-08-18 07:06:00' },
  { id: 1, user_id: 7, type: 'transaction_rejected', title: 'Transaksi Ditolak', message: 'Bukti transaksi Adian Syahsidiq Pratama ditolak: tangkapan layar terpotong, nominal tidak terlihat.', reference_id: 33, is_read: 1, created_at: '2026-08-14 17:31:00' },
  { id: 6, user_id: 7, type: 'transaction_approved', title: 'Transaksi Disetujui', message: 'Bukti transaksi Ilham Maulana telah disetujui dan lisensi sudah diterbitkan.', reference_id: 32, is_read: 1, created_at: '2026-08-13 10:26:00' },
]

const LICENSE = {
  id: 2,
  license_key: 'APEIRON-81BC283035-C7B737988D-776038F43B12-9A0C4E',
  api_key: '63c25c7c003fdf26a5324e73e95ba4c32c20a1094fd8b1e7',
  product_id: 'apeiron-kit',
  assigned_at: '2026-08-21 12:30:00',
}

/** Dashboard user berubah mengikuti transaksi milik USER. */
const myTx = TX.find((t) => t.user_id === USER.id) || null

const routes = {
  'GET /auth/csrf': () => ({ csrf_token: 'mock-csrf-token' }),
  'GET /auth/me': () => ({ user: USER, csrf_token: 'mock-csrf-token' }),
  'POST /auth/login': () => ({ message: 'Login berhasil', user: USER }),
  'POST /auth/logout': () => ({ message: 'Logout berhasil' }),
  'POST /auth/register': () => ({ message: 'Registrasi berhasil', user_id: 99 }),

  'GET /user/dashboard': () => ({
    transaction: myTx
      ? {
          id: myTx.id,
          status: myTx.status,
          status_label: 'Disetujui',
          document_name: myTx.document_original_name,
          admin_notes: myTx.admin_notes,
          created_at: myTx.created_at,
          approved_at: myTx.approved_at,
          rejected_at: myTx.rejected_at,
        }
      : null,
    license: LICENSE,
    templates: TEMPLATES,
    can_upload: false,
    can_download: true,
    latest_plugin: { version: PLUGINS[0].version, filename: PLUGINS[0].filename, changelog: PLUGINS[0].changelog },
  }),
  'GET /user/profile': () => ({
    user: { id: 7, name: 'Sinta Dewi', username: 'sinta', email: 'sinta@example.com', role: 'user', created_at: '2026-03-14 07:30:00', last_login_at: '2026-08-23 22:10:00' },
    stats: { licenses: 1, transactions: 3 },
  }),
  'PUT /user/profile': () => ({ message: 'Profil berhasil diupdate' }),
  'GET /user/licenses': () => ({
    licenses: [LICENSE],
    latest_plugin: { version: PLUGINS[0].version, filename: PLUGINS[0].filename, changelog: PLUGINS[0].changelog },
  }),
  'GET /user/transactions': () => ({
    transactions: TX.filter((t) => t.user_id === 7).concat([
      { id: 22, status: 'approved', status_label: 'Disetujui', document_name: 'transfer-lama.jpg', admin_notes: null, created_at: '2026-05-02 03:10:00', approved_at: '2026-05-02 05:00:00', verified_at: '2026-05-02 05:00:00' },
      { id: 18, status: 'rejected', status_label: 'Ditolak', document_name: 'foto-buram.png', admin_notes: 'Bukti transfer tidak terbaca, mohon unggah ulang.', created_at: '2026-04-28 09:00:00', approved_at: null, verified_at: '2026-04-28 11:20:00' },
    ]).map((t) => ({
      id: t.id,
      status: t.status,
      status_label: ({waiting_verification:'Menunggu Verifikasi',approved:'Disetujui',rejected:'Ditolak',pending_license:'Pending Lisensi (Server Offline)'})[t.status] || t.status,
      document_name: t.document_name || t.document_original_name,
      admin_notes: t.admin_notes,
      created_at: t.created_at,
      approved_at: t.approved_at,
      verified_at: t.verified_at ?? (t.approved_at || t.rejected_at),
    })),
  }),
  'GET /user/templates': () => ({ templates: TEMPLATES }),

  'GET /admin/dashboard': () => ({
    stats: {
      waiting_verification: 6, approved: 128, rejected: 9, pending_license: 2,
      total_users: 143, total_templates: 4, premium_templates: 2, standard_templates: 2,
    },
    recent_transactions: TX,
  }),
  'GET /admin/transactions': () => ({
    transactions: TX,
    pagination: { current_page: 1, per_page: 15, total: 145, total_pages: 10 },
  }),
  'GET /admin/users': () => ({
    users: USERS,
    pagination: { current_page: 1, per_page: 15, total: 143, total_pages: 10 },
  }),
  'GET /admin/templates': () => ({ templates: TEMPLATES }),
  'GET /admin/plugins': () => ({ plugins: PLUGINS }),

  'GET /user/groups': () => ({
    access: ACCESS,
    transaction_status: { none: null, pending: 'waiting_verification', rejected: 'rejected', granted: 'approved' }[ACCESS],
    admin_notes: ACCESS === 'rejected' ? 'Nominal tidak sesuai, mohon unggah bukti yang benar.' : null,
    groups: ACCESS === 'granted' ? GROUPS.filter((g) => g.is_active) : [],
  }),
  'GET /user/tutorials': () => {
    const list = TUTORIALS.filter((t) => t.is_published).map(toCard)
    return { tutorials: list, categories: [...new Set(list.map((t) => t.category).filter(Boolean))] }
  },
  'GET /admin/groups': () => ({ groups: GROUPS }),
  'GET /admin/tutorials': () => ({
    tutorials: TUTORIALS.map((t) => ({ ...toCard(t), content: t.content })),
    icons: TUTORIAL_ICONS,
  }),

  'GET /notifications': () => ({ notifications: NOTIFS, unread_count: 2 }),
  'GET /notifications/unread-count': () => ({ unread_count: 2 }),
}

createServer((req, res) => {
  const url = new URL(req.url, 'http://localhost')
  const path = url.pathname.replace(/^\/api/, '')
  const key = `${req.method} ${path}`

  res.setHeader('Content-Type', 'application/json; charset=utf-8')

  // Detail transaksi (route berparameter)
  const detailMatch = path.match(/^\/admin\/transactions\/(\d+)$/)
  if (req.method === 'GET' && detailMatch) {
    const t = TX.find((x) => x.id === Number(detailMatch[1]))
    if (!t) {
      res.writeHead(404)
      return res.end(JSON.stringify({ error: 'Transaksi tidak ditemukan' }))
    }
    res.writeHead(200)
    return res.end(
      JSON.stringify({
        transaction: { ...t, document_url: `/storage/documents/${t.document_path}` },
      })
    )
  }

  // Detail tutorial (route berparameter)
  const tutorialMatch = path.match(/^\/user\/tutorials\/(\d+)$/)
  if (req.method === 'GET' && tutorialMatch) {
    const t = TUTORIALS.find((x) => x.id === Number(tutorialMatch[1]) && x.is_published)
    if (!t) {
      res.writeHead(404)
      return res.end(JSON.stringify({ error: 'Tutorial tidak ditemukan' }))
    }
    res.writeHead(200)
    return res.end(JSON.stringify({ tutorial: { ...toCard(t), content: t.content } }))
  }

  const handler = routes[key]
  if (handler) {
    res.writeHead(200)
    return res.end(JSON.stringify(handler()))
  }

  // POST/PUT/DELETE lain dianggap sukses agar alur UI bisa ditelusuri.
  if (req.method !== 'GET') {
    res.writeHead(200)
    return res.end(JSON.stringify({ success: true, message: 'OK (mock)' }))
  }

  res.writeHead(404)
  res.end(JSON.stringify({ error: 'Not found' }))
}).listen(PORT, () => {
  console.log(`Mock API (${ROLE}) → http://localhost:${PORT}`)
})
