<script setup>
/**
 * Plugin & Lisensi — GET /user/licenses.
 * Unduhan memakai tautan langsung ke endpoint agar cookie sesi ikut terkirim,
 * sama seperti perilaku sebelumnya.
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import {
  Package,
  KeyRound,
  Download,
  Copy,
  Check,
  Circle,
  CircleAlert,
  History,
  ListChecks,
  RefreshCw,
  CircleCheck,
  CircleHelp,
  CircleX,
  Receipt,
} from 'lucide-vue-next'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import ModalDialog from '@/components/ui/ModalDialog.vue'
import { api, errorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import {
  licenseStatusMeta,
  licenseSummary,
  licenseDuration,
  isLicenseActive,
} from '@/lib/license'
import { toast } from '@/stores/toast'
import { shouldRefreshOnFocus } from '@/lib/refresh'

const loading = ref(true)
const licenses = ref([])
/**
 * Daftar plugin beserta versi terbarunya dan riwayat versinya.
 *
 * Sebelumnya hanya ada satu objek `latest_plugin`, karena hanya satu versi
 * yang pernah bisa hidup. Sekarang tiap plugin membawa `latest` dan
 * `previous`; urutannya sudah ditentukan backend, jadi tampilan tidak
 * mengurutkan apa pun sendiri.
 */
const plugins = ref([])
const copied = ref('')

const hasLicense = computed(() => licenses.value.length > 0)

/**
 * Jumlah lisensi yang AKTIF menurut server.
 *
 * Sebelumnya angka ini diambil dari licenses.length dan diberi label "aktif" —
 * yang berarti lisensi kedaluwarsa pun tetap dihitung aktif. Adanya baris di
 * database portal hanya membuktikan lisensi pernah diterbitkan.
 */
const activeCount = computed(
  () => licenses.value.filter((l) => isLicenseActive(l.status)).length,
)

const STATUS_ICONS = { Circle, CircleAlert, CircleCheck, CircleHelp, CircleX }

function statusIcon(l) {
  return STATUS_ICONS[licenseStatusMeta(l.status?.status).icon] || Circle
}

async function load() {
  loading.value = true
  try {
    await ambilData()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat data lisensi'))
  } finally {
    loading.value = false
  }
}

async function ambilData() {
  const [lic, plug] = await Promise.all([
    api.get('/user/licenses'),
    api.get('/user/plugins'),
  ])
  licenses.value = lic.data.licenses || []
  plugins.value = plug.data.plugins || []
}

/**
 * Muat ulang tanpa terlihat, saat user kembali ke tab ini.
 *
 * Tanpa skeleton: layarnya sudah terisi, dan mengosongkannya sekejap hanya
 * untuk menggantinya dengan angka yang sama membuat halaman berkedip tanpa
 * alasan.
 *
 * Tanpa toast saat gagal: user tidak meminta apa pun — ia cuma kembali ke tab.
 * Memunculkan pesan galat atas sesuatu yang tidak ia lakukan lebih mengganggu
 * daripada menampilkan data yang tertinggal beberapa menit. Yang sudah di layar
 * dibiarkan apa adanya.
 *
 * Tidak memaksa refresh=1: backend punya TTL 10 menit sendiri, jadi kembali ke
 * tab setelah 40 detik tidak menimbulkan permintaan keluar ke License Server —
 * hanya satu permintaan lokal yang dijawab dari cache.
 */
async function muatDiamDiam() {
  try {
    await ambilData()
  } catch {
    /* biarkan data yang sudah tampil */
  }
}

/**
 * Catatan rilis yang sedang dibuka di popup.
 *
 * Changelog panjang tidak dimuat penuh di kartu: satu rilis bisa berisi
 * sepuluh butir perubahan, dan menumpuknya semua membuat kartu memanjang
 * sampai bagian lain terdorong jauh ke bawah. Kartu menampilkan ringkasannya,
 * popup menampilkan daftar lengkapnya.
 */
