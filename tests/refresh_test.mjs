/**
 * Uji syarat memuat ulang sendiri saat tab kembali dibuka.
 *
 *   node tests/refresh_test.mjs
 *
 * Status lisensi berubah di License Server, bukan di portal, dan tidak ada yang
 * memberi tahu halaman yang sedang terbuka. Tombol Cek Status menutup kebutuhan
 * itu hanya bagi yang ingat menekannya.
 *
 * Syaratnya dipisah jadi fungsi murni karena sebagai kondisi di dalam komponen
 * satu-satunya cara memeriksanya adalah menyembunyikan tab betulan lalu
 * menunggu — dan menunggu bukan sesuatu yang bisa diuji dengan andal.
 */
import { shouldRefreshOnFocus } from '../frontend/src/lib/refresh.js'

let pass = 0
let fail = 0
function check(label, got, want) {
  if (got === want) { pass++; return }
  fail++
  console.log(`FAIL  ${label.padEnd(52)} got=${JSON.stringify(got)} want=${JSON.stringify(want)}`)
}

const DETIK = 1000
const now = 1_800_000_000_000

console.log('--- tersembunyi cukup lama: disegarkan ---')
check('tepat 30 detik', shouldRefreshOnFocus({ hiddenAt: now - 30 * DETIK, now }), true)
check('1 menit', shouldRefreshOnFocus({ hiddenAt: now - 60 * DETIK, now }), true)
check('1 jam', shouldRefreshOnFocus({ hiddenAt: now - 3600 * DETIK, now }), true)
check('semalam', shouldRefreshOnFocus({ hiddenAt: now - 8 * 3600 * DETIK, now }), true)

console.log('--- alt-tab sekejap: tidak disegarkan ---')
// Tanpa ambang batas, berpindah tab untuk menyalin sesuatu lalu kembali sudah
// cukup memicu permintaan baru — berkali-kali dalam semenit, tanpa satu pun
// datanya berubah.
check('0 detik', shouldRefreshOnFocus({ hiddenAt: now, now }), false)
check('1 detik', shouldRefreshOnFocus({ hiddenAt: now - 1 * DETIK, now }), false)
check('10 detik', shouldRefreshOnFocus({ hiddenAt: now - 10 * DETIK, now }), false)
check('29,9 detik', shouldRefreshOnFocus({ hiddenAt: now - 29_900, now }), false)

console.log('--- belum pernah tersembunyi ---')
// Halaman yang baru dibuka sudah memuat datanya lewat onMounted. Menyegarkan
// lagi di sini berarti dua permintaan untuk satu kali buka.
check('hiddenAt null', shouldRefreshOnFocus({ hiddenAt: null, now }), false)
check('hiddenAt undefined', shouldRefreshOnFocus({ hiddenAt: undefined, now }), false)
check('tanpa argumen sama sekali', shouldRefreshOnFocus({}), false)

console.log('--- nilai yang tidak masuk akal ---')
// Jam sistem mundur, mis. penyesuaian NTP atau ganti zona waktu. Selisih
// negatif tidak bisa diartikan, dan menganggapnya "sudah lama" akan memicu
// permintaan yang tidak diminta siapa pun.
check('jam mundur', shouldRefreshOnFocus({ hiddenAt: now + 60 * DETIK, now }), false)
check('NaN', shouldRefreshOnFocus({ hiddenAt: NaN, now }), false)
check('Infinity', shouldRefreshOnFocus({ hiddenAt: -Infinity, now }), false)
check('now bukan angka', shouldRefreshOnFocus({ hiddenAt: now - 60 * DETIK, now: null }), false)
check('hiddenAt berupa teks', shouldRefreshOnFocus({ hiddenAt: String(now - 60 * DETIK), now }), false)

console.log('--- ambang bisa disetel ---')
check(
  'ambang 5 detik, tersembunyi 6',
  shouldRefreshOnFocus({ hiddenAt: now - 6 * DETIK, now, minHiddenMs: 5 * DETIK }),
  true,
)
check(
  'ambang 5 menit, tersembunyi 1 menit',
  shouldRefreshOnFocus({ hiddenAt: now - 60 * DETIK, now, minHiddenMs: 300 * DETIK }),
  false,
)

console.log(`\n${pass} lulus, ${fail} gagal`)
process.exit(fail > 0 ? 1 : 0)
