<script setup>
/**
 * Manajemen user.
 *
 * Endpoint & aturan backend dipertahankan:
 *   GET    /admin/users?page=
 *   POST   /admin/users                  { name, email, password, username?, role?, is_active? }
 *   PUT    /admin/users/{id}             (password opsional — kosong = tidak diubah)
 *   DELETE /admin/users/{id}             ← admin tidak bisa dihapus
 *   POST   /admin/users/{id}/toggle-status ← admin tidak bisa diubah statusnya
 */
import { ref, computed, onMounted } from 'vue'
import {
  Users,
  UserPlus,
  Pencil,
  Trash2,
  Power,
  ShieldCheck,
  TriangleAlert,
} from 'lucide-vue-next'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import ModalDialog from '@/components/ui/ModalDialog.vue'
import PaginationBar from '@/components/ui/PaginationBar.vue'
import { api, errorMessage } from '@/lib/api'
import { formatDate, initials } from '@/lib/format'
import { toast } from '@/stores/toast'

const loading = ref(true)
const items = ref([])
const pagination = ref({ current_page: 1, per_page: 15, total: 0, total_pages: 1 })
const page = ref(1)
const acting = ref(false)

// Form tambah/edit
const formOpen = ref(false)
const editing = ref(null) // null = mode tambah
const form = ref({
  name: '',
  username: '',
  email: '',
  password: '',
  role: 'user',
  is_active: 1,
})

// Konfirmasi hapus
const deleteTarget = ref(null)

const isEdit = computed(() => editing.value !== null)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/admin/users', { params: { page: page.value } })
    items.value = data.users || []
    pagination.value = data.pagination || pagination.value
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat daftar user'))
  } finally {
    loading.value = false
  }
}

function changePage(p) {
  page.value = p
  load()
}

function openCreate() {
  editing.value = null
  form.value = {
    name: '',
    username: '',
    email: '',
    password: '',
    role: 'user',
    is_active: 1,
  }
  formOpen.value = true
}

function openEdit(u) {
  editing.value = u
  form.value = {
    name: u.name || '',
    username: u.username || '',
    email: u.email || '',
    password: '', // kosong = password tidak diubah
    role: u.role || 'user',
    is_active: Number(u.is_active) ? 1 : 0,
  }
  formOpen.value = true
}

async function save() {
  if (acting.value) return
  acting.value = true
  try {
    if (isEdit.value) {
      const payload = {
        name: form.value.name.trim(),
        username: form.value.username.trim(),
        email: form.value.email.trim(),
        role: form.value.role,
        is_active: form.value.is_active,
      }
      // Kirim password hanya bila admin benar-benar mengisinya.
      if (form.value.password) payload.password = form.value.password
      await api.put(`/admin/users/${editing.value.id}`, payload)
      toast.success('User berhasil diperbarui')
    } else {
      await api.post('/admin/users', {
        name: form.value.name.trim(),
        username: form.value.username.trim() || undefined,
        email: form.value.email.trim(),
        password: form.value.password,
        role: form.value.role,
        is_active: form.value.is_active,
      })
      toast.success('User berhasil dibuat')
    }
    formOpen.value = false
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal menyimpan user'))
  } finally {
    acting.value = false
  }
}

async function toggleStatus(u) {
  if (acting.value) return
  acting.value = true
  try {
    await api.post(`/admin/users/${u.id}/toggle-status`)
    toast.success('Status user berhasil diubah')
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal mengubah status user'))
  } finally {
    acting.value = false
  }
}

