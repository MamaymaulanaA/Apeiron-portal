<script setup>
/**
 * Kelola plugin dan riwayat versinya.
 *
 *   GET    /admin/plugins                       daftar plugin
 *   POST   /admin/plugins                       tambah plugin
 *   POST   /admin/plugins/{id}/update           ubah plugin
 *   DELETE /admin/plugins/{id}                  hapus plugin + seluruh versinya
 *
 *   GET    /admin/plugins/{id}/versions         riwayat versi
 *   POST   /admin/plugins/{id}/versions         rilis versi baru (multipart)
 *   POST   /admin/plugin-versions/{id}/update   ubah versi
 *   DELETE /admin/plugin-versions/{id}          hapus satu versi
 *
 * Plugin dan versinya sengaja terpisah. Sebelumnya setiap unggahan membuat
 * "plugin" baru dan mematikan seluruh versi sebelumnya, sehingga riwayat rilis
 * tidak pernah bisa ada.
 *
 * "Versi terbaru" datang dari backend (is_latest), bukan dari urutan tampilan.
 */
import { ref, computed, onMounted } from 'vue'
import {
  Package,
  Plus,
  Pencil,
  Trash2,
  FileArchive,
  TriangleAlert,
  CircleCheck,
  EyeOff,
  Search,
  Layers,
  ChevronDown,
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

const search = ref('')
const visibleItems = computed(() => {
  const q = search.value.trim().toLowerCase()
  if (!q) return items.value
  return items.value.filter(
    (p) =>
      String(p.name || '').toLowerCase().includes(q) ||
      String(p.slug || '').toLowerCase().includes(q) ||
      String(p.latest_version || '').toLowerCase().includes(q),
  )
})

/* ---- Panel versi ---- */
const openId = ref(null)
const versions = ref([])
const versionsLoading = ref(false)

/* ---- Form ---- */
const pluginFormOpen = ref(false)
const editingPlugin = ref(null)
const pluginForm = ref({ name: '', slug: '', description: '', is_active: true })

const versionFormOpen = ref(false)
const editingVersion = ref(null)
const versionForm = ref({ version: '', changelog: '', is_active: true })
const zipFile = ref(null)

/* Satu target hapus untuk keduanya; jenisnya menentukan teks dan endpoint. */
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

async function loadVersions(pluginId) {
  versionsLoading.value = true
  try {
    const { data } = await api.get(`/admin/plugins/${pluginId}/versions`)
    versions.value = data.versions || []
  } catch (e) {
    versions.value = []
    toast.error(errorMessage(e, 'Gagal memuat daftar versi'))
  } finally {
    versionsLoading.value = false
  }
}

async function toggleVersions(plugin) {
  if (openId.value === plugin.id) {
    openId.value = null
    return
  }
  openId.value = plugin.id
  await loadVersions(plugin.id)
}

/* ---- Plugin ---- */
function openCreatePlugin() {
  editingPlugin.value = null
  pluginForm.value = { name: '', slug: '', description: '', is_active: true }
  pluginFormOpen.value = true
}

function openEditPlugin(p) {
  editingPlugin.value = p
  pluginForm.value = {
    name: p.name || '',
    slug: p.slug || '',
    description: p.description || '',
    is_active: !!p.is_active,
  }
  pluginFormOpen.value = true
}

async function savePlugin() {
  acting.value = true
  try {
    const fd = new FormData()
    fd.append('name', pluginForm.value.name)
    fd.append('slug', pluginForm.value.slug)
    fd.append('description', pluginForm.value.description)
    fd.append('is_active', pluginForm.value.is_active ? '1' : '0')

    if (editingPlugin.value) {
      await api.post(`/admin/plugins/${editingPlugin.value.id}/update`, fd)
      toast.success('Plugin berhasil diperbarui')
    } else {
      await api.post('/admin/plugins', fd)
      toast.success('Plugin berhasil ditambahkan')
    }

    pluginFormOpen.value = false
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal menyimpan plugin'))
  } finally {
    acting.value = false
  }
}

/* ---- Versi ---- */
function openCreateVersion(plugin) {
  openId.value = plugin.id
  editingVersion.value = null
  versionForm.value = { version: '', changelog: '', is_active: true }
  zipFile.value = null
  versionFormOpen.value = true
}

function openEditVersion(v) {
  editingVersion.value = v
  versionForm.value = {
    version: v.version || '',
    changelog: v.changelog || '',
    is_active: !!v.is_active,
  }
  zipFile.value = null
  versionFormOpen.value = true
}

function onZipChange(e) {
  zipFile.value = e.target.files?.[0] || null
}

async function saveVersion() {
  // Berkas wajib saat merilis versi baru; saat mengedit boleh dikosongkan
  // supaya changelog bisa diperbaiki tanpa mengunggah ulang.
  if (!editingVersion.value && !zipFile.value) {
    toast.error('Berkas plugin (.zip) wajib diunggah')
    return
  }

  acting.value = true
  try {
    const fd = new FormData()
    fd.append('version', versionForm.value.version)
    fd.append('changelog', versionForm.value.changelog)
    fd.append('is_active', versionForm.value.is_active ? '1' : '0')
    if (zipFile.value) fd.append('plugin', zipFile.value)

    if (editingVersion.value) {
      await api.post(`/admin/plugin-versions/${editingVersion.value.id}/update`, fd)
      toast.success('Versi berhasil diperbarui')
    } else {
      const { data } = await api.post(`/admin/plugins/${openId.value}/versions`, fd)
      toast.success(
        data?.notified_users
          ? `Versi dirilis — ${data.notified_users} user diberi tahu`
          : 'Versi berhasil dirilis',
      )
    }

    versionFormOpen.value = false
    await Promise.all([load(), loadVersions(openId.value)])
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal menyimpan versi'))
  } finally {
    acting.value = false
  }
}

/* ---- Hapus ---- */
async function confirmDelete() {
  const t = deleteTarget.value
  if (!t) return

  acting.value = true
  try {
    if (t.kind === 'plugin') {
      await api.delete(`/admin/plugins/${t.item.id}`)
      toast.success('Plugin beserta seluruh versinya dihapus')
      if (openId.value === t.item.id) openId.value = null
      await load()
    } else {
      await api.delete(`/admin/plugin-versions/${t.item.id}`)
      toast.success(`Versi ${t.item.version} dihapus`)
      await Promise.all([load(), loadVersions(openId.value)])
    }
    deleteTarget.value = null
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal menghapus'))
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
        <p class="text-muted">Kelola plugin dan riwayat versinya.</p>
      </div>

      <button class="btn btn-primary" type="button" @click="openCreatePlugin">
        <Plus :size="17" />
        Tambah Plugin
      </button>
    </header>

    <div v-if="!loading && items.length" class="control-row">
      <div class="input-group toolbar-search">
        <Search class="input-icon" :size="16" />
        <input v-model="search" class="input" type="search" placeholder="Cari nama plugin atau versi…">
      </div>
    </div>

    <section class="card">
      <SkeletonRows v-if="loading" :rows="3" :height="80" />

      <EmptyState
        v-else-if="!items.length"
        :icon="Package"
        title="Belum ada plugin"
        description="Tambahkan plugin terlebih dahulu, lalu rilis versinya."
      >
        <template #action>
          <button class="btn btn-primary" type="button" @click="openCreatePlugin">
            <Plus :size="16" />
            Tambah Plugin
          </button>
        </template>
      </EmptyState>

      <EmptyState
        v-else-if="!visibleItems.length"
        :icon="Search"
        title="Tidak ada yang cocok"
        description="Coba kata kunci lain."
      >
        <template #action>
          <button class="btn btn-secondary" type="button" @click="search = ''">
            Hapus filter
          </button>
        </template>
      </EmptyState>

      <div v-else class="plugin-list">
        <article v-for="p in visibleItems" :key="p.id" class="plugin">
          <div class="plugin-main">
            <span class="icon-chip tone-neutral plugin-icon">
              <Package :size="20" />
            </span>

            <div class="plugin-text">
              <header class="plugin-head">
                <h3>{{ p.name }}</h3>
                <span v-if="p.is_active" class="badge tone-ok">
                  <CircleCheck :size="13" />
                  Aktif
                </span>
                <span v-else class="badge tone-neutral">
                  <EyeOff :size="13" />
                  Nonaktif
                </span>
              </header>

              <p class="text-caption plugin-slug">{{ p.slug }}</p>
              <p v-if="p.description" class="plugin-desc">{{ p.description }}</p>

              <p class="text-caption">
                {{ p.version_count }} versi
                <template v-if="p.latest_version">
                  · Terbaru v{{ p.latest_version }}
                  <template v-if="p.latest_released_at">
                    ({{ formatDate(p.latest_released_at, false) }})
                  </template>
                </template>
                <template v-else>· belum ada versi</template>
              </p>
            </div>

            <div class="plugin-actions">
              <button class="btn btn-sm btn-secondary" type="button" @click="toggleVersions(p)">
                <Layers :size="14" />
                Versi
                <ChevronDown :size="14" class="chev" :class="{ open: openId === p.id }" />
              </button>
              <button class="btn btn-sm btn-secondary" type="button" @click="openEditPlugin(p)">
                <Pencil :size="14" />
                Edit
              </button>
              <button
                class="btn btn-sm btn-secondary danger"
                type="button"
                @click="deleteTarget = { kind: 'plugin', item: p }"
              >
                <Trash2 :size="14" />
                Hapus
              </button>
            </div>
          </div>

          <!-- ---------- Riwayat versi ---------- -->
          <div v-if="openId === p.id" class="versions">
            <header class="versions-head">
              <h4>Riwayat versi</h4>
              <button class="btn btn-sm btn-primary" type="button" @click="openCreateVersion(p)">
                <Plus :size="14" />
                Tambah Versi
              </button>
            </header>

            <SkeletonRows v-if="versionsLoading" :rows="2" :height="52" />

            <p v-else-if="!versions.length" class="versions-empty">
              Belum ada versi. Rilis versi pertama agar user dapat mengunduhnya.
            </p>

            <ul v-else class="version-list">
              <li v-for="v in versions" :key="v.id" class="version">
                <span class="icon-chip icon-chip-sm tone-neutral">
                  <FileArchive :size="15" />
                </span>

                <div class="version-text">
                  <span class="version-head">
                    <strong>v{{ v.version }}</strong>
                    <!-- is_latest datang dari backend, bukan dari posisi baris. -->
                    <span v-if="v.is_latest" class="badge tone-ok">Terbaru</span>
                    <span v-if="!v.is_active" class="badge tone-neutral">
                      <EyeOff :size="12" />
                      Disembunyikan
                    </span>
                  </span>
                  <span class="text-caption">{{ v.filename }}</span>
                  <span v-if="v.changelog" class="version-log">{{ v.changelog }}</span>
                  <span class="text-caption">Rilis {{ formatDate(v.released_at, false) }}</span>
                </div>

                <div class="version-actions">
                  <button class="btn btn-sm btn-secondary" type="button" @click="openEditVersion(v)">
                    <Pencil :size="13" />
                  </button>
                  <button
                    class="btn btn-sm btn-secondary danger"
                    type="button"
                    @click="deleteTarget = { kind: 'version', item: v }"
                  >
                    <Trash2 :size="13" />
                  </button>
                </div>
              </li>
            </ul>
          </div>
        </article>
      </div>
    </section>

    <!-- ---------- Form plugin ---------- -->
    <ModalDialog
      :open="pluginFormOpen"
      :title="editingPlugin ? 'Edit Plugin' : 'Tambah Plugin'"
      :subtitle="editingPlugin ? editingPlugin.name : 'Identitas plugin; versinya ditambahkan setelah ini'"
      @close="pluginFormOpen = false"
    >
      <form id="plugin-form" class="form" @submit.prevent="savePlugin">
        <div class="field">
          <label class="field-label" for="pl-name">Nama plugin</label>
          <input id="pl-name" v-model="pluginForm.name" class="input" type="text" placeholder="Apeiron Kit" required :disabled="acting">
        </div>

        <div class="field">
          <label class="field-label" for="pl-slug">Slug</label>
          <input id="pl-slug" v-model="pluginForm.slug" class="input" type="text" placeholder="apeiron-kit" :disabled="acting">
          <p class="field-hint">
            Kosongkan untuk diturunkan dari nama. Slug menghubungkan plugin
            dengan lisensi, jadi mengubahnya pada plugin yang sudah punya
            lisensi sebaiknya dihindari.
          </p>
        </div>

        <div class="field">
          <label class="field-label" for="pl-desc">Deskripsi</label>
          <textarea id="pl-desc" v-model="pluginForm.description" class="textarea" rows="3" :disabled="acting" />
        </div>

        <label class="check">
          <input v-model="pluginForm.is_active" type="checkbox" :disabled="acting">
          <span>Tampilkan plugin ini ke user</span>
        </label>
      </form>

      <template #footer>
        <button class="btn btn-secondary" type="button" @click="pluginFormOpen = false">Batal</button>
        <button class="btn btn-primary" type="submit" form="plugin-form" :disabled="acting">
          {{ acting ? 'Menyimpan…' : editingPlugin ? 'Simpan Perubahan' : 'Tambah Plugin' }}
        </button>
      </template>
    </ModalDialog>

    <!-- ---------- Form versi ---------- -->
    <ModalDialog
      :open="versionFormOpen"
      :title="editingVersion ? 'Edit Versi' : 'Tambah Versi'"
      :subtitle="editingVersion ? `v${editingVersion.version}` : 'Versi sebelumnya tetap tersimpan dan tetap bisa diunduh'"
      @close="versionFormOpen = false"
    >
      <form id="version-form" class="form" @submit.prevent="saveVersion">
        <div class="field">
          <label class="field-label" for="v-number">Nomor versi</label>
          <input id="v-number" v-model="versionForm.version" class="input" type="text" placeholder="1.0.3" required :disabled="acting">
          <p class="field-hint">Diawali angka, misalnya 1.0.3. Tidak boleh sama dengan versi lain pada plugin ini.</p>
        </div>

        <div class="field">
          <label class="field-label" for="v-changelog">Changelog</label>
          <textarea id="v-changelog" v-model="versionForm.changelog" class="textarea" rows="4" placeholder="Apa yang berubah di rilis ini…" :disabled="acting" />
        </div>

        <div class="field">
          <span class="field-label">
            Berkas plugin (.zip){{ editingVersion ? ' — opsional' : '' }}
          </span>

          <label class="zip-picker" :class="{ filled: zipFile }">
            <span class="icon-chip icon-chip-sm tone-neutral">
              <FileArchive :size="16" />
            </span>
            <span class="zip-text">
              {{ zipFile ? zipFile.name : 'Pilih berkas ZIP (maks. 50MB)' }}
            </span>
            <input class="sr-only" type="file" accept=".zip" @change="onZipChange">
          </label>

          <p v-if="editingVersion" class="field-hint">
            Kosongkan bila hanya ingin mengubah nomor versi atau changelog.
            Berkas versi lain tidak akan tersentuh.
          </p>
        </div>

        <label class="check">
          <input v-model="versionForm.is_active" type="checkbox" :disabled="acting">
          <span>Tampilkan versi ini ke user</span>
        </label>
      </form>

      <template #footer>
        <button class="btn btn-secondary" type="button" @click="versionFormOpen = false">Batal</button>
        <button class="btn btn-primary" type="submit" form="version-form" :disabled="acting">
          {{ acting ? 'Menyimpan…' : editingVersion ? 'Simpan Perubahan' : 'Rilis Versi' }}
        </button>
      </template>
    </ModalDialog>

    <!-- ---------- Konfirmasi hapus ---------- -->
    <ModalDialog
      :open="!!deleteTarget"
      :title="deleteTarget?.kind === 'plugin' ? 'Hapus Plugin' : 'Hapus Versi'"
      size="sm"
      @close="deleteTarget = null"
    >
      <div class="confirm">
        <span class="icon-chip tone-err confirm-icon">
          <TriangleAlert :size="20" />
        </span>

        <p v-if="deleteTarget?.kind === 'plugin'">
          Hapus <strong>{{ deleteTarget?.item?.name }}</strong> beserta
          <strong>{{ deleteTarget?.item?.version_count }} versi</strong> dan seluruh berkasnya?
          Lisensi yang sudah terbit tidak terpengaruh, tetapi user tidak akan
          bisa mengunduh plugin ini lagi.
        </p>
        <p v-else>
          Hapus <strong>v{{ deleteTarget?.item?.version }}</strong>? Hanya versi
          ini yang dihapus — plugin dan versi lainnya tetap utuh.
        </p>
      </div>

      <template #footer>
        <button class="btn btn-secondary" type="button" @click="deleteTarget = null">Batal</button>
        <button class="btn btn-danger" type="button" :disabled="acting" @click="confirmDelete">
          {{ acting ? 'Menghapus…' : 'Hapus' }}
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

/* ---- Daftar plugin ---- */
.plugin-list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  padding: var(--sp-5) var(--sp-6);
}

.plugin {
  border: 1px solid var(--border);
  border-radius: var(--r);
  transition: border-color var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
}
.plugin:hover {
  border-color: var(--brand-border);
  box-shadow: var(--sh-card);
}

.plugin-main {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-4);
  padding: var(--sp-5);
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
  flex-wrap: wrap;
  gap: var(--sp-3);
}
.plugin-head h3 {
  font-size: var(--fs-h3);
}

.plugin-slug {
  font-family: var(--font-mono);
  word-break: break-all;
}

.plugin-desc {
  margin-top: var(--sp-1);
  font-size: var(--fs-label);
  color: var(--text-body);
  line-height: var(--lh-normal);
}

.plugin-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-2);
  flex-shrink: 0;
}

