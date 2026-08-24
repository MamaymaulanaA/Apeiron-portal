<script setup>
/**
 * Sidebar navigasi.
 *
 * Daftar menu, urutan, dan tujuan route dipertahankan persis seperti aplikasi
 * sebelumnya. Yang berubah hanya penyajiannya: panel navy, pill aktif, dan
 * ikon Lucide menggantikan ikon lama.
 */
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import {
  LayoutDashboard,
  CreditCard,
  Users,
  Package,
  LayoutTemplate,
  User,
  LogOut,
  X,
  Infinity as InfinityIcon,
} from 'lucide-vue-next'
import { isAdmin } from '@/stores/auth'

const props = defineProps({
  open: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'logout'])

const route = useRoute()

const adminMenu = [
  { to: '/admin/dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/admin/transactions', label: 'Transaksi', icon: CreditCard },
  { to: '/admin/users', label: 'User', icon: Users },
  { to: '/admin/plugins', label: 'Plugin', icon: Package },
  { to: '/admin/templates', label: 'Template', icon: LayoutTemplate },
]

const userMenu = [
  { to: '/dashboard', label: 'Dashboard', icon: LayoutDashboard },
  { to: '/plugins', label: 'Plugin', icon: Package },
  { to: '/transactions', label: 'Transaksi', icon: CreditCard },
  { to: '/templates', label: 'Template', icon: LayoutTemplate },
  { to: '/profile', label: 'Profil', icon: User },
]

const menu = computed(() => (isAdmin.value ? adminMenu : userMenu))

function isActive(path) {
  return route.path === path || route.path.startsWith(path + '/')
}
</script>

<template>
  <!-- Backdrop hanya muncul saat drawer terbuka di layar kecil -->
  <div
    v-if="open"
    class="sidebar-overlay"
    @click="emit('close')"
    aria-hidden="true"
  />

  <aside class="sidebar" :class="{ 'is-open': open }">
    <div class="sidebar-brand">
      <span class="brand-mark">
        <InfinityIcon :size="18" :stroke-width="2" />
      </span>
      <span class="brand-text">
        <strong>Apeiron</strong>
        <small>Portal</small>
      </span>

      <button
        class="sidebar-close"
        type="button"
        aria-label="Tutup menu"
        @click="emit('close')"
      >
        <X :size="18" />
      </button>
    </div>

    <nav class="sidebar-nav scroll-area" aria-label="Navigasi utama">
      <p class="nav-label">Menu</p>
      <ul class="nav-group">
        <li v-for="item in menu" :key="item.to">
          <RouterLink
            :to="item.to"
            class="nav-item"
            :class="{ active: isActive(item.to) }"
            @click="emit('close')"
          >
            <component :is="item.icon" :size="17" :stroke-width="1.9" />
            <span>{{ item.label }}</span>
          </RouterLink>
        </li>
      </ul>
    </nav>

    <div class="sidebar-footer">
      <button class="nav-item nav-item-danger" type="button" @click="emit('logout')">
        <LogOut :size="17" :stroke-width="1.9" />
        <span>Keluar</span>
      </button>
    </div>
  </aside>
</template>

<style scoped>
.sidebar {
  display: flex;
  flex-direction: column;
  width: var(--sidebar-w);
  flex-shrink: 0;
  height: 100dvh;
  position: sticky;
  top: 0;
  background: var(--sidebar-bg);
  color: var(--sidebar-text);
  border-right: 1px solid var(--sidebar-border);
  z-index: var(--z-sidebar);
}

/* ---- Brand ---- */
.sidebar-brand {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  height: var(--topbar-h);
  padding: 0 var(--sp-5);
  flex-shrink: 0;
  border-bottom: 1px solid var(--sidebar-border);
}

.brand-mark {
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  flex-shrink: 0;
  border-radius: var(--r);
  background: var(--brand);
  color: #fff;
}

.brand-text {
  display: flex;
  flex-direction: column;
  line-height: 1.15;
  min-width: 0;
}
.brand-text strong {
  font-size: var(--fs-body-lg);
  font-weight: var(--fw-bold);
  color: var(--text-heading);
  letter-spacing: var(--tracking-tight);
}
.brand-text small {
  font-size: var(--fs-micro);
  font-weight: var(--fw-semibold);
  letter-spacing: var(--tracking-wide);
  text-transform: uppercase;
  color: var(--sidebar-section);
}

.sidebar-close {
  display: none;
  margin-left: auto;
  padding: var(--sp-2);
  border-radius: var(--r-sm);
  color: var(--sidebar-text);
  transition: background var(--dur) var(--ease), color var(--dur) var(--ease);
}
.sidebar-close:hover {
  background: var(--bg-hover);
  color: var(--text-heading);
}

/* ---- Navigasi ---- */
.sidebar-nav {
  flex: 1;
  padding: var(--sp-6) var(--sp-4) var(--sp-4);
  min-height: 0;
}

.nav-label {
  padding: 0 var(--sp-3);
  margin-bottom: var(--sp-3);
  font-size: var(--fs-micro);
  font-weight: var(--fw-semibold);
  letter-spacing: var(--tracking-wide);
  text-transform: uppercase;
  color: var(--sidebar-section);
}

.nav-group {
  display: flex;
  flex-direction: column;
  gap: var(--sp-1);
}

.nav-item {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  width: 100%;
  min-height: var(--h-control);
  padding: 0 var(--sp-3);
  border-radius: var(--r);
  font-size: var(--fs-body);
  font-weight: var(--fw-medium);
  color: var(--sidebar-text);
  transition: background var(--dur) var(--ease), color var(--dur) var(--ease);
}

.nav-item:hover {
  background: var(--bg-hover);
  color: var(--text-heading);
}

.nav-item.active {
  background: var(--brand-soft);
  color: var(--sidebar-text-active);
  font-weight: var(--fw-medium);
}

.nav-item :deep(svg) {
  flex-shrink: 0;
}

/* ---- Footer ---- */
.sidebar-footer {
  padding: var(--sp-4);
  flex-shrink: 0;
  border-top: 1px solid var(--sidebar-border);
}

.nav-item-danger:hover {
  background: var(--err-soft);
  color: var(--err);
}

/* ---- Responsif: drawer di bawah 1024px ---- */
.sidebar-overlay {
  display: none;
}

@media (max-width: 1023px) {
  .sidebar {
    position: fixed;
    inset: 0 auto 0 0;
    transform: translateX(-100%);
    transition: transform var(--dur) var(--ease);
    box-shadow: var(--sh-pop);
  }
  .sidebar.is-open {
    transform: translateX(0);
  }
  .sidebar-close {
    display: block;
  }
  .sidebar-overlay {
    display: block;
    position: fixed;
    inset: 0;
    background: var(--bg-overlay);
    z-index: calc(var(--z-sidebar) - 1);
    animation: fade-in var(--dur) var(--ease);
  }
}
</style>