const changelogOpen = ref(null)

/**
 * Pecah changelog jadi butir daftar.
 *
 * Ditulis admin sebagai teks bebas, satu perubahan per baris. Penanda butir
 * yang mungkin sudah diketik sendiri (-, *, •) dibuang supaya tidak tampil
 * dobel dengan bullet dari daftarnya.
 */
function changelogItems(text) {
  // Dipisah dengan split biasa, bukan regex: pola dengan escape \r\n mudah
  // rusak saat berkasnya disunting, dan hasilnya baru terlihat salah di
  // layar. Sisa carriage return dibersihkan oleh trim().
  return String(text ?? '')
    .split('\n')
    .map((line) => line.replace(/^[\s\-*•]+/, '').trim())
    .filter(Boolean)
}

/** Butir pertama saja, untuk ringkasan di kartu. */
function changelogPreview(text, max = 2) {
  return changelogItems(text).slice(0, max)
}

function openChangelog(plugin, version) {
  changelogOpen.value = { plugin, version }
}

const checking = ref(false)

/**
 * Cek Status — ambil ulang keadaan lisensi langsung dari License Server.
 *
 * Dipakai saat data lisensi diubah di server (durasi jadi Lifetime, batas
 * aktivasi dinaikkan) dan portal masih memegang jawaban lama dari cache.
 *
 * Memakai endpoint yang sudah ada, /user/licenses, dengan refresh=1 yang
 * memberitahu backend agar melewati cache. Tidak ada endpoint baru, dan tidak
 * ada logika lisensi apa pun di sisi ini: portal hanya meminta, membaca, lalu
 * menampilkan.
 */
async function checkStatus() {
  if (checking.value) return
  checking.value = true

  try {
    const { data } = await api.get('/user/licenses', { params: { refresh: 1 } })
    const fresh = data.licenses || []

    // Permintaan berhasil TIDAK berarti server lisensi menjawab.
    //
    // Kalau server diam, backend mempertahankan cache lama dan menandainya
    // status_stale — HTTP-nya tetap 200. Melaporkan "berhasil diperbarui" di
    // sini akan membuat user yakin datanya baru padahal tidak berubah sama
    // sekali. Jadi tanda itu yang menentukan pesannya, bukan kode HTTP.
    const adaYangBasi = fresh.some((l) => l.status_stale)

    licenses.value = fresh

    if (adaYangBasi) {
      // Sebabnya dilaporkan apa adanya bila backend tahu.
      //
      // "Server lisensi tidak merespons" dulu dipakai untuk SEMUA kegagalan —
      // termasuk saat server menjawab 404 dalam sepersekian detik. Keterangan
      // yang salah membuat orang mencari masalahnya di tempat yang salah;
      // ini pernah terjadi dan butuh penelusuran untuk membantahnya.
      const sebab = fresh.find((l) => l.status_stale && l.status_error)?.status_error

      toast.warning(
        sebab
          ? `${sebab} Menampilkan data terakhir yang tersimpan.`
          : 'Server lisensi tidak merespons. Menampilkan data terakhir yang tersimpan.',
      )
    } else {
      toast.success('Status lisensi diperbarui')
    }
  } catch (e) {
    // Data lama sengaja tidak disentuh: gagal mengecek bukan alasan untuk
    // mengosongkan yang sudah ada di layar.
    toast.error(errorMessage(e, 'Gagal mengecek status lisensi'))
  } finally {
    checking.value = false
  }
}

async function copyText(value, key) {
  try {
    await navigator.clipboard.writeText(value)
    copied.value = key
    setTimeout(() => (copied.value = ''), 1800)
  } catch {
    toast.error('Gagal menyalin')
  }
}

/* ---------- Segarkan sendiri saat tab kembali dibuka ---------- */