.chev {
  transition: transform var(--dur) var(--ease);
}
.chev.open {
  transform: rotate(180deg);
}

/* ---- Riwayat versi ---- */
.versions {
  padding: var(--sp-5);
  border-top: 1px solid var(--border);
  background: var(--bg-subtle);
  border-radius: 0 0 var(--r) var(--r);
}

.versions-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  margin-bottom: var(--sp-4);
}
.versions-head h4 {
  font-size: var(--fs-label);
  font-weight: var(--fw-semibold);
  color: var(--text-heading);
}

.versions-empty {
  font-size: var(--fs-caption);
  color: var(--text-muted);
}

.version-list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
}

.version {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-4);
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r-sm);
}

.version-text {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.version-head {
  display: flex;
  align-items: center;
  flex-wrap: wrap;
  gap: var(--sp-2);
  color: var(--text-heading);
}

.version-log {
  font-size: var(--fs-caption);
  color: var(--text-body);
  line-height: var(--lh-snug);
}

.version-actions {
  display: flex;
  gap: var(--sp-2);
  flex-shrink: 0;
}

/* ---- Form ---- */
.zip-picker {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-4);
  border: 1px dashed var(--border-strong, var(--border));
  border-radius: var(--r);
  cursor: pointer;
  transition: border-color var(--dur) var(--ease), background var(--dur) var(--ease);
}
.zip-picker:hover {
  border-color: var(--brand);
}
.zip-picker.filled {
  border-style: solid;
  border-color: var(--brand-border);
  background: var(--brand-soft);
}
.zip-text {
  flex: 1;
  min-width: 0;
  font-size: var(--fs-label);
  color: var(--text-body);
  word-break: break-all;
}

.check {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  font-size: var(--fs-label);
  color: var(--text-body);
  cursor: pointer;
}

.confirm {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-4);
}
.confirm-icon {
  width: 44px;
  height: 44px;
  border-radius: var(--r);
  background: var(--tone-bg);
  flex-shrink: 0;
}

.btn.danger {
  color: var(--err);
}
.btn.danger:hover:not(:disabled) {
  border-color: var(--err);
  background: var(--err-soft);
}

@media (max-width: 720px) {
  .plugin-main {
    flex-wrap: wrap;
  }
  .plugin-actions {
    width: 100%;
  }
  .plugin-actions .btn {
    flex: 1;
  }
}
</style>
