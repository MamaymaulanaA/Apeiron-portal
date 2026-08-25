<script setup>
/**
 * Kelola Bonus — CRUD bonus member.
 *
 * Mengikuti pola halaman admin lain: page-head dengan aksi utama, toolbar cari
 * + saringan, tabel, dan modal form. Komponen dan tokennya dipakai ulang apa
 * adanya — tidak ada gaya baru di sini.
 *
 * Tipe sumber menentukan field mana yang berlaku: Upload File memakai berkas,
 * Link memakai URL. Yang tidak berlaku disembunyikan, bukan dibiarkan
 * terisi diam-diam — field tersembunyi yang masih mengirim nilainya adalah
 * cara paling mudah menyimpan data yang bertentangan dengan tipenya.
 */
import { ref, computed, onMounted } from 'vue'
import {
  Gift,
  Search,
  Plus,
  Pencil,
  Trash2,
  Download,
  ExternalLink,
  RotateCcw,
  EyeOff,
  Upload,
} from 'lucide-vue-next'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import ModalDialog from '@/components/ui/ModalDialog.vue'
import MediaField from '@/components/ui/MediaField.vue'
import { api, errorMessage } from '@/lib/api'
import { formatFileSize } from '@/lib/community'
import { toast } from '@/stores/toast'

const loading = ref(true)
const loadError = ref('')
const bonuses = ref([])

const search = ref('')
const statusFilter = ref('all')

const visibleBonuses = computed(() => {
  const q = search.value.trim().toLowerCase()
  return bonuses.value.filter((b) => {
    if (statusFilter.value === 'active' && !Number(b.is_active)) return false
    if (statusFilter.value === 'inactive' && Number(b.is_active)) return false
    if (!q) return true
    return (
      b.name.toLowerCase().includes(q) ||
      (b.description || '').toLowerCase().includes(q) ||
      (b.category || '').toLowerCase().includes(q)
    )
  })
})

const formOpen = ref(false)
const saving = ref(false)
const editing = ref(null)
const form = ref(blankForm())
const formError = ref('')

const thumb = ref(blankMedia())
const bonusFile = ref(null)

const confirmOpen = ref(false)
const deleting = ref(null)
const removing = ref(false)

function blankForm() {
  return {
    name: '',
    category: '',
    description: '',
    source_type: 'link',
    external_url: '',
    is_active: true,
    sort_order: 0,
  }
}

/** Bentuk model MediaField: path tersimpan, berkas baru, dan niat perubahan. */
function blankMedia(path = null) {
  return { path, file: null, preview: '', action: 'keep' }
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/bonuses')
    bonuses.value = data.bonuses || []
  } catch (e) {
    loadError.value = errorMessage(e, 'Gagal memuat bonus')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = blankForm()
  thumb.value = blankMedia()
  bonusFile.value = null
  formError.value = ''
  formOpen.value = true
}

function openEdit(b) {
  editing.value = b
  form.value = {
    name: b.name,
    category: b.category || '',
    description: b.description || '',
    source_type: b.source_type || 'link',
    external_url: b.external_url || '',
    is_active: !!Number(b.is_active),
    sort_order: Number(b.sort_order) || 0,
  }
  thumb.value = blankMedia(b.thumbnail_path || null)
  bonusFile.value = null
  formError.value = ''
  formOpen.value = true
}

function onFilePick(e) {
  const file = e.target.files?.[0]
  if (!file) return
  // Batas dicerminkan dari backend; backend tetap penentu akhirnya.
  if (file.size > 50 * 1024 * 1024) {
    toast.error('Ukuran berkas maksimal 50MB')
    e.target.value = ''
    return
  }
  bonusFile.value = file
}