/**
 * Status lisensi berubah di License Server, bukan di portal — batas aktivasi
 * dinaikkan, durasi jadi Lifetime — dan tidak ada yang memberi tahu halaman ini.
 * Tombol Cek Status menutup kebutuhan itu hanya bagi yang ingat menekannya.
 *
 * Yang disegarkan di sini bukan pemanggilan ke License Server melainkan
 * pemanggilan ke portal; TTL 10 menit di backend tetap yang menentukan kapan
 * server lisensi benar-benar dihubungi. Jadi ini tidak menambah beban ke sana.
 */
const tersembunyiSejak = ref(null)

function onVisibilitas() {
  if (document.hidden) {
    tersembunyiSejak.value = Date.now()
    return
  }

  if (shouldRefreshOnFocus({ hiddenAt: tersembunyiSejak.value, now: Date.now() })) {
    muatDiamDiam()
  }
  tersembunyiSejak.value = null
}

onMounted(() => {
  load()
  document.addEventListener('visibilitychange', onVisibilitas)
})

onBeforeUnmount(() => {
  document.removeEventListener('visibilitychange', onVisibilitas)
})
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>Plugin &amp; Lisensi</h1>
        <p class="text-muted">Kelola lisensi Anda dan unduh plugin versi terbaru.</p>
      </div>
    </header>

    <!-- ---------- Plugin: versi terbaru + riwayat ---------- -->
    <SkeletonRows v-if="loading" :rows="1" :height="120" />

    <section v-for="p in plugins" v-else :key="p.id" class="release card">
      <div class="release-main">
        <span class="icon-chip release-icon tone-neutral">
          <Package :size="24" />
        </span>

        <div class="release-text">
          <p class="text-overline">Versi Terbaru</p>
          <h2>
            {{ p.name }}
            <span class="release-ver">v{{ p.latest.version }}</span>
            <span class="badge tone-ok">Terbaru</span>
          </h2>
          <!-- Ringkasan saja. Rilis dengan sepuluh butir perubahan akan
               memanjangkan kartu sampai bagian lain terdorong jauh ke bawah,
               jadi selebihnya dipindah ke popup. -->
          <ul v-if="changelogPreview(p.latest.changelog).length" class="release-log">
            <li v-for="(item, i) in changelogPreview(p.latest.changelog)" :key="i">
              {{ item }}
            </li>
          </ul>

          <button
            v-if="changelogItems(p.latest.changelog).length > 2"
            class="link-more"
            type="button"
            @click="openChangelog(p, p.latest)"
          >
            <ListChecks :size="14" />
            Lihat semua catatan rilis ({{ changelogItems(p.latest.changelog).length }})
          </button>

          <p class="text-caption">Rilis {{ formatDate(p.latest.released_at, false) }}</p>
        </div>

        <div class="release-action">
          <!-- Gerbangnya tetap seperti sebelumnya: unduhan butuh lisensi.
               Server yang menentukan, ini hanya menyembunyikan tombolnya. -->
          <a v-if="hasLicense" class="btn btn-primary" :href="p.latest.download_url">
            <Download :size="17" />
            Unduh Plugin
          </a>
          <p v-else class="locked">
            <CircleAlert :size="15" />
            Lisensi aktif diperlukan.
          </p>
        </div>
      </div>

      <!-- Hanya muncul kalau memang ada versi sebelumnya. -->
      <div v-if="hasLicense && p.previous.length" class="history">
        <p class="history-title">
          <History :size="14" />
          Versi sebelumnya
        </p>

        <ul class="history-list">
          <li v-for="v in p.previous" :key="v.id" class="history-row">
            <span class="history-ver">v{{ v.version }}</span>
            <span class="text-caption history-date">
              {{ formatDate(v.released_at, false) }}
            </span>
            <!-- Sebelumnya catatan rilis versi lama tidak terlihat sama
                 sekali; sekarang bisa dibuka tanpa memenuhi daftar. -->
            <button
              v-if="changelogItems(v.changelog).length"
              class="btn btn-sm btn-secondary btn-icon"
              type="button"
              :aria-label="`Catatan rilis v${v.version}`"
              @click="openChangelog(p, v)"
            >
              <ListChecks :size="13" />
            </button>

            <a class="btn btn-sm btn-secondary" :href="v.download_url">
              <Download :size="13" />
              Unduh
            </a>
          </li>
        </ul>
      </div>
    </section>

    <section v-if="!loading && !plugins.length" class="card">
      <EmptyState
        :icon="Package"
        title="Belum ada plugin"
        description="Plugin akan tampil di sini setelah admin merilis versinya."
      />
    </section>

    <!-- ---------- Popup catatan rilis ---------- -->
    <ModalDialog
      :open="!!changelogOpen"
      title="Catatan Rilis"
      :subtitle="changelogOpen ? `${changelogOpen.plugin.name} v${changelogOpen.version.version}` : ''"
      @close="changelogOpen = null"
    >
      <div v-if="changelogOpen" class="cl">
        <p class="text-caption cl-date">
          Rilis {{ formatDate(changelogOpen.version.released_at, false) }}
        </p>

        <ul class="cl-list">
          <li v-for="(item, i) in changelogItems(changelogOpen.version.changelog)" :key="i">
            {{ item }}
          </li>
        </ul>
      </div>

      <template #footer>
        <button class="btn btn-secondary" type="button" @click="changelogOpen = null">
          Tutup
        </button>
        <a
          v-if="hasLicense && changelogOpen"
          class="btn btn-primary"
          :href="changelogOpen.version.download_url"
        >
          <Download :size="15" />
          Unduh v{{ changelogOpen.version.version }}
        </a>
      </template>
    </ModalDialog>

    <!-- ---------- Daftar lisensi ---------- -->
    <section class="card">
      <header class="card-header">
        <h3>
          <span class="icon-chip icon-chip-sm tone-ok"><KeyRound :size="16" /></span>
          Lisensi Saya
        </h3>
        <span v-if="activeCount" class="badge tone-ok">
          {{ activeCount }} aktif
        </span>
      </header>

      <SkeletonRows v-if="loading" :rows="2" :height="88" />

      <div v-else-if="hasLicense" class="lic-list">
        <article v-for="l in licenses" :key="l.id" class="lic">
          <header class="lic-head">
            <span class="row">
              <span class="icon-chip icon-chip-sm tone-ok"><KeyRound :size="15" /></span>
              <span class="lic-title">
                <span class="lic-product">{{ l.product_id || 'Apeiron Kit' }}</span>
                <span class="text-caption">
                  Diterbitkan {{ formatDate(l.assigned_at, false) }}
                </span>
              </span>
            </span>
            <span class="lic-actions">
              <button
                class="btn btn-sm btn-secondary"
                type="button"
                :disabled="checking"
                @click="checkStatus"
              >
                <RefreshCw :size="14" :class="{ spinning: checking }" />
                {{ checking ? 'Mengecek…' : 'Cek Status' }}
              </button>

              <a class="btn btn-sm btn-secondary" href="/api/user/download-invoice">
                <Receipt :size="14" />
                Invoice
              </a>
            </span>
          </header>

          <!-- Keadaan lisensi menurut License Server.
               Tidak ada satu pun angka di bawah yang dihitung di sini. -->
          <div class="lic-state">
            <span class="badge" :class="licenseStatusMeta(l.status?.status).tone">
              <component :is="statusIcon(l)" :size="13" :stroke-width="2.4" />
              {{ licenseSummary(l.status) }}
            </span>

            <span v-if="licenseDuration(l.status)" class="badge tone-neutral">
              {{ licenseDuration(l.status) }}
            </span>

            <!-- Ditampilkan hanya saat server tidak terjangkau DAN masih ada
                 data lama. Lebih jujur daripada menyajikannya sebagai baru. -->
            <span v-if="l.status_stale" class="lic-stale">
              <CircleAlert :size="13" />
              Data tersimpan {{ formatDate(l.status_checked_at) }}
            </span>

            <!-- Ada saat statusnya terbaca tapi tidak bisa disimpan — mis.
                 migration cache belum dijalankan. Hilang sendiri begitu
                 penyebabnya diperbaiki. -->
            <span v-if="l.status_error" class="lic-stale">
              <CircleAlert :size="13" />
              {{ l.status_error }}
            </span>
          </div>

          <dl v-if="l.status" class="lic-facts">
            <div class="fact">
              <dt>Tanggal aktivasi</dt>
              <dd>
                {{ l.status.activated_at
                  ? formatDate(l.status.activated_at, false)
                  : 'Belum diaktifkan' }}
              </dd>
            </div>

            <div class="fact">
              <dt>{{ l.status.status === 'expired' ? 'Berakhir' : 'Berlaku hingga' }}</dt>
              <dd>
                {{ l.status.is_lifetime
                  ? 'Selamanya'
                  : formatDate(l.status.expires_at, false) }}
              </dd>
            </div>

            <!-- Lifetime tidak punya hitung mundur, dan lisensi yang sudah
                 habis tidak perlu diberi tahu sisa nol hari. -->
            <div
              v-if="!l.status.is_lifetime
                && l.status.status !== 'expired'
                && l.status.days_remaining !== null"
              class="fact"
            >
              <dt>Sisa waktu</dt>
              <dd>{{ l.status.days_remaining }} hari</dd>
            </div>

            <div v-if="l.status.activation_limit" class="fact">
              <dt>Pemakaian</dt>
              <dd>{{ l.status.activations_used }} / {{ l.status.activation_limit }} situs</dd>
            </div>
          </dl>

          <p v-else class="lic-unknown">
            <CircleHelp :size="14" />
            Status lisensi belum bisa dibaca dari server. Coba muat ulang beberapa saat lagi.
          </p>

          <div class="lic-keys">
            <div class="field">
              <span class="text-overline">License Key</span>
              <div class="key-row">
                <code class="key">{{ l.license_key }}</code>
                <button
                  class="btn btn-sm btn-secondary btn-icon"
                  type="button"
                  aria-label="Salin license key"
                  @click="copyText(l.license_key, `lic-${l.id}`)"
                >
                  <component :is="copied === `lic-${l.id}` ? Check : Copy" :size="15" />
                </button>
              </div>
            </div>

            <div v-if="l.api_key" class="field">
              <span class="text-overline">API Key</span>
              <div class="key-row">
                <code class="key">{{ l.api_key }}</code>
                <button
                  class="btn btn-sm btn-secondary btn-icon"
                  type="button"
                  aria-label="Salin API key"
                  @click="copyText(l.api_key, `api-${l.id}`)"
                >
                  <component :is="copied === `api-${l.id}` ? Check : Copy" :size="15" />
                </button>
              </div>
            </div>
          </div>
        </article>
      </div>

      <EmptyState
        v-else
        :icon="KeyRound"
        title="Belum ada lisensi"
        description="Lisensi diterbitkan otomatis setelah bukti transaksi Anda disetujui admin."
      >
        <template #action>
          <RouterLink to="/dashboard" class="btn btn-primary">
            Unggah Bukti Transaksi
          </RouterLink>
        </template>
      </EmptyState>
    </section>
  </div>
