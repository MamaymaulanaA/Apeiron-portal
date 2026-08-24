<script setup>
/**
 * Kelola plugin.
 *
 *   GET    /admin/plugins
 *   POST   /admin/plugins             multipart: version, changelog, plugin(.zip)
 *   POST   /admin/plugins/{id}/update multipart: version, changelog, plugin(opsional)
 *   DELETE /admin/plugins/{id}
 *
 * Backend mengirim notifikasi ke semua user berlisensi saat plugin diperbarui —
 * itu tetap terjadi apa adanya di sisi server.
 */
import { ref, onMounted } from 'vue'
import {
  Package,
  Plus,
  Pencil,
  Trash2,
  FileArchive,
  TriangleAlert,
  CircleCheck,
} from 'lucide-vue-next'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import ModalDialog from '@/components/ui/ModalDialog.vue'
import { api, errorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { toast } from '@/stores/toast'

const loading = ref(true)
const items = ref([])
const acting = ref(false)

const formOpen = ref(false)
const editing = ref(null)
const form = ref({ version: '', changelog: '' })
const zipFile = ref(null)
const deleteTarget = ref(null)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/plugins')
    items.value = data.plugins || []
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat daftar plugin'))
  } finally {
    loading.value = false
  }
}

function openCreate() {
  editing.value = null
  form.value = { version: '', changelog: '' }
  zipFile.value = null
  formOpen.value = true
}

function openEdit(p) {
  editing.value = p
  form.value = { version: p.version || '', changelog: p.changelog || '' }
  zipFile.value = null
  formOpen.value = true
}

function onZipChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  if (!/\.zip$/i.test(file.name)) {
    toast.error('File harus berformat ZIP')
    e.target.value = ''
    return
  }
  // Batas dicerminkan dari backend (PLUGIN_MAX_SIZE = 50MB).
  if (file.size > 50 * 1024 * 1024) {
    toast.error('Ukuran file maksimal 50MB')
    e.target.value = ''
    return
  }
  zipFile.value = file
}

async function save() {
  if (acting.value) return
  if (!editing.value && !zipFile.value) {
    toast.error('File plugin wajib diupload')
    return
  }
  acting.value = true
  try {
    const fd = new FormData()
    fd.append('version', form.value.version.trim())
    fd.append('changelog', form.value.changelog.trim())
    if (zipFile.value) fd.append('plugin', zipFile.value)

    if (editing.value) {
      const { data } = await api.post(`/admin/plugins/${editing.value.id}/update`, fd)
      const n = data.notified_users
      toast.success(
        n ? `Plugin diperbarui. ${n} user diberi notifikasi.` : 'Plugin berhasil diperbarui'
      )
    } else {
      await api.post('/admin/plugins', fd)
      toast.success('Plugin berhasil diupload')
    }
    formOpen.value = false
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal menyimpan plugin'))
  } finally {
    acting.value = false
  }
}

