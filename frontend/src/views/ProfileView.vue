<script setup>
/**
 * Profil — GET /user/profile, PUT /user/profile.
 *
 * Backend hanya menerima perubahan `name` dan pasangan
 * current_password + new_password. Username/email tidak bisa diubah user,
 * jadi ditampilkan read-only (sama seperti sebelumnya).
 */
import { ref, computed, onMounted } from 'vue'
import {
  User,
  AtSign,
  Mail,
  Lock,
  Eye,
  EyeOff,
  KeyRound,
  Receipt,
  Save,
  ShieldCheck,
  CalendarDays,
} from 'lucide-vue-next'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import { api, errorMessage } from '@/lib/api'
import { formatDate, initials } from '@/lib/format'
import { patchUser } from '@/stores/auth'
import { toast } from '@/stores/toast'

const loading = ref(true)
const savingProfile = ref(false)
const savingPassword = ref(false)

const profile = ref(null)
const stats = ref({ licenses: 0, transactions: 0 })

const nameForm = ref({ name: '' })
const pwForm = ref({ current_password: '', new_password: '', confirm: '' })
const showPw = ref(false)

const pwMismatch = computed(
  () => !!pwForm.value.confirm && pwForm.value.new_password !== pwForm.value.confirm
)
const pwTooShort = computed(
  () => !!pwForm.value.new_password && pwForm.value.new_password.length < 8
)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/user/profile')
    profile.value = data.user
    stats.value = data.stats || { licenses: 0, transactions: 0 }
    nameForm.value.name = data.user?.name || ''
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat profil'))
  } finally {
    loading.value = false
  }
}

async function saveName() {
  const name = nameForm.value.name.trim()
  if (!name || savingProfile.value) return
  savingProfile.value = true
  try {
    await api.put('/user/profile', { name })
    patchUser({ name })
    if (profile.value) profile.value.name = name
    toast.success('Profil berhasil diperbarui')
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memperbarui profil'))
  } finally {
    savingProfile.value = false
  }
}

