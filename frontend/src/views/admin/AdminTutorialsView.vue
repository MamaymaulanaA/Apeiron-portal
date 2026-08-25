<script setup>
/**
 * Kelola Panduan — CRUD isi seksi Forum.
 *
 * Ikon dipilih dari daftar tertutup, bukan diketik bebas. Daftarnya cermin dari
 * Tutorial::ICONS di backend; nama di luar daftar itu ditolak server dan
 * dijatuhkan ke BookOpen, jadi memberi kotak teks bebas hanya akan menghasilkan
 * kartu yang ikonnya tidak sesuai tanpa admin tahu sebabnya.
 */
import { ref, computed, onMounted } from 'vue'
import {
  BookOpen,
  Search,
  Plus,
  Pencil,
  Trash2,
  ExternalLink,
  Youtube,
  RotateCcw,
  EyeOff,
  Plus as PlusIcon,
  ArrowUp,
  ArrowDown,
} from 'lucide-vue-next'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import ModalDialog from '@/components/ui/ModalDialog.vue'
import MediaField from '@/components/ui/MediaField.vue'
import { api, errorMessage } from '@/lib/api'
import { tutorialIcon, TUTORIAL_ICON_NAMES } from '@/lib/community'
import { toast } from '@/stores/toast'

const loading = ref(true)
const loadError = ref('')
const tutorials = ref([])

/* Dimuat utuh, jadi penyaringan di klien menelusuri seluruh data. */
const search = ref('')
const statusFilter = ref('all')

const visibleTutorials = computed(() => {
  const q = search.value.trim().toLowerCase()
  return tutorials.value.filter((t) => {
    if (statusFilter.value === 'published' && !Number(t.is_published)) return false
    if (statusFilter.value === 'draft' && Number(t.is_published)) return false
    if (!q) return true
    return (
      t.title.toLowerCase().includes(q) ||
      (t.excerpt || '').toLowerCase().includes(q) ||
      (t.category || '').toLowerCase().includes(q)
    )
  })
})

const formOpen = ref(false)
const saving = ref(false)
const editing = ref(null)
const form = ref(blankForm())
const formError = ref('')

const confirmOpen = ref(false)
const deleting = ref(null)
const removing = ref(false)

/** Gambar sampul — tampil di kartu, bukan di dalam isi panduan. */
const cover = ref(blankMedia())

/**
 * Langkah bergambar. Inilah yang membuat gambar berada DI DALAM isi panduan:
 * tiap langkah punya teksnya sendiri dan gambarnya sendiri, bukan satu gambar
 * untuk seluruh panduan.
 */
const steps = ref([])

function blankMedia(path = null) {
  return { path, file: null, preview: '', action: 'keep' }
}

function blankStep() {
  return { body: '', media: blankMedia() }
}

function addStep() {
  steps.value.push(blankStep())
}

function removeStep(i) {
  steps.value.splice(i, 1)
}

/** Pindah satu posisi; urutan inilah yang disimpan sebagai `position`. */
function moveStep(i, delta) {
  const j = i + delta
  if (j < 0 || j >= steps.value.length) return
  const [row] = steps.value.splice(i, 1)
  steps.value.splice(j, 0, row)
}

function blankForm() {
  return {
    title: '',
    excerpt: '',
    content: '',
    category: '',
    icon: 'BookOpen',
    external_url: '',
    is_published: true,
    sort_order: 0,
  }
}

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/tutorials')
    tutorials.value = data.tutorials || []
  } catch (e) {
    loadError.value = errorMessage(e, 'Gagal memuat panduan')
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = blankForm()
  cover.value = blankMedia()
  steps.value = []
  formError.value = ''
  formOpen.value = true
}

function openEdit(t) {
  editing.value = t
  form.value = {
    title: t.title,
    excerpt: t.excerpt || '',
    content: t.content || '',
    category: t.category || '',
    icon: t.icon || 'BookOpen',
    external_url: t.external_url || '',
    is_published: !!Number(t.is_published),
    sort_order: Number(t.sort_order) || 0,
  }
  cover.value = blankMedia(t.image_path || null)
  steps.value = (t.steps || []).map((st) => ({
    body: st.body || '',
    media: blankMedia(st.image_path || null),
  }))
  formError.value = ''
  formOpen.value = true
}

