/**
 * Syarat munculnya popup onboarding user baru.
 *
 * Dipisah jadi fungsi murni supaya bisa diuji tanpa merender komponen. Versi
 * sebelumnya hidup sebagai computed di dalam DashboardView, dan satu-satunya
 * cara memeriksanya adalah lewat browser — yang ternyata tidak bisa
 * diandalkan: navigasi router tidak selalu me-mount ulang, dan popup yang
 * "tidak muncul" jadi tidak bisa dibedakan dari komponen yang memang tidak
 * dijalankan ulang.
 */

/**
 * @param {object} o
 * @param {string|null} o.role     role akun menurut server; hanya 'user' yang digerbang
 * @param {boolean} o.loading      dashboard masih memuat
 * @param {object|null} o.data     isi dashboard; null bila gagal dimuat
 * @param {object|null} o.transaction transaksi user, null bila belum pernah unggah
 * @param {boolean} o.dismissed    sudah ditutup pada sesi ini
 */
export function shouldShowOnboarding({ role, loading, data, transaction, dismissed }) {
  // Aktivasi member hanya berlaku untuk role 'user'.
  //
  // Tanpa syarat ini, syarat "belum punya transaksi" berlaku untuk SETIAP akun
  // yang login — dan admin memang tidak pernah punya transaksi member, sehingga
  // ia justru selalu memenuhi syaratnya. Admin lalu diminta mengunggah bukti
  // pembayaran untuk mendapatkan plugin, yang tidak ada artinya bagi akun yang
  // memang tidak membeli apa pun.
  //
  // Diperiksa positif terhadap 'user', bukan negatif terhadap 'admin': role
  // yang belum termuat (null) atau role baru yang belum dikenal tidak boleh
  // ikut terkena gerbang ini hanya karena ia bukan 'admin'.
  if (role !== 'user') return false

  // Belum selesai memuat: belum ada yang bisa disimpulkan.
  if (loading) return false

  // data null berarti pemuatan GAGAL. Saat itu transaction juga null, dan
  // tanpa syarat ini popup akan muncul pada user lama hanya karena
  // dashboardnya gagal dimuat.
  if (!data) return false

  // Sudah pernah mengunggah — termasuk yang ditolak, karena mereka sudah
  // melewati langkah ini dan dashboard sudah menampilkan alasan penolakan
  // beserta tombol unggah ulang.
  if (transaction) return false

  return !dismissed
}
