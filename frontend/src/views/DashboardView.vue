<script setup>
/**
 * Dashboard user.
 *
 * SEMUA data di halaman ini berasal dari endpoint yang sudah ada:
 *   GET /user/dashboard  → transaksi, lisensi, template, flag izin, plugin
 *   GET /notifications   → daftar aktivitas + jumlah belum dibaca
 *
 * Tidak ada angka, daftar, atau kartu yang diisi data contoh. Bagian yang
 * backend-nya belum menyediakan data memakai empty state apa adanya.
 *
 * Catatan: seksi "Anggota" tidak ada di sini. Satu-satunya sumber daftar user
 * adalah GET /admin/users yang dilindungi requireAdmin(), jadi user biasa tidak
 * punya sumber data nyata untuk itu. Seksi tersebut hidup di dashboard admin.
 */
import { ref, computed, onMounted } from 'vue'
import {
  Upload,
  Download,
  FileText,
  KeyRound,
  Package,
  LayoutTemplate,
  Copy,
  Check,
  Clock,
  CircleCheck,
  CircleAlert,
  Receipt,
  ArrowRight,
  Search,
  LayoutGrid,
  List,
  ExternalLink,
  Bell,
} from 'lucide-vue-next'
import StatCard from '@/components/ui/StatCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import { api, errorMessage } from '@/lib/api'
import { formatDate, formatRelative } from '@/lib/format'
import { authState } from '@/stores/auth'
import { notifState, fetchNotifications } from '@/stores/notifications'
import { toast } from '@/stores/toast'

const loading = ref(true)
const uploading = ref(false)
const data = ref(null)
const fileInput = ref(null)
const selectedFile = ref(null)
const copied = ref('')

// Filter template — bekerja di sisi klien atas data yang sudah dimuat.
const search = ref('')
const kind = ref('all')
const view = ref('grid')

const user = computed(() => authState.user)
const transaction = computed(() => data.value?.transaction || null)
const license = computed(() => data.value?.license || null)
const templates = computed(() => data.value?.templates || [])
const plugin = computed(() => data.value?.latest_plugin || null)
const canUpload = computed(() => !!data.value?.can_upload)
const canDownload = computed(() => !!data.value?.can_download)

/**
 * Tahap perjalanan lisensi, diturunkan dari status transaksi yang dikirim
 * backend. Dipakai untuk progres dan ringkasan status.
 */
const journey = computed(() => {
  if (license.value) {
    return { step: 3, percent: 100, tone: 'tone-ok', text: 'Lisensi aktif' }
  }
  if (!transaction.value) {
    return { step: 0, percent: 0, tone: 'tone-neutral', text: 'Belum ada pengajuan' }
  }
  switch (transaction.value.status) {
    case 'approved':
      return { step: 3, percent: 100, tone: 'tone-ok', text: 'Disetujui' }
    case 'pending_license':
      return { step: 2, percent: 75, tone: 'tone-info', text: 'Lisensi sedang diterbitkan' }
    case 'waiting_verification':
      return { step: 1, percent: 45, tone: 'tone-warn', text: 'Menunggu verifikasi' }
    case 'rejected':
      return { step: 1, percent: 25, tone: 'tone-err', text: 'Perlu unggah ulang' }
    default:
      return { step: 0, percent: 0, tone: 'tone-neutral', text: transaction.value.status }
  }
})

const guidance = computed(() => {
  if (!transaction.value) {
    return 'Unggah bukti pembayaran untuk memulai proses verifikasi lisensi.'
  }
  switch (transaction.value.status) {
    case 'approved':
      return 'Lisensi Anda aktif. Plugin versi terbaru siap diunduh.'
    case 'rejected':
      return transaction.value.admin_notes || 'Silakan unggah ulang bukti transaksi Anda.'
    case 'pending_license':
      return 'Pembayaran sudah disetujui. Lisensi akan terbit begitu server tersedia.'
    default:
      return 'Bukti transaksi sedang diperiksa admin.'
  }
})

