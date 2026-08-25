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
} from 'lucide-vue-next'
import StatCard from '@/components/ui/StatCard.vue'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import WelcomeBanner from '@/components/ui/WelcomeBanner.vue'
import { api, errorMessage } from '@/lib/api'
import { formatDate, formatRelative, initials } from '@/lib/format'
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
 * Aktivitas untuk kolom utama. Sumbernya GET /notifications — endpoint yang
 * sama dengan lonceng di topbar, jadi tidak ada permintaan tambahan ke server
 * yang belum ada. Dipotong 6 supaya kartunya tetap terbaca sekali pandang.
 */
const recentActivity = computed(() => notifState.items.slice(0, 6))

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
  <div class="page">
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

    <!-- ---------- Transaksi terbaru + rail ---------- -->
    <div class="split">
      <div class="stack main-col">
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

        <div v-else-if="recent.length" class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>User</th>
                <th>Status</th>
                <th>Diajukan</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in recent" :key="t.id">
                <td>
                  <span class="user-cell">
                    <span class="avatar avatar-sm">{{ initials(t.user_name) }}</span>
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

        <!-- Aktivitas — GET /notifications, endpoint yang sama dengan lonceng
             di topbar. Kartu kedua di kolom utama ini juga yang membuat kaki
             kedua kolom bertemu di garis yang sama. -->
        <section class="card">
          <header class="card-header">
            <h3>
              <span class="icon-chip icon-chip-sm tone-lilac"><Activity :size="16" /></span>
              Aktivitas
            </h3>
          </header>

          <SkeletonRows v-if="notifState.loading && !recentActivity.length" :rows="3" :height="40" />

          <ul v-else-if="recentActivity.length" class="feed">
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
      </div>

      <!-- Rail: ringkasan katalog + pintasan -->
      <aside class="stack rail">
        <section class="card">
          <header class="card-header">
            <h3>
              <span class="icon-chip icon-chip-sm tone-neutral">
                <LayoutTemplate :size="16" />
              </span>
              Katalog
            </h3>
          </header>

          <!-- Empat angka dalam kisi 2×2, bukan empat baris bertumpuk. Isinya
               sama persis, tingginya turun dari 279px ke sekitar 195px — kolom
               kanan jadi tidak menjulang jauh melewati kolom utama. -->
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

        <!-- Anggota — seluruhnya data nyata dari GET /admin/users -->
        <section class="card members-card">
          <header class="card-header">
            <h3>
              <span class="icon-chip icon-chip-sm tone-neutral"><Users :size="16" /></span>
              Anggota
              <span v-if="membersTotal" class="badge tone-neutral">{{ membersTotal }}</span>
            </h3>
            <RouterLink to="/admin/users" class="link-more">
              Kelola <ArrowRight :size="14" />
            </RouterLink>
          </header>

          <SkeletonRows v-if="membersLoading" :rows="3" :height="40" />

          <template v-else-if="members.length">
            <ul class="members scroll-area">
              <li v-for="m in members" :key="m.id" class="member">
                <span class="avatar avatar-sm">{{ initials(m.name) }}</span>

                <span class="member-text">
                  <span class="member-name">{{ m.name }}</span>
                  <span class="member-mail">{{ m.email }}</span>
                </span>

                <span class="member-tags">
                  <span v-if="m.role === 'admin'" class="badge tone-brand">Admin</span>
                  <span
                    class="status-dot"
                    :class="{ 'is-on': Number(m.is_active) }"
                    :title="Number(m.is_active) ? 'Aktif' : 'Nonaktif'"
                  />
                </span>
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
      </aside>
    </div>
  </div>
</template>

<style scoped>
/* ---------- Grid ---------- */
.stats {
  grid-template-columns: repeat(auto-fit, minmax(min(220px, 100%), 1fr));
}

/* minmax(0, 1fr) — bukan 1fr — supaya kolom boleh menyusut lebih kecil dari
   konten terlebarnya. Tanpa ini tabel dengan min-width memaksa grid melebar
   dan seluruh halaman ikut scroll horizontal di layar kecil. */
/* align-items dibiarkan pada nilai bawaan (stretch), bukan start. Dengan
   stretch kedua kolom mendapat tinggi baris yang sama, sehingga kartu terakhir
   di kolom kanan bisa memanjang mengisi sisa ruang dan kaki kedua kolom
   bertemu di garis yang sama. */
.split {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--sp-6);
}
@media (min-width: 1200px) {
  .split {
    grid-template-columns: minmax(0, 1fr) var(--rail-w);
  }
}

/* Keduanya sudah .stack (flex kolom); yang perlu ditambah hanya izin menyusut
   di bawah lebar konten terlebarnya. */
.main-col,
.rail {
  min-width: 0;
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

/* ---------- Aktivitas ---------- */
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

/* ---------- Anggota ---------- */

/* Kartu terakhir di kolom kanan. flex: 1 membuatnya memakan sisa tinggi kolom,
   yang di layar lebar berarti kakinya berhenti tepat sejajar dengan kolom
   utama. overflow: hidden menahan sudut daftar agar tidak menyembul keluar
   sudut membulat kartu. */
.members-card {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  min-height: 0;
  overflow: hidden;
}
.members-card .card-header,
.members-note {
  flex-shrink: 0;
}

/* GET /admin/users mengirim 15 baris. Dibiarkan apa adanya, kartu ini setinggi
   15 × 61px = 915px dan kolom kanan menjulur jauh melewati kolom utama —
   persis keluhan "anggota kebawah semua".

   Sekarang daftarnya tumbuh mengikuti sisa tinggi kolom, dengan lantai 4,5
   baris supaya tetap terbaca kalau kolom utama kebetulan pendek. Angka 61px
   adalah tinggi baris terukur (avatar 28px, dua baris teks, padding 12px
   atas-bawah, border 1px); setengah baris membuat baris berikutnya terpotong,
   isyarat bahwa daftar masih bisa digulir.

   flex-basis 0 — bukan auto — supaya tinggi 15 baris TIDAK ikut dihitung
   sebagai tinggi alami kolom. Dengan basis auto daftar ini menyeret kolom
   kanan kembali ke 1029px dan seluruh halaman ikut memanjang; dengan basis 0
   daftar hanya memakan sisa ruang yang benar-benar tersedia. */
.members {
  display: flex;
  flex-direction: column;
  flex: 1 1 0;
  min-height: calc(61px * 4.5);
  overscroll-behavior: contain;
}

.members-note {
  padding: var(--sp-3) var(--sp-5);
  border-top: 1px solid var(--border);
  font-size: var(--fs-caption);
  color: var(--text-muted);
}

.member {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-5);
  border-bottom: 1px solid var(--border);
}
.member:last-child {
  border-bottom: none;
}

.member-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  flex: 1;
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

.member-tags {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  flex-shrink: 0;
}
</style>
