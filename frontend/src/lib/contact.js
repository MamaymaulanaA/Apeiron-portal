/**
 * Kontak admin dan pesan konfirmasi transaksi.
 *
 * Dipisah dari komponen supaya bisa diuji langsung. Yang berisiko di sini
 * bukan tampilannya melainkan penyusunan URL: pesannya memuat baris baru dan
 * tanda pagar, dan keduanya memotong tautan kalau ikut mentah.
 */

export const ADMIN_CONTACT = {
  telegram: 'https://t.me/Apeiron_id',
  whatsappNumber: '6285189865707',
}

/**
 * Pesan konfirmasi siap kirim.
 *
 * Nomor transaksi, nama berkas, dan tanggal ikut disertakan supaya admin bisa
 * langsung menemukan barisnya. Tanpa itu ia harus bertanya balik, dan justru
 * memperlambat hal yang ingin dipercepat.
 *
 * Baris yang datanya tidak ada dilewati, bukan diisi tanda hubung: pesan yang
 * penuh "-" terbaca seperti formulir yang gagal, bukan permintaan tolong.
 */
export function buildConfirmationMessage({ user, transaction, formatDate } = {}) {
  const tanggal = typeof formatDate === 'function' ? formatDate : (v) => String(v ?? '')
  const baris = ['Halo Admin Apeiron, saya ingin konfirmasi bukti transaksi saya.', '']

  if (user?.name) baris.push(`Nama: ${user.name}`)
  if (user?.email) baris.push(`Email: ${user.email}`)
  if (transaction?.id) baris.push(`No. Transaksi: #${transaction.id}`)
  if (transaction?.document_name) baris.push(`Dokumen: ${transaction.document_name}`)
  if (transaction?.created_at) baris.push(`Diajukan: ${tanggal(transaction.created_at)}`)

  baris.push('', 'Mohon bantuannya untuk diproses. Terima kasih.')
  return baris.join('\n')
}

/**
 * Pesan untuk user yang ingin naik dari masa percobaan ke Member.
 *
 * Dipisah dari pesan konfirmasi transaksi karena maksudnya berbeda: yang satu
 * menanyakan bukti yang sudah dikirim, yang ini menanyakan cara berlangganan.
 * Satu pesan untuk dua maksud akan membuat admin menebak-nebak.
 *
 * Status percobaan disertakan supaya admin tahu posisi user tanpa bertanya.
 */
export function buildUpgradeMessage({ user, trialLabel } = {}) {
  const baris = ['Halo Admin Apeiron, saya ingin upgrade ke Member.', '']

  if (user?.name) baris.push(`Nama: ${user.name}`)
  if (user?.email) baris.push(`Email: ${user.email}`)
  baris.push(trialLabel ? `Status: Percobaan Plugin (${trialLabel})` : 'Status: Percobaan Plugin')

  baris.push('', 'Mohon informasi paket dan cara berlangganannya. Terima kasih.')
  return baris.join('\n')
}

/**
 * Tautan WhatsApp beserta pesannya.
 *
 * encodeURIComponent, bukan encodeURI: yang terakhir membiarkan '#' lolos, dan
 * '#' pada "No. Transaksi: #41" akan memotong sisa pesannya menjadi fragment.
 */
export function whatsappHref(message, number = ADMIN_CONTACT.whatsappNumber) {
  return `https://wa.me/${number}?text=${encodeURIComponent(message)}`
}
