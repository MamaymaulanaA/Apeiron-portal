<script setup>
/**
 * Dropdown notifikasi.
 * Perilaku (fetch, tandai dibaca, tandai semua) sama seperti sebelumnya —
 * hanya tampilannya yang dirapikan.
 */
import { ref, onMounted, onBeforeUnmount } from 'vue'
import {
  Bell,
  CheckCheck,
  Inbox,
  CreditCard,
  Package,
  CircleCheck,
  CircleX,
} from 'lucide-vue-next'
import {
  notifState,
  fetchNotifications,
  markAllRead,
  markRead,
} from '@/stores/notifications'
import { formatRelative } from '@/lib/format'
import { toast } from '@/stores/toast'

const open = ref(false)
const root = ref(null)

/** Ikon dipilih dari tipe notifikasi yang dikirim backend. */
const ICONS = {
  transaction_uploaded: CreditCard,
  transaction_approved: CircleCheck,
  transaction_rejected: CircleX,
  plugin_update: Package,
}
function iconFor(type) {
  return ICONS[type] || Bell
}

async function toggle() {
  open.value = !open.value
  if (open.value) await fetchNotifications()
}

async function onItemClick(item) {
  try {
    await markRead(item.id)
  } catch {
    /* menandai dibaca bukan aksi kritis */
  }
}

async function onMarkAll() {
  try {
    await markAllRead()
    toast.success('Semua notifikasi ditandai sudah dibaca')
  } catch {
    toast.error('Gagal menandai notifikasi')
  }
}

function onOutside(e) {
  if (root.value && !root.value.contains(e.target)) open.value = false
}
function onEsc(e) {
  if (e.key === 'Escape') open.value = false
}

onMounted(() => {
  document.addEventListener('click', onOutside)
  document.addEventListener('keydown', onEsc)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onOutside)
  document.removeEventListener('keydown', onEsc)
})
</script>

<template>
  <div ref="root" class="notif">
    <button
      class="notif-trigger"
      type="button"
      :aria-expanded="open"
      aria-haspopup="true"
      :aria-label="`Notifikasi${notifState.unread ? `, ${notifState.unread} belum dibaca` : ''}`"
      @click="toggle"
    >
      <Bell :size="19" :stroke-width="2" />
      <span v-if="notifState.unread > 0" class="notif-count">
        {{ notifState.unread > 9 ? '9+' : notifState.unread }}
      </span>
    </button>

    <Transition name="pop">
      <div v-if="open" class="notif-panel">
        <header class="notif-head">
          <h3>Notifikasi</h3>
          <button
            v-if="notifState.unread > 0"
            class="notif-mark"
            type="button"
            @click="onMarkAll"
          >
            <CheckCheck :size="14" />
            Tandai semua
          </button>
        </header>

        <div class="notif-list scroll-area">
          <p v-if="notifState.loading" class="notif-state">Memuat…</p>

          <div v-else-if="!notifState.items.length" class="notif-empty">
            <span class="icon-chip tone-brand"><Inbox :size="20" /></span>
            <p class="text-label">Belum ada notifikasi</p>
            <p class="text-caption">Pemberitahuan akan muncul di sini.</p>
          </div>

          <button
            v-for="n in notifState.items"
            v-else
            :key="n.id"
            class="notif-item"
            :class="{ unread: Number(n.is_read) === 0 }"
            type="button"
            @click="onItemClick(n)"
          >
            <span class="icon-chip icon-chip-sm tone-brand">
              <component :is="iconFor(n.type)" :size="16" />
            </span>
            <span class="notif-body">
              <span class="notif-title">{{ n.title }}</span>
              <span class="notif-msg">{{ n.message }}</span>
              <span class="notif-time">{{ formatRelative(n.created_at) }}</span>
            </span>
            <span v-if="Number(n.is_read) === 0" class="notif-dot" aria-hidden="true" />
          </button>
        </div>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.notif {
  position: relative;
}

.notif-trigger {
  position: relative;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: var(--r);
  color: var(--text-body);
  background: var(--bg-subtle);
  transition: background var(--dur) var(--ease), color var(--dur) var(--ease);
}
.notif-trigger:hover {
  background: var(--brand-soft);
  color: var(--brand);
}

.notif-count {
  position: absolute;
  top: -3px;
  right: -3px;
  min-width: 18px;
  height: 18px;
  padding: 0 5px;
  display: grid;
  place-items: center;
  border-radius: var(--r-pill);
  background: var(--err);
  color: #fff;
  font-size: var(--fs-micro);
  font-weight: var(--fw-bold);
  border: 2px solid var(--bg-surface);
}

/* ---- Panel ---- */
.notif-panel {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: min(360px, calc(100vw - 32px));
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r);
  box-shadow: var(--sh-pop);
  z-index: var(--z-dropdown);
  overflow: hidden;
}

.notif-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  padding: var(--sp-4) var(--sp-5);
  border-bottom: 1px solid var(--border);
}
.notif-head h3 {
  font-size: var(--fs-body-lg);
}

.notif-mark {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  font-size: var(--fs-caption);
  font-weight: var(--fw-semibold);
  color: var(--brand);
}
.notif-mark:hover {
  color: var(--brand-hover);
}

.notif-list {
  max-height: min(420px, 60vh);
}

.notif-state {
  padding: var(--sp-6);
  text-align: center;
  font-size: var(--fs-caption);
  color: var(--text-muted);
}

.notif-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-8) var(--sp-6);
  text-align: center;
}

.notif-item {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-3);
  width: 100%;
  padding: var(--sp-4) var(--sp-5);
  text-align: left;
  border-bottom: 1px solid var(--border);
  transition: background var(--dur-fast) var(--ease);
}
.notif-item:last-child {
  border-bottom: none;
}
.notif-item:hover {
  background: var(--bg-hover);
}
.notif-item.unread {
  background: var(--brand-soft);
}
.notif-item.unread:hover {
  background: var(--brand-border);
}

.notif-body {
  display: flex;
  flex-direction: column;
  gap: 2px;
  min-width: 0;
  flex: 1;
}

.notif-title {
  font-size: var(--fs-label);
  font-weight: var(--fw-semibold);
  color: var(--text-heading);
}

.notif-msg {
  font-size: var(--fs-caption);
  color: var(--text-body);
  line-height: var(--lh-snug);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.notif-time {
  margin-top: 2px;
  font-size: var(--fs-micro);
  color: var(--text-muted);
}

.notif-dot {
  width: 7px;
  height: 7px;
  margin-top: 6px;
  border-radius: 50%;
  background: var(--brand);
  flex-shrink: 0;
}

/* ---- Transisi ---- */
.pop-enter-active,
.pop-leave-active {
  transition: opacity var(--dur) var(--ease), transform var(--dur) var(--ease);
}
.pop-enter-from,
.pop-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.98);
}
</style>
