<script setup>
/**
 * Dashboard user.
 *
 * Sumber data tunggal: GET /user/dashboard.
 * Aksi yang tersedia persis seperti sebelumnya — unggah dokumen, unduh plugin,
 * unduh invoice — dan tetap dibatasi flag can_upload / can_download dari backend.
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
  CloudUpload,
} from 'lucide-vue-next'
import StatCard from '@/components/ui/StatCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import { api, errorMessage } from '@/lib/api'
import { formatDate, initials } from '@/lib/format'
import { authState } from '@/stores/auth'
import { toast } from '@/stores/toast'

const loading = ref(true)
const uploading = ref(false)
const data = ref(null)
const fileInput = ref(null)
const selectedFile = ref(null)
const copied = ref('')

const user = computed(() => authState.user)
const transaction = computed(() => data.value?.transaction || null)
const license = computed(() => data.value?.license || null)
const templates = computed(() => data.value?.templates || [])
const plugin = computed(() => data.value?.latest_plugin || null)
const canUpload = computed(() => !!data.value?.can_upload)
const canDownload = computed(() => !!data.value?.can_download)

/** Ringkasan status untuk hero — teks berbeda per keadaan. */
const heroState = computed(() => {
  if (!transaction.value) {
    return {
      title: 'Mulai dengan mengunggah bukti transaksi',
      text: 'Unggah bukti pembayaran Anda untuk memulai proses verifikasi lisensi.',
      icon: CloudUpload,
    }
  }
  switch (transaction.value.status) {
    case 'approved':
      return {
        title: 'Lisensi Anda sudah aktif',
        text: 'Anda dapat mengunduh plugin versi terbaru kapan saja.',
        icon: CircleCheck,
      }
    case 'rejected':
      return {
        title: 'Bukti transaksi ditolak',
        text: transaction.value.admin_notes || 'Silakan unggah ulang bukti transaksi Anda.',
        icon: CircleAlert,
      }
    case 'pending_license':
      return {
        title: 'Lisensi sedang diproses',
        text: 'Pembayaran disetujui. Lisensi akan segera diterbitkan.',
        icon: Clock,
      }
    default:
      return {
        title: 'Menunggu verifikasi admin',
        text: 'Bukti transaksi Anda sedang diperiksa. Kami akan memberi tahu hasilnya.',
        icon: Clock,
      }
  }
})

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
    setTimeout(() => (copied.value = ''), 1800)
  } catch {
    toast.error('Gagal menyalin')
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <!-- ---------- Hero ---------- -->
    <section class="hero">
      <div class="hero-text">
        <p class="hero-eyebrow">Dashboard</p>
        <h1 class="hero-title">
          Halo, <span>{{ user?.name }}</span>
        </h1>
        <p class="hero-sub">{{ heroState.text }}</p>

        <div class="hero-actions">
          <a
            v-if="canDownload"
            class="btn btn-primary"
            href="/api/user/download-plugin"
          >
            <Download :size="17" />
            Unduh Plugin
          </a>
          <button v-else-if="canUpload" class="btn btn-primary" type="button" @click="pickFile">
            <Upload :size="17" />
            Unggah Bukti Transaksi
          </button>

          <RouterLink to="/plugins" class="btn btn-secondary">
            Lihat Lisensi
            <ArrowRight :size="16" />
          </RouterLink>
        </div>
      </div>

      <div class="hero-side">
        <span class="hero-avatar">{{ initials(user?.name) }}</span>
        <div class="hero-status">
          <component :is="heroState.icon" :size="18" />
          <span>{{ heroState.title }}</span>
        </div>
      </div>
    </section>

    <!-- ---------- Statistik ---------- -->
    <section class="grid stats">
      <StatCard
        label="Status Lisensi"
        :value="license ? 'Aktif' : 'Belum aktif'"
        :icon="KeyRound"
        :tone="license ? 'tone-green' : 'tone-amber'"
        :hint="license ? formatDate(license.assigned_at, false) : 'Menunggu persetujuan'"
      />
      <StatCard
        label="Status Transaksi"
        :value="transaction ? transaction.status_label : 'Belum ada'"
        :icon="Receipt"
        tone="tone-blue"
        :hint="transaction ? formatDate(transaction.created_at, false) : 'Belum ada pengajuan'"
        to="/transactions"
      />
      <StatCard
        label="Versi Plugin"
        :value="plugin ? `v${plugin.version}` : '-'"
        :icon="Package"
        tone="tone-purple"
        :hint="plugin ? 'Versi terbaru tersedia' : 'Belum ada rilis'"
        to="/plugins"
      />
      <StatCard
        label="Template Tersedia"
        :value="templates.length"
        :icon="LayoutTemplate"
        tone="tone-orange"
        hint="Siap dipakai"
        to="/templates"
      />
    </section>

    <!-- ---------- Kolom utama + rail ---------- -->
    <div class="split">
      <div class="stack">
        <!-- Status transaksi / unggah -->
        <section class="card">
          <header class="card-header">
            <h3>
              <span class="icon-chip icon-chip-sm tone-blue"><FileText :size="16" /></span>
              Bukti Transaksi
            </h3>
            <StatusBadge
              v-if="transaction"
              :status="transaction.status"
              :label="transaction.status_label"
            />
          </header>

          <SkeletonRows v-if="loading" :rows="3" />

          <div v-else class="card-body">
            <!-- Sudah ada transaksi -->
            <dl v-if="transaction" class="detail">
              <div>
                <dt>Nama dokumen</dt>
                <dd>{{ transaction.document_name || '-' }}</dd>
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

            <p v-if="transaction?.admin_notes" class="note">
              <strong>Catatan admin:</strong> {{ transaction.admin_notes }}
            </p>

            <!-- Area unggah, hanya saat backend mengizinkan -->
            <div v-if="canUpload" class="upload">
              <input
                ref="fileInput"
                class="sr-only"
                type="file"
                accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
                @change="onFileChange"
              >

              <button
                v-if="!selectedFile"
                class="dropzone"
                type="button"
                @click="pickFile"
              >
                <span class="icon-chip tone-blue"><CloudUpload :size="22" /></span>
                <span class="dropzone-title">Pilih bukti transaksi</span>
                <span class="text-caption">JPG, PNG, atau PDF — maksimal 5MB</span>
              </button>

              <div v-else class="picked">
                <span class="icon-chip icon-chip-sm tone-green"><FileText :size="16" /></span>
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
            </div>

            <EmptyState
              v-else-if="!transaction"
              :icon="FileText"
              title="Belum ada bukti transaksi"
              description="Unggah bukti pembayaran untuk memulai proses verifikasi."
            />
          </div>
        </section>

        <!-- Template -->
        <section class="card">
          <header class="card-header">
            <h3>
              <span class="icon-chip icon-chip-sm tone-orange">
                <LayoutTemplate :size="16" />
              </span>
              Template Undangan
            </h3>
            <RouterLink to="/templates" class="link-more">
              Lihat semua <ArrowRight :size="14" />
            </RouterLink>
          </header>

          <SkeletonRows v-if="loading" :rows="2" :height="72" />

          <div v-else-if="templates.length" class="template-grid">
            <article v-for="t in templates.slice(0, 4)" :key="t.id" class="template">
              <span class="template-thumb">
                <img
                  v-if="t.thumbnail_path"
                  :src="`/storage/templates/thumbnails/${t.thumbnail_path}`"
                  :alt="t.name"
                  loading="lazy"
                >
                <LayoutTemplate v-else :size="20" />
              </span>
              <span class="template-info">
                <span class="template-name">{{ t.name }}</span>
                <span class="badge" :class="Number(t.is_standard) ? 'tone-blue' : 'tone-purple'">
                  {{ Number(t.is_standard) ? 'Standard' : 'Premium' }}
                </span>
              </span>
            </article>
          </div>

          <EmptyState
            v-else
            :icon="LayoutTemplate"
            tone="tone-orange"
            title="Belum ada template"
            description="Template akan muncul di sini setelah ditambahkan admin."
          />
        </section>
      </div>

      <!-- ---------- Rail kanan ---------- -->
      <aside class="stack rail">
        <!-- Lisensi -->
        <section class="card">
          <header class="card-header">
            <h3>
              <span class="icon-chip icon-chip-sm tone-green"><KeyRound :size="16" /></span>
              Lisensi Anda
            </h3>
          </header>

          <div class="card-body">
            <template v-if="license">
              <div class="field">
                <span class="text-overline">License Key</span>
                <div class="key-row">
                  <code class="key">{{ license.license_key }}</code>
                  <button
                    class="btn btn-sm btn-secondary btn-icon"
                    type="button"
                    aria-label="Salin license key"
                    @click="copyText(license.license_key, 'lic')"
                  >
                    <component :is="copied === 'lic' ? Check : Copy" :size="15" />
                  </button>
                </div>
              </div>

              <div v-if="license.api_key" class="field" style="margin-top: var(--sp-4)">
                <span class="text-overline">API Key</span>
                <div class="key-row">
                  <code class="key">{{ license.api_key }}</code>
                  <button
                    class="btn btn-sm btn-secondary btn-icon"
                    type="button"
                    aria-label="Salin API key"
                    @click="copyText(license.api_key, 'api')"
                  >
                    <component :is="copied === 'api' ? Check : Copy" :size="15" />
                  </button>
                </div>
              </div>

              <a class="btn btn-secondary btn-block" href="/api/user/download-invoice"
                 style="margin-top: var(--sp-5)">
                <Receipt :size="16" />
                Unduh Invoice
              </a>
            </template>

            <EmptyState
              v-else
              :icon="KeyRound"
              tone="tone-green"
              title="Lisensi belum terbit"
              description="Lisensi muncul otomatis setelah transaksi disetujui."
            />
          </div>
        </section>

        <!-- Plugin terbaru -->
        <section class="card">
          <header class="card-header">
            <h3>
              <span class="icon-chip icon-chip-sm tone-purple"><Package :size="16" /></span>
              Plugin Terbaru
            </h3>
          </header>

          <div class="card-body">
            <template v-if="plugin">
              <p class="plugin-version">v{{ plugin.version }}</p>
              <p v-if="plugin.changelog" class="text-caption plugin-log">
                {{ plugin.changelog }}
              </p>

              <a
                v-if="canDownload"
                class="btn btn-primary btn-block"
                href="/api/user/download-plugin"
                style="margin-top: var(--sp-4)"
              >
                <Download :size="16" />
                Unduh Plugin
              </a>
              <p v-else class="locked">
                <CircleAlert :size="15" />
                Lisensi aktif diperlukan untuk mengunduh.
              </p>
            </template>

            <EmptyState
              v-else
              :icon="Package"
              tone="tone-purple"
              title="Belum ada rilis"
              description="Versi plugin akan tampil di sini."
            />
          </div>
        </section>
      </aside>
    </div>
  </div>
</template>

<style scoped>
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

/* ---------- Hero ---------- */
.hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-8);
  padding: var(--sp-8);
  border-radius: var(--r-xl);
  background: linear-gradient(135deg, #16234b 0%, #1e3068 55%, #2a4590 100%);
  color: #fff;
  position: relative;
  overflow: hidden;
}