const filteredTemplates = computed(() => {
  let list = templates.value
  if (kind.value === 'standard') list = list.filter((t) => Number(t.is_standard))
  if (kind.value === 'premium') list = list.filter((t) => !Number(t.is_standard))
  const q = search.value.trim().toLowerCase()
  if (q) {
    list = list.filter(
      (t) =>
        (t.name || '').toLowerCase().includes(q) ||
        (t.description || '').toLowerCase().includes(q)
    )
  }
  return list
})

const recentActivity = computed(() => notifState.items.slice(0, 6))

async function load() {
  loading.value = true
  try {
    const { data: res } = await api.get('/user/dashboard')
    data.value = res
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat dashboard'))
  } finally {
    loading.value = false
  }
}

function pickFile() {
  fileInput.value?.click()
}

function onFileChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  // Batas dicerminkan dari backend (5MB, JPG/PNG/GIF/WEBP/PDF).
  if (file.size > 5 * 1024 * 1024) {
    toast.error('Ukuran file maksimal 5MB')
    e.target.value = ''
    return
  }
  selectedFile.value = file
}

async function submitUpload() {
  if (!selectedFile.value || uploading.value) return
  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('document', selectedFile.value)
    await api.post('/user/upload-document', fd)
    toast.success('Dokumen berhasil dikirim')
    selectedFile.value = null
    if (fileInput.value) fileInput.value.value = ''
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal mengupload dokumen'))
  } finally {
    uploading.value = false
  }
}

async function copyText(value, key) {
  try {
    await navigator.clipboard.writeText(value)
    copied.value = key
    setTimeout(() => (copied.value = ''), 1600)
  } catch {
    toast.error('Gagal menyalin')
  }
}