</template>

<style scoped>
.lic-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-2);
}

/* Memakai @keyframes spin yang sudah ada di base.css. */
.spinning {
  animation: spin 0.9s linear infinite;
}

/* ---- Keadaan lisensi ---- */
.lic-state {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: var(--sp-2);
  margin-bottom: var(--sp-4);
}

.lic-stale {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  font-size: var(--fs-caption);
  color: var(--text-muted);
}

/* auto-fit + minmax: jumlah kolomnya ikut berapa fakta yang benar-benar ada,
   karena Lifetime dan Expired sengaja menampilkan lebih sedikit. Kisi tetap
   akan menyisakan kolom kosong. */
.lic-facts {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(150px, 100%), 1fr));
  gap: var(--sp-3) var(--sp-5);
  margin-bottom: var(--sp-4);
  padding: var(--sp-4);
  background: var(--bg-subtle);
  border: 1px solid var(--border);
  border-radius: var(--r-sm);
}

.fact dt {
  font-size: var(--fs-caption);
  color: var(--text-muted);
  margin-bottom: 2px;
}
.fact dd {
  font-size: var(--fs-body);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
}

.lic-unknown {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  margin-bottom: var(--sp-4);
  padding: var(--sp-3) var(--sp-4);
  font-size: var(--fs-caption);
  color: var(--text-muted);
  background: var(--bg-subtle);
  border: 1px solid var(--border);
  border-radius: var(--r-sm);
}

