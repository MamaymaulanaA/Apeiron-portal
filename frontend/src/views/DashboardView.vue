<script setup>
/**
 * Dashboard user — mengikuti struktur referensi: banner sambutan, kartu
 * ringkasan, daftar anggota, katalog template, dan kolom kanan.
 *
 * SUMBER DATA (semuanya endpoint yang sudah ada, tidak ada data karangan):
 *   GET /user/dashboard  → transaksi, lisensi, template, flag izin, plugin
 *   GET /notifications   → Aktivitas + jumlah belum dibaca
 *   GET /admin/users     → Anggota; hanya dipanggil bila yang membuka admin,
 *                          karena endpoint itu dilindungi requireAdmin()
 *
 * Forum dan Group ada di kolom kanan mengikuti referensi, tetapi backend belum
 * punya tabel maupun endpoint untuk keduanya. Selama itu belum ada, keduanya
 * menampilkan empty state — bukan data contoh.
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
  CircleAlert,
  Receipt,
  ArrowRight,
  Search,
  LayoutGrid,
  List,
  ExternalLink,
  Activity,
  MessagesSquare,
  UsersRound,
  Users,
} from 'lucide-vue-next'
import StatCard from '@/components/ui/StatCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import RailCard from '@/components/ui/RailCard.vue'
import BannerArt from '@/components/ui/BannerArt.vue'
import { api, errorMessage } from '@/lib/api'
import { formatDate, formatRelative, initials } from '@/lib/format'
import { authState, isAdmin } from '@/stores/auth'
import { notifState, fetchNotifications } from '@/stores/notifications'
import { toast } from '@/stores/toast'

const loading = ref(true)
const uploading = ref(false)
const data = ref(null)
const fileInput = ref(null)
const selectedFile = ref(null)
const copied = ref('')

const members = ref([])
const membersLoading = ref(false)
const memberTab = ref('Aktif')

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

/** Progres lisensi, diturunkan dari status transaksi yang dikirim backend. */
const progress = computed(() => {
  if (license.value) return 100
  if (!transaction.value) return 0
  switch (transaction.value.status) {
    case 'approved':
      return 100
    case 'pending_license':
      return 75
    case 'waiting_verification':
      return 45
    case 'rejected':
      return 25
    default:
      return 0
  }
})

