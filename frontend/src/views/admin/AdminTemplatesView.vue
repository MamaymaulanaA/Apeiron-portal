<script setup>
/**
 * Kelola template.
 *
 * Pembuatan & perubahan memakai multipart (ada unggahan thumbnail), sehingga
 * update dikirim ke POST /admin/templates/{id}/update — sama seperti
 * sebelumnya, karena PHP tidak mengurai multipart pada request PUT.
 */
import { ref, onMounted } from 'vue'
import {
  LayoutTemplate,
  Plus,
  Pencil,
  Trash2,
  Star,
  ImagePlus,
  TriangleAlert,
  ExternalLink,
} from 'lucide-vue-next'
import EmptyState from '@/components/ui/EmptyState.vue'
import ModalDialog from '@/components/ui/ModalDialog.vue'
import { api, errorMessage } from '@/lib/api'
import { toast } from '@/stores/toast'

const loading = ref(true)
const items = ref([])
const acting = ref(false)

const formOpen = ref(false)
const editing = ref(null)
const form = ref({
  name: '',
  description: '',
  checkout_url: '',
  is_standard: 1,
  is_recommended: 0,
})
const thumbFile = ref(null)
const thumbPreview = ref('')
const deleteTarget = ref(null)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/templates')
    items.value = data.templates || []
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat template'))
  } finally {
    loading.value = false
  }
}

function resetForm() {
  form.value = {
    name: '',
    description: '',
    checkout_url: '',
    is_standard: 1,
    is_recommended: 0,
  }
  thumbFile.value = null
  thumbPreview.value = ''
}

function openCreate() {
  editing.value = null
  resetForm()
  formOpen.value = true
}

function openEdit(t) {
  editing.value = t
  form.value = {
    name: t.name || '',
    description: t.description || '',
    checkout_url: t.checkout_url || '',
    is_standard: Number(t.is_standard) ? 1 : 0,
    is_recommended: Number(t.is_recommended) ? 1 : 0,
  }
  thumbFile.value = null
  thumbPreview.value = t.thumbnail_path
    ? `/storage/templates/thumbnails/${t.thumbnail_path}`
    : ''
  formOpen.value = true
}

function onThumbChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  // Batas dicerminkan dari backend: gambar, maksimal 2MB.
  if (file.size > 2 * 1024 * 1024) {
    toast.error('Ukuran thumbnail maksimal 2MB')
    e.target.value = ''
    return
  }
  thumbFile.value = file
  thumbPreview.value = URL.createObjectURL(file)
}

async function save() {
  if (acting.value) return
  acting.value = true
  try {
    const fd = new FormData()
    fd.append('name', form.value.name.trim())
    fd.append('description', form.value.description.trim())
    fd.append('checkout_url', form.value.checkout_url.trim())
    fd.append('is_standard', String(form.value.is_standard))
    fd.append('is_recommended', String(form.value.is_recommended))
    if (thumbFile.value) fd.append('thumbnail', thumbFile.value)

    if (editing.value) {
      await api.post(`/admin/templates/${editing.value.id}/update`, fd)
      toast.success('Template berhasil diperbarui')
    } else {
      await api.post('/admin/templates', fd)
      toast.success('Template berhasil dibuat')
    }
    formOpen.value = false
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal menyimpan template'))
  } finally {
    acting.value = false
  }
}