onMounted(() => {
  load()
  fetchNotifications()
})
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>Dashboard</h1>
        <p class="text-caption">Halo, {{ user?.name }} — ringkasan lisensi Anda.</p>
      </div>

      <a v-if="canDownload" class="btn btn-primary" href="/api/user/download-plugin">
        <Download :size="16" />
        Unduh Plugin
      </a>
      <button v-else-if="canUpload" class="btn btn-primary" type="button" @click="pickFile">
        <Upload :size="16" />
        Unggah Bukti Transaksi
      </button>
    </header>

    <!-- ---------- Ringkasan status ---------- -->
    <section class="journey card" :class="journey.tone">
      <div class="journey-top">
        <span class="badge" :class="journey.tone">
          <span
            class="status-dot"
            :class="{ 'is-live': journey.step > 0 && journey.step < 3 }"
            style="background: currentColor"
          />
          {{ journey.text }}
        </span>
        <span class="journey-pct">{{ journey.percent }}%</span>
      </div>

      <p class="journey-text">{{ guidance }}</p>

      <div
        class="journey-bar"
        role="progressbar"
        :aria-valuenow="journey.percent"
        aria-valuemin="0"
        aria-valuemax="100"
      >
        <span class="journey-fill" :style="{ width: journey.percent + '%' }" />
      </div>

      <ol class="journey-steps">
        <li :class="{ done: journey.step >= 1 }">Unggah bukti</li>
        <li :class="{ done: journey.step >= 2 }">Verifikasi admin</li>
        <li :class="{ done: journey.step >= 3 }">Lisensi terbit</li>
      </ol>
    </section>

    <!-- ---------- Statistik (semuanya data nyata) ---------- -->
    <section class="grid stats">
      <StatCard
        label="Status lisensi"
        :value="license ? 'Aktif' : 'Belum terbit'"
        :icon="KeyRound"
        :tone="license ? 'tone-ok' : 'tone-neutral'"
        :hint="license ? formatDate(license.assigned_at, false) : 'Menunggu persetujuan'"
        to="/plugins"
      />
      <StatCard
        label="Template tersedia"
        :value="templates.length"
        :icon="LayoutTemplate"
        hint="Siap dipakai"
        to="/templates"
      />
      <StatCard
        label="Versi plugin"
        :value="plugin ? `v${plugin.version}` : '—'"
        :icon="Package"
        :hint="plugin ? 'Rilis terbaru' : 'Belum ada rilis'"
        to="/plugins"
      />
      <StatCard
        label="Belum dibaca"
        :value="notifState.unread"
        :icon="Bell"
        :tone="notifState.unread > 0 ? 'tone-info' : 'tone-neutral'"
        :hint="`dari ${notifState.items.length} notifikasi`"
      />
    </section>

    <div class="split">
      <div class="stack">
        <!-- ---------- Bukti transaksi ---------- -->
        <section class="card">
          <header class="card-header">
            <h2>Bukti transaksi</h2>
            <StatusBadge
              v-if="transaction"
              :status="transaction.status"
              :label="transaction.status_label"
            />
          </header>

          <SkeletonRows v-if="loading" :rows="2" />

          <div v-else class="card-body">
            <template v-if="transaction">
              <dl class="detail">
                <div>
                  <dt>Nama dokumen</dt>
                  <dd>{{ transaction.document_name || '—' }}</dd>
                </div>
                <div>
                  <dt>Diajukan</dt>
                  <dd>{{ formatDate(transaction.created_at) }}</dd>
                </div>
                <div v-if="transaction.approved_at">
                  <dt>Disetujui</dt>
                  <dd>{{ formatDate(transaction.approved_at) }}</dd>
                </div>
                <div v-if="transaction.rejected_at">
                  <dt>Ditolak</dt>
                  <dd>{{ formatDate(transaction.rejected_at) }}</dd>
                </div>
              </dl>

              <p v-if="transaction.admin_notes" class="note">
                <CircleAlert :size="15" />
                <span><strong>Catatan admin:</strong> {{ transaction.admin_notes }}</span>
              </p>
            </template>

            <!-- Area unggah muncul saat backend mengizinkan, atau saat belum
                 ada transaksi sama sekali. -->
            <div v-if="canUpload || !transaction" class="upload" :class="{ first: !transaction }">
              <input
                ref="fileInput"
                class="sr-only"
                type="file"
                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
                @change="onFileChange"
              >

              <div v-if="selectedFile" class="picked">
                <FileText :size="16" />
                <span class="picked-name">{{ selectedFile.name }}</span>
                <button
                  class="btn btn-sm btn-ghost"
                  type="button"
                  :disabled="uploading"
                  @click="selectedFile = null"
                >
                  Ganti
                </button>
                <button
                  class="btn btn-sm btn-primary"
                  type="button"
                  :disabled="uploading"
                  @click="submitUpload"
                >
                  {{ uploading ? 'Mengirim…' : 'Kirim' }}
                </button>
              </div>

              <button v-else class="dropzone" type="button" @click="pickFile">
                <Upload :size="18" />
                <span>
                  <strong>
                    {{ transaction ? 'Unggah ulang bukti transaksi' : 'Unggah bukti transaksi' }}
                  </strong>
                  <small>JPG, PNG, atau PDF — maksimal 5MB</small>
                </span>
              </button>
            </div>
          </div>
        </section>

        <!-- ---------- Template ---------- -->
        <section class="card">
          <header class="card-header">
            <h2>Template undangan</h2>
            <RouterLink to="/templates" class="link-more">
              Lihat semua <ArrowRight :size="14" />
            </RouterLink>
          </header>

          <div class="toolbar control-row">
            <div class="input-group toolbar-search">
              <Search class="input-icon" :size="16" />
              <input
                v-model="search"
                class="input"
                type="search"
                placeholder="Cari template…"
                aria-label="Cari template"
              >
            </div>

            <select v-model="kind" class="select toolbar-select" aria-label="Tipe template">
              <option value="all">Semua tipe</option>
              <option value="standard">Standard</option>
              <option value="premium">Premium</option>
            </select>

            <div class="segmented">
              <button
                class="segmented-item"
                :class="{ active: view === 'grid' }"
                type="button"
                aria-label="Tampilan grid"
                @click="view = 'grid'"
              >
                <LayoutGrid :size="15" />
              </button>
              <button
                class="segmented-item"
                :class="{ active: view === 'list' }"
                type="button"
                aria-label="Tampilan daftar"
                @click="view = 'list'"
              >
                <List :size="15" />
              </button>
            </div>
          </div>

          <SkeletonRows v-if="loading" :rows="2" :height="96" />

          <div
            v-else-if="filteredTemplates.length"
            class="tpl-wrap"
            :class="view === 'grid' ? 'tpl-grid' : 'tpl-list'"
          >
            <article v-for="t in filteredTemplates" :key="t.id" class="tpl">
              <div class="tpl-thumb">
                <img
                  v-if="t.thumbnail_path"
                  :src="`/storage/templates/thumbnails/${t.thumbnail_path}`"
                  :alt="t.name"
                  loading="lazy"
                >
                <LayoutTemplate v-else :size="20" />
              </div>

              <div class="tpl-body">
                <div class="tpl-title">
                  <h3>{{ t.name }}</h3>
                  <span class="badge">
                    {{ Number(t.is_standard) ? 'Standard' : 'Premium' }}
                  </span>
                </div>
                <p v-if="t.description" class="tpl-desc">{{ t.description }}</p>
                <a
                  v-if="t.checkout_url"
                  class="tpl-cta"
                  :href="t.checkout_url"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Pesan <ExternalLink :size="12" />
                </a>
              </div>
            </article>
          </div>

          <EmptyState
            v-else
            :icon="LayoutTemplate"
            title="Template tidak ditemukan"
            :description="
              search || kind !== 'all'
                ? 'Coba ubah kata kunci atau filter tipe.'
                : 'Belum ada template yang tersedia.'
            "
          />
        </section>
      </div>

      <!-- ---------- Kolom kanan ---------- -->
      <aside class="stack">
        <section class="card">
          <header class="card-header">
            <h2>Lisensi Anda</h2>
          </header>

          <div v-if="license" class="card-body">
            <div class="field">
              <span class="text-overline">License key</span>
              <div class="key-row">
                <code class="key">{{ license.license_key }}</code>
                <button
                  class="btn btn-sm btn-secondary btn-icon"
                  type="button"
                  aria-label="Salin license key"
                  @click="copyText(license.license_key, 'lic')"
                >
                  <component :is="copied === 'lic' ? Check : Copy" :size="14" />
                </button>
              </div>
            </div>

            <div v-if="license.api_key" class="field key-field">
              <span class="text-overline">API key</span>
              <div class="key-row">
                <code class="key">{{ license.api_key }}</code>
                <button
                  class="btn btn-sm btn-secondary btn-icon"
                  type="button"
                  aria-label="Salin API key"
                  @click="copyText(license.api_key, 'api')"
                >
                  <component :is="copied === 'api' ? Check : Copy" :size="14" />
                </button>
              </div>
            </div>

            <a class="btn btn-secondary btn-block key-field" href="/api/user/download-invoice">
              <Receipt :size="15" />
              Unduh invoice
            </a>
          </div>

          <EmptyState
            v-else
            :icon="KeyRound"
            title="Lisensi belum terbit"
            description="Lisensi muncul otomatis setelah bukti transaksi disetujui."
            compact
          />
        </section>

        <section class="card">
          <header class="card-header">
            <h2>Plugin terbaru</h2>
          </header>

          <div v-if="plugin" class="card-body">
            <p class="plugin-version">v{{ plugin.version }}</p>
            <p v-if="plugin.changelog" class="text-caption plugin-log">
              {{ plugin.changelog }}
            </p>

            <a
              v-if="canDownload"
              class="btn btn-primary btn-block plugin-cta"
              href="/api/user/download-plugin"
            >
              <Download :size="15" />
              Unduh plugin
            </a>
            <p v-else class="locked">
              <CircleAlert :size="14" />
              Lisensi aktif diperlukan untuk mengunduh.
            </p>
          </div>

          <EmptyState
            v-else
            :icon="Package"
            title="Belum ada rilis"
            description="Versi plugin akan tampil di sini."
            compact
          />
        </section>

        <section class="card">
          <header class="card-header">
            <h2>Aktivitas</h2>
          </header>

          <ul v-if="recentActivity.length" class="feed">
            <li v-for="n in recentActivity" :key="n.id" class="feed-item">
              <span class="feed-dot" :class="{ unread: Number(n.is_read) === 0 }" />
              <span class="feed-text">
                <span class="feed-title">{{ n.title }}</span>
                <span class="feed-msg">{{ n.message }}</span>
                <span class="feed-time">
                  <Clock :size="11" />
                  {{ formatRelative(n.created_at) }}
                </span>
              </span>
            </li>
          </ul>

          <EmptyState
            v-else
            :icon="CircleCheck"
            title="Belum ada aktivitas"
            description="Pemberitahuan akan muncul di sini."
            compact
          />
        </section>
      </aside>
    </div>
  </div>
