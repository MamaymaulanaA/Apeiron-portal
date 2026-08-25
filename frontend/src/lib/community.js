/**
 * Pembantu bersama untuk fitur Group dan Forum.
 *
 * Dipakai dashboard user, halaman Panduan, dan kedua panel admin, supaya label
 * platform dan pemetaan ikon tutorial hanya ditulis di satu tempat.
 */
import {
  BookOpen,
  Download,
  KeyRound,
  Package,
  LayoutTemplate,
  Rocket,
  Settings,
  CircleHelp,
  Video,
  FileText,
  Wrench,
  Zap,
} from 'lucide-vue-next'

/**
 * Label dan nada untuk tiap platform. Nada memakai token yang sudah ada —
 * tidak ada warna merek asli di sini, supaya kartu grup tetap sewarna dengan
 * kartu lain dan tidak berubah jadi papan logo.
 */
const PLATFORMS = {
  whatsapp: { label: 'WhatsApp', tone: 'tone-mint' },
  telegram: { label: 'Telegram', tone: 'tone-sky' },
  discord: { label: 'Discord', tone: 'tone-lilac' },
  facebook: { label: 'Facebook', tone: 'tone-sky' },
  line: { label: 'LINE', tone: 'tone-mint' },
  slack: { label: 'Slack', tone: 'tone-peach' },
}

export function platformLabel(platform) {
  return PLATFORMS[platform]?.label || 'Tautan'
}

export function platformTone(platform) {
  return PLATFORMS[platform]?.tone || 'tone-neutral'
}

/**
 * Ikon tutorial. Daftarnya cermin dari Tutorial::ICONS di backend — keduanya
 * harus sama, karena backend menolak nama di luar daftar itu dan menjatuhkannya
 * ke BookOpen.
 *
 * Diimpor satu per satu, bukan `import * as`, karena impor menyeluruh menarik
 * seluruh pustaka lucide (±770 kB) ke dalam bundle.
 */
export const TUTORIAL_ICONS = {
  BookOpen,
  Download,
  KeyRound,
  Package,
  LayoutTemplate,
  Rocket,
  Settings,
  CircleHelp,
  Video,
  FileText,
  Wrench,
  Zap,
}

export function tutorialIcon(name) {
  return TUTORIAL_ICONS[name] || BookOpen
}

/** Nama ikon yang boleh dipilih admin — urutannya sama dengan backend. */
export const TUTORIAL_ICON_NAMES = Object.keys(TUTORIAL_ICONS)

/**
 * Kalimat untuk tiap keadaan gerbang grup. Keadaannya ditentukan server;
 * di sini hanya diterjemahkan jadi kalimat.
 */
export const GROUP_ACCESS_COPY = {
  none: {
    title: 'Belum ada transaksi',
    description:
      'Silakan lakukan transaksi terlebih dahulu untuk mendapatkan akses group.',
  },
  pending: {
    title: 'Menunggu verifikasi',
    description:
      'Transaksi Anda sedang menunggu verifikasi. Group akan tersedia setelah transaksi disetujui.',
  },
  rejected: {
    title: 'Transaksi ditolak',
    description:
      'Silakan unggah ulang bukti transaksi. Group akan tersedia setelah transaksi disetujui.',
  },
}
