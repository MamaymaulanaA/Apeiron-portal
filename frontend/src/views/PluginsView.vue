<script setup>
/**
 * Plugin & Lisensi — GET /user/licenses.
 * Unduhan memakai tautan langsung ke endpoint agar cookie sesi ikut terkirim,
 * sama seperti perilaku sebelumnya.
 */
import { ref, computed, onMounted } from 'vue'
import {
  Package,
  KeyRound,
  Download,
  Copy,
  Check,
  Circle,
  CircleAlert,
  CircleCheck,
  CircleHelp,
  CircleX,
  Receipt,
} from 'lucide-vue-next'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import { api, errorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import {
  licenseStatusMeta,
  licenseSummary,
  licenseDuration,
  isLicenseActive,
} from '@/lib/license'
import { toast } from '@/stores/toast'

const loading = ref(true)
const licenses = ref([])
const plugin = ref(null)
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
    const { data } = await api.get('/user/licenses')
    licenses.value = data.licenses || []
    plugin.value = data.latest_plugin || null
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat data lisensi'))
  } finally {
    loading.value = false
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

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>Plugin &amp; Lisensi</h1>
        <p class="text-muted">Kelola lisensi Anda dan unduh plugin versi terbaru.</p>
      </div>
    </header>

    <!-- ---------- Kartu rilis plugin ---------- -->
    <section v-if="plugin" class="release card">
      <span class="icon-chip release-icon tone-neutral">
        <Package :size="24" />
      </span>

      <div class="release-text">
        <p class="text-overline">Versi Terbaru</p>
        <h2>Apeiron Kit v{{ plugin.version }}</h2>
        <p v-if="plugin.changelog" class="release-log">{{ plugin.changelog }}</p>
      </div>

      <div class="release-action">
        <a v-if="hasLicense" class="btn btn-primary" href="/api/user/download-plugin">
          <Download :size="17" />
          Unduh Plugin
        </a>
        <p v-else class="locked">
          <CircleAlert :size="15" />
          Lisensi aktif diperlukan.
        </p>
      </div>
    </section>

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
            <a class="btn btn-sm btn-secondary" href="/api/user/download-invoice">
              <Receipt :size="14" />
              Invoice
            </a>
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
  align-items: center;
  gap: var(--sp-5);
  padding: var(--sp-6);
}

.release-icon {
  width: 56px;
  height: 56px;
  border-radius: var(--r);
  background: var(--tone-bg);
  flex-shrink: 0;
}

.release-text {
  flex: 1;
  min-width: 0;
}
.release-text h2 {
  margin-top: 2px;
  font-size: var(--fs-h2);
}
.release-log {
  margin-top: var(--sp-2);
  font-size: var(--fs-label);
  color: var(--text-muted);
  line-height: var(--lh-normal);
}

.release-action {
  flex-shrink: 0;
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