</template>

<style scoped>
/* ============ Ringkasan status ============ */
.journey {
  padding: var(--sp-5);
}

.journey-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  margin-bottom: var(--sp-3);
}

.journey-pct {
  font-size: var(--fs-label);
  font-weight: var(--fw-semibold);
  color: var(--text-heading);
}

/* Denyut hanya saat proses memang sedang berjalan */
.status-dot.is-live {
  animation: pulse-soft 2s var(--ease) infinite;
}

.journey-text {
  font-size: var(--fs-body);
  color: var(--text-body);
  margin-bottom: var(--sp-4);
  max-width: 68ch;
}

.journey-bar {
  height: 6px;
  background: var(--n-100);
  border-radius: var(--r-pill);
  overflow: hidden;
}

.journey-fill {
  display: block;
  height: 100%;
  border-radius: var(--r-pill);
  background: var(--tone-fg, var(--brand));
  transition: width var(--dur) var(--ease);
}

.journey-steps {
  display: flex;
  gap: var(--sp-5);
  margin-top: var(--sp-3);
  flex-wrap: wrap;
}
.journey-steps li {
  position: relative;
  padding-left: var(--sp-4);
  font-size: var(--fs-caption);
  color: var(--text-faint);
}
.journey-steps li::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 7px;
  height: 7px;
  border-radius: var(--r-pill);
  border: 1px solid var(--n-300);
}
.journey-steps li.done {
  color: var(--text-body);
}
.journey-steps li.done::before {
  background: var(--tone-fg, var(--brand));
  border-color: var(--tone-fg, var(--brand));
}

