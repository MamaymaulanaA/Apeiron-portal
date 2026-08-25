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
    const payload = {
      title: form.value.title.trim(),
      excerpt: form.value.excerpt.trim(),
      content: form.value.content.trim(),
      category: form.value.category.trim(),
      icon: form.value.icon,
      external_url: form.value.external_url.trim(),
      is_published: form.value.is_published ? 1 : 0,
      sort_order: Number(form.value.sort_order) || 0,
    }

    if (editing.value) {
      await api.put(`/admin/tutorials/${editing.value.id}`, payload)
      toast.success('Panduan berhasil diperbarui')
    } else {
      await api.post('/admin/tutorials', payload)
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
                  <span class="icon-chip icon-chip-sm tone-sky">
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
