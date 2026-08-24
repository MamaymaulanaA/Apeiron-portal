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
const stats = ref(null)
const recent = ref([])

/** Daftar anggota — data nyata dari GET /admin/users (halaman pertama). */
const members = ref([])
const membersLoading = ref(true)

const admin = computed(() => authState.user)

/** Jumlah yang butuh tindakan admin — dipakai di hero. */
const needsAction = computed(
  () =>
    (Number(stats.value?.waiting_verification) || 0) +
    (Number(stats.value?.pending_license) || 0)
)

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
  } catch {
    members.value = []
  } finally {
    membersLoading.value = false
  }
}

onMounted(() => {
  load()
  loadMembers()
})
</script>

<template>
  <div class="page">
    <!-- ---------- Hero ---------- -->
    <section class="hero">
      <div class="hero-text">
        <p class="hero-eyebrow">Panel Admin</p>
        <h1 class="hero-title">Halo, <span>{{ admin?.name }}</span></h1>
        <p class="hero-sub">
          <template v-if="needsAction > 0">
            Ada <strong>{{ needsAction }}</strong> transaksi yang menunggu tindakan Anda.
          </template>
          <template v-else>
            Semua transaksi sudah tertangani. Tidak ada antrean verifikasi.
          </template>
        </p>

        <div class="hero-actions">
          <RouterLink to="/admin/transactions" class="btn btn-primary">
            <Receipt :size="17" />
            Verifikasi Transaksi
          </RouterLink>
          <RouterLink to="/admin/users" class="btn btn-secondary">
            <Users :size="16" />
            Kelola User
          </RouterLink>
        </div>
      </div>
    </section>

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

          <div class="card-body catalog">
            <div class="catalog-row">
              <span>Total template</span>
              <strong>{{ Number(stats?.total_templates) || 0 }}</strong>
            </div>
            <div class="catalog-row">
              <span>Standard</span>
              <strong>{{ Number(stats?.standard_templates) || 0 }}</strong>
            </div>
            <div class="catalog-row">
              <span>Premium</span>
              <strong>{{ Number(stats?.premium_templates) || 0 }}</strong>
            </div>
            <div class="catalog-row">
              <span>Total user</span>
              <strong>{{ Number(stats?.total_users) || 0 }}</strong>
            </div>
          </div>
        </section>

        <section class="card">
          <header class="card-header">
            <h3>Pintasan</h3>
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
        <section class="card">
          <header class="card-header">
            <h2>Anggota</h2>
            <RouterLink to="/admin/users" class="link-more">
              Kelola <ArrowRight :size="14" />
            </RouterLink>
          </header>

          <SkeletonRows v-if="membersLoading" :rows="3" :height="40" />

          <ul v-else-if="members.length" class="members">
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
/* ---------- Hero ---------- */
.hero {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-5);
  padding: var(--sp-5);
  border-radius: var(--r);
  background: var(--bg-surface);
  border: 1px solid var(--border);
  box-shadow: var(--sh-card);
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
  color: var(--text-muted);
  margin-bottom: var(--sp-2);
}
.hero-title {
  font-size: var(--fs-h1);
  margin-bottom: var(--sp-1);
}
.hero-title span {
  color: var(--brand);
}
.hero-sub {
  color: var(--text-body);
  max-width: 52ch;
}
.hero-sub strong {
  color: var(--brand);
  font-weight: var(--fw-bold);
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-2);
  margin-top: var(--sp-4);
}


/* ---------- Grid ---------- */
.stats {
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
}

/* minmax(0, 1fr) — bukan 1fr — supaya kolom boleh menyusut lebih kecil dari
   konten terlebarnya. Tanpa ini tabel dengan min-width memaksa grid melebar
   dan seluruh halaman ikut scroll horizontal di layar kecil. */
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
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
}
.catalog-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding-bottom: var(--sp-3);
  border-bottom: 1px solid var(--border);
  font-size: var(--fs-label);
  color: var(--text-body);
}
.catalog-row:last-child {
  border-bottom: none;
  padding-bottom: 0;
}
.catalog-row strong {
  font-size: var(--fs-body-lg);
  font-weight: var(--fw-bold);
  color: var(--text-heading);
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
@media (max-width: 900px) {
  .hero {
    flex-direction: column;
    align-items: flex-start;
  }
}

@media (max-width: 640px) {
  .hero,
  .card-body {
    padding: var(--sp-4);
  }
  .hero-actions {
    width: 100%;
  }
  .hero-actions .btn {
    flex: 1;
  }
}

/* ---------- Anggota ---------- */
.members {
  display: flex;
  flex-direction: column;
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