async function confirmDelete() {
  if (!deleteTarget.value || acting.value) return
  acting.value = true
  try {
    await api.delete(`/admin/users/${deleteTarget.value.id}`)
    toast.success('User berhasil dihapus')
    deleteTarget.value = null
    await load()
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal menghapus user'))
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
        <h1>Manajemen User</h1>
        <p class="text-muted">Kelola akun pengguna portal.</p>
      </div>

      <button class="btn btn-primary" type="button" @click="openCreate">
        <UserPlus :size="17" />
        Tambah User
      </button>
    </header>

    <section class="card">
      <SkeletonRows v-if="loading" :rows="6" />

      <EmptyState
        v-else-if="!items.length"
        :icon="Users"
        title="Belum ada user"
        description="Tambahkan user pertama untuk memulai."
      >
        <template #action>
          <button class="btn btn-primary" type="button" @click="openCreate">
            <UserPlus :size="16" />
            Tambah User
          </button>
        </template>
      </EmptyState>

      <template v-else>
        <div class="table-wrap">
          <table class="table">
            <thead>
              <tr>
                <th>User</th>
                <th>Username</th>
                <th>Peran</th>
                <th>Status</th>
                <th>Terakhir masuk</th>
                <th style="text-align: right">Aksi</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in items" :key="u.id">
                <td>
                  <span class="user-cell">
                    <span class="avatar avatar-sm">{{ initials(u.name) }}</span>
                    <span class="user-cell-text">
                      <span class="cell-strong">{{ u.name }}</span>
                      <span class="text-caption">{{ u.email }}</span>
                    </span>
                  </span>
                </td>
                <td class="text-mono">{{ u.username || '—' }}</td>
                <td>
                  <span
                    class="badge"
                    :class="u.role === 'admin' ? 'tone-neutral' : 'tone-brand'"
                  >
                    <ShieldCheck v-if="u.role === 'admin'" :size="13" />
                    {{ u.role === 'admin' ? 'Admin' : 'User' }}
                  </span>
                </td>
                <td>
                  <span
                    class="badge badge-dot"
                    :class="Number(u.is_active) ? 'tone-ok' : 'tone-err'"
                  >
                    {{ Number(u.is_active) ? 'Aktif' : 'Nonaktif' }}
                  </span>
                </td>
                <td>{{ u.last_login_at ? formatDate(u.last_login_at) : '—' }}</td>
                <td>
                  <span class="cell-actions">
                    <button
                      class="btn btn-sm btn-secondary btn-icon"
                      type="button"
                      aria-label="Edit user"
                      @click="openEdit(u)"
                    >
                      <Pencil :size="14" />
                    </button>

                    <!-- Backend menolak toggle & hapus untuk akun admin -->
                    <button
                      class="btn btn-sm btn-secondary btn-icon"
                      type="button"
                      :aria-label="Number(u.is_active) ? 'Nonaktifkan user' : 'Aktifkan user'"
                      :disabled="u.role === 'admin' || acting"
                      :title="u.role === 'admin' ? 'Status admin tidak dapat diubah' : ''"
                      @click="toggleStatus(u)"
                    >
                      <Power :size="14" />
                    </button>

                    <button
                      class="btn btn-sm btn-secondary btn-icon danger"
                      type="button"
                      aria-label="Hapus user"
                      :disabled="u.role === 'admin' || acting"
                      :title="u.role === 'admin' ? 'Admin tidak dapat dihapus' : ''"
                      @click="deleteTarget = u"
                    >
                      <Trash2 :size="14" />
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

    <!-- ---------- Modal tambah/edit ---------- -->
    <ModalDialog
      :open="formOpen"
      :title="isEdit ? 'Edit User' : 'Tambah User'"
      :subtitle="isEdit ? `Mengubah data ${editing?.name}` : 'Buat akun pengguna baru'"
      @close="formOpen = false"
    >
      <form id="user-form" class="form" @submit.prevent="save">
        <div class="field">
          <label class="field-label" for="u-name">Nama lengkap</label>
          <input
            id="u-name"
            v-model="form.name"
            class="input"
            type="text"
            required
            :disabled="acting"
          >
        </div>

        <div class="form-row">
          <div class="field">
            <label class="field-label" for="u-username">Username</label>
            <input
              id="u-username"
              v-model="form.username"
              class="input"
              type="text"
              :required="false"
              :disabled="acting"
            >
            <p class="field-hint">Huruf, angka, underscore.</p>
          </div>

          <div class="field">
            <label class="field-label" for="u-email">Email</label>
            <input
              id="u-email"
              v-model="form.email"
              class="input"
              type="email"
              required
              :disabled="acting"
            >
          </div>
        </div>

        <div class="field">
          <label class="field-label" for="u-password">
            Password {{ isEdit ? '(kosongkan bila tidak diubah)' : '' }}
          </label>
          <input
            id="u-password"
            v-model="form.password"
            class="input"
            type="password"
            autocomplete="new-password"
            :required="!isEdit"
            :placeholder="isEdit ? 'Biarkan kosong untuk mempertahankan' : 'Minimal 8 karakter'"
            :disabled="acting"
          >
        </div>

        <div class="form-row">
          <div class="field">
            <label class="field-label" for="u-role">Peran</label>
            <select id="u-role" v-model="form.role" class="select" :disabled="acting">
              <option value="user">User</option>
              <option value="admin">Admin</option>
            </select>
          </div>

          <div class="field">
            <label class="field-label" for="u-status">Status</label>
            <select
              id="u-status"
              v-model.number="form.is_active"
              class="select"
              :disabled="acting"
            >
              <option :value="1">Aktif</option>
              <option :value="0">Nonaktif</option>
            </select>
          </div>
        </div>
      </form>

      <template #footer>
        <button class="btn btn-secondary" type="button" @click="formOpen = false">
          Batal
        </button>
        <button class="btn btn-primary" type="submit" form="user-form" :disabled="acting">
          {{ acting ? 'Menyimpan…' : isEdit ? 'Simpan Perubahan' : 'Tambah User' }}
        </button>
      </template>
    </ModalDialog>

    <!-- ---------- Konfirmasi hapus ---------- -->
    <ModalDialog
      :open="!!deleteTarget"
      title="Hapus User"
      size="sm"
      @close="deleteTarget = null"
    >
      <div class="confirm">
        <span class="icon-chip tone-err confirm-icon"><TriangleAlert :size="22" /></span>
        <p>
          Yakin ingin menghapus <strong>{{ deleteTarget?.name }}</strong>?
          Tindakan ini tidak dapat dibatalkan.
        </p>
      </div>

      <template #footer>
        <button class="btn btn-secondary" type="button" @click="deleteTarget = null">
          Batal
        </button>
        <button class="btn btn-danger" type="button" :disabled="acting" @click="confirmDelete">
          {{ acting ? 'Menghapus…' : 'Hapus User' }}
        </button>
      </template>
    </ModalDialog>
  </div>
</template>

<style scoped>
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

.btn-icon.danger:hover:not(:disabled) {
  background: var(--err-soft);
  border-color: var(--err-border);
  color: var(--err);
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
  border-radius: var(--r);
}
.confirm p {
  color: var(--text-body);
  line-height: var(--lh-normal);
}
.confirm strong {
  color: var(--text-heading);
}
</style>
