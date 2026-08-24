<script setup>
/**
 * Dashboard user.
 *
 * Sumber data:
 *   GET /user/dashboard  → transaksi, lisensi, template, flag izin, plugin
 *   GET /notifications   → mengisi panel "Aktivitas" (data asli)
 *   GET /admin/users     → daftar anggota, HANYA bila yang membuka adalah admin
 *                          (endpoint-nya admin-only di backend)
 *   placeholder.js       → Forum & Group, yang belum punya endpoint
 *
 * Aksi tetap sama seperti sebelumnya dan tetap dibatasi flag can_upload /
 * can_download yang dikirim backend.
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
  Users,
  MessagesSquare,
  Activity,
  UsersRound,
  Search,
  LayoutGrid,
  List,
  Star,
  ExternalLink,
  MessageCircle,
  Lock,
} from 'lucide-vue-next'
import StatCard from '@/components/ui/StatCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import AvatarRing from '@/components/ui/AvatarRing.vue'
import RailCard from '@/components/ui/RailCard.vue'
import { api, errorMessage } from '@/lib/api'
import { formatDate, formatRelative, initials, formatNumber } from '@/lib/format'
import { authState, isAdmin } from '@/stores/auth'
import { notifState, fetchNotifications } from '@/stores/notifications'
import { toast } from '@/stores/toast'
import {
  PLACEHOLDER_FORUMS,
  PLACEHOLDER_GROUPS,
  PLACEHOLDER_MEMBERS,
} from '@/lib/placeholder'

const loading = ref(true)
const uploading = ref(false)
const data = ref(null)
const fileInput = ref(null)
const selectedFile = ref(null)
const copied = ref('')

const members = ref([])
const memberTab = ref('Aktif')
const groupTab = ref('Aktif')

// Filter template (murni di sisi klien, atas data yang sudah dimuat)
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

const forums = PLACEHOLDER_FORUMS
const groups = PLACEHOLDER_GROUPS

/**
 * Progres lisensi — memetakan status transaksi nyata ke persentase cincin.
 * Bukan angka hiasan: tiap tahap berasal dari status yang dikirim backend.
 */
const licenseProgress = computed(() => {
  if (license.value) return 100
  if (!transaction.value) return 0
  switch (transaction.value.status) {
    case 'approved':
      return 100
    case 'pending_license':
      return 75
    case 'waiting_verification':
      return 50
    case 'rejected':
      return 25
    default:
      return 0
  }
})

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

const visibleMembers = computed(() => {
  if (memberTab.value === 'Aktif') return members.value.filter((m) => Number(m.is_active))
  if (memberTab.value === 'Admin') return members.value.filter((m) => m.role === 'admin')
  return members.value
})

const visibleGroups = computed(() =>
  groupTab.value === 'Aktif' ? groups.filter((g) => Number(g.is_active)) : groups
)

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
 * Daftar anggota hanya diambil bila pengguna adalah admin — GET /admin/users
 * dilindungi requireAdmin(), jadi memanggilnya sebagai user biasa hanya akan
 * menghasilkan 403. Untuk non-admin dipakai daftar placeholder.
 */
