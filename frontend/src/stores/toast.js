/**
 * Toast — pengganti visual untuk flash message.
 * Tidak mengubah data apa pun; murni umpan balik UI.
 */
import { reactive } from 'vue'

let seq = 0

export const toasts = reactive([])

function push(type, message, timeout = 4000) {
  const id = ++seq
  toasts.push({ id, type, message })
  if (timeout) {
    setTimeout(() => dismiss(id), timeout)
  }
  return id
}

export function dismiss(id) {
  const i = toasts.findIndex((t) => t.id === id)
  if (i !== -1) toasts.splice(i, 1)
}

export const toast = {
  success: (msg, t) => push('success', msg, t),
  error: (msg, t) => push('error', msg, t ?? 6000),
  info: (msg, t) => push('info', msg, t),
  warning: (msg, t) => push('warning', msg, t),
}
