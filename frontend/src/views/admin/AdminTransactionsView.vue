<script setup>
/**
 * Verifikasi transaksi — GET /admin/transactions?status=&page=
 *
 * Aksi dipertahankan apa adanya:
 *   POST /admin/transactions/{id}/approve        { notes? }
 *   POST /admin/transactions/{id}/reject         { notes }  ← wajib
 *   POST /admin/transactions/{id}/retry-license  (khusus pending_license)
 */
import { ref, computed, watch, onMounted } from 'vue'
import {
  Receipt,
  Eye,
  CircleCheck,
  CircleX,
  RefreshCw,
  FileText,
  ExternalLink,
  Search,
} from 'lucide-vue-next'
import StatusBadge from '@/components/ui/StatusBadge.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import ModalDialog from '@/components/ui/ModalDialog.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'
import { api, errorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { toast } from '@/stores/toast'

const FILTERS = [
  { key: '', label: 'Semua' },
  { key: 'waiting_verification', label: 'Menunggu' },
  { key: 'approved', label: 'Disetujui' },
  { key: 'rejected', label: 'Ditolak' },
  { key: 'pending_license', label: 'Pending Lisensi' },
]

const loading = ref(true)
const items = ref([])
const pagination = ref({ current_page: 1, per_page: 15, total: 0, total_pages: 1 })
const status = ref('')

/**
 * Pencarian dikirim ke server bersama filter status.
 *
 * Daftar ini berpaginasi 15 baris. Menyaring di klien hanya akan menelusuri
 * halaman yang sedang tampil, jadi mencari nama yang ada di halaman lima akan
 * menjawab "tidak ada" padahal ada. Endpoint menerima ?search= dan menghitung
 * paginasinya dengan saringan yang sama.
 */
const search = ref('')
let searchTimer = null

watch(search, () => {
  clearTimeout(searchTimer)
  // Menunggu ketikan berhenti; tanpa jeda satu kata berarti satu permintaan
  // per huruf.
  searchTimer = setTimeout(() => {
    page.value = 1
    load()
  }, 350)
})
const page = ref(1)

// Detail
const detailOpen = ref(false)
const detailLoading = ref(false)
const detail = ref(null)

// Aksi
const acting = ref(false)
const rejectOpen = ref(false)
const rejectNotes = ref('')
const approveNotes = ref('')

const isImage = computed(() => {
  const p = detail.value?.document_path || ''
  return /\.(jpe?g|png|gif|webp)$/i.test(p)
})

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/transactions', {
      params: {
        status: status.value || undefined,
        page: page.value,
        search: search.value.trim() || undefined,
      },
    })
    items.value = data.transactions || []
    pagination.value = data.pagination || pagination.value
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat transaksi'))
  } finally {
    loading.value = false
  }
}

function setFilter(key) {
  status.value = key
  page.value = 1
  load()
}

function changePage(p) {
  page.value = p
  load()
}

async function openDetail(id) {
  detailOpen.value = true
  detailLoading.value = true
  detail.value = null
  approveNotes.value = ''
  try {
    const { data } = await api.get(`/admin/transactions/${id}`)
    detail.value = data.transaction
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat detail transaksi'))
    detailOpen.value = false
  } finally {
    detailLoading.value = false
  }
}

async function approve() {
  if (!detail.value || acting.value) return
  acting.value = true
  try {
    const { data } = await api.post(`/admin/transactions/${detail.value.id}/approve`, {
      notes: approveNotes.value || undefined,
    })
    // Backend bisa menjawab sukses tapi status pending_license bila server
    // lisensi sedang tidak tersedia — sampaikan apa adanya.
    if (data.transaction_status === 'pending_license') {
      toast.warning(
        'Transaksi disetujui, tetapi lisensi belum terbit (server lisensi offline). Coba "Terbitkan Ulang" nanti.'
      )
    } else {
      toast.success(data.message || 'Transaksi disetujui')
    }
    detailOpen.value = false
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal menyetujui transaksi'))
  } finally {
    acting.value = false
  }
}

async function reject() {
  if (!detail.value || acting.value) return
  if (!rejectNotes.value.trim()) {
    toast.error('Alasan penolakan wajib diisi')
    return
  }
  acting.value = true
  try {
    const { data } = await api.post(`/admin/transactions/${detail.value.id}/reject`, {
      notes: rejectNotes.value.trim(),
    })
    toast.success(data.message || 'Transaksi ditolak')
    rejectOpen.value = false
    detailOpen.value = false
    rejectNotes.value = ''
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal menolak transaksi'))
  } finally {
    acting.value = false
  }
}

