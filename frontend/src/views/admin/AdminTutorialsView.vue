<script setup>
/**
 * Kelola Panduan — CRUD isi seksi Forum.
 *
 * Ikon dipilih dari daftar tertutup, bukan diketik bebas. Daftarnya cermin dari
 * Tutorial::ICONS di backend; nama di luar daftar itu ditolak server dan
 * dijatuhkan ke BookOpen, jadi memberi kotak teks bebas hanya akan menghasilkan
 * kartu yang ikonnya tidak sesuai tanpa admin tahu sebabnya.
 */
import { ref, onMounted } from 'vue'
import {
  BookOpen,
  Plus,
  Pencil,
  Trash2,
  ExternalLink,
  Youtube,
  RotateCcw,
  EyeOff,
  ImagePlus,
  X,
} from 'lucide-vue-next'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import ModalDialog from '@/components/ui/ModalDialog.vue'
import { api, errorMessage } from '@/lib/api'
import { tutorialIcon, TUTORIAL_ICON_NAMES } from '@/lib/community'
import { toast } from '@/stores/toast'

const loading = ref(true)
const loadError = ref('')
const tutorials = ref([])

const formOpen = ref(false)
const saving = ref(false)
const editing = ref(null)
const form = ref(blankForm())
const formError = ref('')

const confirmOpen = ref(false)
const deleting = ref(null)
const removing = ref(false)

/**
 * Media tutorial. `imageFile` hanya terisi bila admin memilih berkas baru;
 * `removeImage` menandai penghapusan gambar yang sudah tersimpan. Keduanya
 * dibedakan karena tiga keadaan itu berbeda bagi backend: ganti, hapus, atau
 * biarkan.
 */
const imageFile = ref(null)
const imagePreview = ref('')
const removeImage = ref(false)
const dragging = ref(false)

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

function resetMedia() {
  // Preview dari berkas lokal adalah object URL; melepasnya mencegah kebocoran
  // memori saat form dibuka-tutup berkali-kali.
  if (imagePreview.value.startsWith('blob:')) URL.revokeObjectURL(imagePreview.value)
  imageFile.value = null
  imagePreview.value = ''
  removeImage.value = false
  dragging.value = false
}

function openCreate() {
  editing.value = null
  form.value = blankForm()
  resetMedia()
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
  resetMedia()
  imagePreview.value = t.image_path ? `/storage/tutorials/${t.image_path}` : ''
  formError.value = ''
  formOpen.value = true
}

/** Batas dicerminkan dari backend; backend tetap penentu akhirnya. */
function acceptFile(file) {
  if (!file) return
  if (!file.type.startsWith('image/')) {
    toast.error('Berkas harus berupa gambar')
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    toast.error('Ukuran gambar maksimal 2MB')
    return
  }
  if (imagePreview.value.startsWith('blob:')) URL.revokeObjectURL(imagePreview.value)
  imageFile.value = file
  imagePreview.value = URL.createObjectURL(file)
  removeImage.value = false
}

function onPick(e) {
  acceptFile(e.target.files?.[0])
  // Kosongkan supaya memilih berkas yang sama dua kali tetap memicu change.
  e.target.value = ''
}

function onDrop(e) {
  dragging.value = false
  acceptFile(e.dataTransfer?.files?.[0])
}

function clearImage() {
  if (imagePreview.value.startsWith('blob:')) URL.revokeObjectURL(imagePreview.value)
  imageFile.value = null
  imagePreview.value = ''
  // Hanya berarti "hapus di server" bila sebelumnya memang ada yang tersimpan.
  removeImage.value = !!editing.value?.image_path
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
    if (imageFile.value) fd.append('image', imageFile.value)
    if (removeImage.value) fd.append('remove_image', '1')

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
            <tr v-for="t in tutorials" :key="t.id">
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
          <span class="label">Isi panduan</span>
          <textarea
            v-model="form.content"
            class="input textarea"
            rows="7"
            placeholder="Tulis langkah-langkahnya. Pisahkan paragraf dengan satu baris kosong."
          />
          <span class="hint">Baris kosong memisahkan paragraf saat ditampilkan ke user.</span>
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
          <span class="label">Gambar</span>

          <!-- Area jatuh + klik. Input file-nya disembunyikan, bukan dihapus:
               ia tetap yang menangani pemilihan berkas, termasuk lewat keyboard. -->
          <div
            class="media"
            :class="{ dragging, filled: !!imagePreview }"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
          >
            <template v-if="imagePreview">
              <img class="media-img" :src="imagePreview" alt="Pratinjau gambar">
              <div class="media-actions">
                <label class="btn btn-sm btn-secondary">
                  <ImagePlus :size="14" />
                  Ganti
                  <input class="media-input" type="file" accept="image/*" @change="onPick">
                </label>
                <button class="btn btn-sm btn-danger btn-icon" type="button" aria-label="Hapus gambar" @click="clearImage">
                  <X :size="14" />
                </button>
              </div>
            </template>

            <label v-else class="media-drop">
              <span class="icon-chip tone-sky"><ImagePlus :size="20" /></span>
              <span class="media-title">Tarik gambar ke sini, atau klik untuk memilih</span>
              <span class="media-hint">JPG, PNG, GIF, atau WEBP · maksimal 2MB</span>
              <input class="media-input" type="file" accept="image/*" @change="onPick">
            </label>
          </div>

          <span class="hint">
            Dipakai sebagai gambar kartu di halaman Panduan user. Bila kosong,
            kartunya memakai ikon di bawah.
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

.media {
  position: relative;
  border: 1px dashed var(--border-strong);
  border-radius: var(--r);
  background: var(--bg-subtle);
  overflow: hidden;
  transition: border-color var(--dur) var(--ease), background var(--dur) var(--ease);
}
.media.dragging {
  border-color: var(--brand);
  background: var(--brand-soft);
}
/* Setelah terisi ia bukan lagi target jatuh yang kosong, jadi garis putus
   diganti garis utuh seperti kontrol lain. */
.media.filled {
  border-style: solid;
  border-color: var(--border);
}

.media-drop {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-6) var(--sp-5);
  text-align: center;
  cursor: pointer;
}
.media-title {
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
}
.media-hint {
  font-size: var(--fs-caption);
  color: var(--text-muted);
}

/* Disembunyikan dari mata, tetap ada untuk keyboard dan pembaca layar. */
.media-input {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
  border: 0;
}

.media-img {
  display: block;
  width: 100%;
  max-height: 220px;
  object-fit: cover;
}

.media-actions {
  display: flex;
  gap: var(--sp-2);
  padding: var(--sp-3);
  border-top: 1px solid var(--border);
  background: var(--bg-surface);
}
.media-actions .btn {
  cursor: pointer;
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
</style>