async function save() {
  formError.value = ''

  if (!form.value.name.trim()) {
    formError.value = 'Nama bonus wajib diisi'
    return
  }
  if (form.value.source_type === 'link' && !form.value.external_url.trim()) {
    formError.value = 'Link bonus wajib diisi untuk tipe Link'
    return
  }
  // Saat mengubah, berkas lama tetap sah — unggahan baru hanya perlu bila
  // bonusnya memang belum punya berkas.
  const hasStoredFile = editing.value?.source_type === 'file' && editing.value?.file_original_name
  if (form.value.source_type === 'file' && !bonusFile.value && !hasStoredFile) {
    formError.value = 'Berkas bonus wajib diunggah untuk tipe Upload File'
    return
  }

  saving.value = true
  try {
    // Multipart karena ada berkas. Perubahan lewat POST /update — PHP tidak
    // mengurai body multipart pada PUT.
    const fd = new FormData()
    fd.append('name', form.value.name.trim())
    fd.append('category', form.value.category.trim())
    fd.append('description', form.value.description.trim())
    fd.append('source_type', form.value.source_type)
    fd.append('external_url', form.value.source_type === 'link' ? form.value.external_url.trim() : '')
    fd.append('is_active', form.value.is_active ? '1' : '0')
    fd.append('sort_order', String(Number(form.value.sort_order) || 0))

    if (thumb.value.action === 'replace' && thumb.value.file) fd.append('thumbnail', thumb.value.file)
    if (thumb.value.action === 'remove') fd.append('remove_thumbnail', '1')
    if (form.value.source_type === 'file' && bonusFile.value) fd.append('file', bonusFile.value)

    if (editing.value) {
      await api.post(`/admin/bonuses/${editing.value.id}/update`, fd)
      toast.success('Bonus berhasil diperbarui')
    } else {
      await api.post('/admin/bonuses', fd)
      toast.success('Bonus berhasil ditambahkan')
    }

    formOpen.value = false
    await load()
  } catch (e) {
    formError.value = errorMessage(e, 'Gagal menyimpan bonus')
  } finally {
    saving.value = false
  }
}

function askDelete(b) {
  deleting.value = b
  confirmOpen.value = true
}