/* ============ Statistik ============ */
.stats {
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
}

/* ============ Split ============ */
.split {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--sp-4);
  align-items: start;
}

@media (min-width: 1200px) {
  .split {
    grid-template-columns: minmax(0, 1fr) var(--rail-w);
  }
}

.split > * {
  min-width: 0;
}

.link-more {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  font-size: var(--fs-caption);
  font-weight: var(--fw-medium);
  color: var(--brand);
}
.link-more:hover {
  color: var(--brand-hover);
}

/* ============ Detail transaksi ============ */
.detail {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: var(--sp-4);
}
.detail dt {
  font-size: var(--fs-caption);
  color: var(--text-muted);
  margin-bottom: 2px;
}
.detail dd {
  font-size: var(--fs-body);
  color: var(--text-heading);
  word-break: break-word;
}

.note {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-2);
  margin-top: var(--sp-4);
  padding: var(--sp-3);
  background: var(--warn-soft);
  border: 1px solid var(--warn-border);
  border-radius: var(--r);
  font-size: var(--fs-label);
  color: var(--text-body);
}
.note svg {
  color: var(--warn);
  flex-shrink: 0;
  margin-top: 2px;
}
.note strong {
  color: var(--text-heading);
  font-weight: var(--fw-medium);
}

/* ============ Unggah ============ */
.upload {
  margin-top: var(--sp-4);
}
.upload.first {
  margin-top: 0;
}

.dropzone {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  width: 100%;
  padding: var(--sp-4);
  border: 1px dashed var(--border-strong);
  border-radius: var(--r);
  background: var(--bg-subtle);
  color: var(--text-muted);
  text-align: left;
  transition: border-color var(--dur) var(--ease), background var(--dur) var(--ease);
}
.dropzone:hover {
  border-color: var(--brand);
  background: var(--bg-surface);
}
.dropzone span {
  display: flex;
  flex-direction: column;
}
.dropzone strong {
  font-size: var(--fs-body);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
}
.dropzone small {
  font-size: var(--fs-caption);
}

.picked {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  flex-wrap: wrap;
  padding: var(--sp-3);
  border: 1px solid var(--border);
  border-radius: var(--r);
  color: var(--text-muted);
}
.picked-name {
  flex: 1;
  min-width: 0;
  font-size: var(--fs-label);
  color: var(--text-heading);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ============ Toolbar template ============ */
.toolbar {
  padding: var(--sp-3) var(--sp-5);
  border-bottom: 1px solid var(--border);
}

.toolbar-search {
  flex: 1;
  min-width: 160px;
}

.toolbar-select {
  width: auto;
  min-width: 140px;
}

/* ============ Template ============ */
.tpl-wrap {
  padding: var(--sp-5);
}

.tpl-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: var(--sp-3);
}