async function retryLicense(id) {
  if (acting.value) return
  acting.value = true
  try {
    const { data } = await api.post(`/admin/transactions/${id}/retry-license`)
    toast.success(data.message || 'Lisensi berhasil diterbitkan')
    detailOpen.value = false
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal menerbitkan lisensi'))
  } finally {
    acting.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>Verifikasi Transaksi</h1>
        <p class="text-muted">Tinjau dan proses bukti transaksi dari user.</p>
      </div>
    </header>

    <div v-if="!loading" class="control-row">
      <div class="input-group toolbar-search">
        <Search class="input-icon" :size="16" />
        <input v-model="search" class="input" type="search" placeholder="Cari nama atau email user…">
      </div>
    </div>

    <!-- Filter status -->
    <div class="filters scroll-area">
      <button
        v-for="f in FILTERS"
        :key="f.key"
        class="filter"
        :class="{ active: status === f.key }"
        type="button"
        @click="setFilter(f.key)"
      >
        {{ f.label }}
      </button>
    </div>

    <section class="card">
      <SkeletonRows v-if="loading" :rows="6" />

      <EmptyState
        v-else-if="!items.length"
        :icon="Receipt"
        title="Tidak ada transaksi"
        :description="
          status
            ? 'Tidak ada transaksi pada status ini.'
            : 'Belum ada pengajuan dari user.'
        "
      />

      <template v-else>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>User</th>
                <th>Dokumen</th>
                <th>Status</th>
                <th>Diajukan</th>
                <th style="text-align: right">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="t in items" :key="t.id">
                <td>
                  <span class="user-cell">
                    <span class="avatar avatar-sm"><UserAvatar :name="t.user_name" :seed="t.user_email" /></span>
                    <span class="user-cell-text">
                      <span class="cell-strong">{{ t.user_name || 'User dihapus' }}</span>
                      <span class="text-caption">{{ t.user_email || '—' }}</span>
                    </span>
                  </span>
                </td>
                <td class="doc-cell">{{ t.document_original_name || '—' }}</td>
                <td><StatusBadge :status="t.status" /></td>
                <td>{{ formatDate(t.created_at) }}</td>
                <td>
                  <span class="cell-actions">
                    <button
                      class="btn btn-sm btn-secondary"
                      type="button"
                      @click="openDetail(t.id)"
                    >
                      <Eye :size="14" />
                      Detail
                    </button>
                    <button
                      v-if="t.status === 'pending_license'"
                      class="btn btn-sm btn-primary"
                      type="button"
                      :disabled="acting"
                      @click="retryLicense(t.id)"
                    >
                      <RefreshCw :size="14" />
                      Terbitkan
                    </button>
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <PaginationBar :pagination="pagination" @change="changePage" />
      </template>
    </section>

    <!-- ---------- Modal detail ---------- -->
    <ModalDialog
      :open="detailOpen"
      title="Detail Transaksi"
      :subtitle="detail ? `#${detail.id} — ${detail.user_name || 'User dihapus'}` : ''"
      size="lg"
      @close="detailOpen = false"
    >
      <p v-if="detailLoading" class="text-muted">Memuat detail…</p>

      <div v-else-if="detail" class="detail">
        <dl class="detail-grid">
          <div>
            <dt>User</dt>
            <dd>{{ detail.user_name || 'User dihapus' }}</dd>
          </div>
          <div>
            <dt>Email</dt>
            <dd>{{ detail.user_email || '—' }}</dd>
          </div>
          <div>
            <dt>Status</dt>
            <dd><StatusBadge :status="detail.status" /></dd>
          </div>
          <div>
            <dt>Diajukan</dt>
            <dd>{{ formatDate(detail.created_at) }}</dd>
          </div>
          <div v-if="detail.approved_at">
            <dt>Disetujui</dt>
            <dd>{{ formatDate(detail.approved_at) }}</dd>
          </div>
          <div v-if="detail.rejected_at">
            <dt>Ditolak</dt>
            <dd>{{ formatDate(detail.rejected_at) }}</dd>
          </div>
        </dl>

        <p v-if="detail.admin_notes" class="note">
          <strong>Catatan:</strong> {{ detail.admin_notes }}
        </p>

        <!-- Pratinjau dokumen -->
        <div v-if="detail.document_url" class="doc-preview">
          <p class="text-overline">Bukti Transaksi</p>

          <a
            v-if="isImage"
            class="doc-image"
            :href="detail.document_url"
            target="_blank"
            rel="noopener noreferrer"
          >
            <img :src="detail.document_url" :alt="detail.document_original_name || 'Bukti transaksi'">
          </a>

          <a
            v-else
            class="doc-file"
            :href="detail.document_url"
            target="_blank"
            rel="noopener noreferrer"
          >
            <span class="icon-chip icon-chip-sm tone-brand"><FileText :size="16" /></span>
            <span class="doc-file-name">
              {{ detail.document_original_name || 'Lihat dokumen' }}
            </span>
            <ExternalLink :size="15" />
          </a>
        </div>

        <!-- Catatan opsional saat menyetujui -->
        <div v-if="detail.status === 'waiting_verification'" class="field approve-note">
          <label class="field-label" for="approve-notes">Catatan (opsional)</label>
          <textarea
            id="approve-notes"
            v-model="approveNotes"
            class="textarea"
            rows="2"
            placeholder="Catatan internal untuk transaksi ini…"
          />
        </div>
      </div>

      <template #footer>
        <button class="btn btn-secondary" type="button" @click="detailOpen = false">
          Tutup
        </button>

        <template v-if="detail?.status === 'waiting_verification'">
          <button
            class="btn btn-danger"
            type="button"
            :disabled="acting"
            @click="rejectOpen = true"
          >
            <CircleX :size="16" />
            Tolak
          </button>
          <button
            class="btn btn-success"
            type="button"
            :disabled="acting"
            @click="approve"
          >
            <CircleCheck :size="16" />
            {{ acting ? 'Memproses…' : 'Setujui' }}
          </button>
        </template>

        <button
          v-else-if="detail?.status === 'pending_license'"
          class="btn btn-primary"
          type="button"
          :disabled="acting"
          @click="retryLicense(detail.id)"
        >
          <RefreshCw :size="16" />
          {{ acting ? 'Memproses…' : 'Terbitkan Lisensi' }}
        </button>
      </template>
    </ModalDialog>

    <!-- ---------- Modal alasan penolakan ---------- -->
    <ModalDialog
      :open="rejectOpen"
      title="Tolak Transaksi"
      subtitle="Alasan akan dikirim ke user dan wajib diisi."
      size="sm"
      @close="rejectOpen = false"
    >
      <div class="field">
        <label class="field-label" for="reject-notes">Alasan penolakan</label>
        <textarea
          id="reject-notes"
          v-model="rejectNotes"
          class="textarea"
          rows="4"
          placeholder="Contoh: Bukti transfer tidak terbaca, mohon unggah ulang."
          required
        />
      </div>

      <template #footer>
        <button class="btn btn-secondary" type="button" @click="rejectOpen = false">
          Batal
        </button>
        <button
          class="btn btn-danger"
          type="button"
          :disabled="acting || !rejectNotes.trim()"
          @click="reject"
        >
          {{ acting ? 'Memproses…' : 'Tolak Transaksi' }}
        </button>
      </template>
    </ModalDialog>
  </div>
</template>

<style scoped>
/* ---- Filter ---- */
.filters {
  display: flex;
  gap: var(--sp-2);
  padding: var(--sp-1);
  overflow-x: auto;
}

.filter {
  padding: var(--sp-2) var(--sp-4);
  border-radius: var(--r-pill);
  background: var(--bg-surface);
  border: 1px solid var(--border);
  font-size: var(--fs-label);
  font-weight: var(--fw-semibold);
  color: var(--text-muted);
  white-space: nowrap;
  transition: all var(--dur) var(--ease);
}
.filter:hover {
  border-color: var(--brand-border);
  color: var(--text-heading);
}
.filter.active {
  background: var(--brand);
  border-color: var(--brand);
  color: #fff;
}

/* ---- Tabel ---- */
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

.doc-cell {
  max-width: 220px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: var(--text-muted);
}

/* ---- Detail ---- */
.detail {
  display: flex;
  flex-direction: column;
  gap: var(--sp-5);
}

.detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(180px, 100%), 1fr));
  gap: var(--sp-4);
}
.detail-grid dt {
  font-size: var(--fs-micro);
  font-weight: var(--fw-semibold);
  letter-spacing: var(--tracking-wide);
  text-transform: uppercase;
  color: var(--text-muted);
  margin-bottom: 3px;
}
.detail-grid dd {
  font-size: var(--fs-body);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
  word-break: break-word;
}

