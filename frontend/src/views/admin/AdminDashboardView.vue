<script setup>
/**
 * Dashboard admin — GET /admin/dashboard.
 *
 * Catatan: recent_transactions dikirim sebagai baris mentah dari query JOIN,
 * jadi field-nya snake_case apa adanya (user_name, user_email, status, …) dan
 * tanpa status_label — label diambil dari peta di lib/format.
 */
import { ref, computed, onMounted } from 'vue'
import {
  Clock,
  CircleCheck,
  CircleX,
  Users,
  LayoutTemplate,
  ServerCrash,
  Receipt,
  ArrowRight,
  Package,
  UserPlus,
  Zap,
  Activity,
  MessagesSquare,
} from 'lucide-vue-next'
import StatCard from '@/components/ui/StatCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import WelcomeBanner from '@/components/ui/WelcomeBanner.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import { api, errorMessage } from '@/lib/api'
import { formatDate, formatRelative } from '@/lib/format'
import { authState } from '@/stores/auth'
import { notifState, fetchNotifications } from '@/stores/notifications'
import { toast } from '@/stores/toast'

const loading = ref(true)
const stats = ref(null)
const recent = ref([])

/** Daftar anggota — data nyata dari GET /admin/users (halaman pertama). */
const members = ref([])
const membersLoading = ref(true)
/**
 * Total anggota sebenarnya, dari pagination.total. Endpoint mengirim 15 per
 * halaman, jadi members.length BUKAN jumlah anggota — menampilkannya sebagai
 * jumlah akan menyesatkan begitu anggota lebih dari 15.
 */
const membersTotal = ref(0)

const admin = computed(() => authState.user)

/** Jumlah yang butuh tindakan admin — dipakai di banner. */
const needsAction = computed(
  () =>
    (Number(stats.value?.waiting_verification) || 0) +
    (Number(stats.value?.pending_license) || 0)
)

/** Pil banner. Sejajar dengan pil kemajuan lisensi di dashboard user. */
const queueLabel = computed(() =>
  needsAction.value > 0 ? `${needsAction.value} antrean verifikasi` : 'Antrean bersih'
)

/**
 * Aktivitas untuk kolom kanan. Sumbernya GET /notifications — endpoint yang
 * sama dengan lonceng di topbar, jadi tidak ada permintaan tambahan ke server.
 * Tidak dipotong di sini: kartunya menampilkan tiga baris lalu digulir, jadi
 * memotong daftar hanya akan menyembunyikan sisanya tanpa alasan.
 */
const recentActivity = computed(() => notifState.items)

/**
 * Transaksi yang ditampilkan. Endpoint mengirim sepuluh baris; kartunya hanya
 * memuat lima supaya tidak perlu digulir di dalam kartu.
 *
 * Lima adalah tinggi yang membuat kaki kolom utama jatuh paling dekat dengan
 * kolom kanan pada susunan sekarang. Selisihnya tidak nol, dan tidak bisa nol
 * tanpa menggulir — Forum masih empty state setinggi 138px, dan begitu backend
 * punya tabel forum kartu itu akan tumbuh dan menutup sisanya sendiri.
 * Sepuluh baris penuh tetap bisa dilihat di halaman Transaksi.
 */
const VISIBLE_TX = 5
const visibleRecent = computed(() => recent.value.slice(0, VISIBLE_TX))

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/dashboard')
    stats.value = data.stats
    recent.value = data.recent_transactions || []
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat dashboard'))
  } finally {
    loading.value = false
  }
}

/**
 * Anggota diambil dari endpoint yang sudah ada. Tidak ada data contoh:
 * bila permintaan gagal, daftar dibiarkan kosong dan empty state yang tampil.
 */
async function loadMembers() {
  membersLoading.value = true
  try {
    const { data } = await api.get('/admin/users')
    members.value = data.users || []
    membersTotal.value = Number(data.pagination?.total) || members.value.length
  } catch {
    members.value = []
    membersTotal.value = 0
  } finally {
    membersLoading.value = false
  }
}

