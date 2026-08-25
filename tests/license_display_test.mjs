/**
 * Uji penerjemahan status lisensi jadi teks tampilan.
 *
 *   node tests/license_display_test.mjs
 *
 * Modulnya ESM murni tanpa dependensi Vue, jadi bisa diimpor node apa adanya —
 * yang diuji berkas yang sama dengan yang dipakai halaman Plugin.
 *
 * Yang dijaga: tidak ada satu pun angka di sini yang dihitung sendiri. Semua
 * berasal dari objek jawaban server, dan bentuk-bentuk yang mudah salah —
 * Lifetime, Expired, dan sisa nol hari — diperiksa satu per satu.
 */
import {
  licenseSummary,
  licenseDuration,
  licenseStatusMeta,
  isLicenseActive,
} from '../frontend/src/lib/license.js'

let pass = 0
let fail = 0
function check(label, got, want) {
  if (Object.is(got, want)) { pass++; return }
  fail++
  console.log(`FAIL  ${label.padEnd(52)} got=${JSON.stringify(got)} want=${JSON.stringify(want)}`)
}

const AKTIF = { status: 'active', is_lifetime: false, duration_label: '1 Tahun', days_remaining: 327 }
const LIFETIME = { status: 'active', is_lifetime: true, duration_label: 'Lifetime', days_remaining: null }
const EXPIRED = { status: 'expired', is_lifetime: false, duration_label: '1 Tahun', days_remaining: 0 }

console.log('--- ringkasan ---')
check('aktif + sisa hari', licenseSummary(AKTIF), 'Aktif • 327 hari lagi')
// Lifetime tidak punya yang bisa dihitung mundur.
check('lifetime tanpa hitung mundur', licenseSummary(LIFETIME), 'Aktif')
// "Expired • 0 hari lagi" akan membingungkan; tanggalnya yang ditampilkan.
check('expired tanpa hitung mundur', licenseSummary(EXPIRED), 'Expired')
check('ditangguhkan', licenseSummary({ status: 'suspended', days_remaining: 5 }), 'Ditangguhkan • 5 hari lagi')

console.log('--- sisa nol hari pada lisensi yang masih berlaku ---')
// 0 adalah angka yang sah: habis hari ini. Kalau diperiksa dengan falsy,
// angka ini ikut tersaring dan sisa waktunya hilang dari tampilan.
check('sisa 0 hari tetap tampil', licenseSummary({ status: 'active', is_lifetime: false, days_remaining: 0 }), 'Aktif • 0 hari lagi')
check('sisa 1 hari', licenseSummary({ status: 'active', is_lifetime: false, days_remaining: 1 }), 'Aktif • 1 hari lagi')

console.log('--- server diam ---')
check('null -> belum diketahui', licenseSummary(null), 'Belum diketahui')
check('undefined -> belum diketahui', licenseSummary(undefined), 'Belum diketahui')
// Server mengirim status tapi tanpa days_remaining: tampilkan statusnya saja,
// jangan mengarang angkanya.
check('tanpa days_remaining', licenseSummary({ status: 'active', is_lifetime: false }), 'Aktif')

console.log('--- status yang tidak dikenal ---')
// Server boleh menambah status baru kapan saja. Yang penting nilainya tampil
// apa adanya, bukan berubah jadi "Aktif" karena jatuh ke nilai bawaan.
check('status asing tampil apa adanya', licenseSummary({ status: 'revoked', is_lifetime: false }), 'revoked')
check('status asing bukan tone ok', licenseStatusMeta('revoked').tone, 'tone-neutral')

console.log('--- tipe/durasi ---')
check('durasi dari server', licenseDuration(AKTIF), '1 Tahun')
check('lifetime', licenseDuration(LIFETIME), 'Lifetime')
// Cadangan saat server belum mengirim duration_label.
check('lifetime tanpa label', licenseDuration({ is_lifetime: true }), 'Lifetime')
check('berjangka tanpa label', licenseDuration({ is_lifetime: false }), null)
check('null', licenseDuration(null), null)

console.log('--- warna status ---')
check('aktif -> hijau', licenseStatusMeta('active').tone, 'tone-ok')
check('expired -> merah', licenseStatusMeta('expired').tone, 'tone-err')
check('ditangguhkan -> kuning', licenseStatusMeta('suspended').tone, 'tone-warn')
check('belum diketahui -> netral', licenseStatusMeta(null).tone, 'tone-neutral')

console.log('--- menghitung yang aktif ---')
check('aktif dihitung', isLicenseActive(AKTIF), true)
check('expired tidak dihitung', isLicenseActive(EXPIRED), false)
// Inilah aturan intinya: tidak tahu bukan berarti aktif.
check('belum diketahui tidak dihitung', isLicenseActive(null), false)
check('ditangguhkan tidak dihitung', isLicenseActive({ status: 'suspended' }), false)

console.log(`\n${pass} lulus, ${fail} gagal`)
process.exit(fail > 0 ? 1 : 0)