const guidance = computed(() => {
  // Lisensi diperiksa lebih dulu, sama seperti `progress` di atas. Tanpa ini
  // cincin bisa menunjukkan 100% sementara teksnya masih berkata "sedang
  // diperiksa" — dua keterangan yang saling bertentangan.
  if (license.value) {
    return 'Lisensi Anda aktif. Plugin versi terbaru siap diunduh.'
  }
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
      return 'Bukti transaksi Anda sedang diperiksa admin.'
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

const visibleMembers = computed(() =>
  memberTab.value === 'Aktif'
    ? members.value.filter((m) => Number(m.is_active))
    : members.value
)

const recentActivity = computed(() => notifState.items.slice(0, 5))

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

/**
 * Anggota hanya diambil bila yang membuka adalah admin — GET /admin/users
 * dilindungi requireAdmin(), jadi memanggilnya sebagai user biasa hanya akan
 * menghasilkan 403. Bila gagal, daftar dibiarkan kosong (tanpa data contoh).
 */
async function loadMembers() {
  if (!isAdmin.value) return
  membersLoading.value = true
  try {
    const { data: res } = await api.get('/admin/users')
    members.value = res.users || []
  } catch {
    members.value = []
  } finally {
    membersLoading.value = false
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
  loadMembers()
  fetchNotifications()
})
</script>

<template>
  <div class="dash">
    <!-- ================= Kolom utama ================= -->
    <div class="dash-main">
      <!-- ---------- Banner sambutan ---------- -->
      <section class="banner">
        <div class="banner-text">
          <span class="banner-pill">{{ progress }}% proses lisensi</span>
          <h1 class="banner-title">
            Selamat datang,<br>
            <span>{{ user?.name }}</span>
          </h1>
          <p class="banner-sub">{{ guidance }}</p>

                  <div class="banner-actions">
            <a v-if="canDownload" class="btn btn-primary" href="/api/user/download-plugin">
              <Download :size="16" />
              Unduh Plugin
            </a>
            <button
              v-else-if="canUpload || !transaction"
              class="btn btn-primary"
              type="button"
              @click="pickFile"
            >
              <Upload :size="16" />
              Unggah Bukti Transaksi
            </button>
            <RouterLink v-else to="/plugins" class="btn btn-secondary">
              Lihat Lisensi
              <ArrowRight :size="15" />
            </RouterLink>
          </div>
        </div>


        <BannerArt class="banner-art" />
      </section>

      <!-- ---------- Kartu ringkasan (semuanya data nyata) ---------- -->
      <section class="grid stats">
        <StatCard
          label="Template"
          :value="templates.length"
          :icon="LayoutTemplate"
          tone="tone-peach"
          hint="Siap dipakai"
          to="/templates"
        />
        <StatCard
          label="Status lisensi"
          :value="license ? 'Aktif' : 'Belum terbit'"
          :icon="KeyRound"
          tone="tone-sky"
          :hint="license ? formatDate(license.assigned_at, false) : 'Menunggu persetujuan'"
          to="/plugins"
        />
        <StatCard
          label="Versi plugin"
          :value="plugin ? `v${plugin.version}` : '—'"
          :icon="Package"
          tone="tone-mint"
          :hint="plugin ? 'Rilis terbaru' : 'Belum ada rilis'"
          to="/plugins"
        />
        <StatCard
          label="Notifikasi"
          :value="notifState.items.length"
          :icon="Activity"
          tone="tone-lilac"
          :hint="`${notifState.unread} belum dibaca`"
        />
      </section>


      <!-- ---------- Template ---------- -->
      <section class="card">
        <header class="card-header">
          <h2>
            <span class="icon-chip icon-chip-sm tone-peach">
              <LayoutTemplate :size="15" />
            </span>
            Template Undangan
          </h2>
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
              <!-- Judul memakai lebar penuh; badge turun ke baris aksi supaya
                   nama template panjang tidak terpotong. -->
              <h3 class="tpl-name">{{ t.name }}</h3>
              <p v-if="t.description" class="tpl-desc">{{ t.description }}</p>

              <div class="tpl-foot">
                <span
                  class="badge"
                  :class="Number(t.is_standard) ? 'tone-sky' : 'tone-lilac'"
                >
                  {{ Number(t.is_standard) ? 'Standard' : 'Premium' }}
                </span>
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

      <!-- ---------- Bukti transaksi ---------- -->
      <section class="card">
        <header class="card-header">
          <h2>
            <span class="icon-chip icon-chip-sm tone-sky"><FileText :size="15" /></span>
            Bukti Transaksi
          </h2>
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
    </div>

    <!-- ================= Kolom kanan ================= -->
    <aside class="dash-rail">
      <!-- Forum — belum ada tabel/endpoint di backend -->
      <RailCard title="Forum" :icon="MessagesSquare" tone="tone-sky" flush>
        <EmptyState
          :icon="MessagesSquare"
          title="Belum ada forum"
          description="Fitur forum belum tersedia di server."
          inline
        />
      </RailCard>

      <!-- Aktivitas — data nyata dari GET /notifications -->
      <RailCard title="Aktivitas" :icon="Activity" tone="tone-lilac" flush>
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
          :icon="Activity"
          title="Belum ada aktivitas"
          description="Pemberitahuan akan muncul di sini."
          compact
        />
      </RailCard>


      <!-- Anggota — data nyata dari GET /admin/users (hanya admin) -->
      <RailCard v-if="isAdmin" title="Anggota" :icon="Users" tone="tone-sky" to="/admin/users" flush>
        <SkeletonRows v-if="membersLoading" :rows="3" :height="36" />

        <ul v-else-if="visibleMembers.length" class="member-list">
          <li v-for="m in visibleMembers.slice(0, 6)" :key="m.id" class="member-row">
            <span class="avatar avatar-sm">{{ initials(m.name) }}</span>
            <span class="member-text">
              <span class="member-name">{{ m.name }}</span>
              <span class="member-mail">{{ m.email }}</span>
            </span>
            <span class="status-dot" :class="{ 'is-on': Number(m.is_active) }" />
          </li>
        </ul>

        <EmptyState
          v-else
          :icon="Users"
          title="Belum ada anggota"
          description="Daftar pengguna akan tampil di sini."
          inline
        />
      </RailCard>

      <!-- Group — belum ada tabel/endpoint di backend -->
      <RailCard title="Group" :icon="UsersRound" tone="tone-mint" flush>
        <EmptyState
          :icon="UsersRound"
          title="Belum ada group"
          description="Fitur group belum tersedia di server."
          inline
        />
      </RailCard>

      <!-- Lisensi — data nyata -->
      <RailCard title="Lisensi Anda" :icon="KeyRound" tone="tone-mint">
        <template v-if="license">
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

          <div v-if="license.api_key" class="field">
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

          <a class="btn btn-secondary btn-block" href="/api/user/download-invoice">
            <Receipt :size="15" />
            Unduh invoice
          </a>
        </template>

        <EmptyState
          v-else
          :icon="KeyRound"
          title="Lisensi belum terbit"
          description="Muncul otomatis setelah bukti transaksi disetujui."
          compact
        />
      </RailCard>

      <!-- Plugin — data nyata -->
      <RailCard title="Plugin Terbaru" :icon="Package" tone="tone-peach">
        <template v-if="plugin">
          <p class="plugin-version">v{{ plugin.version }}</p>
          <p v-if="plugin.changelog" class="text-caption plugin-log">
            {{ plugin.changelog }}
          </p>

          <a
            v-if="canDownload"
            class="btn btn-primary btn-block"
            href="/api/user/download-plugin"
          >
            <Download :size="15" />
            Unduh plugin
          </a>
          <p v-else class="locked">
            <CircleAlert :size="14" />
            Lisensi aktif diperlukan untuk mengunduh.
          </p>
        </template>

        <EmptyState
          v-else
          :icon="Package"
          title="Belum ada rilis"
          description="Versi plugin akan tampil di sini."
          compact
        />
      </RailCard>
    </aside>
  </div>
</template>

<style scoped>
/* ============ Kerangka dua kolom ============ */
.dash {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--sp-4);
  align-items: start;
}

.dash-main,
.dash-rail {
  display: flex;
  flex-direction: column;
  gap: var(--sp-4);
  min-width: 0;
}

@media (min-width: 1280px) {
  .dash {
    grid-template-columns: minmax(0, 1fr) var(--rail-w);
  }
}

/* ============ Banner ============ */
.banner {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--sp-5);
  padding: var(--sp-6) var(--sp-7);
  border-radius: var(--r);
  background: linear-gradient(105deg, #eaf1ff 0%, #f4f8ff 46%, #e8f0ff 100%);
  border: 1px solid var(--brand-border);
  overflow: hidden;
}

.banner-text {
  min-width: 0;
  flex: 1;
}

.banner-title {
  text-wrap: balance;
}

.banner-pill {
  display: inline-flex;
  align-items: center;
  height: 26px;
  padding: 0 var(--sp-3);
  border-radius: var(--r-pill);
  background: var(--bg-surface);
  color: var(--brand);
  font-size: var(--fs-caption);
  font-weight: var(--fw-medium);
}

.banner-title {
  font-size: var(--fs-display);
  font-weight: var(--fw-bold);
  line-height: 1.25;
  margin-top: var(--sp-3);
  margin-bottom: var(--sp-2);
}
.banner-title span {
  color: var(--brand);
}

.banner-sub {
  color: var(--text-body);
  max-width: 60ch;
  line-height: var(--lh-snug);
}

.banner-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-2);
  margin-top: var(--sp-4);
}