async function confirmDelete() {
  if (!deleting.value) return
  removing.value = true
  try {
    await api.delete(`/admin/bonuses/${deleting.value.id}`)
    toast.success('Bonus berhasil dihapus')
    confirmOpen.value = false
    deleting.value = null
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal menghapus bonus'))
  } finally {
    removing.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>Kelola Bonus</h1>
        <p class="text-caption">
          Berkas dan tautan tambahan yang tampil di dashboard member.
        </p>
      </div>

      <button class="btn btn-primary" type="button" @click="openCreate">
        <Plus :size="16" />
        Tambah Bonus
      </button>
    </header>

    <div v-if="!loading && !loadError && bonuses.length" class="control-row">
      <div class="input-group toolbar-search">
        <Search class="input-icon" :size="16" />
        <input v-model="search" class="input" type="search" placeholder="Cari bonus…">
      </div>

      <select v-model="statusFilter" class="select toolbar-select">
        <option value="all">Semua status</option>
        <option value="active">Aktif</option>
        <option value="inactive">Nonaktif</option>
      </select>
    </div>

    <SkeletonRows v-if="loading" :rows="3" :height="64" />

    <section v-else-if="loadError" class="card">
      <EmptyState :icon="Gift" title="Gagal memuat bonus" :description="loadError">
        <template #action>
          <button class="btn btn-secondary" type="button" @click="load">
            <RotateCcw :size="15" />
            Coba lagi
          </button>
        </template>
      </EmptyState>
    </section>

    <section v-else-if="!bonuses.length" class="card">
      <EmptyState
        :icon="Gift"
        title="Belum ada bonus"
        description="Tambahkan berkas atau tautan bonus untuk dibagikan ke member."
      >
        <template #action>
          <button class="btn btn-primary" type="button" @click="openCreate">
            <Plus :size="16" />
            Tambah Bonus
          </button>
        </template>
      </EmptyState>
    </section>

    <section v-else-if="!visibleBonuses.length" class="card">
      <EmptyState
        :icon="Search"
        title="Tidak ada yang cocok"
        description="Coba kata kunci lain atau ubah saringan status."
      />
    </section>

    <section v-else class="card">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Bonus</th>
              <th>Kategori</th>
              <th>Sumber</th>
              <th>Status</th>
              <th class="col-action">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="b in visibleBonuses" :key="b.id">
              <td>
                <span class="bonus-cell">
                  <span v-if="b.thumbnail_path" class="bonus-thumb">
                    <img
                      :src="`/storage/bonus-thumbnails/${b.thumbnail_path}`"
                      :alt="b.name"
                      loading="lazy"
                    >
                  </span>
                  <span v-else class="icon-chip icon-chip-sm tone-peach"><Gift :size="15" /></span>

                  <span class="bonus-text">
                    <span class="cell-strong">{{ b.name }}</span>
                    <span v-if="b.description" class="text-caption bonus-desc">{{ b.description }}</span>
                  </span>
                </span>
              </td>
              <td>{{ b.category || '—' }}</td>
              <td>
                <span class="bonus-source">
                  <component :is="b.source_type === 'file' ? Download : ExternalLink" :size="13" />
                  {{ b.source_type === 'file' ? formatFileSize(b.file_size) : 'Link' }}
                </span>
              </td>
              <td>
                <span class="badge" :class="Number(b.is_active) ? 'tone-ok' : 'tone-neutral'">
                  {{ Number(b.is_active) ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td class="col-action">
                <span class="row-actions">
                  <button
                    class="btn btn-sm btn-secondary btn-icon"
                    type="button"
                    aria-label="Ubah bonus"
                    @click="openEdit(b)"
                  >
                    <Pencil :size="14" />
                  </button>
                  <button
                    class="btn btn-sm btn-danger btn-icon"
                    type="button"
                    aria-label="Hapus bonus"
                    @click="askDelete(b)"
                  >
                    <Trash2 :size="14" />
                  </button>
                </span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- ---------- Form ---------- -->
    <ModalDialog
      :open="formOpen"
      :title="editing ? 'Ubah Bonus' : 'Tambah Bonus'"
      size="lg"
      @close="formOpen = false"
    >
      <form class="stack" @submit.prevent="save">
        <label class="field">
          <span class="label">Nama bonus <span class="req">*</span></span>
          <input v-model="form.name" class="input" type="text" maxlength="255" placeholder="Pack Ikon Undangan">
        </label>

        <label class="field">
          <span class="label">Deskripsi</span>
          <textarea
            v-model="form.description"
            class="input textarea"
            rows="3"
            placeholder="Penjelasan singkat isi bonusnya."
          />
        </label>

        <div class="field">
          <span class="label">Tipe sumber <span class="req">*</span></span>
          <div class="segmented source-toggle">
            <button
              class="segmented-item"
              :class="{ active: form.source_type === 'file' }"
              type="button"
              @click="form.source_type = 'file'"
            >
              <Upload :size="14" />
              Upload File
            </button>
            <button
              class="segmented-item"
              :class="{ active: form.source_type === 'link' }"
              type="button"
              @click="form.source_type = 'link'"
            >
              <ExternalLink :size="14" />
              Link
            </button>
          </div>
        </div>

        <!-- Hanya field yang berlaku bagi tipe terpilih yang tampil. -->
        <label v-if="form.source_type === 'file'" class="field">
          <span class="label">Berkas bonus <span class="req">*</span></span>
          <input class="input file-input" type="file" @change="onFilePick">
          <span class="hint">
            <template v-if="bonusFile">
              Berkas baru: <strong>{{ bonusFile.name }}</strong> ({{ formatFileSize(bonusFile.size) }})
            </template>
            <template v-else-if="editing?.file_original_name">
              Tersimpan: <strong>{{ editing.file_original_name }}</strong>
              ({{ formatFileSize(editing.file_size) }}). Kosongkan bila tidak ingin mengganti.
            </template>
            <template v-else>
              ZIP, PDF, dokumen, gambar, audio, atau video · maksimal 50MB
            </template>
          </span>
        </label>

        <label v-else class="field">
          <span class="label">Link bonus <span class="req">*</span></span>
          <input
            v-model="form.external_url"
            class="input"
            type="url"
            maxlength="500"
            placeholder="https://drive.google.com/…"
          >
          <span class="hint">
            Tombol Unduh akan membuka tautan ini di tab baru.
          </span>
        </label>

        <div class="field">
          <span class="label">Thumbnail</span>
          <MediaField v-model="thumb" />
          <span class="hint">
            Tampil di kartu bonus. Bila kosong, kartunya memakai ikon hadiah.
          </span>
        </div>

        <div class="field-row">
          <label class="field field-grow">
            <span class="label">Kategori</span>
            <input v-model="form.category" class="input" type="text" maxlength="100" placeholder="Template, Aset, Panduan…">
          </label>

          <label class="field">
            <span class="label">Urutan</span>
            <input v-model.number="form.sort_order" class="input" type="number" min="0">
          </label>
        </div>

        <label class="check">
          <input v-model="form.is_active" type="checkbox">
          <span>
            Aktif
            <small class="text-caption">Nonaktif berarti tidak tampil dan tidak bisa diunduh member.</small>
          </span>
        </label>

        <p v-if="formError" class="form-error">{{ formError }}</p>
      </form>

      <template #footer>
        <button class="btn btn-secondary" type="button" @click="formOpen = false">Batal</button>
        <button class="btn btn-primary" type="button" :disabled="saving" @click="save">
          {{ saving ? 'Menyimpan…' : 'Simpan' }}
        </button>
      </template>
    </ModalDialog>

    <!-- ---------- Konfirmasi hapus ---------- -->
    <ModalDialog :open="confirmOpen" title="Hapus bonus?" size="sm" @close="confirmOpen = false">
      <p>
        <strong>{{ deleting?.name }}</strong> akan dihapus beserta berkasnya, dan
        tidak lagi tampil di dashboard member. Tindakan ini tidak bisa dibatalkan.
      </p>
      <p class="text-caption confirm-hint">
        <EyeOff :size="13" />
        Bila hanya ingin menyembunyikannya sementara, ubah statusnya jadi nonaktif.
      </p>

      <template #footer>
        <button class="btn btn-secondary" type="button" @click="confirmOpen = false">Batal</button>
        <button class="btn btn-danger" type="button" :disabled="removing" @click="confirmDelete">
          {{ removing ? 'Menghapus…' : 'Hapus' }}
        </button>
      </template>
    </ModalDialog>
  </div>
</template>

<style scoped>
.toolbar-search {
  flex: 1;
  min-width: 160px;
}
.toolbar-select {
  width: auto;
  min-width: 150px;
}

.bonus-cell {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}
.bonus-thumb {
  flex-shrink: 0;
  width: 40px;
  height: 30px;
  border-radius: var(--r-sm);
  overflow: hidden;
  background: var(--bg-subtle);
  border: 1px solid var(--border);
}
.bonus-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.bonus-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  line-height: 1.35;
}
.bonus-desc {
  max-width: 380px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.bonus-source {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  font-size: var(--fs-caption);
  color: var(--text-muted);
}

.col-action {
  width: 1%;
  white-space: nowrap;
}
.row-actions {
  display: inline-flex;
  gap: var(--sp-2);
}

.field {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}
.label {
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
}
.req {
  color: var(--err);
}
.hint {
  font-size: var(--fs-caption);
  color: var(--text-muted);
  line-height: var(--lh-snug);
}
.textarea {
  height: auto;
  padding: var(--sp-3);
  resize: vertical;
  line-height: var(--lh-snug);
}

/* Input file bawaan tidak bisa ditata sepenuhnya; yang bisa dijaga adalah
   tingginya agar sebaris dengan kontrol lain. */
.file-input {
  height: auto;
  padding: var(--sp-3);
}

/* inline-grid dengan kolom 1fr: kedua segmen ikut selebar label terpanjang,
   jadi "Link" tidak tampak terjepit di sebelah "Upload File". flex-start
   menahan seluruh kontrolnya agar tidak melar selebar form. */
.source-toggle {
  align-self: flex-start;
  display: inline-grid;
  grid-auto-flow: column;
  grid-auto-columns: 1fr;
}
.source-toggle .segmented-item {
  padding: 0 var(--sp-4);
  font-size: var(--fs-label);
}

.field-row {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-4);
}
.field-grow {
  flex: 1;
  min-width: 200px;
}
.field-row .field:not(.field-grow) {
  flex: 0 0 120px;
}

.check {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-2);
  font-size: var(--fs-label);
  color: var(--text-heading);
}
.check small {
  display: block;
}

.form-error {
  padding: var(--sp-3) var(--sp-4);
  border-radius: var(--r);
  background: var(--err-soft);
  border: 1px solid var(--err-border);
  color: var(--err);
  font-size: var(--fs-caption);
}

.confirm-hint {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  margin-top: var(--sp-3);
}

@media (max-width: 640px) {
  .toolbar-search,
  .toolbar-select {
    flex: 1 1 100%;
    width: 100%;
  }
}
</style>
