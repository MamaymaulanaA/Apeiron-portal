/**
 * Uji kalimat Percobaan Plugin.
 *
 *   node tests/trial_copy_test.mjs
 *
 * Kalimatnya menyusun durasi dari setelan admin. Yang mudah salah dan hanya
 * terlihat di layar user: durasi kosong meninggalkan "selama " yang
 * menggantung, dan satuan yang salah membuat "2 Hari" tertulis "2 Bulan".
 */
import { bonusTrialCopy, BONUS_ACCESS_COPY } from '../frontend/src/lib/community.js'

let pass = 0
let fail = 0
function check(label, got, want) {
  if (got === want) { pass++; return }
  fail++
  console.log(`FAIL  ${label.padEnd(52)}\n      got  = ${JSON.stringify(got)}\n      want = ${JSON.stringify(want)}`)
}

console.log('--- kartu dashboard: ringkas ---')
const ringkas = bonusTrialCopy('2 Bulan')
check('judulnya', ringkas.title, 'Percobaan Plugin')
check(
  'kalimatnya',
  ringkas.description,
  'Anda sedang mendapatkan akses percobaan plugin selama 2 Bulan. Bonus Member tersedia setelah masa percobaan selesai.',
)

console.log('--- halaman Bonus: lengkap ---')
// User datang ke sini KARENA mencari bonus, jadi alasannya harus lengkap:
// bonus itu hak member berlangganan, dan percobaan bukan langganan.
const lengkap = bonusTrialCopy('2 Bulan', { detail: true })
check('judulnya menyebut bonus', lengkap.title, 'Bonus Member belum tersedia')
check('menyebut durasi', lengkap.description.includes('selama 2 Bulan'), true)
check('menyebut berlangganan', lengkap.description.includes('member berlangganan'), true)
// Arahannya di sini bukan "tunggu sampai percobaan selesai" melainkan upgrade:
// percobaan tidak berubah sendiri jadi Member, admin yang mengubahnya. Menyuruh
// menunggu di halaman ini membuat user duduk diam menanti hal yang tidak datang.
check('mengarahkan hubungi admin', lengkap.description.includes('hubungi admin'), true)
check('menyebut upgrade ke Member', lengkap.description.includes('upgrade ke Member'), true)
check('tidak menyuruh menunggu', lengkap.description.includes('masa percobaan selesai'), false)

// Kartu dashboard sengaja tidak ikut berubah: di sana user hanya diberi tahu
// keadaannya, sedangkan ajakan upgrade tempatnya di halaman Bonus.
check('kartu tidak mengajak upgrade', ringkas.description.includes('upgrade'), false)
check('kartu tetap menyebut percobaan selesai', ringkas.description.includes('masa percobaan selesai'), true)

console.log('--- durasi mengikuti setelan admin ---')
check('14 Hari', bonusTrialCopy('14 Hari').description.includes('selama 14 Hari'), true)
check('1 Bulan', bonusTrialCopy('1 Bulan').description.includes('selama 1 Bulan'), true)
// Satuannya ikut apa adanya; tidak ada yang menerjemahkan Hari jadi Bulan.
check('Hari tidak jadi Bulan', bonusTrialCopy('14 Hari').description.includes('Bulan'), false)

console.log('--- durasi belum diisi ---')
// "selama ." tanpa angka lebih membingungkan daripada tidak menyebut durasi.
for (const kosong of [null, undefined, '']) {
  const c = bonusTrialCopy(kosong, { detail: true })
  check(`${JSON.stringify(kosong)}: tanpa "selama" menggantung`, c.description.includes('selama'), false)
  check(`${JSON.stringify(kosong)}: tanpa spasi ganda`, /\s{2}/.test(c.description), false)
  check(`${JSON.stringify(kosong)}: tetap menjelaskan`, c.description.includes('percobaan plugin'), true)
}

console.log('--- keadaan lain tidak ikut berubah ---')
// Percobaan adalah keadaan tersendiri; transaksi menunggu atau ditolak tetap
// memakai kalimatnya sendiri.
check('pending punya kalimat sendiri', BONUS_ACCESS_COPY.pending.title, 'Menunggu verifikasi')
check('rejected punya kalimat sendiri', BONUS_ACCESS_COPY.rejected.title, 'Transaksi ditolak')
check('none punya kalimat sendiri', BONUS_ACCESS_COPY.none.title, 'Belum ada transaksi')
check(
  'tidak ada yang menyebut percobaan',
  Object.values(BONUS_ACCESS_COPY).some((c) => /percobaan/i.test(c.description)),
  false,
)

console.log(`\n${pass} lulus, ${fail} gagal`)
process.exit(fail > 0 ? 1 : 0)
