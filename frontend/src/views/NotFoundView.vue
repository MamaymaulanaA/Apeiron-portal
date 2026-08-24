<script setup>
import { computed } from 'vue'
import { Compass, ArrowLeft } from 'lucide-vue-next'
import { isAuthenticated, isAdmin } from '@/stores/auth'

const home = computed(() => {
  if (!isAuthenticated.value) return '/login'
  return isAdmin.value ? '/admin/dashboard' : '/dashboard'
})
</script>

<template>
  <div class="nf">
    <span class="icon-chip nf-icon tone-brand">
      <Compass :size="30" :stroke-width="1.8" />
    </span>

    <p class="nf-code">404</p>
    <h1>Halaman tidak ditemukan</h1>
    <p class="text-muted nf-text">
      Alamat yang Anda tuju tidak tersedia atau sudah dipindahkan.
    </p>

    <RouterLink :to="home" class="btn btn-primary nf-cta">
      <ArrowLeft :size="16" />
      Kembali
    </RouterLink>
  </div>
</template>

<style scoped>
.nf {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: var(--sp-2);
  min-height: 60vh;
  text-align: center;
  padding: var(--sp-8) var(--sp-4);
}

.nf-icon {
  width: 64px;
  height: 64px;
  border-radius: var(--r);
  margin-bottom: var(--sp-3);
}

.nf-code {
  font-size: var(--fs-display);
  font-weight: var(--fw-bold);
  color: var(--brand);
  letter-spacing: var(--tracking-tight);
}

.nf-text {
  max-width: 44ch;
}

.nf-cta {
  margin-top: var(--sp-5);
}
</style>
