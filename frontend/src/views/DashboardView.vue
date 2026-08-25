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
  Download,
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
  BookOpen,
  UsersRound,
  Users,
  Lock,
} from 'lucide-vue-next'
import StatCard from '@/components/ui/StatCard.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import RailCard from '@/components/ui/RailCard.vue'
import WelcomeBanner from '@/components/ui/WelcomeBanner.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import PlatformIcon from '@/components/ui/PlatformIcon.vue'
import { tutorialIcon, platformLabel, platformTone, GROUP_ACCESS_COPY } from '@/lib/community'
import { api, errorMessage } from '@/lib/api'
import { formatDate, formatRelative } from '@/lib/format'
import { authState, isAdmin } from '@/stores/auth'
import { notifState, fetchNotifications } from '@/stores/notifications'
import { toast } from '@/stores/toast'

const loading = ref(true)
const data = ref(null)
const copied = ref('')

/** Anggota aktif — jumlah dan beberapa avatar, dari GET /user/active-members. */
const activeCount = ref(0)
const activeMembers = ref([])
const activeLoading = ref(true)

/**
 * Forum (panduan) dan Group. Keduanya dari endpoint yang menyajikan data
 * database apa adanya, jadi apa pun yang admin ubah langsung terlihat di sini.
 */
const tutorials = ref([])
const tutorialsLoading = ref(true)
const tutorialsError = ref('')

const groups = ref([])
const groupAccess = ref('none')
const groupsLoading = ref(true)
const groupsError = ref('')

// Filter template — bekerja di sisi klien atas data yang sudah dimuat.
const search = ref('')
const kind = ref('all')
const view = ref('grid')

const user = computed(() => authState.user)

/** Kalimat untuk keadaan gerbang yang sedang berlaku. */
const groupCopy = computed(
  () => GROUP_ACCESS_COPY[groupAccess.value] || GROUP_ACCESS_COPY.none
)
const transaction = computed(() => data.value?.transaction || null)
const license = computed(() => data.value?.license || null)
const templates = computed(() => data.value?.templates || [])
const plugin = computed(() => data.value?.latest_plugin || null)
const canUpload = computed(() => !!data.value?.can_upload)

/**
 * Apakah lisensi sudah aktif. Dashboard tetap terbuka apa pun hasilnya —
 * nilai ini hanya menentukan aksi mana yang ditawarkan di banner.
 * Pengajuan dan status menunggu ditangani halaman Transaksi.
 */
const hasAccess = computed(
  () => !!license.value || transaction.value?.status === 'approved'
)
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


/**
 * Tidak dipotong di sini: kartunya menampilkan tiga baris lalu digulir, sama
 * seperti Aktivitas di dashboard admin. Memotong daftar hanya menyembunyikan
 * sisanya tanpa memberi cara melihatnya.
 */
const recentActivity = computed(() => notifState.items)

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

async function loadActiveMembers() {
  activeLoading.value = true
  try {
    const { data } = await api.get('/user/active-members')
    activeCount.value = Number(data.active_count) || 0
    activeMembers.value = data.members || []
  } catch {
    // Kartu pelengkap; kegagalannya tidak boleh mengganggu sisa dashboard.
    activeCount.value = 0
    activeMembers.value = []
  } finally {
    activeLoading.value = false
  }
}




async function loadTutorials() {
  tutorialsLoading.value = true
  tutorialsError.value = ''
  try {
    const { data } = await api.get('/user/tutorials')
    tutorials.value = data.tutorials || []
  } catch (e) {
    tutorialsError.value = errorMessage(e, 'Gagal memuat panduan')
  } finally {
    tutorialsLoading.value = false
  }
}

async function loadGroups() {
  groupsLoading.value = true
  groupsError.value = ''
  try {
    const { data } = await api.get('/user/groups')
    // Keadaan akses datang dari server. Frontend tidak pernah menghitungnya
    // sendiri dari status transaksi — kalau ia menghitung sendiri, gerbangnya
    // jadi soal tampilan, bukan soal izin.
    groupAccess.value = data.access || 'none'
    groups.value = data.groups || []
  } catch (e) {
    groupsError.value = errorMessage(e, 'Gagal memuat group')
    groupAccess.value = 'none'
    groups.value = []
  } finally {
    groupsLoading.value = false
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
  loadActiveMembers()
  loadTutorials()
  loadGroups()
  fetchNotifications()
})
</script>