async function loadMembers() {
  if (!isAdmin.value) {
    members.value = PLACEHOLDER_MEMBERS
    return
  }
  try {
    const { data: res } = await api.get('/admin/users')
    members.value = res.users || []
  } catch {
    members.value = PLACEHOLDER_MEMBERS
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

onMounted(() => {
  load()
  loadMembers()
  fetchNotifications()
})
</script>

<template>
  <div class="dash">
    <!-- ============ Kolom utama ============ -->
    <div class="dash-main">
      <!-- ---------- Banner sambutan ---------- -->
      <section class="banner">
        <AvatarRing :name="user?.name" :percent="licenseProgress" :size="104" />

        <div class="banner-text">
          <p class="banner-progress">
            <strong>{{ licenseProgress }}%</strong> proses lisensi
          </p>
          <h1 class="banner-title">
            Selamat datang, <span>{{ user?.name }}</span>
          </h1>
          <p class="banner-sub">{{ heroState.text }}</p>

          <div class="banner-actions">
            <a v-if="canDownload" class="btn btn-primary" href="/api/user/download-plugin">
              <Download :size="17" />
              Unduh Plugin
            </a>
            <button
              v-else-if="canUpload"
              class="btn btn-primary"
              type="button"
              @click="pickFile"
            >
              <Upload :size="17" />
              Unggah Bukti Transaksi
            </button>
            <RouterLink v-else to="/plugins" class="btn btn-primary">
              Lihat Lisensi
              <ArrowRight :size="16" />
            </RouterLink>
          </div>
        </div>

        <span class="banner-deco" aria-hidden="true" />
      </section>

      <!-- ---------- Statistik ---------- -->
      <section class="grid stats">
        <StatCard
          label="Template Tersedia"
          :value="templates.length"
          :icon="LayoutTemplate"
          tone="tone-orange"
          hint="Siap dipakai"
          to="/templates"
        />
        <StatCard
          label="Lisensi Aktif"
          :value="license ? 1 : 0"
          :icon="KeyRound"
          tone="tone-blue"
          :hint="license ? 'Terbit' : 'Belum terbit'"
          to="/plugins"
        />
        <StatCard
          label="Anggota"
          :value="members.length"
          :icon="Users"
          tone="tone-green"
          hint="Pengguna portal"
        />
        <StatCard
          label="Notifikasi"
          :value="notifState.items.length"
          :icon="Activity"
          tone="tone-purple"
          :hint="`${notifState.unread} belum dibaca`"
        />
      </section>

      <!-- ---------- Anggota ---------- -->
      <section class="card">
        <header class="sec-head">
          <h2>
            <span class="icon-chip icon-chip-sm tone-blue"><Users :size="16" /></span>
            Anggota
          </h2>

          <div class="sec-tabs" role="tablist">
            <button
              v-for="t in ['Aktif', 'Semua', 'Admin']"
              :key="t"
              class="sec-tab"
              :class="{ active: memberTab === t }"
              type="button"
              role="tab"
              :aria-selected="memberTab === t"
              @click="memberTab = t"
            >
              {{ t }}
            </button>
          </div>
        </header>

        <div v-if="visibleMembers.length" class="member-strip scroll-area">
          <article v-for="m in visibleMembers" :key="m.id" class="member">
            <span class="member-avatar">
              {{ initials(m.name) }}
              <span
                class="member-dot"
                :class="Number(m.is_active) ? 'on' : 'off'"
                :title="Number(m.is_active) ? 'Aktif' : 'Nonaktif'"
              />
            </span>
            <span class="member-name">{{ m.name }}</span>
            <span class="member-role">{{ m.role === 'admin' ? 'Admin' : 'Member' }}</span>
          </article>
        </div>

        <EmptyState
          v-else
          :icon="Users"
          title="Tidak ada anggota"
          description="Belum ada anggota pada kategori ini."
        />
      </section>

      <!-- ---------- Template ---------- -->
      <section class="card">
        <header class="sec-head">
          <h2>
            <span class="icon-chip icon-chip-sm tone-orange">
              <LayoutTemplate :size="16" />
            </span>
            Template Undangan
          </h2>

          <RouterLink to="/templates" class="sec-more">
            Lihat semua <ArrowRight :size="14" />
          </RouterLink>
        </header>

        <!-- Filter bekerja atas data yang sudah dimuat, tanpa request tambahan -->
        <div class="toolbar">
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
            <option value="all">Semua Tipe</option>
            <option value="standard">Standard</option>
            <option value="premium">Premium</option>
          </select>

          <div class="toolbar-view">
            <button
              class="view-btn"
              :class="{ active: view === 'grid' }"
              type="button"
              aria-label="Tampilan grid"
              @click="view = 'grid'"
            >
              <LayoutGrid :size="16" />
            </button>
            <button
              class="view-btn"
              :class="{ active: view === 'list' }"
              type="button"
              aria-label="Tampilan daftar"
              @click="view = 'list'"
            >
              <List :size="16" />
            </button>
          </div>
        </div>

        <SkeletonRows v-if="loading" :rows="2" :height="120" />

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
              <LayoutTemplate v-else :size="26" />

              <span v-if="Number(t.is_recommended)" class="tpl-flag">
                <Star :size="11" fill="currentColor" />
                Rekomendasi
              </span>
            </div>

            <div class="tpl-body">
              <h3 class="tpl-name">{{ t.name }}</h3>
              <p v-if="t.description" class="tpl-desc">{{ t.description }}</p>

              <div class="tpl-meta">
                <span class="badge" :class="Number(t.is_standard) ? 'tone-blue' : 'tone-purple'">
                  {{ Number(t.is_standard) ? 'Standard' : 'Premium' }}
                </span>
                <a
                  v-if="t.checkout_url"
                  class="tpl-cta"
                  :href="t.checkout_url"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Pesan <ExternalLink :size="13" />
                </a>
              </div>
            </div>
          </article>
        </div>

        <EmptyState
          v-else
          :icon="LayoutTemplate"
          tone="tone-orange"
          title="Template tidak ditemukan"
          :description="
            search || kind !== 'all'
              ? 'Coba ubah kata kunci atau filter tipe.'
              : 'Template akan muncul setelah ditambahkan admin.'
          "
        />
      </section>

      <!-- ---------- Bukti transaksi ---------- -->
      <section class="card">
        <header class="sec-head">
          <h2>
            <span class="icon-chip icon-chip-sm tone-blue"><FileText :size="16" /></span>
            Bukti Transaksi
          </h2>
          <StatusBadge
            v-if="transaction"
            :status="transaction.status"
            :label="transaction.status_label"
          />
        </header>

        <SkeletonRows v-if="loading" :rows="2" />

        <div v-else class="sec-body">
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

          <div v-if="canUpload" class="upload">
            <input
              ref="fileInput"
              class="sr-only"
              type="file"
              accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
              @change="onFileChange"
            >

            <button v-if="!selectedFile" class="dropzone" type="button" @click="pickFile">
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
    </div>

    <!-- ============ Kolom kanan ============ -->
    <aside class="dash-rail">
      <!-- Forum — data contoh, belum ada endpoint -->
      <RailCard title="Forum" :icon="MessagesSquare" tone="tone-blue">
        <ul class="rail-list">
          <li v-for="f in forums" :key="f.id" class="rail-item">
            <span class="rail-avatar tone-blue">{{ initials(f.author) }}</span>
            <span class="rail-text">
              <span class="rail-title">
                {{ f.title }}
                <Lock v-if="Number(f.is_closed)" :size="12" class="rail-lock" />
              </span>
              <span class="rail-excerpt">{{ f.excerpt }}</span>
              <span class="rail-meta">
                <MessageCircle :size="12" />
                {{ f.replies }} balasan · {{ formatRelative(f.created_at) }}
              </span>
            </span>
          </li>
        </ul>
        <p class="rail-note">Data contoh — fitur forum belum tersedia di server.</p>
      </RailCard>

      <!-- Aktivitas — data asli dari notifikasi -->
      <RailCard title="Aktivitas" :icon="Activity" tone="tone-purple">
        <ul v-if="notifState.items.length" class="rail-list">
          <li v-for="n in notifState.items.slice(0, 5)" :key="n.id" class="rail-item">
            <span class="rail-avatar tone-purple"><Activity :size="15" /></span>
            <span class="rail-text">
              <span class="rail-title">{{ n.title }}</span>
              <span class="rail-excerpt">{{ n.message }}</span>
              <span class="rail-meta">
                <Clock :size="12" />
                {{ formatRelative(n.created_at) }}
              </span>
            </span>
          </li>
        </ul>
        <p v-else class="rail-empty">Belum ada aktivitas.</p>
      </RailCard>

      <!-- Group — data contoh, belum ada endpoint -->
      <RailCard
        title="Group"
        :icon="UsersRound"
        tone="tone-green"
        :tabs="['Aktif', 'Semua']"
        :active-tab="groupTab"
        @tab="groupTab = $event"
      >
        <ul v-if="visibleGroups.length" class="rail-list">
          <li v-for="g in visibleGroups" :key="g.id" class="rail-item">
            <span class="rail-avatar tone-green"><UsersRound :size="15" /></span>
            <span class="rail-text">
              <span class="rail-title">{{ g.name }}</span>
              <span class="rail-meta">
                <Users :size="12" />
                {{ formatNumber(g.members) }} anggota ·
                {{ formatRelative(g.last_active_at) }}
              </span>
            </span>
          </li>
        </ul>
        <p v-else class="rail-empty">Tidak ada group pada kategori ini.</p>
        <p class="rail-note">Data contoh — fitur group belum tersedia di server.</p>
      </RailCard>

      <!-- Lisensi & plugin — data asli -->
      <RailCard title="Lisensi Anda" :icon="KeyRound" tone="tone-green">
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

          <a class="btn btn-secondary btn-block" href="/api/user/download-invoice"
             style="margin-top: var(--sp-4)">
            <Receipt :size="16" />
            Unduh Invoice
          </a>
        </template>

        <p v-else class="rail-empty">
          Lisensi muncul otomatis setelah transaksi disetujui.
        </p>
      </RailCard>

      <RailCard title="Plugin Terbaru" :icon="Package" tone="tone-purple">
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

        <p v-else class="rail-empty">Belum ada rilis plugin.</p>
      </RailCard>
    </aside>
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

/* ============ Kerangka dua kolom ============ */
.dash {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--sp-6);
  align-items: start;
}

.dash-main,
.dash-rail {
  display: flex;
  flex-direction: column;
  gap: var(--sp-5);
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
  gap: var(--sp-6);
  padding: var(--sp-7);
  border-radius: var(--r-xl);
  background: var(--grad-banner);
  border: 1px solid var(--c-primary-border);
  overflow: hidden;
}

/* Bentuk dekoratif lembut di kanan, meniru ilustrasi pada referensi */
.banner-deco {
  position: absolute;
  right: -60px;
  top: -80px;
  width: 320px;
  height: 320px;
  border-radius: 50%;
  background: radial-gradient(
    circle at 35% 35%,
    rgba(86, 204, 242, 0.35),
    rgba(47, 128, 237, 0.12) 55%,
    transparent 70%
  );
  pointer-events: none;
}

.banner-text {
  position: relative;
  z-index: 1;
  min-width: 0;
}

.banner-progress {
  font-size: var(--fs-label);
  color: var(--text-muted);
  margin-bottom: var(--sp-1);
}
.banner-progress strong {
  color: var(--c-primary);
  font-weight: var(--fw-bold);
}

.banner-title {
  font-size: var(--fs-display);
  margin-bottom: var(--sp-2);
}
.banner-title span {
  color: var(--c-primary);
}

.banner-sub {
  color: var(--text-body);
  max-width: 52ch;
  line-height: var(--lh-normal);
}

.banner-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-3);
  margin-top: var(--sp-5);
}