async function confirmDelete() {
  if (!deleteTarget.value || acting.value) return
  acting.value = true
  try {
    await api.delete(`/admin/plugins/${deleteTarget.value.id}`)
    toast.success('Plugin berhasil dihapus')
    deleteTarget.value = null
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal menghapus plugin'))
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
        <h1>Kelola Plugin</h1>
        <p class="text-muted">Unggah dan kelola rilis Apeiron Kit.</p>
      </div>

      <button class="btn btn-primary" type="button" @click="openCreate">
        <Plus :size="17" />
        Upload Plugin
      </button>
    </header>

    <section class="card">
      <SkeletonRows v-if="loading" :rows="4" :height="64" />

      <EmptyState
        v-else-if="!items.length"
        :icon="Package"
        title="Belum ada plugin"
        description="Unggah rilis pertama agar user dapat mengunduhnya."
      >
        <template #action>
          <button class="btn btn-primary" type="button" @click="openCreate">
            <Plus :size="16" />
            Upload Plugin
          </button>
        </template>
      </EmptyState>

      <div v-else class="plugin-list">
        <article v-for="p in items" :key="p.id" class="plugin">
          <span class="icon-chip tone-neutral plugin-icon">
            <FileArchive :size="20" />
          </span>

          <div class="plugin-text">
            <header class="plugin-head">
              <h3>v{{ p.version }}</h3>
              <span v-if="Number(p.is_active)" class="badge tone-ok">
                <CircleCheck :size="13" />
                Aktif
              </span>
            </header>

            <p class="text-caption plugin-file">{{ p.filename }}</p>
            <p v-if="p.changelog" class="plugin-log">{{ p.changelog }}</p>
            <p class="text-caption">Diunggah {{ formatDate(p.created_at) }}</p>
          </div>

          <div class="plugin-actions">
            <button class="btn btn-sm btn-secondary" type="button" @click="openEdit(p)">
              <Pencil :size="14" />
              Edit
            </button>
            <button
              class="btn btn-sm btn-secondary danger"
              type="button"
              @click="deleteTarget = p"
            >
              <Trash2 :size="14" />
              Hapus
            </button>
          </div>
        </article>
      </div>
    </section>

    <!-- ---------- Modal form ---------- -->
    <ModalDialog
      :open="formOpen"
      :title="editing ? 'Edit Plugin' : 'Upload Plugin'"
      :subtitle="
        editing
          ? `Memperbarui v${editing.version} — user berlisensi akan diberi notifikasi`
          : 'Unggah rilis baru Apeiron Kit'
      "
      @close="formOpen = false"
    >
      <form id="plugin-form" class="form" @submit.prevent="save">
        <div class="field">
          <label class="field-label" for="p-version">Versi</label>
          <input
            id="p-version"
            v-model="form.version"
            class="input"
            type="text"
            placeholder="1.2.0"
            required
            :disabled="acting"
          >
          <p class="field-hint">Dipakai sebagai nama file: apeiron-kit-{versi}.zip</p>
        </div>

        <div class="field">
          <label class="field-label" for="p-changelog">Changelog</label>
          <textarea
            id="p-changelog"
            v-model="form.changelog"
            class="textarea"
            rows="4"
            placeholder="Apa yang berubah di rilis ini…"
            :disabled="acting"
          />
        </div>

        <div class="field">
          <span class="field-label">
            File plugin (.zip) {{ editing ? '— opsional' : '' }}
          </span>

          <label class="zip-picker" :class="{ filled: zipFile }">
            <span class="icon-chip icon-chip-sm tone-neutral">
              <FileArchive :size="16" />
            </span>
            <span class="zip-text">
              {{ zipFile ? zipFile.name : 'Pilih file ZIP (maks. 50MB)' }}
            </span>
            <input class="sr-only" type="file" accept=".zip" @change="onZipChange">
          </label>

          <p v-if="editing" class="field-hint">
            Kosongkan bila hanya ingin mengubah versi atau changelog.
          </p>
        </div>
      </form>

      <template #footer>
        <button class="btn btn-secondary" type="button" @click="formOpen = false">
          Batal
        </button>
        <button class="btn btn-primary" type="submit" form="plugin-form" :disabled="acting">
          {{ acting ? 'Mengunggah…' : editing ? 'Simpan Perubahan' : 'Upload Plugin' }}
        </button>
      </template>
    </ModalDialog>

    <!-- ---------- Konfirmasi hapus ---------- -->
    <ModalDialog
      :open="!!deleteTarget"
      title="Hapus Plugin"
      size="sm"
      @close="deleteTarget = null"
    >
      <div class="confirm">
        <span class="icon-chip tone-err confirm-icon"><TriangleAlert :size="22" /></span>
        <p>
          Yakin ingin menghapus <strong>v{{ deleteTarget?.version }}</strong>?
          File ZIP di server juga akan dihapus.
        </p>
      </div>

      <template #footer>
        <button class="btn btn-secondary" type="button" @click="deleteTarget = null">
          Batal
        </button>
        <button class="btn btn-danger" type="button" :disabled="acting" @click="confirmDelete">
          {{ acting ? 'Menghapus…' : 'Hapus Plugin' }}
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

/* ---- Daftar plugin ---- */
.plugin-list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  padding: var(--sp-5) var(--sp-6);
}

.plugin {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-4);
  padding: var(--sp-5);
  border: 1px solid var(--border);
  border-radius: var(--r);
  transition: border-color var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
}
.plugin:hover {
  border-color: var(--brand-border);
  box-shadow: var(--sh-card);
}

.plugin-icon {
  width: 48px;
  height: 48px;
  border-radius: var(--r);
  background: var(--tone-bg);
  flex-shrink: 0;
}

.plugin-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: var(--sp-1);
}

.plugin-head {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}
.plugin-head h3 {
  font-size: var(--fs-h3);
}

.plugin-file {
  font-family: var(--font-mono);
  word-break: break-all;
}

.plugin-log {
  margin-top: var(--sp-1);
  font-size: var(--fs-label);
  color: var(--text-body);
  line-height: var(--lh-normal);
}

.plugin-actions {
  display: flex;
  gap: var(--sp-2);
  flex-shrink: 0;
}

.btn.danger:hover:not(:disabled) {
  background: var(--err-soft);
  border-color: var(--err-border);
  color: var(--err);
}

/* ---- Pemilih ZIP ---- */
.zip-picker {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-4);
  border: 1.5px dashed var(--border-strong);
  border-radius: var(--r);
  background: var(--bg-subtle);
  cursor: pointer;
  transition: border-color var(--dur) var(--ease);
}
.zip-picker:hover {
  border-color: var(--brand);
}
.zip-picker.filled {
  border-style: solid;
  border-color: var(--ok-border);
  background: var(--ok-soft);
}
.zip-text {
  flex: 1;
  min-width: 0;
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

/* ---- Form ---- */
.form {
  display: flex;
  flex-direction: column;
  gap: var(--sp-5);
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
  border-radius: var(--r);
}
.confirm p {
  color: var(--text-body);
  line-height: var(--lh-normal);
}
.confirm strong {
  color: var(--text-heading);
}

/* ---- Responsif ---- */
@media (max-width: 780px) {
  .plugin {
    flex-wrap: wrap;
  }
  .plugin-actions {
    width: 100%;
  }
  .plugin-actions .btn {
    flex: 1;
  }
}

@media (max-width: 640px) {
  .plugin-list {
    padding: var(--sp-4);
  }
}
</style>