async function confirmDelete() {
  if (!deleteTarget.value || acting.value) return
  acting.value = true
  try {
    await api.delete(`/admin/templates/${deleteTarget.value.id}`)
    toast.success('Template berhasil dihapus')
    deleteTarget.value = null
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal menghapus template'))
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
        <h1>Kelola Template</h1>
        <p class="text-muted">Atur katalog template undangan yang tersedia untuk user.</p>
      </div>

      <button class="btn btn-primary" type="button" @click="openCreate">
        <Plus :size="17" />
        Tambah Template
      </button>
    </header>

    <div v-if="loading" class="grid cards">
      <div v-for="i in 6" :key="i" class="card skeleton-card" />
    </div>

    <div v-else-if="items.length" class="grid cards">
      <article v-for="t in items" :key="t.id" class="tpl card">
        <div class="tpl-thumb">
          <img
            v-if="t.thumbnail_path"
            :src="`/storage/templates/thumbnails/${t.thumbnail_path}`"
            :alt="t.name"
            loading="lazy"
          >
          <LayoutTemplate v-else :size="30" />

          <span v-if="Number(t.is_recommended)" class="tpl-flag">
            <Star :size="12" fill="currentColor" />
            Rekomendasi
          </span>
        </div>

        <div class="tpl-body">
          <header class="row-between">
            <h3 class="tpl-name">{{ t.name }}</h3>
            <span
              class="badge"
              :class="Number(t.is_standard) ? 'tone-blue' : 'tone-purple'"
            >
              {{ Number(t.is_standard) ? 'Standard' : 'Premium' }}
            </span>
          </header>

          <p v-if="t.description" class="tpl-desc">{{ t.description }}</p>

          <a
            v-if="t.checkout_url"
            class="tpl-link"
            :href="t.checkout_url"
            target="_blank"
            rel="noopener noreferrer"
          >
            <ExternalLink :size="13" />
            Link checkout
          </a>

          <div class="tpl-actions">
            <button class="btn btn-sm btn-secondary" type="button" @click="openEdit(t)">
              <Pencil :size="14" />
              Edit
            </button>
            <button
              class="btn btn-sm btn-secondary danger"
              type="button"
              @click="deleteTarget = t"
            >
              <Trash2 :size="14" />
              Hapus
            </button>
          </div>
        </div>
      </article>
    </div>

    <div v-else class="card">
      <EmptyState
        :icon="LayoutTemplate"
        tone="tone-orange"
        title="Belum ada template"
        description="Tambahkan template pertama untuk ditampilkan ke user."
      >
        <template #action>
          <button class="btn btn-primary" type="button" @click="openCreate">
            <Plus :size="16" />
            Tambah Template
          </button>
        </template>
      </EmptyState>
    </div>

    <!-- ---------- Modal form ---------- -->
    <ModalDialog
      :open="formOpen"
      :title="editing ? 'Edit Template' : 'Tambah Template'"
      :subtitle="editing ? editing.name : 'Lengkapi detail template baru'"
      size="lg"
      @close="formOpen = false"
    >
      <form id="tpl-form" class="form" @submit.prevent="save">
        <!-- Thumbnail -->
        <div class="field">
          <span class="field-label">Thumbnail</span>
          <label class="thumb-picker">
            <img v-if="thumbPreview" :src="thumbPreview" alt="Pratinjau thumbnail">
            <span v-else class="thumb-empty">
              <ImagePlus :size="24" />
              <span class="text-caption">Pilih gambar (maks. 2MB)</span>
            </span>
            <input
              class="sr-only"
              type="file"
              accept="image/jpeg,image/png,image/gif,image/webp"
              @change="onThumbChange"
            >
          </label>
          <p class="field-hint">
            JPG, PNG, GIF, atau WEBP.
            {{ editing ? 'Kosongkan bila tidak ingin mengganti.' : '' }}
          </p>
        </div>

        <div class="field">
          <label class="field-label" for="t-name">Nama template</label>
          <input
            id="t-name"
            v-model="form.name"
            class="input"
            type="text"
            required
            :disabled="acting"
          >
        </div>

        <div class="field">
          <label class="field-label" for="t-desc">Deskripsi</label>
          <textarea
            id="t-desc"
            v-model="form.description"
            class="textarea"
            rows="3"
            :disabled="acting"
          />
        </div>

        <div class="field">
          <label class="field-label" for="t-url">Link checkout</label>
          <input
            id="t-url"
            v-model="form.checkout_url"
            class="input"
            type="url"
            placeholder="https://…"
            :disabled="acting"
          >
        </div>

        <div class="form-row">
          <div class="field">
            <label class="field-label" for="t-type">Tipe</label>
            <select
              id="t-type"
              v-model.number="form.is_standard"
              class="select"
              :disabled="acting"
            >
              <option :value="1">Standard</option>
              <option :value="0">Premium</option>
            </select>
          </div>

          <div class="field">
            <label class="field-label" for="t-rec">Rekomendasi</label>
            <select
              id="t-rec"
              v-model.number="form.is_recommended"
              class="select"
              :disabled="acting"
            >
              <option :value="0">Tidak</option>
              <option :value="1">Ya</option>
            </select>
          </div>
        </div>
      </form>

      <template #footer>
        <button class="btn btn-secondary" type="button" @click="formOpen = false">
          Batal
        </button>
        <button class="btn btn-primary" type="submit" form="tpl-form" :disabled="acting">
          {{ acting ? 'Menyimpan…' : editing ? 'Simpan Perubahan' : 'Tambah Template' }}
        </button>
      </template>
    </ModalDialog>

    <!-- ---------- Konfirmasi hapus ---------- -->
    <ModalDialog
      :open="!!deleteTarget"
      title="Hapus Template"
      size="sm"
      @close="deleteTarget = null"
    >
      <div class="confirm">
        <span class="icon-chip tone-red confirm-icon"><TriangleAlert :size="22" /></span>
        <p>
          Yakin ingin menghapus <strong>{{ deleteTarget?.name }}</strong>?
          Thumbnail-nya juga akan dihapus.
        </p>
      </div>

      <template #footer>
        <button class="btn btn-secondary" type="button" @click="deleteTarget = null">
          Batal
        </button>
        <button class="btn btn-danger" type="button" :disabled="acting" @click="confirmDelete">
          {{ acting ? 'Menghapus…' : 'Hapus Template' }}
        </button>
      </template>
    </ModalDialog>
  </div>
</template>

<style scoped>
.sr-only {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
}

.cards {
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
}

.skeleton-card {
  height: 320px;
  background: linear-gradient(
    90deg,
    var(--bg-subtle) 25%,
    var(--bg-hover) 50%,
    var(--bg-subtle) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.4s var(--ease) infinite;
  border: none;
}

/* ---- Kartu template ---- */
.tpl {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.tpl-thumb {
  position: relative;
  display: grid;
  place-items: center;
  aspect-ratio: 16 / 10;
  background: var(--c-accent-soft);
  color: var(--c-accent);
  overflow: hidden;
}
.tpl-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.tpl-flag {
  position: absolute;
  top: var(--sp-3);
  left: var(--sp-3);
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  padding: var(--sp-1) var(--sp-3);
  border-radius: var(--r-pill);
  background: var(--c-warning);
  color: #fff;
  font-size: var(--fs-micro);
  font-weight: var(--fw-bold);
}

.tpl-body {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  padding: var(--sp-5);
  flex: 1;
}

.tpl-name {
  font-size: var(--fs-h3);
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tpl-desc {
  font-size: var(--fs-label);
  color: var(--text-muted);
  line-height: var(--lh-normal);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.tpl-link {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  font-size: var(--fs-caption);
  font-weight: var(--fw-medium);
  color: var(--c-primary);
}
.tpl-link:hover {
  color: var(--c-primary-hover);
}

.tpl-actions {
  display: flex;
  gap: var(--sp-2);
  margin-top: auto;
  padding-top: var(--sp-2);
}
.tpl-actions .btn {
  flex: 1;
}

.btn.danger:hover:not(:disabled) {
  background: var(--c-danger-soft);
  border-color: var(--c-danger-border);
  color: var(--c-danger);
}

/* ---- Pemilih thumbnail ---- */
.thumb-picker {
  display: grid;
  place-items: center;
  aspect-ratio: 16 / 9;
  max-height: 200px;
  border: 1.5px dashed var(--border-strong);
  border-radius: var(--r-md);
  background: var(--bg-subtle);
  cursor: pointer;
  overflow: hidden;
  transition: border-color var(--dur) var(--ease);
}
.thumb-picker:hover {
  border-color: var(--c-primary);
}
.thumb-picker img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}
.thumb-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-2);
  color: var(--text-muted);
}

/* ---- Form ---- */
.form {
  display: flex;
  flex-direction: column;
  gap: var(--sp-5);
}
.form-row {
  display: grid;
  grid-template-columns: 1fr;
  gap: var(--sp-5);
}
@media (min-width: 560px) {
  .form-row {
    grid-template-columns: 1fr 1fr;
  }
}

/* ---- Konfirmasi ---- */
.confirm {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-4);
  text-align: center;
}
.confirm-icon {
  width: 52px;
  height: 52px;
  border-radius: var(--r-lg);
}
.confirm p {
  color: var(--text-body);
  line-height: var(--lh-normal);
}
.confirm strong {
  color: var(--text-heading);
}
</style>
