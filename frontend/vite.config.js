import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

// Build output mirrors the deployed layout: index.html at the web root with a
// sibling assets/ directory. scripts/deploy.mjs syncs dist/ into the repo root.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  build: {
    outDir: 'dist',
    assetsDir: 'assets',
    rollupOptions: {
      output: {
        manualChunks: {
          vue: ['vue', 'vue-router'],
          axios: ['axios'],
          // Login dan Register selalu berpasangan: user berpindah bolak-balik
          // di antara keduanya. Menyatukannya menghapus satu unduhan chunk
          // tepat saat tautan diklik, yang di jaringan nyata terasa sebagai jeda.
          auth: [
            './src/views/LoginView.vue',
            './src/views/RegisterView.vue',
            './src/components/layout/AuthShell.vue',
          ],
        },
      },
    },
  },
  server: {
    // Dev server proxies /api and /storage to the PHP backend so the frontend
    // can run against real data without touching any backend code.
    proxy: {
      '/api': {
        target: process.env.VITE_API_TARGET || 'http://localhost:8000',
        changeOrigin: true,
      },
      '/storage': {
        target: process.env.VITE_API_TARGET || 'http://localhost:8000',
        changeOrigin: true,
      },
    },
  },
})
