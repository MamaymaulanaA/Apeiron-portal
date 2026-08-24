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

const USER = {
  id: 7,
  name: ROLE === 'admin' ? 'Rangga Pratama' : 'Sinta Dewi',
  email: ROLE === 'admin' ? 'admin@apeiron.id' : 'sinta@example.com',
  role: ROLE,
}

const TEMPLATES = [
  { id: 1, name: 'Elegant Rose', description: 'Nuansa lembut dengan aksen bunga mawar dan tipografi serif.', thumbnail_path: null, checkout_url: 'https://example.com/rose', is_standard: 1, is_recommended: 1 },
  { id: 2, name: 'Midnight Luxe', description: 'Tema gelap mewah dengan detail emas.', thumbnail_path: null, checkout_url: 'https://example.com/luxe', is_standard: 0, is_recommended: 0 },
  { id: 3, name: 'Minimalis Putih', description: 'Bersih, ringan, fokus pada isi undangan.', thumbnail_path: null, checkout_url: '', is_standard: 1, is_recommended: 0 },
  { id: 4, name: 'Tropical Garden', description: 'Dedaunan tropis yang segar dan berwarna.', thumbnail_path: null, checkout_url: 'https://example.com/tropical', is_standard: 0, is_recommended: 1 },
]

const PLUGINS = [
  { id: 3, version: '2.4.1', filename: 'apeiron-kit-2.4.1.zip', changelog: 'Perbaikan kompatibilitas Elementor 3.25 dan penambahan widget Countdown.', is_active: 1, created_at: '2026-08-18 04:12:00' },
  { id: 2, version: '2.3.0', filename: 'apeiron-kit-2.3.0.zip', changelog: 'Widget Soundscape Player dan perbaikan performa.', is_active: 0, created_at: '2026-07-02 09:40:00' },
]

const TX = [
  { id: 41, user_id: 7, user_name: 'Sinta Dewi', user_email: 'sinta@example.com', status: 'waiting_verification', document_path: 'doc_7_1.png', document_original_name: 'bukti-transfer.png', admin_notes: null, created_at: '2026-08-23 02:15:00', approved_at: null, rejected_at: null },
  { id: 40, user_id: 9, user_name: 'Bagus Herlambang', user_email: 'bagus@example.com', status: 'approved', document_path: 'doc_9_1.jpg', document_original_name: 'transfer-bca.jpg', admin_notes: null, created_at: '2026-08-21 11:02:00', approved_at: '2026-08-21 12:30:00', rejected_at: null },
  { id: 39, user_id: 12, user_name: 'Ayu Lestari', user_email: 'ayu@example.com', status: 'rejected', document_path: 'doc_12_1.pdf', document_original_name: 'struk.pdf', admin_notes: 'Nominal tidak sesuai, mohon unggah bukti yang benar.', created_at: '2026-08-20 08:45:00', approved_at: null, rejected_at: '2026-08-20 10:10:00' },
  { id: 38, user_id: 15, user_name: 'Dimas Anggara', user_email: 'dimas@example.com', status: 'pending_license', document_path: 'doc_15_1.png', document_original_name: 'bukti.png', admin_notes: null, created_at: '2026-08-19 15:20:00', approved_at: '2026-08-19 16:00:00', rejected_at: null },
]

const USERS = [
  { id: 1, username: 'rangga', name: 'Rangga Pratama', email: 'admin@apeiron.id', role: 'admin', is_active: 1, created_at: '2025-11-02 03:00:00', last_login_at: '2026-08-24 01:20:00' },
  { id: 7, username: 'sinta', name: 'Sinta Dewi', email: 'sinta@example.com', role: 'user', is_active: 1, created_at: '2026-03-14 07:30:00', last_login_at: '2026-08-23 22:10:00' },
  { id: 9, username: 'bagus_h', name: 'Bagus Herlambang', email: 'bagus@example.com', role: 'user', is_active: 1, created_at: '2026-05-01 04:15:00', last_login_at: '2026-08-22 09:00:00' },
  { id: 12, username: null, name: 'Ayu Lestari', email: 'ayu@example.com', role: 'user', is_active: 0, created_at: '2026-06-20 02:45:00', last_login_at: null },
  { id: 15, username: 'dimas', name: 'Dimas Anggara', email: 'dimas@example.com', role: 'user', is_active: 1, created_at: '2026-07-11 06:00:00', last_login_at: '2026-08-19 14:50:00' },
]

const NOTIFS = [
  { id: 5, user_id: 7, type: 'plugin_update', title: 'Update Plugin Tersedia!', message: 'Plugin Apeiron Kit versi 2.4.1 telah tersedia. Silakan download versi terbaru dari dashboard Anda.', reference_id: 3, is_read: 0, created_at: '2026-08-23 23:40:00' },
  { id: 4, user_id: 7, type: 'transaction_uploaded', title: 'Bukti Transaksi Baru', message: 'Sinta Dewi telah mengupload bukti transaksi dan menunggu verifikasi.', reference_id: 41, is_read: 0, created_at: '2026-08-23 02:16:00' },
  { id: 3, user_id: 7, type: 'transaction_approved', title: 'Transaksi Disetujui', message: 'Bukti transaksi Anda telah disetujui dan lisensi sudah diterbitkan.', reference_id: 40, is_read: 1, created_at: '2026-08-21 12:31:00' },
]

const LICENSE = {
  id: 2,
  license_key: 'APRN-8F3D-91KC-2200-XQ7T',
  api_key: 'ak_live_9c1f4b7e2d6a8350bb17',
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
          status_label: 'Menunggu Verifikasi',
          document_name: myTx.document_original_name,
          admin_notes: myTx.admin_notes,
          created_at: myTx.created_at,
          approved_at: myTx.approved_at,
          rejected_at: myTx.rejected_at,
        }
      : null,
    license: null,
    templates: TEMPLATES,
    can_upload: false,
    can_download: false,
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
      status_label: t.status_label || 'Menunggu Verifikasi',
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