/* ============ Statistik ============ */
/* Ambang 160px dipilih agar keempat kartu tetap satu baris setelah kolom
   kanan muncul (kolom utama ±790px pada layar 1440px). */
.stats {
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
}

/* ============ Header seksi ============ */
.sec-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-4);
  flex-wrap: wrap;
  padding: var(--sp-5) var(--sp-6);
  border-bottom: 1px solid var(--border);
}

.sec-head h2 {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  font-size: var(--fs-h3);
}

.sec-more {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  font-size: var(--fs-caption);
  font-weight: var(--fw-semibold);
  color: var(--c-primary);
}
.sec-more:hover {
  color: var(--c-primary-hover);
}

.sec-tabs {
  display: flex;
  gap: var(--sp-4);
}

.sec-tab {
  position: relative;
  padding-bottom: var(--sp-1);
  font-size: var(--fs-label);
  font-weight: var(--fw-semibold);
  color: var(--text-muted);
  transition: color var(--dur) var(--ease);
}
.sec-tab:hover {
  color: var(--text-heading);
}
.sec-tab.active {
  color: var(--c-primary);
}
.sec-tab.active::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: -2px;
  height: 2px;
  border-radius: 2px;
  background: var(--c-primary);
}

.sec-body {
  padding: var(--sp-6);
}

