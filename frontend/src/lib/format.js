/**
 * Formatter tampilan.
 *
 * Label status sengaja dicerminkan dari Transaction::getStatusLabel() di
 * backend, karena endpoint admin mengirim baris transaksi mentah (tanpa
 * status_label) sedangkan endpoint user mengirimnya. Peta di bawah menjaga
 * kedua jalur tampil identik.
 */

export const TX_STATUS = {
  waiting_verification: {
    label: 'Menunggu Verifikasi',
    tone: 'tone-amber',
    icon: 'Clock',
  },
  approved: {
    label: 'Disetujui',
    tone: 'tone-green',
    icon: 'CircleCheck',
  },
  rejected: {
    label: 'Ditolak',
    tone: 'tone-red',
    icon: 'CircleX',
  },
  pending_license: {
    label: 'Pending Lisensi (Server Offline)',
    tone: 'tone-purple',
    icon: 'ServerCrash',
  },
}

export function statusMeta(status) {
  return (
    TX_STATUS[status] || { label: status || '-', tone: 'tone-blue', icon: 'Circle' }
  )
}

/** Backend menyimpan waktu dalam UTC dan menampilkannya di Asia/Jakarta. */
const TZ = 'Asia/Jakarta'

function toDate(value) {
  if (!value) return null
  // MySQL DATETIME ("2025-01-04 08:30:00") tidak punya penanda zona; tandai
  // sebagai UTC supaya konversi ke WIB sama dengan format_date() di PHP.
  const normalized =
    typeof value === 'string' && /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}/.test(value)
      ? value.replace(' ', 'T') + 'Z'
      : value
  const d = new Date(normalized)
  return Number.isNaN(d.getTime()) ? null : d
}

export function formatDate(value, withTime = true) {
  const d = toDate(value)
  if (!d) return '-'
  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    timeZone: TZ,
  }).format(d)
}

export function formatDateShort(value) {
  return formatDate(value, false)
}

/** Waktu relatif untuk daftar notifikasi. */
export function formatRelative(value) {
  const d = toDate(value)
  if (!d) return '-'
  const diff = Math.floor((Date.now() - d.getTime()) / 1000)
  if (diff < 60) return 'baru saja'
  if (diff < 3600) return `${Math.floor(diff / 60)} menit lalu`
  if (diff < 86400) return `${Math.floor(diff / 3600)} jam lalu`
  if (diff < 604800) return `${Math.floor(diff / 86400)} hari lalu`
  return formatDateShort(value)
}

export function formatNumber(value) {
  return new Intl.NumberFormat('id-ID').format(Number(value) || 0)
}

export function initials(name) {
  if (!name) return '?'
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => w[0])
    .join('')
    .toUpperCase()
}

/** Potong license key panjang untuk tampilan tabel. */
export function truncateMiddle(text, head = 12, tail = 6) {
  if (!text || text.length <= head + tail + 3) return text || '-'
  return `${text.slice(0, head)}…${text.slice(-tail)}`
}
