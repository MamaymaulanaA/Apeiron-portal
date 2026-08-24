/**
 * Menyalin hasil build (frontend/dist) ke root repo, mengikuti tata letak yang
 * dipakai server: index.html di root + folder assets/ di sebelahnya.
 *
 * Aman terhadap file lain: skrip hanya menghapus berkas yang IA SENDIRI salin
 * pada deploy sebelumnya, dicatat di assets/.build-manifest.json. Jadi file
 * yang dirawat manual (mis. assets/css/, assets/img/) tidak pernah tersentuh.
 */
import {
  cpSync,
  existsSync,
  mkdirSync,
  readdirSync,
  readFileSync,
  rmSync,
  statSync,
  writeFileSync,
} from 'node:fs'
import { dirname, join, relative, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const HERE = dirname(fileURLToPath(import.meta.url))
const FRONTEND = resolve(HERE, '..')
const ROOT = resolve(FRONTEND, '..')
const DIST = join(FRONTEND, 'dist')
const MANIFEST = join(ROOT, 'assets', '.build-manifest.json')

if (!existsSync(DIST)) {
  console.error('dist/ tidak ditemukan — jalankan "npm run build" dulu.')
  process.exit(1)
}

/** Semua file di dalam dir, relatif terhadap base. */
function walk(dir, base = dir, out = []) {
  for (const entry of readdirSync(dir)) {
    const full = join(dir, entry)
    if (statSync(full).isDirectory()) walk(full, base, out)
    else out.push(relative(base, full).split('\\').join('/'))
  }
  return out
}

// 1. Bersihkan berkas hasil deploy sebelumnya.
let previous = []
if (existsSync(MANIFEST)) {
  try {
    previous = JSON.parse(readFileSync(MANIFEST, 'utf8')).files || []
  } catch {
    previous = []
  }
}

let removed = 0
for (const rel of previous) {
  const target = join(ROOT, rel)
  if (existsSync(target)) {
    rmSync(target)
    removed++
  }
}

// 2. Salin build baru.
const files = walk(DIST)
for (const rel of files) {
  const src = join(DIST, rel)
  const dest = join(ROOT, rel)
  mkdirSync(dirname(dest), { recursive: true })
  cpSync(src, dest)
}

// 3. Catat apa yang baru saja disalin untuk pembersihan berikutnya.
mkdirSync(dirname(MANIFEST), { recursive: true })
writeFileSync(
  MANIFEST,
  JSON.stringify({ deployedAt: new Date().toISOString(), files }, null, 2)
)

console.log(`Deploy selesai — ${files.length} file disalin, ${removed} file lama dibersihkan.`)
console.log(files.map((f) => `  ${f}`).join('\n'))