/* ============ Strip anggota ============ */
.member-strip {
  display: flex;
  gap: var(--sp-3);
  padding: var(--sp-5) var(--sp-6);
  overflow-x: auto;
}

.member {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-2);
  flex-shrink: 0;
  width: 116px;
  padding: var(--sp-4) var(--sp-2);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  text-align: center;
  transition: border-color var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
}
.member:hover {
  border-color: var(--c-primary-border);
  box-shadow: var(--sh-sm);
}

.member-avatar {
  position: relative;
  display: grid;
  place-items: center;
  width: 56px;
  height: 56px;
  border-radius: var(--r-pill);
  background: var(--c-primary-soft);
  border: 2px solid var(--bg-surface);
  outline: 2px solid var(--c-primary-border);
  color: var(--c-primary);
  font-size: var(--fs-body);
  font-weight: var(--fw-bold);
}

.member-dot {
  position: absolute;
  right: -1px;
  bottom: -1px;
  width: 13px;
  height: 13px;
  border-radius: 50%;
  border: 2px solid var(--bg-surface);
}
.member-dot.on {
  background: var(--c-success);
}
.member-dot.off {
  background: var(--text-faint);
}

.member-name {
  font-size: var(--fs-caption);
  font-weight: var(--fw-semibold);
  color: var(--text-heading);
  max-width: 100%;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.member-role {
  font-size: var(--fs-micro);
  color: var(--text-muted);
}

/* ============ Toolbar template ============ */
.toolbar {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  flex-wrap: wrap;
  padding: var(--sp-4) var(--sp-6);
  border-bottom: 1px solid var(--border);
}

.toolbar-search {
  flex: 1;
  min-width: 180px;
}
.toolbar-search .input {
  height: 38px;
  min-height: 38px;
}

.toolbar-select {
  width: auto;
  min-width: 150px;
  min-height: 38px;
  height: 38px;
  padding-top: 0;
  padding-bottom: 0;
}

.toolbar-view {
  display: flex;
  gap: var(--sp-1);
  padding: 3px;
  background: var(--bg-subtle);
  border-radius: var(--r-sm);
}

.view-btn {
  display: grid;
  place-items: center;
  width: 32px;
  height: 32px;
  border-radius: 6px;
  color: var(--text-muted);
  transition: background var(--dur) var(--ease), color var(--dur) var(--ease);
}
.view-btn:hover {
  color: var(--text-heading);
}
.view-btn.active {
  background: var(--bg-surface);
  color: var(--c-primary);
  box-shadow: var(--sh-xs);
}

/* ============ Kartu template ============ */
.tpl-wrap {
  padding: var(--sp-5) var(--sp-6);
}

.tpl-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
  gap: var(--sp-4);
}

