<script setup>
/**
 * Ilustrasi banner sambutan.
 *
 * Memakai berkas gambar dari src/assets sehingga Vite ikut memberi hash dan
 * memasukkannya ke manifest build — skrip deploy otomatis membersihkan versi
 * lamanya saat gambar diganti.
 *
 * Gambar raster tidak bisa dianimasikan per bagian, jadi gerakannya berupa
 * ayunan naik-turun yang sangat pelan pada keseluruhan ilustrasi, ditambah
 * bayangan lembut yang ikut bernapas. Semuanya berhenti pada
 * prefers-reduced-motion.
 */
import characterUrl from '@/assets/banner-character.png'
</script>

<template>
  <div class="art">
    <span class="art-glow" aria-hidden="true" />
    <img
      :src="characterUrl"
      class="art-img"
      alt=""
      aria-hidden="true"
      width="1536"
      height="1024"
      decoding="async"
      fetchpriority="high"
    >
  </div>
</template>

<style scoped>
.art {
  position: relative;
  display: block;
}

/* Lingkaran lembut di belakang karakter supaya ia tidak terlihat mengambang
   begitu saja di atas latar banner. */
.art-glow {
  position: absolute;
  bottom: -18%;
  left: 50%;
  width: 78%;
  aspect-ratio: 1;
  transform: translateX(-50%);
  border-radius: var(--r-pill);
  background: radial-gradient(
    circle,
    rgba(37, 99, 235, 0.12) 0%,
    rgba(37, 99, 235, 0.05) 55%,
    transparent 72%
  );
}

.art-img {
  position: relative;
  width: 100%;
  height: auto;
  animation: bob 6s var(--ease) infinite;
}

/* Ayunan sangat kecil — terasa hidup tanpa menarik perhatian dari isi. */
@keyframes bob {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-7px);
  }
}

/* Bayangan ikut mengecil saat karakter naik, supaya gerakannya terbaca
   sebagai melayang, bukan sekadar bergeser. */
@keyframes breathe {
  0%,
  100% {
    opacity: 1;
    transform: translateX(-50%) scale(1);
  }
  50% {
    opacity: 0.75;
    transform: translateX(-50%) scale(0.96);
  }
}

.art-glow {
  animation: breathe 6s var(--ease) infinite;
}

@media (prefers-reduced-motion: reduce) {
  .art-img,
  .art-glow {
    animation: none;
  }
}
</style>
