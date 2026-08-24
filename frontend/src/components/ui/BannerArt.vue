<script setup>
/**
 * Ilustrasi dekoratif untuk banner sambutan.
 *
 * Digambar langsung sebagai SVG (bukan file gambar) supaya ringan, tajam di
 * layar apa pun, dan ikut warna token. Gerakannya sangat halus dan otomatis
 * berhenti bila pengguna memilih prefers-reduced-motion.
 */
</script>

<template>
  <svg
    class="art"
    viewBox="0 0 240 160"
    fill="none"
    aria-hidden="true"
    focusable="false"
  >
    <!-- Lingkaran latar lembut -->
    <circle class="halo" cx="150" cy="76" r="62" />

    <!-- Kartu belakang -->
    <g class="drift drift-slow">
      <rect x="96" y="34" width="86" height="60" rx="8" class="card-back" />
    </g>

    <!-- Kartu depan: menggambarkan dokumen lisensi -->
    <g class="drift">
      <rect x="112" y="52" width="94" height="66" rx="8" class="card-front" />
      <rect x="124" y="66" width="44" height="6" rx="3" class="line-strong" />
      <rect x="124" y="80" width="70" height="5" rx="2.5" class="line" />
      <rect x="124" y="92" width="56" height="5" rx="2.5" class="line" />
      <circle cx="188" cy="104" r="10" class="seal" />
      <path d="M183.5 104l3.2 3.2 6-6.4" class="check" />
    </g>

    <!-- Butir kecil yang mengambang -->
    <circle class="dot dot-a" cx="72" cy="46" r="4" />
    <circle class="dot dot-b" cx="86" cy="118" r="5" />
    <circle class="dot dot-c" cx="214" cy="38" r="3.5" />
  </svg>
</template>

<style scoped>
.art {
  width: 100%;
  height: 100%;
  overflow: visible;
}

.halo {
  fill: var(--brand);
  opacity: 0.06;
}

.card-back {
  fill: var(--bg-surface);
  stroke: var(--brand-border);
  stroke-width: 1.5;
  opacity: 0.75;
}

.card-front {
  fill: var(--bg-surface);
  stroke: var(--brand-border);
  stroke-width: 1.5;
}

.line-strong {
  fill: var(--brand);
  opacity: 0.55;
}
.line {
  fill: var(--n-300);
}

.seal {
  fill: var(--ok-soft);
  stroke: var(--ok-border);
  stroke-width: 1.5;
}
.check {
  stroke: var(--ok);
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  fill: none;
}

.dot {
  fill: var(--brand);
  opacity: 0.18;
}
.dot-b {
  fill: var(--ok);
  opacity: 0.2;
}
.dot-c {
  fill: var(--warn);
  opacity: 0.2;
}

/* Gerakan sangat pelan — hadir sebagai suasana, bukan sebagai gimmick. */
@keyframes float-y {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-5px);
  }
}

.drift {
  animation: float-y 6s var(--ease) infinite;
  transform-origin: center;
}
.drift-slow {
  animation-duration: 8s;
  animation-direction: reverse;
}
.dot-a {
  animation: float-y 5s var(--ease) infinite;
}
.dot-b {
  animation: float-y 7s var(--ease) infinite;
}
.dot-c {
  animation: float-y 6.5s var(--ease) infinite reverse;
}

@media (prefers-reduced-motion: reduce) {
  .drift,
  .dot {
    animation: none;
  }
}
</style>