/* ---- Rilis ---- */
.release {
  display: flex;
  flex-direction: column;
}

.release-main {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-6);
  padding: var(--sp-6) var(--sp-6) var(--sp-7);
}

.release-ver {
  font-family: var(--font-mono);
}

/* ---- Riwayat versi ---- */
.history {
  padding: var(--sp-4) var(--sp-6) var(--sp-5);
  border-top: 1px solid var(--border);
  background: var(--bg-subtle);
}

.history-title {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  margin-bottom: var(--sp-3);
  font-size: var(--fs-caption);
  font-weight: var(--fw-medium);
  color: var(--text-muted);
}

.history-list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}

.history-row {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-2) var(--sp-3);
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r-sm);
}

.history-ver {
  font-family: var(--font-mono);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
}

/* Mendorong tombol ke kanan tanpa memaksa lebar tetap pada tanggalnya. */
.history-date {
  flex: 1;
  min-width: 0;
}

.release-icon {
  width: 56px;
  height: 56px;
  border-radius: var(--r);
  background: var(--tone-bg);
  flex-shrink: 0;
}

/* Jarak dibedakan menurut kedekatan makna, bukan diseragamkan: label dan
   judul adalah satu kesatuan sehingga rapat, sedangkan catatan rilis blok
   tersendiri sehingga diberi ruang lebih. Jarak yang sama rata justru
   membuat semuanya terbaca menempel. */
