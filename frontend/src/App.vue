<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import AppLayout from '@/components/layout/AppLayout.vue'
import ToastHost from '@/components/ui/ToastHost.vue'
import { authState } from '@/stores/auth'

const route = useRoute()

// Halaman login/register berdiri sendiri tanpa sidebar/topbar.
const isAuthLayout = computed(() => route.meta.layout === 'auth')
</script>

<template>
  <!-- Tahan render sampai percobaan restore sesi selesai, supaya tidak ada
       kedipan dari layout tamu ke layout aplikasi. -->
  <div v-if="!authState.ready" class="boot">
    <span class="boot-spinner" aria-label="Memuat" />
  </div>

  <template v-else>
    <!-- Halaman auth tanpa transisi: mode="out-in" menahan halaman baru
         sampai fade-out selesai, sehingga perpindahan Masuk <-> Daftar
         terasa tertunda padahal isinya nyaris sama. -->
    <RouterView v-if="isAuthLayout" />

    <AppLayout v-else />
  </template>

  <ToastHost />
</template>

<style scoped>
.boot {
  display: grid;
  place-items: center;
  min-height: 100dvh;
  background: var(--bg-page);
}

.boot-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid var(--brand-border);
  border-top-color: var(--brand);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
</style>