.tpl-list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}

.tpl {
  display: flex;
  flex-direction: column;
  border: 1px solid var(--border);
  border-radius: var(--r-md);
  overflow: hidden;
  transition: transform var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
}
.tpl:hover {
  transform: translateY(-2px);
  box-shadow: var(--sh-md);
}

.tpl-thumb {
  position: relative;
  display: grid;
  place-items: center;
  aspect-ratio: 16 / 10;
  background: var(--c-accent-soft);
  color: var(--c-accent);
  overflow: hidden;
}
.tpl-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.tpl-flag {
  position: absolute;
  top: var(--sp-2);
  left: var(--sp-2);
  display: inline-flex;
  align-items: center;
  gap: 3px;
  padding: 2px var(--sp-2);
  border-radius: var(--r-pill);
  background: var(--c-warning);
  color: #fff;
  font-size: var(--fs-micro);
  font-weight: var(--fw-bold);
}

.tpl-body {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
  padding: var(--sp-4);
  flex: 1;
}

.tpl-name {
  font-size: var(--fs-body);
  font-weight: var(--fw-semibold);
  color: var(--text-heading);
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

.tpl-meta {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-2);
  margin-top: auto;
  padding-top: var(--sp-2);
}

.tpl-cta {
  display: inline-flex;
  align-items: center;
  gap: 3px;
  font-size: var(--fs-caption);
  font-weight: var(--fw-semibold);
  color: var(--c-primary);
}
.tpl-cta:hover {
  color: var(--c-primary-hover);
}