<template>
  <!-- Satu akar tunggal. Transisi route di AppLayout memakai <Transition>,
       yang menuntut tepat satu elemen anak — komentar atau elemen kedua di
       tingkat akar membuat kelas transisi tersangkut dan halaman tetap
       opacity: 0. -->
  <div class="dash-root">
    <div v-if="loading" class="dash-boot">
      <SkeletonRows :rows="3" :height="72" />
    </div>


    <div v-else class="dash">
    <!-- ================= Kolom utama ================= -->
    <div class="dash-main">
      <!-- ---------- Banner sambutan ---------- -->
      <WelcomeBanner :pill="`${progress}% proses lisensi`">
        <template #title>
          Selamat datang,<br>
          <span>{{ user?.name }}</span>
        </template>

        <template #sub>{{ guidance }}</template>

        <!-- Pengajuan bukti transaksi hidup di halaman Transaksi, jadi di
             sini cukup mengarahkan ke sana selama lisensi belum aktif. -->
        <template #actions>
          <a v-if="canDownload" class="btn btn-primary" href="/api/user/download-plugin">
            <Download :size="16" />
            Unduh Plugin
          </a>
          <RouterLink v-else-if="!hasAccess" to="/transactions" class="btn btn-primary">
            <Receipt :size="16" />
            {{ transaction ? 'Lihat Status Pengajuan' : 'Unggah Bukti Transaksi' }}
            <ArrowRight :size="15" />
          </RouterLink>
          <RouterLink v-else to="/plugins" class="btn btn-secondary">
            Lihat Lisensi
            <ArrowRight :size="15" />
          </RouterLink>
        </template>
      </WelcomeBanner>

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

    </div>

    <!-- ================= Kolom kanan ================= -->
    <aside class="dash-rail">
      <!-- Panduan — data nyata dari GET /user/tutorials. Tiga teratas saja;
           selebihnya lewat "Lihat semua". -->
      <RailCard title="Panduan" :icon="BookOpen" tone="tone-sky" to="/tutorials" flush>
        <SkeletonRows v-if="tutorialsLoading" :rows="3" :height="36" />

        <EmptyState
          v-else-if="tutorialsError"
          :icon="BookOpen"
          title="Gagal memuat panduan"
          :description="tutorialsError"
          inline
        />

        <ul v-else-if="tutorials.length" class="tut-list">
          <li v-for="t in tutorials.slice(0, 3)" :key="t.id">
            <RouterLink to="/tutorials" class="tut-row">
              <span class="icon-chip icon-chip-sm tone-sky">
                <component :is="tutorialIcon(t.icon)" :size="15" />
              </span>
              <span class="tut-row-text">
                <span class="tut-row-title">{{ t.title }}</span>
                <span v-if="t.category" class="tut-row-meta">{{ t.category }}</span>
              </span>
              <ArrowRight class="tut-row-arrow" :size="15" />
            </RouterLink>
          </li>
        </ul>

        <EmptyState
          v-else
          :icon="BookOpen"
          title="Belum ada panduan"
          description="Tutorial akan tampil di sini setelah admin menambahkannya."
          inline
        />
      </RailCard>

      <!-- Group — GET /user/groups.
           Gerbangnya di server: ketika akses belum terbuka, respons memang
           tidak memuat link sama sekali, jadi tidak ada yang bisa didapat
           dengan mengutak-atik tampilan. -->
      <RailCard title="Group" :icon="UsersRound" tone="tone-mint" flush>
        <SkeletonRows v-if="groupsLoading" :rows="2" :height="44" />

        <EmptyState
          v-else-if="groupsError"
          :icon="UsersRound"
          title="Gagal memuat group"
          :description="groupsError"
          inline
        />

        <!-- Akses terbuka -->
        <ul v-else-if="groupAccess === 'granted' && groups.length" class="group-list">
          <li v-for="g in groups" :key="g.id">
            <a
              class="group-row"
              :href="g.link"
              target="_blank"
              rel="noopener noreferrer"
            >
              <span class="icon-chip icon-chip-sm" :class="platformTone(g.platform)">
                <PlatformIcon :platform="g.platform" :size="15" />
              </span>
              <span class="group-text">
                <span class="group-name">{{ g.name }}</span>
                <span class="group-meta">{{ platformLabel(g.platform) }}</span>
              </span>
              <ExternalLink class="group-arrow" :size="15" />
            </a>
          </li>
        </ul>

        <!-- Akses terbuka, tapi admin belum menambahkan grup apa pun -->
        <EmptyState
          v-else-if="groupAccess === 'granted'"
          :icon="UsersRound"
          title="Belum ada group"
          description="Group akan tampil di sini setelah admin menambahkannya."
          inline
        />

        <!-- Belum berhak: alasannya dijelaskan, bukan sekadar kosong -->
        <div v-else class="group-locked">
          <span class="icon-chip icon-chip-sm tone-neutral"><Lock :size="15" /></span>
          <div class="group-locked-text">
            <p class="text-label">{{ groupCopy.title }}</p>
            <p class="text-caption">{{ groupCopy.description }}</p>
          </div>
          <RouterLink
            v-if="groupAccess !== 'pending'"
            to="/transactions"
            class="btn btn-sm btn-secondary group-locked-cta"
          >
            {{ groupAccess === 'rejected' ? 'Unggah Ulang' : 'Ke Transaksi' }}
            <ArrowRight :size="14" />
          </RouterLink>
        </div>
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

      <!-- Aktivitas — data nyata dari GET /notifications -->
      <RailCard title="Aktivitas" :icon="Activity" tone="tone-lilac" flush>
        <ul v-if="recentActivity.length" class="feed scroll-area">
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

      <!-- Anggota aktif — GET /user/active-members.
           Jumlah dan beberapa avatar, bukan direktori anggota: endpoint daftar
           penuh tetap admin-only. Emailnya tidak ikut dikirim; benih avatar
           berupa hash, jadi wajah tiap orang tetap sama tanpa alamat siapa pun
           bocor ke sesama user. -->
      <RailCard title="Anggota Aktif" :icon="Users" tone="tone-sky" :badge="activeCount || null" flush>
        <SkeletonRows v-if="activeLoading" :rows="1" :height="72" />

        <ul v-else-if="activeMembers.length" class="active-strip">
          <li v-for="(m, i) in activeMembers" :key="m.seed || i" class="active-item">
            <span class="active-face">
              <span class="avatar"><UserAvatar :name="m.name" :seed="m.seed" /></span>
              <span class="active-dot" aria-hidden="true" />
            </span>
            <span class="active-name" :title="m.name">{{ m.name }}</span>
          </li>
        </ul>

        <EmptyState
          v-else
          :icon="Users"
          title="Belum ada anggota lain"
          description="Anggota yang baru bergabung akan tampil di sini."
          inline
        />
      </RailCard>






      </aside>
    </div>
  </div>
