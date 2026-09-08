/**
 * Kapan halaman perlu memuat ulang datanya sendiri setelah tab kembali dibuka.
 *
 * Yang membuat data terasa basi bukan halaman yang dibiarkan terbuka, melainkan
 * user yang kembali ke tab lama dan melihat angka kemarin. Status lisensi bisa
 * berubah di License Server kapan saja — batas aktivasi dinaikkan, durasi jadi
 * Lifetime — dan tidak ada yang memberi tahu portal.
 *
 * Dipisah jadi fungsi murni supaya bisa diuji langsung. Sebagai kondisi di
 * dalam komponen, satu-satunya cara memeriksanya adalah menyembunyikan tab
 * betulan lalu menunggu — dan menunggu bukan sesuatu yang bisa diuji dengan
 * andal.
 *
 * @param {object} o
 * @param {number|null} o.hiddenAt   kapan tab mulai tersembunyi (ms epoch)
 * @param {number} o.now             waktu sekarang (ms epoch)
 * @param {number} [o.minHiddenMs]   ambang minimal tersembunyi
 */
export function shouldRefreshOnFocus({ hiddenAt, now, minHiddenMs = 30_000 }) {
  // Belum pernah tersembunyi: tidak ada yang perlu disusul.
  if (typeof hiddenAt !== 'number') return false
  if (typeof now !== 'number') return false

  const lama = now - hiddenAt

  // Jam sistem mundur — mis. penyesuaian NTP atau zona waktu. Selisih negatif
  // tidak bisa diartikan, dan menganggapnya "sudah lama" akan memicu permintaan
  // yang tidak diminta siapa pun.
  if (!Number.isFinite(lama) || lama < 0) return false

  // Ambang batas menahan alt-tab sekejap. Tanpa ini, berpindah ke tab lain
  // untuk menyalin sesuatu lalu kembali sudah cukup untuk memicu permintaan
  // baru — berkali-kali dalam semenit, tanpa satu pun datanya berubah.
  return lama >= minHiddenMs
}
