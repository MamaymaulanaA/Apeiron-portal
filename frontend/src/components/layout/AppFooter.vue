<script setup>
/**
 * Footer aplikasi.
 * Tahun diambil dari jam perangkat saat render, jadi tidak perlu diperbarui
 * manual setiap pergantian tahun.
 */
import { APP_NAME } from '@/lib/app'

const year = new Date().getFullYear()
</script>

<template>
  <footer class="footer">
    <p class="footer-copy">&copy; {{ year }} {{ APP_NAME }}. Seluruh hak cipta dilindungi.</p>
    <p class="footer-meta">Versi portal 2.0</p>
  </footer>
</template>

<style scoped>
.footer {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-4);
  flex-wrap: wrap;
  padding: var(--sp-5) 0 0;
  margin-top: var(--sp-6);
  border-top: 1px solid var(--border);
}

.footer-copy,
.footer-meta {
  font-size: var(--fs-caption);
  color: var(--text-muted);
}

@media (max-width: 640px) {
  .footer {
    justify-content: center;
    text-align: center;
  }
}
</style>