async function save() {
  formError.value = ''
  if (!form.value.title.trim()) {
    formError.value = 'Judul wajib diisi'
    return
  }

  saving.value = true
  try {
    // Multipart, bukan JSON, karena ada berkas gambar. Perubahan memakai POST
    // ke /update — PHP tidak mengurai body multipart pada request PUT, jadi
    // $_POST dan $_FILES akan kosong kalau dikirim lewat PUT.
    const fd = new FormData()
    fd.append('title', form.value.title.trim())
    fd.append('excerpt', form.value.excerpt.trim())
    fd.append('content', form.value.content.trim())
    fd.append('category', form.value.category.trim())
    fd.append('icon', form.value.icon)
    fd.append('external_url', form.value.external_url.trim())
    fd.append('is_published', form.value.is_published ? '1' : '0')
    fd.append('sort_order', String(Number(form.value.sort_order) || 0))
    if (cover.value.action === 'replace' && cover.value.file) {
      fd.append('image', cover.value.file)
    }
    if (cover.value.action === 'remove') fd.append('remove_image', '1')

    // Teks langkah dikirim sebagai JSON, gambarnya sebagai berkas terpisah
    // bernomor. Backend memasangkannya lewat indeks yang sama.
    fd.append(
      'steps',
      JSON.stringify(
        steps.value.map((st) => ({
          body: st.body.trim(),
          image_path: st.media.path,
          image_action: st.media.action,
        }))
      )
    )
    steps.value.forEach((st, i) => {
      if (st.media.action === 'replace' && st.media.file) {
        fd.append(`step_image_${i}`, st.media.file)
      }
    })

    if (editing.value) {
      await api.post(`/admin/tutorials/${editing.value.id}/update`, fd)
      toast.success('Panduan berhasil diperbarui')
    } else {
      await api.post('/admin/tutorials', fd)
      toast.success('Panduan berhasil ditambahkan')
    }

    formOpen.value = false
    await load()
  } catch (e) {
    formError.value = errorMessage(e, 'Gagal menyimpan panduan')
  } finally {
    saving.value = false
  }
}

function askDelete(t) {
  deleting.value = t
  confirmOpen.value = true
}