.hero::after {
  content: '';
  position: absolute;
  width: 380px;
  height: 380px;
  right: -110px;
  top: -140px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(91, 141, 255, 0.5), transparent 68%);
}

.hero-text {
  position: relative;
  z-index: 1;
  min-width: 0;
}

.hero-eyebrow {
  font-size: var(--fs-micro);
  font-weight: var(--fw-semibold);
  letter-spacing: var(--tracking-wide);
  text-transform: uppercase;
  color: #8fa3d0;
  margin-bottom: var(--sp-2);
}

.hero-title {
  font-size: var(--fs-display);
  color: #fff;
  margin-bottom: var(--sp-2);
}
.hero-title span {
  color: #7fa8ff;
}

.hero-sub {
  color: #c2cde6;
  max-width: 52ch;
  line-height: var(--lh-normal);
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-3);
  margin-top: var(--sp-6);
}
.hero-actions .btn-secondary {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.18);
  color: #fff;
  box-shadow: none;
}
.hero-actions .btn-secondary:hover {
  background: rgba(255, 255, 255, 0.18);
  border-color: rgba(255, 255, 255, 0.28);
}

.hero-side {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-4);
  flex-shrink: 0;
}

.hero-avatar {
  display: grid;
  place-items: center;
  width: 76px;
  height: 76px;
  border-radius: var(--r-pill);
  background: rgba(255, 255, 255, 0.12);
  border: 2px solid rgba(255, 255, 255, 0.24);
  font-size: var(--fs-h2);
  font-weight: var(--fw-bold);
  color: #fff;
}

