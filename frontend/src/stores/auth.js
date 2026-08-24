/**
 * Store auth — reactive sederhana (tanpa dependency state-management baru).
 *
 * Alur dipertahankan sama seperti sebelumnya:
 *   login  → POST /auth/login  → session cookie di-set backend
 *   sesi   → GET  /auth/me     → mengembalikan user + csrf_token
 *   logout → POST /auth/logout → backend menghancurkan session
 */
import { reactive, computed } from 'vue'
import { api, setCsrfToken } from '@/lib/api'

const state = reactive({
  user: null,
  ready: false, // true setelah percobaan restore sesi pertama selesai
})

export const authState = state

export const isAuthenticated = computed(() => state.user !== null)
export const isAdmin = computed(() => state.user?.role === 'admin')

/** Restore sesi dari cookie yang sudah ada. Dipanggil sekali saat app boot. */
export async function restoreSession() {
  try {
    const { data } = await api.get('/auth/me')
    state.user = data.user
    setCsrfToken(data.csrf_token)
  } catch {
    state.user = null
    setCsrfToken(null)
  } finally {
    state.ready = true
  }
}

export async function login(email, password) {
  const { data } = await api.post('/auth/login', { email, password })
  state.user = data.user
  // Sesi baru dibuat backend (session_regenerate_id), jadi token CSRF lama
  // sudah tidak berlaku — ambil ulang lewat /auth/me.
  try {
    const me = await api.get('/auth/me')
    setCsrfToken(me.data.csrf_token)
  } catch {
    setCsrfToken(null)
  }
  return data.user
}

export async function register(payload) {
  const { data } = await api.post('/auth/register', payload)
  return data
}

export async function logout() {
  try {
    await api.post('/auth/logout')
  } finally {
    state.user = null
    setCsrfToken(null)
  }
}

/** Dipakai saat profil diperbarui agar nama di topbar ikut berubah. */
export function patchUser(patch) {
  if (state.user) Object.assign(state.user, patch)
}

/** Dipanggil interceptor saat backend membalas 401. */
export function clearSession() {
  state.user = null
  setCsrfToken(null)
}
