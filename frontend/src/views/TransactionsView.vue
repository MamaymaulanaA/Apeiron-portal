<script setup>
/**
 * Halaman Transaksi.
 *
 * Dua bagian:
 *   1. Panel pengajuan — unggah bukti, atau keterangan menunggu verifikasi.
 *   2. Riwayat seluruh pengajuan.
 *
 * Memakai dua endpoint yang sudah ada:
 *   GET /user/transactions → daftar riwayat
 *   GET /user/dashboard    → transaksi aktif + flag can_upload
 *
 * can_upload sengaja diambil dari backend, bukan disimpulkan dari daftar
 * riwayat, supaya aturan siapa yang boleh mengunggah tetap satu sumber.
 */
import { ref, computed, onMounted } from 'vue'
import { Receipt, FileText,
  Search,
} from 'lucide-vue-next'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import SubmissionPanel from '@/components/transaction/SubmissionPanel.vue'
import { api, errorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { toast } from '@/stores/toast'

const loading = ref(true)
const items = ref([])

/* Riwayat transaksi user dimuat utuh dari /user/transactions, jadi menyaring
   di klien menelusuri seluruh datanya — bukan sebagian yang kebetulan tampil. */
const search = ref('')
const statusFilter = ref('all')

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return items.value.filter((t) => {
    if (statusFilter.value !== 'all' && t.status !== statusFilter.value) return false
    if (!q) return true
    return (
      String(t.status_label || '').toLowerCase().includes(q) ||
      String(t.document_name || '').toLowerCase().includes(q) ||
      String(t.admin_notes || '').toLowerCase().includes(q) ||
      String(t.created_at || '').toLowerCase().includes(q)
    )
  })
})

const active = ref(null)
const canUpload = ref(false)
const hasLicense = ref(false)

/** Panel hanya relevan selama pengajuan belum disetujui. */
const showPanel = computed(
  () => !hasLicense.value && active.value?.status !== 'approved'
)

async function load() {
  loading.value = true
  try {
    const [list, dash] = await Promise.all([
      api.get('/user/transactions'),
      api.get('/user/dashboard'),
    ])
    items.value = list.data.transactions || []
    active.value = dash.data.transaction || null
    canUpload.value = !!dash.data.can_upload
    hasLicense.value = !!dash.data.license
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

    <div v-if="!loading && items.length" class="control-row">
      <div class="input-group toolbar-search">
        <Search class="input-icon" :size="16" />
        <input v-model="search" class="input" type="search" placeholder="Cari status, berkas, atau tanggal…">
      </div>

      <select v-model="statusFilter" class="select toolbar-select">
        <option value="all">Semua status</option>
        <option value="waiting_verification">Menunggu Verifikasi</option>
        <option value="approved">Disetujui</option>
        <option value="rejected">Ditolak</option>
        <option value="pending_license">Pending Lisensi</option>
      </select>
    </div>

    <SubmissionPanel
      v-if="!loading && showPanel"
      :transaction="active"
      :can-upload="canUpload"
      @uploaded="load"
    />

    <section class="card">
      <header class="card-header">
        <h2>Riwayat pengajuan</h2>
      </header>

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
      <EmptyState
        v-else-if="!filtered.length"
        :icon="Search"
        title="Tidak ada yang cocok"
        description="Coba kata kunci lain atau ubah saringan status."
      />

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
            <tr v-for="t in filtered" :key="t.id">
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
      <div v-if="!loading && filtered.length" class="tx-cards mobile-only">
        <article v-for="t in filtered" :key="t.id" class="tx-card">
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

/* ---------- Toolbar ----------
   Bentuknya sama dengan halaman Panduan. .toolbar-select WAJIB menimpa
   width: 100% milik .select — tanpa itu select memakan satu baris penuh dan
   kontrolnya menumpuk ke bawah. */
.toolbar-search {
  flex: 1;
  min-width: 160px;
}
.toolbar-select {
  width: auto;
  min-width: 180px;
}

@media (max-width: 640px) {
  .toolbar-search,
  .toolbar-select {
    flex: 1 1 100%;
    width: 100%;
  }
}
</style>
