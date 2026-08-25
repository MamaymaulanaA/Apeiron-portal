<script setup>
/**
 * Kelola Group — CRUD grup komunitas.
 *
 * Platform TIDAK diisi admin. Backend menurunkannya dari host pada link setiap
 * kali dibaca (CommunityGroup::detectPlatform), jadi lambang WhatsApp tidak
 * mungkin menempel pada link Telegram karena admin mengganti link tanpa
 * mengganti platform. Pratinjau di form memakai aturan yang sama supaya admin
 * melihat hasilnya sebelum menyimpan.
 */
import { ref, computed, onMounted } from 'vue'
import {
  UsersRound,
  Plus,
  Pencil,
  Trash2,
  ExternalLink,
  RotateCcw,
  EyeOff,
} from 'lucide-vue-next'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import ModalDialog from '@/components/ui/ModalDialog.vue'
import PlatformIcon from '@/components/ui/PlatformIcon.vue'
import { api, errorMessage } from '@/lib/api'
import { platformLabel, platformTone } from '@/lib/community'
import { toast } from '@/stores/toast'

const loading = ref(true)
const loadError = ref('')
const groups = ref([])

const formOpen = ref(false)
const saving = ref(false)
const editing = ref(null)
const form = ref(blankForm())
const formError = ref('')

const confirmOpen = ref(false)
const deleting = ref(null)
const removing = ref(false)

function blankForm() {
  return { name: '', description: '', link: '', is_active: true, sort_order: 0 }
}

/**
 * Cermin dari CommunityGroup::detectPlatform() di backend — dipakai hanya untuk
 * pratinjau. Yang tersimpan tetap link mentahnya; platform selalu dihitung
 * ulang server saat membaca.
 */
const HOSTS = {
  whatsapp: ['chat.whatsapp.com', 'wa.me', 'whatsapp.com', 'api.whatsapp.com'],
  telegram: ['t.me', 'telegram.me', 'telegram.org'],
  discord: ['discord.gg', 'discord.com', 'discordapp.com'],
  facebook: ['facebook.com', 'fb.me', 'fb.com', 'm.me'],
  line: ['line.me'],
  slack: ['slack.com'],
}

