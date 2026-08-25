<script setup>
/**
 * Topbar.
 *
 * Catatan:
 *  - Sengaja TIDAK ada kolom pencarian global. Backend tidak punya endpoint
 *    pencarian, jadi menambahkannya hanya akan jadi kontrol kosong.
 *  - Judul halaman TIDAK ditampilkan di sini; setiap halaman sudah memuat
 *    <h1>-nya sendiri. Menaruhnya di dua tempat membuat judul tampil ganda.
 */
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { Menu, ChevronDown, User, LogOut } from 'lucide-vue-next'
import { authState, isAdmin } from '@/stores/auth'
import NotificationDropdown from './NotificationDropdown.vue'
import UserAvatar from '@/components/ui/UserAvatar.vue'

const emit = defineEmits(['toggle-sidebar', 'logout'])

const menuOpen = ref(false)
const root = ref(null)

const user = computed(() => authState.user)
const roleLabel = computed(() => (isAdmin.value ? 'Administrator' : 'Member'))

function onOutside(e) {
  if (root.value && !root.value.contains(e.target)) menuOpen.value = false
}
function onEsc(e) {
  if (e.key === 'Escape') menuOpen.value = false
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
  <header class="topbar">
    <button
      class="topbar-burger"
      type="button"
      aria-label="Buka menu"
      @click="emit('toggle-sidebar')"
    >
      <Menu :size="20" />
    </button>

    <div class="topbar-actions">
      <NotificationDropdown />

      <div ref="root" class="user-menu">
        <button
          class="user-trigger"
          type="button"
          :aria-expanded="menuOpen"
          aria-haspopup="true"
          @click="menuOpen = !menuOpen"
        >
          <span class="avatar avatar-sm"><UserAvatar :name="user?.name" :seed="user?.email" /></span>
          <span class="user-meta">
            <span class="user-name">{{ user?.name }}</span>
            <span class="user-role">{{ roleLabel }}</span>
          </span>
          <ChevronDown
            class="user-caret"
            :class="{ flipped: menuOpen }"
            :size="16"
          />
        </button>

        <Transition name="pop">
          <div v-if="menuOpen" class="user-panel">
            <div class="user-panel-head">
              <span class="avatar"><UserAvatar :name="user?.name" :seed="user?.email" /></span>
              <span class="user-panel-id">
                <strong>{{ user?.name }}</strong>
                <small>{{ user?.email }}</small>
              </span>
            </div>

            <hr class="divider" />

            <RouterLink
              v-if="!isAdmin"
              to="/profile"
              class="user-action"
              @click="menuOpen = false"
            >
              <User :size="17" />
              Profil saya
            </RouterLink>

            <button class="user-action danger" type="button" @click="emit('logout')">
              <LogOut :size="17" />
              Keluar
            </button>
          </div>
        </Transition>
      </div>
    </div>
  </header>
</template>

<style scoped>
.topbar {
  position: sticky;
  top: 0;
  z-index: calc(var(--z-sidebar) - 10);
  display: flex;
  align-items: center;
  gap: var(--sp-4);
  height: var(--topbar-h);
  padding: 0 var(--sp-6);
  background: rgba(255, 255, 255, 0.85);
  backdrop-filter: blur(12px);
  border-bottom: 1px solid var(--border);
}

.topbar-burger {
  display: none;
  place-items: center;
  width: 40px;
  height: 40px;
  border-radius: var(--r);
  background: var(--bg-subtle);
  color: var(--text-heading);
  transition: background var(--dur) var(--ease);
}
.topbar-burger:hover {
  background: var(--brand-soft);
  color: var(--brand);
}

.topbar-actions {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  margin-left: auto;
}

/* ---- Menu user ---- */
.user-menu {
  position: relative;
}

.user-trigger {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-1) var(--sp-2) var(--sp-1) var(--sp-1);
  border-radius: var(--r-pill);
  transition: background var(--dur) var(--ease);
}
.user-trigger:hover {
  background: var(--bg-hover);
}

.user-meta {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  line-height: 1.25;
  min-width: 0;
}
.user-name {
  font-size: var(--fs-label);
  font-weight: var(--fw-semibold);
  color: var(--text-heading);
  max-width: 140px;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.user-role {
  font-size: var(--fs-micro);
  color: var(--text-muted);
}

.user-caret {
  color: var(--text-muted);
  transition: transform var(--dur) var(--ease);
}
.user-caret.flipped {
  transform: rotate(180deg);
}

.user-panel {
  position: absolute;
  top: calc(100% + 10px);
  right: 0;
  width: 250px;
  padding: var(--sp-2);
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r);
  box-shadow: var(--sh-pop);
  z-index: var(--z-dropdown);
}

.user-panel-head {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-3);
}
.user-panel-id {
  display: flex;
  flex-direction: column;
  min-width: 0;
  line-height: 1.35;
}
.user-panel-id strong {
  font-size: var(--fs-label);
  color: var(--text-heading);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.user-panel-id small {
  font-size: var(--fs-caption);
  color: var(--text-muted);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.divider {
  margin: var(--sp-2) 0;
}

.user-action {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  width: 100%;
  padding: var(--sp-3);
  border-radius: var(--r);
  font-size: var(--fs-body);
  font-weight: var(--fw-medium);
  color: var(--text-body);
  transition: background var(--dur) var(--ease), color var(--dur) var(--ease);
}
.user-action:hover {
  background: var(--bg-hover);
  color: var(--text-heading);
}
.user-action.danger:hover {
  background: var(--err-soft);
  color: var(--err);
}

.pop-enter-active,
.pop-leave-active {
  transition: opacity var(--dur) var(--ease), transform var(--dur) var(--ease);
}
.pop-enter-from,
.pop-leave-to {
  opacity: 0;
  transform: translateY(-6px) scale(0.98);
}

/* ---- Responsif ---- */
@media (max-width: 1023px) {
  .topbar-burger {
    display: grid;
  }
}

@media (max-width: 640px) {
  .topbar {
    padding: 0 var(--sp-4);
    gap: var(--sp-3);
  }
  .user-meta,
  .user-caret {
    display: none;
  }
}
</style>