.hero-status {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-2) var(--sp-4);
  border-radius: var(--r-pill);
  background: rgba(255, 255, 255, 0.12);
  font-size: var(--fs-caption);
  font-weight: var(--fw-medium);
  color: #e3e9f7;
  text-align: center;
}

/* ---------- Statistik ---------- */
.stats {
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

/* ---------- Split ---------- */
/* minmax(0, 1fr) — bukan 1fr — supaya kolom boleh menyusut lebih kecil dari
   konten terlebarnya dan area yang overflow menangani scroll-nya sendiri. */
.split {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--sp-6);
  align-items: start;
}

@media (min-width: 1200px) {
  .split {
    grid-template-columns: minmax(0, 1fr) var(--rail-w);
  }
}

.rail {
  min-width: 0;
}

/* ---------- Detail ---------- */
.detail {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: var(--sp-4);
}
.detail dt {
  font-size: var(--fs-micro);
  font-weight: var(--fw-semibold);
  letter-spacing: var(--tracking-wide);
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 2px;
}
.detail dd {
  font-size: var(--fs-body);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
  word-break: break-word;
}

.note {
  margin-top: var(--sp-4);
  padding: var(--sp-3) var(--sp-4);
  background: var(--c-warning-soft);
  border: 1px solid var(--c-warning-border);
  border-radius: var(--r-md);
  font-size: var(--fs-label);
  color: var(--text-body);
}
.note strong {
  color: var(--text-heading);
}