/* Ilustrasi hanya muncul saat ada ruang lebih; di layar sempit ia
   disembunyikan agar tidak menekan teks. */
.banner-art {
  display: none;
  width: 300px;
  height: 190px;
  flex-shrink: 0;
  margin: calc(var(--sp-6) * -1) calc(var(--sp-7) * -1) calc(var(--sp-6) * -1) 0;
  align-self: stretch;
}

@media (min-width: 1060px) {
  .banner-art {
    display: block;
  }
}

/* ============ Ringkasan ============ */
.stats {
  grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr));
}

/* ============ Tab ============ */
.tabs {
  display: flex;
  gap: var(--sp-4);
}

.tab {
  position: relative;
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  color: var(--text-muted);
  transition: color var(--dur) var(--ease);
}
.tab:hover {
  color: var(--text-heading);
}
.tab.active {
  color: var(--brand);
}
.tab.active::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: -6px;
  height: 2px;
  background: var(--brand);
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

/* ============ Anggota (kolom kanan) ============ */
.member-list {
  display: flex;
  flex-direction: column;
}

.member-row {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-5);
  border-bottom: 1px solid var(--border);
}
.member-row:last-child {
  border-bottom: none;
}

.member-text {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
  line-height: 1.35;
}

.member-name {
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.member-mail {
  font-size: var(--fs-caption);
  color: var(--text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ============ Toolbar ============ */
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
  grid-template-columns: repeat(auto-fill, minmax(min(215px, 100%), 1fr));
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

/* Nama template boleh membungkus sampai dua baris — memotongnya membuat
   nama seperti "217+ Template Undangan Digital Premium" kehilangan arti. */
.tpl-name {
  font-size: var(--fs-body);
  font-weight: var(--fw-medium);
  line-height: var(--lh-snug);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.tpl-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-2);
  margin-top: auto;
  padding-top: var(--sp-1);
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
  font-size: var(--fs-caption);
  font-weight: var(--fw-medium);
  color: var(--brand);
}
.tpl-cta:hover {
  color: var(--brand-hover);
}

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

/* ============ Detail transaksi ============ */
.detail {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(160px, 100%), 1fr));
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

/* ============ Lisensi & plugin ============ */
.key-row {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}
/* Dipotong dengan elipsis, bukan discroll. Scrollbar horizontal di dalam
   field sempit terlihat kotor, dan nilai penuh tetap ikut saat disalin. */
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
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: block;
  line-height: var(--h-control-sm);
}

.plugin-version {
  font-size: var(--fs-h1);
  font-weight: var(--fw-bold);
  color: var(--text-heading);
}
.plugin-log {
  line-height: var(--lh-snug);
}
.locked {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
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
@media (max-width: 900px) {
  .banner {
    flex-direction: column;
    align-items: flex-start;
  }
  .banner-title {
    font-size: var(--fs-h1);
  }
}

@media (max-width: 640px) {
  .toolbar,
  .tpl-wrap,
  .card-body,
  .member-strip {
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
  .banner-actions,
  .banner-actions .btn {
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