onMounted(() => {
  load()
  loadMembers()
  fetchNotifications()
})
</script>

<template>
  <!-- Tata letak mengikuti dashboard user: kolom kanan berjalan dari baris
       paling atas, bukan menyusul di bawah banner. Banner ikut menyempit ke
       lebar kolom utama, jadi ia tidak lagi membentang sampai tepi kanan. -->
  <div class="dash">
    <div class="dash-main">
    <!-- ---------- Banner sambutan (komponen yang sama dengan dashboard user) -->
    <WelcomeBanner :pill="queueLabel">
      <template #title>
        Halo,<br>
        <span>{{ admin?.name }}</span>
      </template>

      <template #sub>
        <template v-if="needsAction > 0">
          Ada <strong>{{ needsAction }}</strong> transaksi yang menunggu tindakan Anda.
        </template>
        <template v-else>
          Semua transaksi sudah tertangani. Tidak ada antrean verifikasi.
        </template>
      </template>

      <template #actions>
        <RouterLink to="/admin/transactions" class="btn btn-primary">
          <Receipt :size="17" />
          Verifikasi Transaksi
        </RouterLink>
        <RouterLink to="/admin/users" class="btn btn-secondary">
          <Users :size="16" />
          Kelola User
        </RouterLink>
      </template>
    </WelcomeBanner>

    <!-- ---------- Statistik transaksi ---------- -->
    <section class="grid stats">
      <StatCard
        label="Menunggu Verifikasi"
        :value="Number(stats?.waiting_verification) || 0"
        :icon="Clock"
        tone="tone-warn"
        hint="Perlu ditinjau"
        to="/admin/transactions"
      />
      <StatCard
        label="Disetujui"
        :value="Number(stats?.approved) || 0"
        :icon="CircleCheck"
        tone="tone-ok"
        hint="Lisensi terbit"
        to="/admin/transactions"
      />
      <StatCard
        label="Ditolak"
        :value="Number(stats?.rejected) || 0"
        :icon="CircleX"
        tone="tone-err"
        hint="Perlu unggah ulang"
        to="/admin/transactions"
      />
      <StatCard
        label="Pending Lisensi"
        :value="Number(stats?.pending_license) || 0"
        :icon="ServerCrash"
        tone="tone-neutral"
        hint="Server lisensi offline"
        to="/admin/transactions"
      />
    </section>

      <!-- ---------- Anggota ----------
           Duduk di kolom utama, tepat di bawah empat kartu ringkasan, sebagai
           deretan avatar mendatar. Di kolom kanan yang selebar 312px daftar ini
           hanya muat satu nama per baris dan memanjang ke bawah; di sini lebar
           kolom utama dipakai untuk memperlihatkan banyak anggota sekaligus. -->
      <section class="card">
        <header class="card-header">
          <h3>
            <span class="icon-chip icon-chip-sm tone-sky"><Users :size="16" /></span>
            Anggota
            <span v-if="membersTotal" class="badge tone-neutral">{{ membersTotal }}</span>
          </h3>
          <RouterLink to="/admin/users" class="link-more">
            Kelola <ArrowRight :size="14" />
          </RouterLink>
        </header>

        <SkeletonRows v-if="membersLoading" :rows="1" :height="104" />

        <template v-else-if="members.length">
          <ul class="strip">
            <li v-for="m in members" :key="m.id" class="strip-item">
              <span class="strip-face" :class="{ 'is-on': Number(m.is_active) }">
                <span class="avatar avatar-lg"><UserAvatar :name="m.name" :seed="m.email" /></span>
                <span
                  class="strip-dot"
                  :class="{ 'is-on': Number(m.is_active) }"
                  :title="Number(m.is_active) ? 'Aktif' : 'Nonaktif'"
                />
              </span>
              <!-- Email dipasang sebagai title, bukan baris kedua: pada lebar
                   104px alamat email selalu terpotong jadi beberapa huruf dan
                   tidak memberi informasi apa pun. -->
              <span class="strip-name" :title="m.email">{{ m.name }}</span>
              <span v-if="m.role === 'admin'" class="badge tone-brand">Admin</span>
            </li>
          </ul>

          <!-- Endpoint hanya mengirim halaman pertama (15 baris). Mengatakannya
               terus terang lebih baik daripada membiarkan daftar yang terpotong
               terlihat seperti daftar lengkap. -->
          <p v-if="members.length < membersTotal" class="members-note">
            {{ members.length }} teratas dari {{ membersTotal }} anggota
          </p>
        </template>

        <EmptyState
          v-else
          :icon="Users"
          title="Belum ada anggota"
          description="Daftar pengguna akan tampil di sini."
          compact
        />
      </section>

      <!-- ---------- Transaksi terbaru ---------- -->
      <section class="card">
        <header class="card-header">
          <h3>
            <span class="icon-chip icon-chip-sm tone-brand"><Receipt :size="16" /></span>
            Transaksi Terbaru
          </h3>
          <RouterLink to="/admin/transactions" class="link-more">
            Lihat semua <ArrowRight :size="14" />
          </RouterLink>
        </header>

        <SkeletonRows v-if="loading" :rows="5" />

        <div v-else-if="visibleRecent.length" class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>User</th>
                <th>Status</th>
                <th>Diajukan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in visibleRecent" :key="t.id">
                <td>
                  <span class="user-cell">
                    <span class="avatar avatar-sm"><UserAvatar :name="t.user_name" :seed="t.user_email" /></span>
                    <span class="user-cell-text">
                      <span class="cell-strong">{{ t.user_name || 'User dihapus' }}</span>
                      <span class="text-caption">{{ t.user_email || '—' }}</span>
                    </span>
                  </span>
                </td>
                <td><StatusBadge :status="t.status" /></td>
                <td>{{ formatDate(t.created_at) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <EmptyState
          v-else
          :icon="Receipt"
          title="Belum ada transaksi"
          description="Pengajuan dari user akan tampil di sini."
        />
      </section>
    </div>

    <!-- ---------- Kolom kanan ---------- -->
    <aside class="dash-rail">
      <!-- Forum — sama seperti di dashboard user: backend belum punya tabel
           maupun endpoint forum, jadi yang tampil empty state yang jujur,
           bukan daftar contoh. -->
      <section class="card">
        <header class="card-header">
          <h3>
            <span class="icon-chip icon-chip-sm tone-sky"><MessagesSquare :size="16" /></span>
            Forum
          </h3>
        </header>

        <!-- inline, sama seperti di dashboard user: kartu yang backend-nya
             memang belum ada tidak perlu memakan tinggi kolom kanan. -->
        <EmptyState
          :icon="MessagesSquare"
          title="Belum ada forum"
          description="Fitur forum belum tersedia di server."
          inline
        />
      </section>

      <!-- Aktivitas — GET /notifications, endpoint yang sama dengan lonceng di
           topbar, jadi tidak ada permintaan baru ke server. Tempatnya di kolom
           kanan, sejajar dengan Aktivitas di dashboard user. -->
      <section class="card">
        <header class="card-header">
          <h3>
            <span class="icon-chip icon-chip-sm tone-lilac"><Activity :size="16" /></span>
            Aktivitas
          </h3>
        </header>

        <SkeletonRows v-if="notifState.loading && !recentActivity.length" :rows="3" :height="40" />

        <ul v-else-if="recentActivity.length" class="feed scroll-area">
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
      </section>

      <section class="card">
        <header class="card-header">
          <h3>
            <span class="icon-chip icon-chip-sm tone-neutral">
              <LayoutTemplate :size="16" />
            </span>
            Katalog
          </h3>
        </header>

        <!-- Empat angka dalam kisi 2×2, masing-masing di kotaknya sendiri. -->
        <div class="card-body catalog">
          <div class="catalog-cell">
            <span class="catalog-label">Total template</span>
            <strong>{{ Number(stats?.total_templates) || 0 }}</strong>
          </div>
          <div class="catalog-cell">
            <span class="catalog-label">Total user</span>
            <strong>{{ Number(stats?.total_users) || 0 }}</strong>
          </div>
          <div class="catalog-cell">
            <span class="catalog-label">Standard</span>
            <strong>{{ Number(stats?.standard_templates) || 0 }}</strong>
          </div>
          <div class="catalog-cell">
            <span class="catalog-label">Premium</span>
            <strong>{{ Number(stats?.premium_templates) || 0 }}</strong>
          </div>
        </div>
      </section>

      <section class="card">
        <header class="card-header">
          <h3>
            <span class="icon-chip icon-chip-sm tone-neutral"><Zap :size="16" /></span>
            Pintasan
          </h3>
        </header>

        <div class="card-body shortcuts">
          <RouterLink to="/admin/users" class="shortcut">
            <span class="icon-chip icon-chip-sm tone-brand"><UserPlus :size="16" /></span>
            Kelola User
            <ArrowRight class="shortcut-arrow" :size="15" />
          </RouterLink>
          <RouterLink to="/admin/plugins" class="shortcut">
            <span class="icon-chip icon-chip-sm tone-neutral"><Package :size="16" /></span>
            Kelola Plugin
            <ArrowRight class="shortcut-arrow" :size="15" />
          </RouterLink>
          <RouterLink to="/admin/templates" class="shortcut">
            <span class="icon-chip icon-chip-sm tone-neutral">
              <LayoutTemplate :size="16" />
            </span>
            Kelola Template
            <ArrowRight class="shortcut-arrow" :size="15" />
          </RouterLink>
        </div>
      </section>
    </aside>
  </div>
</template>

<style scoped>
/* ---------- Grid ----------
   Struktur dan ambang batasnya sengaja sama persis dengan dashboard user:
   dua kolom sejak baris pertama, sehingga banner dan kolom kanan berangkat
   dari garis atas yang sama.

   minmax(0, 1fr) — bukan 1fr — supaya kolom boleh menyusut lebih kecil dari
   konten terlebarnya. Tanpa ini tabel dengan min-width memaksa grid melebar
   dan seluruh halaman ikut scroll horizontal di layar kecil. */
.dash {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--sp-4);
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

/* Empat kartu ringkasan tetap muat berjajar di dalam kolom utama yang kini
   lebih sempit — 180px sama dengan ambang di dashboard user. */
.stats {
  grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr));
}

