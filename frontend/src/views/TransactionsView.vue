<script setup>
/**
 * Riwayat transaksi user — GET /user/transactions.
 * Hanya menampilkan; tidak ada aksi yang mengubah data di halaman ini.
 */
import { ref, onMounted } from 'vue'
import { Receipt, FileText } from 'lucide-vue-next'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import { api, errorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { toast } from '@/stores/toast'

const loading = ref(true)
const items = ref([])

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/user/transactions')
    items.value = data.transactions || []
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat riwayat transaksi'))
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>Riwayat Transaksi</h1>
        <p class="text-muted">Daftar pengajuan bukti transaksi Anda.</p>
      </div>
    </header>

    <section class="card">
      <SkeletonRows v-if="loading" :rows="4" />

      <EmptyState
        v-else-if="!items.length"
        :icon="Receipt"
        title="Belum ada transaksi"
        description="Riwayat akan muncul setelah Anda mengunggah bukti transaksi."
      >
        <template #action>
          <RouterLink to="/dashboard" class="btn btn-primary">
            Ke Dashboard
          </RouterLink>
        </template>
      </EmptyState>

      <!-- Tabel untuk layar lega -->
      <div v-else class="table-wrap desktop-only">
        <table class="table">
          <thead>
            <tr>
              <th>Dokumen</th>
              <th>Status</th>
              <th>Diajukan</th>
              <th>Diverifikasi</th>
              <th>Catatan</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in items" :key="t.id">
              <td>
                <span class="doc">
                  <span class="icon-chip icon-chip-sm tone-brand"><FileText :size="15" /></span>
                  <span class="cell-strong">{{ t.document_name || '-' }}</span>
                </span>
              </td>
              <td><StatusBadge :status="t.status" :label="t.status_label" /></td>
              <td>{{ formatDate(t.created_at) }}</td>
              <td>{{ t.verified_at ? formatDate(t.verified_at) : '—' }}</td>
              <td class="notes">{{ t.admin_notes || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Kartu untuk layar kecil -->
      <div v-if="!loading && items.length" class="tx-cards mobile-only">
        <article v-for="t in items" :key="t.id" class="tx-card">
          <header class="row-between">
            <span class="row">
              <span class="icon-chip icon-chip-sm tone-brand"><FileText :size="15" /></span>
              <span class="cell-strong">{{ t.document_name || '-' }}</span>
            </span>
            <StatusBadge :status="t.status" :label="t.status_label" />
          </header>

          <dl class="tx-meta">
            <div>
              <dt>Diajukan</dt>
              <dd>{{ formatDate(t.created_at) }}</dd>
            </div>
            <div>
              <dt>Diverifikasi</dt>
              <dd>{{ t.verified_at ? formatDate(t.verified_at) : '—' }}</dd>
            </div>
          </dl>

          <p v-if="t.admin_notes" class="tx-note">{{ t.admin_notes }}</p>
        </article>
      </div>
    </section>
  </div>
</template>

<style scoped>
.doc {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}

.notes {
  max-width: 320px;
  color: var(--text-muted);
  font-size: var(--fs-label);
}

/* ---- Kartu mobile ---- */
.tx-cards {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  padding: var(--sp-4);
}

.tx-card {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  padding: var(--sp-4);
  border: 1px solid var(--border);
  border-radius: var(--r);
}

.tx-card header {
  flex-wrap: wrap;
  gap: var(--sp-2);
}

.tx-meta {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--sp-3);
}
.tx-meta dt {
  font-size: var(--fs-micro);
  font-weight: var(--fw-semibold);
  letter-spacing: var(--tracking-wide);
  text-transform: uppercase;
  color: var(--text-muted);
}
.tx-meta dd {
  font-size: var(--fs-caption);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
}

.tx-note {
  padding: var(--sp-2) var(--sp-3);
  background: var(--warn-soft);
  border-radius: var(--r-sm);
  font-size: var(--fs-caption);
  color: var(--text-body);
}

/* ---- Peralihan tampilan ---- */
.mobile-only {
  display: none;
}
@media (max-width: 860px) {
  .desktop-only {
    display: none;
  }
  .mobile-only {
    display: flex;
  }
}
</style>