.note {
  padding: var(--sp-3) var(--sp-4);
  background: var(--warn-soft);
  border: 1px solid var(--warn-border);
  border-radius: var(--r);
  font-size: var(--fs-label);
  color: var(--text-body);
}
.note strong {
  color: var(--text-heading);
}

/* ---- Pratinjau dokumen ---- */
.doc-preview {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}

.doc-image {
  display: block;
  border: 1px solid var(--border);
  border-radius: var(--r);
  overflow: hidden;
  background: var(--bg-subtle);
}
.doc-image img {
  width: 100%;
  max-height: 420px;
  object-fit: contain;
}

.doc-file {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-4);
  border: 1px solid var(--border);
  border-radius: var(--r);
  background: var(--bg-subtle);
  color: var(--text-body);
  transition: border-color var(--dur) var(--ease);
}
.doc-file:hover {
  border-color: var(--brand);
  color: var(--brand);
}
.doc-file-name {
  flex: 1;
  min-width: 0;
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.approve-note {
  padding-top: var(--sp-2);
  border-top: 1px solid var(--border);
}

/* ---------- Toolbar ----------
   Sama bentuknya dengan halaman Panduan di sisi user. */
.toolbar-search {
  flex: 1;
  min-width: 160px;
}

@media (max-width: 640px) {
  .toolbar-search {
    flex: 1 1 100%;
    width: 100%;
  }
}
</style>