/* ---------- Sel user ---------- */
.user-cell {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}
.user-cell-text {
  display: flex;
  flex-direction: column;
  line-height: 1.3;
  min-width: 0;
}

.link-more {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  font-size: var(--fs-caption);
  font-weight: var(--fw-semibold);
  color: var(--brand);
}
.link-more:hover {
  color: var(--brand-hover);
}

/* ---------- Katalog ---------- */
.catalog {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: var(--sp-3);
}

/* Tiap angka duduk di kotaknya sendiri, radius dan border 1px yang sama dengan
   kontrol lain. Sebelumnya empat angka mengambang tanpa batas dan terbaca
   seperti daftar yang belum selesai ditata. */
.catalog-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  padding: var(--sp-3) var(--sp-4);
  border: 1px solid var(--border);
  border-radius: var(--r);
  background: var(--bg-subtle);
}
.catalog-label {
  font-size: var(--fs-micro);
  color: var(--text-muted);
}
.catalog-cell strong {
  font-size: var(--fs-h2);
  font-weight: var(--fw-bold);
  line-height: var(--lh-tight);
  color: var(--text-heading);
}

/* ---------- Aktivitas ----------
   Tiga baris lalu digulir. Satu baris terukur 110px (padding 12px atas-bawah,
   judul, pesan yang dipotong dua baris, dan waktu), jadi batasnya 330px.
   Daftarnya sendiri tidak dipotong di JavaScript — menggulir memperlihatkan
   sisanya, bukan menyembunyikannya. */
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

