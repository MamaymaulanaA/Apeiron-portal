/**
 * Klien HTTP.
 *
 * Kontrak backend dipertahankan apa adanya:
 *  - Auth memakai session cookie PHP → butuh withCredentials.
 *  - Request POST/PUT/DELETE butuh header X-CSRF-Token; token didapat dari
 *    GET /auth/csrf atau ikut di response GET /auth/me.
 *  - Error dikirim backend sebagai { error: "pesan" }.
 */
import axios from 'axios'

let csrfToken = null

/** Simpan token CSRF terbaru (dipanggil dari store auth). */
export function setCsrfToken(token) {
  csrfToken = token || null
}

export function getCsrfToken() {
  return csrfToken
}

export const api = axios.create({
  baseURL: '/api',
  withCredentials: true,
  headers: { 'X-Requested-With': 'XMLHttpRequest' },
})

/** Ambil token CSRF dari backend bila belum ada. */
export async function ensureCsrfToken() {
  if (csrfToken) return csrfToken
  const { data } = await api.get('/auth/csrf')
  csrfToken = data.csrf_token
  return csrfToken
}

api.interceptors.request.use(async (config) => {
  const method = (config.method || 'get').toLowerCase()
  if (['post', 'put', 'delete', 'patch'].includes(method)) {
    // /auth/login dan /auth/register dikecualikan CSRF di backend, tapi
    // mengirim token tetap aman bila kebetulan sudah ada.
    if (!csrfToken && !config.url?.startsWith('/auth/')) {
      try {
        await ensureCsrfToken()
      } catch {
        // Biarkan request lanjut — backend punya fallback untuk sesi yang valid.
      }
    }
    if (csrfToken) {
      config.headers['X-CSRF-Token'] = csrfToken
    }
  }
  return config
})

/** Handler 401 dipasang dari router agar lib ini tak bergantung pada router. */
let onUnauthorized = null
export function setUnauthorizedHandler(fn) {
  onUnauthorized = fn
}

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status
    if (status === 401 && onUnauthorized) {
      onUnauthorized()
    }
    return Promise.reject(error)
  }
)

/**
 * Ambil pesan error yang bisa ditampilkan ke user.
 * Backend selalu memakai bentuk { error: "..." }.
 */
export function errorMessage(error, fallback = 'Terjadi kesalahan. Silakan coba lagi.') {
  return error?.response?.data?.error || error?.message || fallback
}