const previewPlatform = computed(() => {
  try {
    const host = new URL(form.value.link).hostname.toLowerCase().replace(/^www\./, '')
    for (const [platform, hosts] of Object.entries(HOSTS)) {
      if (hosts.some((h) => host === h || host.endsWith('.' + h))) return platform
    }
  } catch {
    /* link belum lengkap atau belum valid */
  }
  return 'other'
})

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/admin/groups')
    groups.value = data.groups || []
  } catch (e) {
    loadError.value = errorMessage(e, 'Gagal memuat group')
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

function openEdit(group) {
  editing.value = group
  form.value = {
    name: group.name,
    description: group.description || '',
    link: group.link,
    is_active: !!Number(group.is_active),
    sort_order: Number(group.sort_order) || 0,
  }
  formError.value = ''
  formOpen.value = true
}

async function save() {
  formError.value = ''

  // Pemeriksaan di sini hanya agar admin tidak perlu menunggu jaringan untuk
  // kesalahan yang jelas. Backend tetap memvalidasi ulang — termasuk skema
  // link, yang tidak boleh diserahkan ke klien.
  if (!form.value.name.trim()) {
    formError.value = 'Nama group wajib diisi'
    return
  }
  if (!form.value.link.trim()) {
    formError.value = 'Link group wajib diisi'
    return
  }

  saving.value = true
  try {
    const payload = {
      name: form.value.name.trim(),
      description: form.value.description.trim(),
      link: form.value.link.trim(),
      is_active: form.value.is_active ? 1 : 0,
      sort_order: Number(form.value.sort_order) || 0,
    }

    if (editing.value) {
      await api.put(`/admin/groups/${editing.value.id}`, payload)
      toast.success('Group berhasil diperbarui')
    } else {
      await api.post('/admin/groups', payload)
      toast.success('Group berhasil ditambahkan')
    }

    formOpen.value = false
    await load()
  } catch (e) {
    formError.value = errorMessage(e, 'Gagal menyimpan group')
  } finally {
    saving.value = false
  }
}

function askDelete(group) {
  deleting.value = group
  confirmOpen.value = true
}

async function confirmDelete() {
  if (!deleting.value) return
  removing.value = true
  try {
    await api.delete(`/admin/groups/${deleting.value.id}`)
    toast.success('Group berhasil dihapus')
    confirmOpen.value = false
    deleting.value = null
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal menghapus group'))
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
        <h1>Kelola Group</h1>
        <p class="text-caption">
          Grup komunitas yang tampil di dashboard user yang transaksinya sudah disetujui.
        </p>
      </div>

      <button class="btn btn-primary" type="button" @click="openCreate">
        <Plus :size="16" />
        Tambah Group
      </button>
    </header>

    <SkeletonRows v-if="loading" :rows="3" :height="64" />

    <section v-else-if="loadError" class="card">
      <EmptyState :icon="UsersRound" title="Gagal memuat group" :description="loadError">
        <template #action>
          <button class="btn btn-secondary" type="button" @click="load">
            <RotateCcw :size="15" />
            Coba lagi
          </button>
        </template>
      </EmptyState>
    </section>

    <section v-else-if="!groups.length" class="card">
      <EmptyState
        :icon="UsersRound"
        title="Belum ada group"
        description="Tambahkan grup WhatsApp, Telegram, atau Discord agar user yang sudah disetujui bisa bergabung."
      >
        <template #action>
          <button class="btn btn-primary" type="button" @click="openCreate">
            <Plus :size="16" />
            Tambah Group
          </button>
        </template>
      </EmptyState>
    </section>

    <section v-else class="card">
      <div class="table-wrap">
        <table class="table">
          <thead>
            <tr>
              <th>Group</th>
              <th>Platform</th>
              <th>Status</th>
              <th>Urutan</th>
              <th class="col-action">Aksi</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="g in groups" :key="g.id">
              <td>
                <span class="grp-cell">
                  <span class="icon-chip icon-chip-sm" :class="platformTone(g.platform)">
                    <PlatformIcon :platform="g.platform" :size="15" />
                  </span>
                  <span class="grp-text">
                    <span class="cell-strong">{{ g.name }}</span>
                    <a
                      class="grp-link"
                      :href="g.link"
                      target="_blank"
                      rel="noopener noreferrer"
                    >
                      {{ g.link }}
                      <ExternalLink :size="12" />
                    </a>
                  </span>
                </span>
              </td>
              <td>{{ platformLabel(g.platform) }}</td>
              <td>
                <span class="badge" :class="Number(g.is_active) ? 'tone-ok' : 'tone-neutral'">
                  {{ Number(g.is_active) ? 'Aktif' : 'Nonaktif' }}
                </span>
              </td>
              <td>{{ g.sort_order }}</td>
              <td class="col-action">
                <span class="row-actions">
                  <button
                    class="btn btn-sm btn-secondary btn-icon"
                    type="button"
                    aria-label="Ubah group"
                    @click="openEdit(g)"
                  >
                    <Pencil :size="14" />
                  </button>
                  <button
                    class="btn btn-sm btn-danger btn-icon"
                    type="button"
                    aria-label="Hapus group"
                    @click="askDelete(g)"
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
      :title="editing ? 'Ubah Group' : 'Tambah Group'"
      subtitle="Platform terdeteksi otomatis dari link"
      @close="formOpen = false"
    >
      <form class="stack" @submit.prevent="save">
        <label class="field">
          <span class="label">Nama group <span class="req">*</span></span>
          <input v-model="form.name" class="input" type="text" maxlength="255" placeholder="Komunitas Apeiron Kit">
        </label>

        <label class="field">
          <span class="label">Link group <span class="req">*</span></span>
          <input
            v-model="form.link"
            class="input"
            type="url"
            maxlength="500"
            placeholder="https://chat.whatsapp.com/…"
          >
          <span class="hint">
            <span class="icon-chip icon-chip-sm" :class="platformTone(previewPlatform)">
              <PlatformIcon :platform="previewPlatform" :size="14" />
            </span>
            Terdeteksi sebagai <strong>{{ platformLabel(previewPlatform) }}</strong>
          </span>
        </label>

        <label class="field">
          <span class="label">Deskripsi</span>
          <textarea
            v-model="form.description"
            class="input textarea"
            rows="3"
            placeholder="Opsional — dipakai admin sebagai catatan, tidak wajib."
          />
        </label>

        <div class="field-row">
          <label class="field">
            <span class="label">Urutan tampil</span>
            <input v-model.number="form.sort_order" class="input" type="number" min="0">
          </label>

          <label class="check">
            <input v-model="form.is_active" type="checkbox">
            <span>
              Aktif
              <small class="text-caption">Nonaktif berarti tidak tampil di dashboard user.</small>
            </span>
          </label>
        </div>

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
    <ModalDialog
      :open="confirmOpen"
      title="Hapus group?"
      size="sm"
      @close="confirmOpen = false"
    >
      <p>
        <strong>{{ deleting?.name }}</strong> akan dihapus dan tidak lagi tampil di
        dashboard user. Tindakan ini tidak bisa dibatalkan.
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
.grp-cell {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}
.grp-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  line-height: 1.35;
}
.grp-link {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  font-size: var(--fs-caption);
  color: var(--text-muted);
  max-width: 320px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.grp-link:hover {
  color: var(--brand);
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
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  font-size: var(--fs-caption);
  color: var(--text-muted);
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
  align-items: flex-start;
}
.field-row .field {
  flex: 0 0 140px;
}

.check {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-2);
  flex: 1;
  min-width: 200px;
  font-size: var(--fs-label);
  color: var(--text-heading);
  /* Sejajarkan dengan baris input di sebelahnya, bukan dengan labelnya */
  padding-top: calc(var(--fs-label) * 1.4 + var(--sp-2));
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
  .check {
    padding-top: 0;
  }
}
</style>