/* ---------- Pintasan ---------- */
.shortcuts {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}
.shortcut {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-3);
  border-radius: var(--r);
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  color: var(--text-body);
  transition: background var(--dur) var(--ease), color var(--dur) var(--ease);
}
.shortcut:hover {
  background: var(--bg-hover);
  color: var(--text-heading);
}
.shortcut-arrow {
  margin-left: auto;
  color: var(--text-faint);
}

/* ---------- Responsif ---------- */
@media (max-width: 640px) {
  .card-body {
    padding: var(--sp-4);
  }
}

/* ---------- Anggota ----------
   Deretan avatar mendatar, mengikuti referensi. Bergulir ke samping, bukan ke
   bawah: kartunya tetap setinggi satu baris berapa pun jumlah anggotanya,
   jadi kolom utama tidak lagi terseret memanjang seperti waktu daftar ini
   masih berupa baris bertumpuk di kolom kanan. */
.strip {
  display: flex;
  gap: var(--sp-3);
  padding: var(--sp-4) var(--sp-5);
  overflow-x: auto;
  overscroll-behavior-x: contain;
  /* Batang scrollbar mendatar memotong kaki kartu dan terbaca sebagai garis
     nyasar. Avatar yang terpotong di tepi kanan sudah cukup memberi tahu
     bahwa daftarnya masih berlanjut. Menggulir tetap bisa — dengan roda,
     usap, maupun keyboard. */
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.strip::-webkit-scrollbar {
  display: none;
}

.strip-item {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-2);
  /* Lebar tetap supaya jarak antar-avatar rata berapa pun panjang namanya. */
  width: 96px;
  flex-shrink: 0;
  text-align: center;
}