async function confirmDelete() {
  if (!deleting.value) return
  removing.value = true
  try {
    await api.delete(`/admin/tutorials/${deleting.value.id}`)
    toast.success('Panduan berhasil dihapus')
    confirmOpen.value = false
    deleting.value = null
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal menghapus panduan'))
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
        <h1>Kelola Panduan</h1>
        <p class="text-caption">
          Tutorial dan informasi produk yang tampil di seksi Forum dashboard user.
        </p>
      </div>

      <button class="btn btn-primary" type="button" @click="openCreate">
        <Plus :size="16" />
        Tambah Panduan
      </button>
    </header>

    <div v-if="!loading && !loadError && tutorials.length" class="control-row">
      <div class="input-group toolbar-search">
        <Search class="input-icon" :size="16" />
        <input v-model="search" class="input" type="search" placeholder="Cari panduan…">
      </div>

      <select v-model="statusFilter" class="select toolbar-select">
        <option value="all">Semua status</option>
        <option value="published">Terbit</option>
        <option value="draft">Draf</option>
      </select>
    </div>

    <SkeletonRows v-if="loading" :rows="3" :height="64" />

    <section v-else-if="loadError" class="card">
      <EmptyState :icon="BookOpen" title="Gagal memuat panduan" :description="loadError">
        <template #action>
          <button class="btn btn-secondary" type="button" @click="load">
            <RotateCcw :size="15" />
            Coba lagi
          </button>
        </template>
      </EmptyState>
    </section>

    <section v-else-if="!tutorials.length" class="card">
      <EmptyState
        :icon="BookOpen"
        title="Belum ada panduan"
        description="Tambahkan tutorial seperti Cara Install Template atau Panduan Aktivasi Lisensi."
      >
        <template #action>
          <button class="btn btn-primary" type="button" @click="openCreate">
            <Plus :size="16" />
            Tambah Panduan
          </button>
        </template>
      </EmptyState>
    </section>

    <section v-else-if="!visibleTutorials.length" class="card">
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
              <th>Panduan</th>
              <th>Kategori</th>
              <th>Tautan</th>
              <th>Status</th>
              <th class="col-action">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="t in visibleTutorials" :key="t.id">
              <td>
                <span class="tut-cell">
                  <span v-if="t.image_path" class="tut-thumb">
                    <img :src="`/storage/tutorials/${t.image_path}`" :alt="t.title" loading="lazy">
                  </span>
                  <span v-else class="icon-chip icon-chip-sm tone-sky">
                    <component :is="tutorialIcon(t.icon)" :size="15" />
                  </span>
                  <span class="tut-text">
                    <span class="cell-strong">{{ t.title }}</span>
                    <span v-if="t.excerpt" class="text-caption tut-excerpt">{{ t.excerpt }}</span>
                  </span>
                </span>
              </td>
              <td>{{ t.category || '—' }}</td>
              <td>
                <a
                  v-if="t.external_url"
                  class="tut-link"
                  :href="t.external_url"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  <component :is="t.youtube_id ? Youtube : ExternalLink" :size="13" />
                  {{ t.youtube_id ? 'YouTube' : 'Eksternal' }}
                </a>
                <span v-else>—</span>
              </td>
              <td>
                <span class="badge" :class="Number(t.is_published) ? 'tone-ok' : 'tone-neutral'">
                  {{ Number(t.is_published) ? 'Terbit' : 'Draf' }}
                </span>
              </td>
              <td class="col-action">
                <span class="row-actions">
                  <button
                    class="btn btn-sm btn-secondary btn-icon"
                    type="button"
                    aria-label="Ubah panduan"
                    @click="openEdit(t)"
                  >
                    <Pencil :size="14" />
                  </button>
                  <button
                    class="btn btn-sm btn-danger btn-icon"
                    type="button"
                    aria-label="Hapus panduan"
                    @click="askDelete(t)"
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
      :title="editing ? 'Ubah Panduan' : 'Tambah Panduan'"
      size="lg"
      @close="formOpen = false"
    >
      <form class="stack" @submit.prevent="save">
        <label class="field">
          <span class="label">Judul <span class="req">*</span></span>
          <input v-model="form.title" class="input" type="text" maxlength="255" placeholder="Cara Install Template">
        </label>

        <label class="field">
          <span class="label">Ringkasan</span>
          <input
            v-model="form.excerpt"
            class="input"
            type="text"
            maxlength="500"
            placeholder="Satu kalimat yang tampil di kartu."
          >
        </label>

        <label class="field">
          <span class="label">Pengantar</span>
          <textarea
            v-model="form.content"
            class="input textarea"
            rows="5"
            placeholder="Penjelasan pembuka: untuk siapa panduan ini, dan apa yang perlu disiapkan."
          />
          <span class="hint">
            Paragraf pembuka, tampil di atas langkah-langkah. Untuk instruksi
            bergambar, pakai <strong>Langkah</strong> di bawah. Pisahkan
            paragraf dengan satu baris kosong.
          </span>
        </label>

        <div class="field-row">
          <label class="field field-grow">
            <span class="label">Kategori</span>
            <input v-model="form.category" class="input" type="text" maxlength="100" placeholder="Template, Plugin, Lisensi…">
          </label>

          <label class="field">
            <span class="label">Urutan</span>
            <input v-model.number="form.sort_order" class="input" type="number" min="0">
          </label>
        </div>

        <div class="field">
          <span class="label">Gambar sampul</span>
          <MediaField v-model="cover" />
          <span class="hint">
            Tampil di kartu halaman Panduan. Bila kosong, kartunya memakai ikon
            di bawah. Untuk gambar di dalam isi panduan, pakai Langkah.
          </span>
        </div>

        <div class="field">
          <span class="label">Ikon</span>
          <div class="icon-picker">
            <button
              v-for="name in TUTORIAL_ICON_NAMES"
              :key="name"
              class="icon-option"
              :class="{ active: form.icon === name }"
              type="button"
              :aria-label="name"
              :aria-pressed="form.icon === name"
              @click="form.icon = name"
            >
              <component :is="tutorialIcon(name)" :size="17" />
            </button>
          </div>
        </div>

        <label class="field">
          <span class="label">Link eksternal</span>
          <input
            v-model="form.external_url"
            class="input"
            type="url"
            maxlength="500"
            placeholder="https://youtube.com/watch?v=…"
          >
          <span class="hint">
            Link YouTube otomatis jadi tombol “Tonton di YouTube”; link lain jadi
            “Lihat Tutorial”. Kosongkan bila tidak ada.
          </span>
        </label>

        <!-- Langkah bergambar: di sinilah gambar masuk ke dalam isi panduan -->
        <div class="field">
          <span class="label">Langkah</span>
          <span class="hint">
            Instruksi bernomor, tiap langkah dengan gambarnya sendiri — di
            sinilah tangkapan layar dimasukkan. Kosongkan bila panduannya cukup
            berupa teks di Pengantar.
          </span>

          <ol v-if="steps.length" class="steps">
            <li v-for="(st, i) in steps" :key="i" class="step">
              <header class="step-head">
                <span class="step-no">{{ i + 1 }}</span>
                <span class="step-tools">
                  <button
                    class="btn btn-sm btn-secondary btn-icon"
                    type="button"
                    aria-label="Naikkan langkah"
                    :disabled="i === 0"
                    @click="moveStep(i, -1)"
                  >
                    <ArrowUp :size="14" />
                  </button>
                  <button
                    class="btn btn-sm btn-secondary btn-icon"
                    type="button"
                    aria-label="Turunkan langkah"
                    :disabled="i === steps.length - 1"
                    @click="moveStep(i, 1)"
                  >
                    <ArrowDown :size="14" />
                  </button>
                  <button
                    class="btn btn-sm btn-danger btn-icon"
                    type="button"
                    aria-label="Hapus langkah"
                    @click="removeStep(i)"
                  >
                    <Trash2 :size="14" />
                  </button>
                </span>
              </header>

              <textarea
                v-model="st.body"
                class="input textarea"
                rows="3"
                placeholder="Jelaskan langkah ini…"
              />

              <MediaField
                v-model="st.media"
                compact
                :max-height="160"
                label="Tambah gambar untuk langkah ini"
              />
            </li>
          </ol>

          <button class="btn btn-secondary step-add" type="button" @click="addStep">
            <PlusIcon :size="15" />
            Tambah Langkah
          </button>
        </div>

        <label class="check">
          <input v-model="form.is_published" type="checkbox">
          <span>
            Terbitkan
            <small class="text-caption">Draf hanya terlihat di halaman ini, tidak di dashboard user.</small>
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
    <ModalDialog :open="confirmOpen" title="Hapus panduan?" size="sm" @close="confirmOpen = false">
      <p>
        <strong>{{ deleting?.title }}</strong> akan dihapus dan tidak lagi tampil di
        dashboard user. Tindakan ini tidak bisa dibatalkan.
      </p>
      <p class="text-caption confirm-hint">
        <EyeOff :size="13" />
        Bila hanya ingin menyembunyikannya sementara, ubah statusnya jadi draf.
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
.tut-cell {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}
.tut-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  line-height: 1.35;
}
.tut-excerpt {
  max-width: 380px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.tut-link {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  font-size: var(--fs-caption);
  color: var(--brand);
}
.tut-link:hover {
  color: var(--brand-hover);
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

/* ---------- Media ---------- */
.tut-thumb {
  flex-shrink: 0;
  width: 40px;
  height: 30px;
  border-radius: var(--r-sm);
  overflow: hidden;
  background: var(--bg-subtle);
  border: 1px solid var(--border);
}
.tut-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

/* ---------- Langkah ---------- */
.steps {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  /* Nomor langkah digambar sendiri lewat .step-no, jadi penanda bawaan <ol>
     tidak diperlukan dan justru menggandakan angkanya. */
  list-style: none;
  padding: 0;
}

.step {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  padding: var(--sp-4);
  border: 1px solid var(--border);
  border-radius: var(--r);
  background: var(--bg-subtle);
}

.step-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
}

.step-no {
  display: grid;
  place-items: center;
  width: 26px;
  height: 26px;
  border-radius: var(--r-pill);
  background: var(--brand-soft);
  border: 1px solid var(--brand-border);
  color: var(--brand-ink);
  font-size: var(--fs-caption);
  font-weight: var(--fw-bold);
}

.step-tools {
  display: inline-flex;
  gap: var(--sp-2);
}

.step-add {
  align-self: flex-start;
}

/* Pemilih ikon: kisi tombol, bukan kotak teks bebas. Daftarnya tertutup di
   backend, jadi mengetik nama sendiri hanya akan menghasilkan ikon bawaan. */
.icon-picker {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-2);
}
.icon-option {
  display: grid;
  place-items: center;
  width: var(--h-control);
  height: var(--h-control);
  border-radius: var(--r);
  border: 1px solid var(--border-strong);
  background: var(--bg-surface);
  color: var(--text-muted);
  transition: border-color var(--dur) var(--ease), color var(--dur) var(--ease),
    background var(--dur) var(--ease);
}
.icon-option:hover {
  border-color: var(--brand-border);
  color: var(--brand);
}
.icon-option.active {
  border-color: var(--brand);
  background: var(--brand-soft);
  color: var(--brand-ink);
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

/* ---------- Toolbar ----------
   Bentuknya sama dengan halaman Panduan di sisi user: satu baris berisi kotak
   cari lalu saringan. .toolbar-select WAJIB menimpa width: 100% milik .select —
   tanpa itu select memakan satu baris penuh dan kontrolnya menumpuk ke bawah. */
.toolbar-search {
  flex: 1;
  min-width: 160px;
}
.toolbar-select {
  width: auto;
  min-width: 150px;
}

@media (max-width: 640px) {
  .toolbar-search,
  .toolbar-select {
    flex: 1 1 100%;
    width: 100%;
  }
}
</style>
