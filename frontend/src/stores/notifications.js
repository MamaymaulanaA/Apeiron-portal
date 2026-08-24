/**
 * Store notifikasi.
 *
 * Endpoint yang dipakai identik dengan sebelumnya:
 *   GET  /notifications              → daftar + unread_count
 *   GET  /notifications/unread-count → polling ringan
 *   POST /notifications/{id}/read
 *   POST /notifications/read-all
 */
import { reactive } from 'vue'
import { api } from '@/lib/api'

const POLL_MS = 60000

export const notifState = reactive({
  items: [],
  unread: 0,
  loading: false,
})

let pollTimer = null

export async function fetchNotifications() {
  notifState.loading = true
  try {
    const { data } = await api.get('/notifications')
    notifState.items = data.notifications || []
    notifState.unread = data.unread_count || 0
  } catch {
    // Notifikasi bersifat pelengkap — kegagalan tidak boleh mengganggu halaman.
  } finally {
    notifState.loading = false
  }
}

export async function fetchUnreadCount() {
  try {
    const { data } = await api.get('/notifications/unread-count')
    notifState.unread = data.unread_count || 0
  } catch {
    /* diamkan */
  }
}

export async function markRead(id) {
  const item = notifState.items.find((n) => n.id === id)
  if (!item || Number(item.is_read) === 1) return
  await api.post(`/notifications/${id}/read`)
  item.is_read = 1
  notifState.unread = Math.max(0, notifState.unread - 1)
}

export async function markAllRead() {
  await api.post('/notifications/read-all')
  notifState.items.forEach((n) => {
    n.is_read = 1
  })
  notifState.unread = 0
}

export function startPolling() {
  stopPolling()
  fetchUnreadCount()
  pollTimer = setInterval(fetchUnreadCount, POLL_MS)
}

export function stopPolling() {
  if (pollTimer) {
    clearInterval(pollTimer)
    pollTimer = null
  }
}

export function resetNotifications() {
  stopPolling()
  notifState.items = []
  notifState.unread = 0
}