/* Cincin ala story: border 2px dengan jarak 3px ke avatar. Dibuat dari border
   pada pembungkus, bukan box-shadow, supaya tetap satu bahasa dengan kontrol
   lain yang membedakan state lewat border 1–2px dan tidak pernah lewat
   bayangan.

   Warnanya bukan hiasan — cincin brand berarti anggota aktif, cincin netral
   berarti nonaktif. Itu keadaan nyata dari kolom is_active, sama dengan yang
   dinyatakan titik di sudut avatar. */
.strip-face {
  position: relative;
  display: block;
  padding: 3px;
  border-radius: var(--r-pill);
  border: 2px solid var(--border-strong);
  transition: border-color var(--dur) var(--ease);
}
.strip-face.is-on {
  border-color: var(--brand-border);
}
.strip-item:hover .strip-face.is-on {
  border-color: var(--brand);
}

.strip-dot {
  position: absolute;
  right: 2px;
  bottom: 2px;
  width: 12px;
  height: 12px;
  border-radius: var(--r-pill);
  background: var(--n-400);
  border: 2px solid var(--bg-surface);
}
.strip-dot.is-on {
  background: var(--ok);
}

/* Nama boleh turun ke baris kedua, lalu dipotong. Dipaksa satu baris, tujuh
   dari lima belas nama terukur terpotong — termasuk nama sependek "Rangga
   Pratama". min-height dua baris membuat semua kartu tetap rata tingginya
   meski namanya cuma satu kata. */
.strip-name {
  max-width: 100%;
  font-size: var(--fs-caption);
  font-weight: var(--fw-medium);
  line-height: var(--lh-snug);
  color: var(--text-heading);
  min-height: calc(var(--fs-caption) * var(--lh-snug) * 2);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  overflow-wrap: anywhere;
}

.members-note {
  padding: var(--sp-3) var(--sp-5);
  border-top: 1px solid var(--border);
  font-size: var(--fs-caption);
  color: var(--text-muted);
}
</style>
