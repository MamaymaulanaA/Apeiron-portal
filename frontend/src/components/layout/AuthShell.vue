<script setup>
/**
 * Kerangka halaman login & register: panel brand di kiri, form di kanan.
 * Panel kiri disembunyikan di bawah 900px agar form mendapat ruang penuh.
 */
import BannerArt from '@/components/ui/BannerArt.vue'
import {
  ShieldCheck,
  LayoutTemplate,
  Headphones,
  Infinity as InfinityIcon,
} from 'lucide-vue-next'

defineProps({
  pitchTitle: { type: String, default: 'Lisensi dan template Apeiron Kit di satu tempat.' },
})

const highlights = [
  { icon: ShieldCheck, text: 'Lisensi terverifikasi & aman' },
  { icon: LayoutTemplate, text: 'Koleksi template undangan siap pakai' },
  { icon: Headphones, text: 'Dukungan langsung dari tim' },
]
</script>

<template>
  <div class="auth">
    <aside class="auth-aside">
      <div class="auth-intro">
      <div class="auth-brand">
        <span class="brand-mark"><InfinityIcon :size="22" :stroke-width="2.5" /></span>
        <span class="brand-text">
          <strong>Apeiron</strong>
          <small>Portal</small>
        </span>
      </div>

      <div class="auth-pitch">
        <h2>{{ pitchTitle }}</h2>
        <BannerArt class="auth-art" />

        <ul class="auth-points">
          <li v-for="h in highlights" :key="h.text">
            <span class="point-icon"><component :is="h.icon" :size="16" /></span>
            {{ h.text }}
          </li>
        </ul>
      </div>
      </div>

      <p class="auth-foot">© {{ new Date().getFullYear() }} Apeiron Portal</p>
    </aside>

    <main class="auth-main scroll-area">
      <div class="auth-card">
        <slot />
      </div>
    </main>
  </div>
</template>

<style scoped>
.auth {
  display: grid;
  grid-template-columns: 1fr;
  min-height: 100dvh;
  background: var(--bg-page);
}

.auth-aside {
  display: none;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  padding: var(--sp-10);
  background: linear-gradient(160deg, #16234b 0%, #1c2c5e 55%, #24397a 100%);
  color: #fff;
  position: relative;
  overflow: hidden;
}

/* Lingkaran dekoratif lembut */
.auth-aside::before,
.auth-aside::after {
  content: '';
  position: absolute;
  border-radius: 50%;
  opacity: 0.16;
}
.auth-aside::before {
  width: 420px;
  height: 420px;
  right: -140px;
  top: -120px;
  background: radial-gradient(circle, var(--brand), transparent 65%);
}
.auth-aside::after {
  width: 320px;
  height: 320px;
  left: -110px;
  bottom: -90px;
  background: radial-gradient(circle, var(--n-500), transparent 65%);
}

/* Brand dan pitch dijadikan satu blok agar terpusat sebagai satu kesatuan,
   bukan brand menempel di atas dan pitch mengambang di tengah. */
.auth-intro {
  position: relative;
  z-index: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-6);
  width: 100%;
  max-width: 420px;
  text-align: center;
}

.auth-foot {
  position: absolute;
  left: 0;
  right: 0;
  bottom: var(--sp-8);
  z-index: 1;
  text-align: center;
}

.auth-brand {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
}
.brand-mark {
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  border-radius: var(--r);
  background: linear-gradient(135deg, var(--brand), #5b8dff);
}
.brand-text {
  display: flex;
  flex-direction: column;
  line-height: 1.15;
}
.brand-text strong {
  font-size: var(--fs-body-lg);
  font-weight: var(--fw-bold);
}
.brand-text small {
  font-size: var(--fs-micro);
  font-weight: var(--fw-semibold);
  letter-spacing: var(--tracking-wide);
  text-transform: uppercase;
  color: var(--sidebar-section);
}

.auth-pitch h2 {
  font-size: 1.75rem;
  line-height: 1.3;
  color: #fff;
  margin-bottom: var(--sp-3);
  text-wrap: balance;
}
.auth-pitch > p {
  color: #b9c4de;
  line-height: var(--lh-normal);
  text-wrap: pretty;
}

/* Ilustrasi memakai berkas yang sama dengan banner dashboard, jadi tidak ada
   aset tambahan yang perlu diunduh setelah user masuk. */
.auth-art {
  display: block;
  width: 100%;
  max-width: 300px;
  margin: var(--sp-6) auto 0;
}

.auth-points {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--sp-3);
  margin: var(--sp-6) auto 0;
  width: max-content;
  max-width: 100%;
  text-align: left;
}
.auth-points li {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  font-size: var(--fs-body);
  color: #d5dcec;
}
.point-icon {
  display: grid;
  place-items: center;
  width: 30px;
  height: 30px;
  flex-shrink: 0;
  border-radius: var(--r-sm);
  background: rgba(255, 255, 255, 0.1);
  color: #8fb4ff;
}

.auth-foot {
  margin: 0;
  font-size: var(--fs-caption);
  color: #7f8db0;
}

.auth-main {
  display: grid;
  place-items: center;
  padding: var(--sp-6);
  max-height: 100dvh;
}

.auth-card {
  width: 100%;
  max-width: 420px;
  padding: var(--sp-4) 0;
}

@media (min-width: 900px) {
  .auth {
    grid-template-columns: 1.05fr 1fr;
  }
  .auth-aside {
    display: flex;
  }
}
</style>
