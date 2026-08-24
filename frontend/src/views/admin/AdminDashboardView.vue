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

onMounted(load)
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

      <div class="hero-side">
        <span class="hero-avatar">{{ initials(admin?.name) }}</span>
      </div>
    </section>

    <!-- ---------- Statistik transaksi ---------- -->
    <section class="grid stats">
      <StatCard
        label="Menunggu Verifikasi"
        :value="Number(stats?.waiting_verification) || 0"
        :icon="Clock"
        tone="tone-amber"
        hint="Perlu ditinjau"
        to="/admin/transactions"
      />
      <StatCard
        label="Disetujui"
        :value="Number(stats?.approved) || 0"
        :icon="CircleCheck"
        tone="tone-green"
        hint="Lisensi terbit"
        to="/admin/transactions"
      />
      <StatCard
        label="Ditolak"
        :value="Number(stats?.rejected) || 0"
        :icon="CircleX"
        tone="tone-red"
        hint="Perlu unggah ulang"
        to="/admin/transactions"
      />
      <StatCard
        label="Pending Lisensi"
        :value="Number(stats?.pending_license) || 0"
        :icon="ServerCrash"
        tone="tone-purple"
        hint="Server lisensi offline"
        to="/admin/transactions"
      />
    </section>

    <!-- ---------- Transaksi terbaru + rail ---------- -->
    <div class="split">
      <section class="card">
        <header class="card-header">
          <h3>
            <span class="icon-chip icon-chip-sm tone-blue"><Receipt :size="16" /></span>
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
              <span class="icon-chip icon-chip-sm tone-orange">
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
              <span class="icon-chip icon-chip-sm tone-blue"><UserPlus :size="16" /></span>
              Kelola User
              <ArrowRight class="shortcut-arrow" :size="15" />
            </RouterLink>
            <RouterLink to="/admin/plugins" class="shortcut">
              <span class="icon-chip icon-chip-sm tone-purple"><Package :size="16" /></span>
              Kelola Plugin
              <ArrowRight class="shortcut-arrow" :size="15" />
            </RouterLink>
            <RouterLink to="/admin/templates" class="shortcut">
              <span class="icon-chip icon-chip-sm tone-orange">
                <LayoutTemplate :size="16" />
              </span>
              Kelola Template
              <ArrowRight class="shortcut-arrow" :size="15" />
            </RouterLink>
          </div>
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
  gap: var(--sp-8);
  padding: var(--sp-7);
  border-radius: var(--r-xl);
  background: var(--grad-banner);
  border: 1px solid var(--c-primary-border);
  position: relative;
  overflow: hidden;
}
.hero::after {
  content: '';
  position: absolute;
  width: 320px;
  height: 320px;
  right: -60px;
  top: -80px;
  border-radius: 50%;
  background: radial-gradient(
    circle at 35% 35%,
    rgba(86, 204, 242, 0.35),
    rgba(47, 128, 237, 0.12) 55%,
    transparent 70%
  );
  pointer-events: none;
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
  font-size: var(--fs-display);
  margin-bottom: var(--sp-2);
}
.hero-title span {
  color: var(--c-primary);
}
.hero-sub {
  color: var(--text-body);
  max-width: 52ch;
}
.hero-sub strong {
  color: var(--c-primary);
  font-weight: var(--fw-bold);
}

.hero-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-3);
  margin-top: var(--sp-6);
}

.hero-side {
  position: relative;
  z-index: 1;
  flex-shrink: 0;
}
.hero-avatar {
  display: grid;
  place-items: center;
  width: 76px;
  height: 76px;
  border-radius: var(--r-pill);
  background: var(--bg-surface);
  border: 3px solid var(--c-primary-border);
  font-size: var(--fs-h2);
  font-weight: var(--fw-bold);
  color: var(--c-primary);
  box-shadow: var(--sh-sm);
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
  color: var(--c-primary);
}
.link-more:hover {
  color: var(--c-primary-hover);
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
  border-radius: var(--r-md);
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
    padding: var(--sp-6);
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
}
</style>