/* ---------- Unggah ---------- */
.upload {
  margin-top: var(--sp-5);
}

.dropzone {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-2);
  width: 100%;
  padding: var(--sp-8) var(--sp-5);
  border: 1.5px dashed var(--border-strong);
  border-radius: var(--r-lg);
  background: var(--bg-subtle);
  transition: border-color var(--dur) var(--ease), background var(--dur) var(--ease);
}
.dropzone:hover {
  border-color: var(--c-primary);
  background: var(--c-primary-soft);
}
.dropzone-title {
  font-size: var(--fs-body);
  font-weight: var(--fw-semibold);
  color: var(--text-heading);
}

.picked {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  flex-wrap: wrap;
  padding: var(--sp-3) var(--sp-4);
  background: var(--bg-subtle);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
}
.picked-name {
  flex: 1;
  min-width: 0;
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ---------- Template ---------- */
.template-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--sp-3);
  padding: var(--sp-5) var(--sp-6);
}

.template {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-3);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  transition: border-color var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
}
.template:hover {
  border-color: var(--c-primary-border);
  box-shadow: var(--sh-sm);
}

.template-thumb {
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  flex-shrink: 0;
  border-radius: var(--r-sm);
  background: var(--c-accent-soft);
  color: var(--c-accent);
  overflow: hidden;
}
.template-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.template-info {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--sp-1);
  min-width: 0;
}
.template-name {
  font-size: var(--fs-label);
  font-weight: var(--fw-semibold);
  color: var(--text-heading);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  max-width: 100%;
}

.link-more {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  font-size: var(--fs-caption);
  font-weight: var(--fw-semibold);
  color: var(--c-primary);
}
.link-more:hover {
  color: var(--c-primary-hover);
}

/* ---------- Lisensi & plugin ---------- */
.key-row {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}
.key {
  flex: 1;
  min-width: 0;
  padding: var(--sp-2) var(--sp-3);
  background: var(--bg-subtle);
  border: 1px solid var(--border);
  border-radius: var(--r-sm);
  font-family: var(--font-mono);
  font-size: var(--fs-caption);
  color: var(--text-heading);
  overflow-x: auto;
  white-space: nowrap;
}

.plugin-version {
  font-size: var(--fs-h1);
  font-weight: var(--fw-bold);
  color: var(--text-heading);
}
.plugin-log {
  margin-top: var(--sp-2);
  line-height: var(--lh-normal);
}

.locked {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  margin-top: var(--sp-4);
  padding: var(--sp-3);
  background: var(--c-warning-soft);
  border-radius: var(--r-md);
  font-size: var(--fs-caption);
  color: var(--text-body);
}

/* ---------- Responsif ---------- */
@media (max-width: 900px) {
  .hero {
    flex-direction: column;
    align-items: flex-start;
    padding: var(--sp-6);
  }
  .hero-side {
    flex-direction: row;
    align-self: stretch;
  }
  .hero-title {
    font-size: var(--fs-h1);
  }
}

@media (max-width: 640px) {
  .hero-avatar {
    width: 56px;
    height: 56px;
    font-size: var(--fs-h3);
  }
  .hero-actions .btn {
    flex: 1;
  }
  .template-grid,
  .detail {
    grid-template-columns: 1fr;
  }
}
</style>
