/**
 * DATA PLACEHOLDER — BELUM ADA BACKEND-NYA.
 *
 * Forum dan Group tidak punya tabel maupun endpoint di api/. Isi di bawah
 * dipakai supaya bagian tersebut sudah tampil sesuai rancangan, dan tinggal
 * ditukar dengan pemanggilan API begitu endpoint-nya dibuat.
 *
 * CARA MENGGANTI NANTI (tanpa menyentuh tampilan):
 *   1. Buat endpoint, mis. GET /forums dan GET /groups.
 *   2. Di DashboardView.vue, ganti `PLACEHOLDER_FORUMS` / `PLACEHOLDER_GROUPS`
 *      dengan hasil `api.get(...)` — bentuk objeknya sudah disamakan dengan
 *      gaya response backend yang ada (snake_case, is_* sebagai 0/1).
 *   3. Hapus impor dari berkas ini.
 *
 * Anggota (members) TIDAK ada di sini: dashboard mencoba mengambil data asli
 * lebih dulu lewat GET /admin/users, dan hanya jatuh ke daftar kosong bila
 * pengguna tidak berhak (endpoint itu admin-only di backend).
 */

export const PLACEHOLDER_FORUMS = [
  {
    id: 1,
    title: 'Cara memasang lisensi di WordPress',
    excerpt:
      'Panduan langkah demi langkah memasukkan license key ke plugin Apeiron Kit.',
    author: 'Rangga Pratama',
    replies: 12,
    is_closed: 0,
    created_at: '2026-08-23 09:10:00',
  },
  {
    id: 2,
    title: 'Tips memilih template undangan',
    excerpt:
      'Diskusi memilih antara template Standard dan Premium sesuai kebutuhan acara.',
    author: 'Sinta Dewi',
    replies: 8,
    is_closed: 0,
    created_at: '2026-08-22 14:35:00',
  },
  {
    id: 3,
    title: 'Kendala upload bukti transaksi',
    excerpt:
      'Kumpulan solusi umum bila berkas bukti transfer ditolak saat diunggah.',
    author: 'Bagus Herlambang',
    replies: 21,
    is_closed: 1,
    created_at: '2026-08-20 08:00:00',
  },
]

export const PLACEHOLDER_GROUPS = [
  {
    id: 1,
    name: 'Pengguna Apeiron Kit',
    members: 143,
    is_active: 1,
    last_active_at: '2026-08-24 01:05:00',
  },
  {
    id: 2,
    name: 'Desainer Undangan Digital',
    members: 87,
    is_active: 1,
    last_active_at: '2026-08-23 22:40:00',
  },
  {
    id: 3,
    name: 'Tanya Jawab Lisensi',
    members: 64,
    is_active: 1,
    last_active_at: '2026-08-23 19:15:00',
  },
]

/** Dipakai bila daftar member asli tidak bisa diambil (bukan admin). */
export const PLACEHOLDER_MEMBERS = [
  { id: 'p1', name: 'Amir Khan', role: 'user', is_active: 1 },
  { id: 'p2', name: 'Cipta Annusa', role: 'user', is_active: 1 },
  { id: 'p3', name: 'Diana Anggot', role: 'user', is_active: 1 },
  { id: 'p4', name: 'Erlangga Khan', role: 'user', is_active: 1 },
  { id: 'p5', name: 'Khanna Hema', role: 'user', is_active: 0 },
  { id: 'p6', name: 'Rangga Pratama', role: 'admin', is_active: 1 },
]