.release-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--sp-1);
}

/* Nama, nomor versi, dan badge diberi jarak sendiri; sebelumnya ketiganya
   hanya dipisah spasi teks sehingga terbaca menempel. */
.release-text h2 {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--sp-2);
  font-size: var(--fs-h2);
  line-height: var(--lh-snug);
}

/* pre-line: baris baru pada changelog dipertahankan.
   Tanpa ini HTML meruntuhkan newline jadi spasi, sehingga catatan rilis yang
   ditulis beberapa baris tercetak menyamping sebagai satu kalimat panjang. */
/* Daftar berbullet, bukan paragraf: catatan rilis memang berisi beberapa
   butir perubahan, dan menampilkannya sebagai satu blok teks membuat batas
   antar butir hilang. white-space tidak lagi diperlukan karena tiap baris
   sudah menjadi elemennya sendiri. */
.release-log {
  margin-top: var(--sp-3);
  display: flex;
  flex-direction: column;
  gap: var(--sp-1);
  font-size: var(--fs-label);
  color: var(--text-body);
  line-height: var(--lh-normal);
}
.release-log li {
  position: relative;
  padding-left: var(--sp-4);
}
.release-log li::before {
  content: '';
  position: absolute;
  left: 4px;
  top: 0.62em;
  width: 4px;
  height: 4px;
  border-radius: var(--r-pill);
  background: var(--text-muted);
}

/* Tautan teks, bukan tombol: aksi utama kartu ini tetap Unduh Plugin, dan
   dua tombol setara akan membuat keduanya bersaing. */
.link-more {
  align-self: flex-start;
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  margin-top: var(--sp-2);
  font-size: var(--fs-caption);
  font-weight: var(--fw-medium);
  color: var(--brand);
}
.link-more:hover {
  text-decoration: underline;
}

