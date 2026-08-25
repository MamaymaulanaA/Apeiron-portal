/**
 * Tampilan status lisensi.
 *
 * SEMUA ANGKA DAN STATUS DATANG DARI SERVER. Berkas ini tidak pernah
 * menghitung sisa hari, menentukan kedaluwarsa, atau menyimpulkan status dari
 * tanggal. Satu-satunya tugasnya menerjemahkan jawaban server menjadi label,
 * warna, dan ikon — persis seperti TX_STATUS melakukannya untuk transaksi.
 *
 * Kalau server tidak menjawab, yang ditampilkan adalah "belum diketahui",
 * bukan tebakan. Menebak "aktif" karena barisnya ada di database portal
 * adalah persis kesalahan yang membuat status jadi tidak bisa dipercaya.
 */

export const LICENSE_STATUS = {
  active: {
    label: 'Aktif',
    tone: 'tone-ok',
    icon: 'CircleCheck',
  },
  expired: {
    label: 'Expired',
    tone: 'tone-err',
    icon: 'CircleX',
  },
  suspended: {
    label: 'Ditangguhkan',
    tone: 'tone-warn',
    icon: 'CircleAlert',
  },
  // Status ini dipasang server saat lisensi lama digantikan yang baru.
  expired_replaced: {
    label: 'Digantikan',
    tone: 'tone-neutral',
    icon: 'Circle',
  },
  inactive: {
    label: 'Belum Diaktifkan',
    tone: 'tone-neutral',
    icon: 'Circle',
  },
}

/** Keadaan saat server belum pernah terbaca — bukan sebuah status lisensi. */
export const UNKNOWN_STATUS = {
  label: 'Belum diketahui',
  tone: 'tone-neutral',
  icon: 'CircleHelp',
}

export function licenseStatusMeta(status) {
  if (!status) return UNKNOWN_STATUS
  return (
    LICENSE_STATUS[status] || { label: status, tone: 'tone-neutral', icon: 'Circle' }
  )
}

/**
 * Baris ringkas keadaan lisensi, mis. "Aktif • 327 hari lagi".
 *
 * Lifetime tidak pernah mendapat hitung mundur — tidak ada yang dihitung.
 * Expired juga tidak, karena "0 hari lagi" pada lisensi yang sudah habis
 * membingungkan; tanggal berakhirnya yang ditampilkan, ditangani template.
 *
 * @param {object|null} s Objek status dari server apa adanya.
 */
export function licenseSummary(s) {
  const meta = licenseStatusMeta(s?.status)
  if (!s) return meta.label

  if (s.is_lifetime) return meta.label
  if (s.status === 'expired') return meta.label

  // days_remaining sengaja diperiksa terhadap null, bukan falsy: 0 adalah
  // angka yang sah (habis hari ini) dan tidak boleh ikut tersaring.
  if (s.days_remaining === null || s.days_remaining === undefined) return meta.label

  return `${meta.label} • ${s.days_remaining} hari lagi`
}

/**
 * Label tipe/durasi lisensi, mis. "Lifetime" atau "1 Tahun".
 *
 * Nilainya datang dari server; di sini hanya disediakan cadangan seandainya
 * server belum mengirim field tersebut.
 */
export function licenseDuration(s) {
  if (!s) return null
  if (s.duration_label) return s.duration_label
  return s.is_lifetime ? 'Lifetime' : null
}

/**
 * Apakah lisensi ini sedang berlaku menurut SERVER.
 *
 * Dipakai untuk hal-hal seperti menghitung "berapa lisensi aktif". Lisensi
 * yang statusnya belum terbaca tidak dihitung aktif — tidak tahu bukan berarti
 * aktif.
 */
export function isLicenseActive(s) {
  return s?.status === 'active'
}
