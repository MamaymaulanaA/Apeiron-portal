<script setup>
/**
 * Registrasi.
 * Aturan validasi dicerminkan dari AuthController::register() supaya user
 * mendapat umpan balik lebih awal — backend tetap jadi penentu akhir.
 */
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { User, AtSign, Mail, Lock, Eye, EyeOff, ArrowRight } from 'lucide-vue-next'
import AuthShell from '@/components/layout/AuthShell.vue'
import { register } from '@/stores/auth'
import { errorMessage } from '@/lib/api'
import { toast } from '@/stores/toast'

const router = useRouter()

const form = ref({ name: '', username: '', email: '', password: '' })
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')

const usernameValid = computed(
  () => !form.value.username || /^[a-zA-Z0-9_]{3,30}$/.test(form.value.username)
)
const passwordValid = computed(
  () => !form.value.password || form.value.password.length >= 8
)

async function onSubmit() {
  if (loading.value) return
  error.value = ''

  if (!usernameValid.value) {
    error.value = 'Username hanya boleh huruf, angka, underscore (3-30 karakter)'
    return
  }
  if (!passwordValid.value) {
    error.value = 'Password minimal 8 karakter'
    return
  }

  loading.value = true
  try {
    await register({
      name: form.value.name.trim(),
      username: form.value.username.trim(),
      email: form.value.email.trim(),
      password: form.value.password,
    })
    toast.success('Registrasi berhasil. Silakan masuk.')
    router.push('/login')
  } catch (e) {
    error.value = errorMessage(e, 'Gagal mendaftar. Silakan coba lagi.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthShell
    pitch-title="Mulai kelola lisensi Anda hari ini."
    pitch-text="Buat akun untuk mengunggah bukti transaksi dan mendapatkan lisensi Apeiron Kit."
  >
    <header class="auth-head">
      <h1>Buat akun baru</h1>
      <p class="text-muted">Isi data di bawah untuk mendaftar.</p>
    </header>

    <form class="auth-form" @submit.prevent="onSubmit">
      <p v-if="error" class="auth-error" role="alert">{{ error }}</p>

      <div class="field">
        <label class="field-label" for="name">Nama lengkap</label>
        <div class="input-group">
          <User class="input-icon" :size="17" />
          <input
            id="name"
            v-model="form.name"
            class="input"
            type="text"
            autocomplete="name"
            placeholder="Nama lengkap Anda"
            required
            :disabled="loading"
          >
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="username">Username</label>
        <div class="input-group">
          <AtSign class="input-icon" :size="17" />
          <input
            id="username"
            v-model="form.username"
            class="input"
            :class="{ 'has-error': !usernameValid }"
            type="text"
            autocomplete="username"
            placeholder="username_anda"
            required
            :disabled="loading"
          >
        </div>
        <p v-if="!usernameValid" class="field-error">
          Hanya huruf, angka, dan underscore (3–30 karakter).
        </p>
        <p v-else class="field-hint">Dipakai untuk masuk dan identitas akun.</p>
      </div>

      <div class="field">
        <label class="field-label" for="email">Email</label>
        <div class="input-group">
          <Mail class="input-icon" :size="17" />
          <input
            id="email"
            v-model="form.email"
            class="input"
            type="email"
            autocomplete="email"
            placeholder="nama@email.com"
            required
            :disabled="loading"
          >
        </div>
      </div>

      <div class="field">
        <label class="field-label" for="password">Password</label>
        <div class="input-group">
          <Lock class="input-icon" :size="17" />
          <input
            id="password"
            v-model="form.password"
            class="input has-toggle"
            :class="{ 'has-error': !passwordValid }"
            :type="showPassword ? 'text' : 'password'"
            autocomplete="new-password"
            placeholder="Minimal 8 karakter"
            required
            :disabled="loading"
          >
          <button
            class="input-toggle"
            type="button"
            :aria-label="showPassword ? 'Sembunyikan password' : 'Tampilkan password'"
            @click="showPassword = !showPassword"
          >
            <component :is="showPassword ? EyeOff : Eye" :size="17" />
          </button>
        </div>
        <p v-if="!passwordValid" class="field-error">Password minimal 8 karakter.</p>
      </div>

      <button class="btn btn-primary btn-lg btn-block" type="submit" :disabled="loading">
        <template v-if="loading">Memproses…</template>
        <template v-else>
          Daftar
          <ArrowRight :size="17" />
        </template>
      </button>
    </form>

    <p class="auth-alt">
      Sudah punya akun?
      <RouterLink to="/login">Masuk di sini</RouterLink>
    </p>
  </AuthShell>
</template>
