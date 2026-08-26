/**
 * Uji pesan konfirmasi dan tautan kontak admin.
 *
 *   node tests/contact_test.mjs
 *
 * Yang berisiko di sini bukan tampilannya melainkan URL-nya. Pesannya memuat
 * baris baru dan tanda pagar; keduanya memotong tautan kalau ikut mentah,
 * dan akibatnya baru terlihat setelah user menekan tombolnya dan mendapat
 * pesan yang terpenggal.
 */
import {
  ADMIN_CONTACT,
  buildConfirmationMessage,
  buildUpgradeMessage,
  whatsappHref,
} from '../frontend/src/lib/contact.js'

let pass = 0
let fail = 0
function check(label, got, want) {
  if (got === want) { pass++; return }
  fail++
  console.log(`FAIL  ${label.padEnd(54)} got=${JSON.stringify(got)} want=${JSON.stringify(want)}`)
}

const user = { name: 'Sinta Dewi', email: 'sinta@example.com' }
const trx = { id: 41, document_name: 'ektrens.png', created_at: '2026-08-26 08:47:00' }
const tanggal = () => '26 Agu 2026, 15.47'

console.log('--- isi pesan ---')
const pesan = buildConfirmationMessage({ user, transaction: trx, formatDate: tanggal })
check('menyapa admin', pesan.startsWith('Halo Admin Apeiron'), true)
check('memuat nama', pesan.includes('Nama: Sinta Dewi'), true)
check('memuat email', pesan.includes('Email: sinta@example.com'), true)
// Nomor transaksi yang membuat admin bisa langsung menemukan barisnya.
check('memuat nomor transaksi', pesan.includes('No. Transaksi: #41'), true)
check('memuat nama dokumen', pesan.includes('Dokumen: ektrens.png'), true)
check('memuat tanggal terformat', pesan.includes('Diajukan: 26 Agu 2026, 15.47'), true)
check('ditutup permintaan tolong', pesan.trimEnd().endsWith('Terima kasih.'), true)

console.log('--- data yang tidak ada dilewati, bukan diisi "-" ---')
// Pesan yang penuh tanda hubung terbaca seperti formulir yang gagal.
const minim = buildConfirmationMessage({ user: null, transaction: null })
check('tanpa baris Nama', minim.includes('Nama:'), false)
check('tanpa baris No. Transaksi', minim.includes('No. Transaksi'), false)
check('tanpa tanda hubung kosong', minim.includes(': -'), false)
check('tetap menyapa', minim.startsWith('Halo Admin Apeiron'), true)
check('tetap menutup', minim.trimEnd().endsWith('Terima kasih.'), true)

console.log('--- tautan WhatsApp ---')
const href = whatsappHref(pesan)
check('memakai nomor admin', href.startsWith(`https://wa.me/${ADMIN_CONTACT.whatsappNumber}?text=`), true)
// INI INTINYA. '#' yang tidak di-encode memotong sisa pesan jadi fragment URL,
// dan baris baru mentah memutus tautannya.
check('tanpa # mentah', href.includes('#'), false)
check('tanpa baris baru mentah', /[\r\n]/.test(href), false)
check('baris baru jadi %0A', href.includes('%0A'), true)
check('pagar jadi %23', href.includes('%23'), true)

console.log('--- pesannya utuh saat dibaca kembali ---')
// Encode lalu decode harus menghasilkan teks yang sama persis; kalau ada yang
// hilang, itu yang akan sampai ke admin.
const kembali = decodeURIComponent(href.split('?text=')[1])
check('utuh setelah decode', kembali, pesan)
check('nomor transaksi selamat', kembali.includes('#41'), true)

console.log('--- kontak ---')
check('telegram', ADMIN_CONTACT.telegram, 'https://t.me/Apeiron_id')
check('nomor whatsapp', ADMIN_CONTACT.whatsappNumber, '6285189865707')

console.log('--- karakter yang menyulitkan pada nama berkas ---')
// Nama berkas ditentukan user, jadi bisa memuat apa saja.
const nakal = buildConfirmationMessage({
  user: { name: 'A&B' },
  transaction: { id: 7, document_name: 'bukti #1 (final)&draft.png' },
})
const hrefNakal = whatsappHref(nakal)
check('ampersand ter-encode', hrefNakal.includes('%26'), true)
check('tanpa & mentah pada query', hrefNakal.split('?text=')[1].includes('&'), false)
check('utuh setelah decode', decodeURIComponent(hrefNakal.split('?text=')[1]), nakal)

console.log('--- pesan upgrade ke Member ---')
// Maksudnya berbeda dari pesan konfirmasi transaksi: yang satu menanyakan
// bukti yang sudah dikirim, yang ini menanyakan cara berlangganan. Satu pesan
// untuk dua maksud akan membuat admin menebak-nebak.
const upgrade = buildUpgradeMessage({ user, trialLabel: '2 Bulan' })
check('menyebut maksudnya', upgrade.includes('ingin upgrade ke Member'), true)
check('memuat nama', upgrade.includes('Nama: Sinta Dewi'), true)
check('memuat email', upgrade.includes('Email: sinta@example.com'), true)
// Status percobaan disertakan supaya admin tahu posisi user tanpa bertanya.
check('menyebut status percobaan', upgrade.includes('Status: Percobaan Plugin (2 Bulan)'), true)
check('menanyakan paket', upgrade.includes('paket'), true)
// Tidak boleh tertukar dengan pesan konfirmasi transaksi.
check('bukan pesan konfirmasi', upgrade.includes('konfirmasi bukti transaksi'), false)
check('tanpa nomor transaksi', upgrade.includes('No. Transaksi'), false)

console.log('--- upgrade saat durasi belum diisi ---')
// Statusnya tetap disebut, tanpa kurung kosong yang menggantung.
const tanpaDurasi = buildUpgradeMessage({ user, trialLabel: null })
check('tetap menyebut percobaan', tanpaDurasi.includes('Status: Percobaan Plugin'), true)
check('tanpa kurung kosong', tanpaDurasi.includes('()'), false)

console.log('--- tautan upgrade ---')
const hrefUpgrade = whatsappHref(upgrade)
check('tanpa baris baru mentah', /[\r\n]/.test(hrefUpgrade), false)
check('baris baru ter-encode', hrefUpgrade.includes('%0A'), true)
check('utuh setelah decode', decodeURIComponent(hrefUpgrade.split('?text=')[1]), upgrade)

console.log(`\n${pass} lulus, ${fail} gagal`)
process.exit(fail > 0 ? 1 : 0)