/* Mode daftar: thumbnail kecil di kiri */
.tpl-list .tpl {
  flex-direction: row;
  align-items: stretch;
}
.tpl-list .tpl-thumb {
  width: 120px;
  aspect-ratio: auto;
  flex-shrink: 0;
}
.tpl-list .tpl-body {
  padding: var(--sp-3) var(--sp-4);
}
.tpl-list .tpl-desc {
  -webkit-line-clamp: 1;
  line-clamp: 1;
}

/* ============ Daftar rail ============ */
.rail-list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-4);
}

.rail-item {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-3);
}

.rail-avatar {
  display: grid;
  place-items: center;
  width: 36px;
  height: 36px;
  flex-shrink: 0;
  border-radius: var(--r-pill);
  background: var(--tone-bg);
  color: var(--tone-fg);
  font-size: var(--fs-caption);
  font-weight: var(--fw-bold);
}

.rail-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
}

.rail-title {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
  font-size: var(--fs-label);
  font-weight: var(--fw-semibold);
  color: var(--text-heading);
  line-height: var(--lh-snug);
}

.rail-lock {
  color: var(--text-faint);
  flex-shrink: 0;
}

.rail-excerpt {
  font-size: var(--fs-caption);
  color: var(--text-muted);
  line-height: var(--lh-snug);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.rail-meta {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
  margin-top: 2px;
  font-size: var(--fs-micro);
  color: var(--text-faint);
}

.rail-empty {
  font-size: var(--fs-caption);
  color: var(--text-muted);
  line-height: var(--lh-normal);
}

/* Penanda jujur bahwa isinya belum berasal dari server */
.rail-note {
  margin-top: var(--sp-4);
  padding-top: var(--sp-3);
  border-top: 1px dashed var(--border-strong);
  font-size: var(--fs-micro);
  color: var(--text-faint);
}

/* ============ Detail transaksi ============ */
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

/* ============ Unggah ============ */
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

/* ============ Lisensi & plugin ============ */
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
  font-size: var(--fs-h2);
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

/* ============ Responsif ============ */
@media (max-width: 900px) {
  .banner {
    flex-direction: column;
    align-items: flex-start;
    padding: var(--sp-6);
    gap: var(--sp-5);
  }
  .banner-title {
    font-size: var(--fs-h1);
  }
}

@media (max-width: 640px) {
  .sec-head {
    padding: var(--sp-4);
  }
  .sec-body,
  .tpl-wrap,
  .member-strip,
  .toolbar {
    padding: var(--sp-4);
  }
  .banner-actions .btn {
    width: 100%;
  }
  .detail {
    grid-template-columns: 1fr;
  }
  .toolbar-search,
  .toolbar-select {
    width: 100%;
    flex: 1 1 100%;
  }
  /* Mode daftar kembali bertumpuk agar tidak sempit */
  .tpl-list .tpl {
    flex-direction: column;
  }
  .tpl-list .tpl-thumb {
    width: 100%;
    aspect-ratio: 16 / 10;
  }
}
</style>