</template>

<style scoped>
/* ============ Kerangka dua kolom ============ */
.dash-root {
  display: contents;
}

.dash-boot {
  padding: var(--sp-4) 0;
}

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
/* Tiga baris lalu digulir. Satu baris terukur 110px (padding, judul, pesan
   yang dipotong dua baris, dan waktu), jadi batasnya 330px — nilai yang sama
   dengan Aktivitas di dashboard admin.

   Tanpa batas ini kartunya tumbuh sampai 613px dan kolom kanan menjulur jauh
   melewati kolom utama. */
.feed {
  display: flex;
  flex-direction: column;
  max-height: calc(110px * 3);
  overscroll-behavior: contain;
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

/* ============ Anggota aktif ============ */
/* Satu baris yang digulir ke samping, bukan kisi dua baris.
   Kartunya jadi setinggi satu baris berapa pun jumlah anggotanya, sehingga
   kaki kolom kanan tidak bergeser tiap kali komunitasnya bertambah.

   Batang scrollbar-nya disembunyikan: ia memotong kaki kartu dan terbaca
   sebagai garis nyasar. Avatar yang terpotong di tepi kanan sudah cukup
   memberi tahu bahwa barisnya berlanjut, dan menggulir tetap bisa — dengan
   roda, usap, maupun keyboard. */
.active-strip {
  display: flex;
  gap: var(--sp-3);
  padding: var(--sp-4) var(--sp-5);
  overflow-x: auto;
  overscroll-behavior-x: contain;
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.active-strip::-webkit-scrollbar {
  display: none;
}

.active-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-2);
  /* Lebar tetap supaya jaraknya rata berapa pun panjang namanya. 84px karena
     pada 72px dua nama tiga-kata terukur terpotong. */
  width: 84px;
  flex-shrink: 0;
  text-align: center;
}

.active-face {
  position: relative;
  display: block;
}
.active-face .avatar {
  width: 40px;
  height: 40px;
  border: 1px solid var(--border-strong);
}

/* Semua yang tampil di sini memang berstatus aktif, jadi titiknya menegaskan
   itu — bukan membedakan satu dari yang lain. */
.active-dot {
  position: absolute;
  right: -1px;
  bottom: -1px;
  width: 11px;
  height: 11px;
  border-radius: var(--r-pill);
  background: var(--ok);
  border: 2px solid var(--bg-surface);
}

/* Dua baris lalu dipotong; nama panjang tidak boleh menggeser kolomnya. */
.active-name {
  max-width: 100%;
  font-size: var(--fs-micro);
  line-height: var(--lh-snug);
  color: var(--text-body);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  overflow-wrap: anywhere;
}

/* ============ Forum (panduan) ============ */
.tut-list,
.group-list {
  display: flex;
  flex-direction: column;
}

.tut-row,
.group-row {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-5);
  border-bottom: 1px solid var(--border);
  transition: background var(--dur) var(--ease);
}
.tut-list li:last-child .tut-row,
.group-list li:last-child .group-row {
  border-bottom: none;
}
.tut-row:hover,
.group-row:hover {
  background: var(--bg-hover);
}

.tut-row-text,
.group-text {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
  line-height: 1.35;
}

.tut-row-title,
.group-name {
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tut-row-meta,
.group-meta {
  font-size: var(--fs-micro);
  color: var(--text-muted);
}

.tut-row-arrow,
.group-arrow {
  flex-shrink: 0;
  color: var(--text-faint);
}

/* ============ Group terkunci ============ */
.group-locked {
  display: flex;
  flex-wrap: wrap;
  align-items: flex-start;
  gap: var(--sp-3);
  padding: var(--sp-4) var(--sp-5);
}

.group-locked-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.group-locked-cta {
  flex-basis: 100%;
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
  .tpl-list .tpl {
    flex-direction: column;
  }
  .tpl-list .tpl-thumb {
    width: 100%;
    aspect-ratio: 16 / 9;
  }
}
</style>