async function savePassword() {
  if (savingPassword.value) return
  if (pwTooShort.value) {
    toast.error('Password baru minimal 8 karakter')
    return
  }
  if (pwMismatch.value) {
    toast.error('Konfirmasi password tidak cocok')
    return
  }
  savingPassword.value = true
  try {
    await api.put('/user/profile', {
      current_password: pwForm.value.current_password,
      new_password: pwForm.value.new_password,
    })
    pwForm.value = { current_password: '', new_password: '', confirm: '' }
    toast.success('Password berhasil diubah')
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal mengubah password'))
  } finally {
    savingPassword.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>Profil Saya</h1>
        <p class="text-muted">Kelola informasi akun dan keamanan Anda.</p>
      </div>
    </header>

    <SkeletonRows v-if="loading" :rows="4" :height="72" />

    <div v-else class="split">
      <!-- ---------- Kartu identitas ---------- -->
      <aside class="stack">
        <section class="card identity">
          <span class="avatar avatar-lg">{{ initials(profile?.name) }}</span>
          <h2 class="identity-name">{{ profile?.name }}</h2>
          <p class="text-caption">{{ profile?.email }}</p>

          <span class="badge tone-brand identity-role">
            <ShieldCheck :size="13" />
            {{ profile?.role === 'admin' ? 'Administrator' : 'Member' }}
          </span>

          <hr class="divider identity-sep">

          <dl class="identity-meta">
            <div>
              <dt><KeyRound :size="14" /> Lisensi</dt>
              <dd>{{ stats.licenses }}</dd>
            </div>
            <div>
              <dt><Receipt :size="14" /> Transaksi</dt>
              <dd>{{ stats.transactions }}</dd>
            </div>
          </dl>

          <hr class="divider">

          <dl class="identity-dates">
            <div>
              <dt><CalendarDays :size="14" /> Bergabung</dt>
              <dd>{{ formatDate(profile?.created_at, false) }}</dd>
            </div>
            <div v-if="profile?.last_login_at">
              <dt><CalendarDays :size="14" /> Terakhir masuk</dt>
              <dd>{{ formatDate(profile.last_login_at) }}</dd>
            </div>
          </dl>
        </section>
      </aside>

      <!-- ---------- Form ---------- -->
      <div class="stack">
        <!-- Informasi akun -->
        <section class="card">
          <header class="card-header">
            <h3>
              <span class="icon-chip icon-chip-sm tone-brand"><User :size="16" /></span>
              Informasi Akun
            </h3>
          </header>

          <form class="card-body form" @submit.prevent="saveName">
            <div class="field">
              <label class="field-label" for="pf-name">Nama lengkap</label>
              <div class="input-group">
                <User class="input-icon" :size="17" />
                <input
                  id="pf-name"
                  v-model="nameForm.name"
                  class="input"
                  type="text"
                  required
                  :disabled="savingProfile"
                >
              </div>
            </div>

            <div class="form-row">
              <div class="field">
                <label class="field-label" for="pf-username">Username</label>
                <div class="input-group">
                  <AtSign class="input-icon" :size="17" />
                  <input
                    id="pf-username"
                    class="input"
                    type="text"
                    :value="profile?.username || '-'"
                    readonly
                    disabled
                  >
                </div>
                <p class="field-hint">Username tidak dapat diubah.</p>
              </div>

              <div class="field">
                <label class="field-label" for="pf-email">Email</label>
                <div class="input-group">
                  <Mail class="input-icon" :size="17" />
                  <input
                    id="pf-email"
                    class="input"
                    type="email"
                    :value="profile?.email"
                    readonly
                    disabled
                  >
                </div>
                <p class="field-hint">Hubungi admin untuk mengubah email.</p>
              </div>
            </div>

            <div class="form-actions">
              <button class="btn btn-primary" type="submit" :disabled="savingProfile">
                <Save :size="16" />
                {{ savingProfile ? 'Menyimpan…' : 'Simpan Perubahan' }}
              </button>
            </div>
          </form>
        </section>

        <!-- Ganti password -->
        <section class="card">
          <header class="card-header">
            <h3>
              <span class="icon-chip icon-chip-sm tone-err"><Lock :size="16" /></span>
              Ubah Password
            </h3>
          </header>

          <form class="card-body form" @submit.prevent="savePassword">
            <div class="field">
              <label class="field-label" for="pw-current">Password saat ini</label>
              <div class="input-group">
                <Lock class="input-icon" :size="17" />
                <input
                  id="pw-current"
                  v-model="pwForm.current_password"
                  class="input has-toggle"
                  :type="showPw ? 'text' : 'password'"
                  autocomplete="current-password"
                  required
                  :disabled="savingPassword"
                >
                <button
                  class="input-toggle"
                  type="button"
                  :aria-label="showPw ? 'Sembunyikan password' : 'Tampilkan password'"
                  @click="showPw = !showPw"
                >
                  <component :is="showPw ? EyeOff : Eye" :size="17" />
                </button>
              </div>
            </div>

            <div class="form-row">
              <div class="field">
                <label class="field-label" for="pw-new">Password baru</label>
                <div class="input-group">
                  <Lock class="input-icon" :size="17" />
                  <input
                    id="pw-new"
                    v-model="pwForm.new_password"
                    class="input"
                    :class="{ 'has-error': pwTooShort }"
                    type="password"
                    autocomplete="new-password"
                    placeholder="Minimal 8 karakter"
                    required
                    :disabled="savingPassword"
                  >
                </div>
                <p v-if="pwTooShort" class="field-error">Minimal 8 karakter.</p>
              </div>

              <div class="field">
                <label class="field-label" for="pw-confirm">Konfirmasi password</label>
                <div class="input-group">
                  <Lock class="input-icon" :size="17" />
                  <input
                    id="pw-confirm"
                    v-model="pwForm.confirm"
                    class="input"
                    :class="{ 'has-error': pwMismatch }"
                    type="password"
                    autocomplete="new-password"
                    required
                    :disabled="savingPassword"
                  >
                </div>
                <p v-if="pwMismatch" class="field-error">Password tidak cocok.</p>
              </div>
            </div>

            <div class="form-actions">
              <button class="btn btn-primary" type="submit" :disabled="savingPassword">
                <Lock :size="16" />
                {{ savingPassword ? 'Menyimpan…' : 'Ubah Password' }}
              </button>
            </div>
          </form>
        </section>
      </div>
    </div>
  </div>
</template>

<style scoped>
.split {
  display: grid;
  grid-template-columns: minmax(0, 1fr);
  gap: var(--sp-6);
  align-items: start;
}

@media (min-width: 1100px) {
  .split {
    grid-template-columns: 320px minmax(0, 1fr);
  }
}

/* ---- Identitas ---- */
.identity {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: var(--sp-7) var(--sp-6);
  text-align: center;
}

.identity-name {
  margin-top: var(--sp-4);
  font-size: var(--fs-h3);
}

.identity-role {
  margin-top: var(--sp-3);
}

.identity-sep {
  width: 100%;
  margin: var(--sp-6) 0 var(--sp-5);
}

.identity-meta,
.identity-dates {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: var(--sp-4);
  width: 100%;
}

.identity-dates {
  grid-template-columns: 1fr;
  gap: var(--sp-3);
  margin-top: var(--sp-5);
  text-align: left;
}

.identity-meta dt,
.identity-dates dt {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: var(--sp-1);
  font-size: var(--fs-micro);
  font-weight: var(--fw-semibold);
  letter-spacing: var(--tracking-wide);
  text-transform: uppercase;
  color: var(--text-muted);
}
.identity-dates dt {
  justify-content: flex-start;
}

.identity-meta dd {
  margin-top: var(--sp-1);
  font-size: var(--fs-h2);
  font-weight: var(--fw-bold);
  color: var(--text-heading);
}

.identity-dates dd {
  margin-top: 2px;
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
}

.divider {
  width: 100%;
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
@media (min-width: 640px) {
  .form-row {
    grid-template-columns: 1fr 1fr;
  }
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  padding-top: var(--sp-1);
}

.input:disabled {
  background: var(--bg-subtle);
  color: var(--text-muted);
  cursor: not-allowed;
}

@media (max-width: 640px) {
  .form-actions .btn {
    width: 100%;
  }
}
</style>