.tpl-list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}

.tpl {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--border);
  border-radius: var(--r);
  overflow: hidden;
  transition: border-color var(--dur) var(--ease);
}
.tpl:hover {
  border-color: var(--n-400);
}

.tpl-thumb {
  display: grid;
  place-items: center;
  aspect-ratio: 16 / 9;
  background: var(--n-100);
  color: var(--n-400);
  overflow: hidden;
}
.tpl-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.tpl-body {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
  padding: var(--sp-3);
  flex: 1;
}

.tpl-title {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-2);
}
.tpl-title h3 {
  font-size: var(--fs-body);
  font-weight: var(--fw-medium);
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tpl-desc {
  font-size: var(--fs-caption);
  color: var(--text-muted);
  line-height: var(--lh-snug);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.tpl-cta {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  margin-top: auto;
  font-size: var(--fs-caption);
  font-weight: var(--fw-medium);
  color: var(--brand);
}
.tpl-cta:hover {
  color: var(--brand-hover);
}

/* Mode daftar */
.tpl-list .tpl {
  flex-direction: row;
}
.tpl-list .tpl-thumb {
  width: 96px;
  aspect-ratio: auto;
  flex-shrink: 0;
}
.tpl-list .tpl-desc {
  -webkit-line-clamp: 1;
  line-clamp: 1;
}

/* ============ Lisensi & plugin ============ */
.key-field {
  margin-top: var(--sp-4);
}

.key-row {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}
.key {
  flex: 1;
  min-width: 0;
  height: var(--h-control-sm);
  display: flex;
  align-items: center;
  padding: 0 var(--sp-2);
  background: var(--bg-subtle);
  border: 1px solid var(--border);
  border-radius: var(--r);
  font-family: var(--font-mono);
  font-size: var(--fs-caption);
  color: var(--text-heading);
  overflow-x: auto;
  white-space: nowrap;
}

.plugin-version {
  font-size: var(--fs-h1);
  font-weight: var(--fw-semibold);
  color: var(--text-heading);
}
.plugin-log {
  margin-top: var(--sp-2);
  line-height: var(--lh-snug);
}
.plugin-cta {
  margin-top: var(--sp-4);
}

.locked {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  margin-top: var(--sp-4);
  padding: var(--sp-3);
  background: var(--bg-subtle);
  border-radius: var(--r);
  font-size: var(--fs-caption);
  color: var(--text-muted);
}

/* ============ Aktivitas ============ */
.feed {
  display: flex;
  flex-direction: column;
}

.feed-item {
  display: flex;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-5);
  border-bottom: 1px solid var(--border);
}
.feed-item:last-child {
  border-bottom: none;
}

.feed-dot {
  width: 6px;
  height: 6px;
  margin-top: 7px;
  flex-shrink: 0;
  border-radius: var(--r-pill);
  background: var(--n-300);
}
.feed-dot.unread {
  background: var(--brand);
}

.feed-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.feed-title {
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
}

.feed-msg {
  font-size: var(--fs-caption);
  color: var(--text-muted);
  line-height: var(--lh-snug);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.feed-time {
  display: flex;
  align-items: center;
  gap: 3px;
  margin-top: 2px;
  font-size: var(--fs-micro);
  color: var(--text-faint);
}

/* ============ Responsif ============ */
@media (max-width: 640px) {
  .toolbar,
  .tpl-wrap,
  .card-body {
    padding: var(--sp-4);
  }
  .detail {
    grid-template-columns: 1fr;
  }
  .toolbar-search,
  .toolbar-select {
    flex: 1 1 100%;
    width: 100%;
  }
  .segmented {
    width: 100%;
  }
  .segmented-item {
    flex: 1;
  }
  .page-head .btn {
    width: 100%;
  }
  .tpl-list .tpl {
    flex-direction: column;
  }
  .tpl-list .tpl-thumb {
    width: 100%;
    aspect-ratio: 16 / 9;
  }
}
</style>
