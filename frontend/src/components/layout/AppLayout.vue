<script setup>
/**
 * Shell aplikasi: sidebar + topbar + area konten.
 */
import { ref, watch, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import AppSidebar from './AppSidebar.vue'
import AppTopBar from './AppTopBar.vue'
import { logout as doLogout } from '@/stores/auth'
import {
  startPolling,
  stopPolling,
  resetNotifications,
} from '@/stores/notifications'
import { toast } from '@/stores/toast'

const route = useRoute()
const router = useRouter()

const sidebarOpen = ref(false)

// Tutup drawer setiap pindah halaman agar tidak menutupi konten baru.
watch(() => route.fullPath, () => {
  sidebarOpen.value = false
})

// Kunci scroll body saat drawer terbuka di layar kecil.
watch(sidebarOpen, (open) => {
  document.body.style.overflow = open ? 'hidden' : ''
})

async function handleLogout() {
  try {
    await doLogout()
    resetNotifications()
    router.push('/login')
  } catch {
    toast.error('Gagal keluar. Silakan coba lagi.')
  }
}

onMounted(startPolling)
onBeforeUnmount(() => {
  stopPolling()
  document.body.style.overflow = ''
})
</script>

<template>
  <div class="shell">
    <AppSidebar
      :open="sidebarOpen"
      @close="sidebarOpen = false"
      @logout="handleLogout"
    />

    <div class="shell-main">
      <AppTopBar
        @toggle-sidebar="sidebarOpen = !sidebarOpen"
        @logout="handleLogout"
      />

      <main class="shell-content">
        <RouterView v-slot="{ Component }">
          <Transition name="route" mode="out-in">
            <component :is="Component" />
          </Transition>
        </RouterView>
      </main>
    </div>
  </div>
</template>

<style scoped>
.shell {
  display: flex;
  min-height: 100dvh;
  background: var(--bg-page);
}

.shell-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  min-width: 0; /* cegah anak yang lebar mendorong layout */
}

.shell-content {
  flex: 1;
  width: 100%;
  max-width: var(--content-max);
  margin: 0 auto;
  padding: var(--sp-6);
}

@media (max-width: 640px) {
  .shell-content {
    padding: var(--sp-4);
  }
}
</style>
