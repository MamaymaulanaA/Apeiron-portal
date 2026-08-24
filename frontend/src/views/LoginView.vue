<script setup>
/**
 * Login.
 * Kontrak tidak berubah: POST /auth/login { email, password }.
 */
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Mail, Lock, Eye, EyeOff, ArrowRight } from 'lucide-vue-next'
import AuthShell from '@/components/layout/AuthShell.vue'
import { login, isAdmin } from '@/stores/auth'
import { errorMessage } from '@/lib/api'

const route = useRoute()
const router = useRouter()

const form = ref({ email: '', password: '' })
const showPassword = ref(false)
const loading = ref(false)
const error = ref('')

async function onSubmit() {
  if (loading.value) return
  error.value = ''
  loading.value = true
  try {
    await login(form.value.email.trim(), form.value.password)
    const target =
      route.query.redirect || (isAdmin.value ? '/admin/dashboard' : '/dashboard')
    router.push(target)
  } catch (e) {
    error.value = errorMessage(e, 'Gagal masuk. Periksa email dan password Anda.')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <AuthShell>
    <header class="auth-head">
      <h1>Selamat datang kembali</h1>
      <p class="text-muted">Masuk untuk melanjutkan ke portal Anda.</p>
    </header>

    <form class="auth-form" @submit.prevent="onSubmit">
      <p v-if="error" class="auth-error" role="alert">{{ error }}</p>

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
            :type="showPassword ? 'text' : 'password'"
            autocomplete="current-password"
            placeholder="Masukkan password"
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
      </div>

      <button class="btn btn-primary btn-lg btn-block" type="submit" :disabled="loading">
        <template v-if="loading">Memproses…</template>
        <template v-else>
          Masuk
          <ArrowRight :size="17" />
        </template>
      </button>
    </form>

    <p class="auth-alt">
      Belum punya akun?
      <RouterLink to="/register">Daftar sekarang</RouterLink>
    </p>
  </AuthShell>
</template>
