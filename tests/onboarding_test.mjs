/**
 * Uji syarat munculnya popup onboarding user baru.
 *
 *   node tests/onboarding_test.mjs
 *
 * Syaratnya sengaja berupa fungsi murni supaya bisa diperiksa langsung. Saat
 * ia masih hidup sebagai computed di dalam komponen, satu-satunya cara
 * memeriksanya adalah lewat browser — dan itu tidak bisa diandalkan: navigasi
 * router tidak selalu me-mount ulang, sehingga "popup tidak muncul" tidak bisa
 * dibedakan dari "komponennya memang tidak dijalankan ulang".
 *
 * Yang dijaga:
 *   - hanya user yang BELUM pernah mengunggah bukti yang melihatnya;
 *   - yang sudah mengunggah — termasuk yang ditolak — tidak pernah melihatnya;
 *   - gagal memuat dashboard tidak boleh disalahartikan sebagai user baru.
 */
import { shouldShowOnboarding } from '../frontend/src/lib/onboarding.js'

let pass = 0
let fail = 0
function check(label, got, want) {
  if (got === want) { pass++; return }
  fail++
  console.log(`FAIL  ${label.padEnd(56)} got=${JSON.stringify(got)} want=${JSON.stringify(want)}`)
}

const dasar = { loading: false, data: {}, transaction: null, dismissed: false }
const tx = (status) => ({ id: 1, status })

console.log('--- user baru ---')
check('belum unggah, belum ditutup -> muncul', shouldShowOnboarding(dasar), true)

console.log('--- sudah mengunggah: tidak pernah muncul ---')
// Inti permintaannya. Sekali bukti dikirim, popup tidak boleh kembali —
// tanpa bergantung pada penyimpanan apa pun di browser.
for (const status of ['waiting_verification', 'approved', 'pending_license', 'rejected']) {
  check(
    `${status} -> tidak muncul`,
    shouldShowOnboarding({ ...dasar, transaction: tx(status) }),
    false,
  )
}
// Bahkan di browser yang belum pernah menutupnya sekalipun.
check(
  'sudah unggah + browser bersih -> tetap tidak muncul',
  shouldShowOnboarding({ ...dasar, transaction: tx('approved'), dismissed: false }),
  false,
)

console.log('--- sudah ditutup pada sesi ini ---')
check('ditutup -> tidak muncul', shouldShowOnboarding({ ...dasar, dismissed: true }), false)

console.log('--- saat masih memuat ---')
// Sebelum data tiba, transaction juga null. Menampilkannya di sini akan
// membuat popup berkedip pada setiap user, termasuk yang sudah mengunggah.
check('loading -> tidak muncul', shouldShowOnboarding({ ...dasar, loading: true }), false)
check(
  'loading + sudah punya transaksi -> tidak muncul',
  shouldShowOnboarding({ ...dasar, loading: true, transaction: tx('approved') }),
  false,
)

console.log('--- dashboard gagal dimuat ---')
// data null berarti gagal, dan transaction ikut null. Tanpa penjagaan ini,
// user lama akan melihat popup "user baru" hanya karena jaringannya bermasalah.
check('data null -> tidak muncul', shouldShowOnboarding({ ...dasar, data: null }), false)
check(
  'data null + belum ditutup -> tetap tidak muncul',
  shouldShowOnboarding({ loading: false, data: null, transaction: null, dismissed: false }),
  false,
)

console.log(`\n${pass} lulus, ${fail} gagal`)
process.exit(fail > 0 ? 1 : 0)