/* ---- Popup catatan rilis ---- */
.cl-date {
  margin-bottom: var(--sp-4);
}
.cl-list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  font-size: var(--fs-label);
  color: var(--text-body);
  line-height: var(--lh-normal);
}
.cl-list li {
  position: relative;
  padding-left: var(--sp-5);
}
.cl-list li::before {
  content: '';
  position: absolute;
  left: 6px;
  top: 0.6em;
  width: 5px;
  height: 5px;
  border-radius: var(--r-pill);
  background: var(--brand);
}

/* Tanggal rilis adalah keterangan, bukan bagian dari catatan rilis. */
.release-text > .text-caption {
  margin-top: var(--sp-2);
}

.release-action {
  flex-shrink: 0;
}

/* Di layar sempit tombol turun ke bawah teks. Tanpa ini barisnya nowrap dan
   tombolnya terdorong keluar tepi kartu — terukur 80px melewati kartu pada
   lebar sempit, meski halamannya sendiri tidak ikut menggeser. */
@media (max-width: 640px) {
  .release-main {
    flex-wrap: wrap;
  }
  .release-text {
    flex-basis: 100%;
  }
  .release-action {
    width: 100%;
  }
  .release-action .btn {
    width: 100%;
  }
  .history-row {
    flex-wrap: wrap;
  }
}

.locked {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-3) var(--sp-4);
  background: var(--warn-soft);
  border-radius: var(--r);
  font-size: var(--fs-caption);
  color: var(--text-body);
}

/* ---- Lisensi ---- */
.lic-list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-4);
  padding: var(--sp-5) var(--sp-6);
}

.lic {
  padding: var(--sp-5);
  border: 1px solid var(--border);
  border-radius: var(--r);
  background: var(--bg-subtle);
}

.lic-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  flex-wrap: wrap;
  margin-bottom: var(--sp-4);
}
/* Dulu selektornya `.lic-head span span`, yang ikut mengenai .icon-chip —
   chip itu juga sebuah <span> di dalam <span class="row">. Specificity-nya
   (0,1,2) mengalahkan .icon-chip (0,1,0), jadi `display: grid; place-items:
   center` milik chip tertimpa flex-column tanpa perataan dan ikon kuncinya
   naik ke sudut. Sekarang hanya pembungkus teks yang disasar. */
.lic-title {
  display: flex;
  flex-direction: column;
  line-height: 1.3;
}

.lic-product {
  font-size: var(--fs-body);
  font-weight: var(--fw-semibold);
  color: var(--text-heading);
}

.lic-keys {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(260px, 100%), 1fr));
  gap: var(--sp-4);
}

/* Grid item bawaannya min-width: auto, sehingga minimumnya mengikuti
   min-content isinya — license key panjang membuat track melebar melewati
   wadahnya. min-width: 0 mengizinkan item menyusut. */
.lic-keys > .field {
  min-width: 0;
}

.key-row {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  min-width: 0;
}
/* Dipotong dengan elipsis, bukan discroll. Selain lebih rapi, ini mencegah
   license key panjang mendorong lebar halaman di layar kecil. Nilai penuh
   tetap ikut saat disalin lewat tombol di sebelahnya. */
.key {
  flex: 1;
  min-width: 0;
  height: var(--h-control);
  padding: 0 var(--sp-3);
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r);
  font-family: var(--font-mono);
  font-size: var(--fs-caption);
  line-height: var(--h-control);
  color: var(--text-heading);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

@media (max-width: 780px) {
  .release {
    flex-direction: column;
    align-items: flex-start;
    gap: var(--sp-4);
  }
  .release-action,
  .release-action .btn {
    width: 100%;
  }
}

@media (max-width: 640px) {
  .lic-list {
    padding: var(--sp-4);
  }
  .lic {
    padding: var(--sp-4);
  }
  .lic-keys {
    grid-template-columns: 1fr;
  }
}
</style>
