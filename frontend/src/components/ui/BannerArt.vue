<script setup>
/**
 * Ilustrasi banner sambutan.
 *
 * Digambar langsung sebagai SVG — ringan, tajam di layar apa pun, dan ikut
 * palet token. Gaya-nya flat, bukan render 3D; untuk hasil 3D dibutuhkan
 * berkas gambar tersendiri.
 *
 * Gerakannya sangat pelan dan berhenti otomatis pada prefers-reduced-motion.
 */
</script>

<template>
  <svg
    class="art"
    viewBox="0 0 300 190"
    fill="none"
    aria-hidden="true"
    focusable="false"
  >
    <!-- ---------- Latar ---------- -->
    <circle class="halo" cx="176" cy="92" r="86" />
    <path
      class="wave"
      d="M0 150c40-26 74-6 112-24s60-52 96-46 74 46 92 40v70H0z"
    />

    <!-- ---------- Elemen mengambang ---------- -->
    <g class="float float-a">
      <rect x="18" y="34" width="52" height="38" rx="8" class="chip" />
      <path d="M32 47l-6 6 6 6M50 47l6 6-6 6" class="chip-code" />
    </g>

    <g class="float float-b">
      <rect x="226" y="24" width="58" height="44" rx="8" class="chip" />
      <rect x="236" y="36" width="30" height="4.5" rx="2.25" class="chip-line" />
      <rect x="236" y="46" width="38" height="4" rx="2" class="chip-line dim" />
      <rect x="236" y="55" width="22" height="4" rx="2" class="chip-line dim" />
    </g>

    <!-- Kilau kecil -->
    <path class="spark spark-a" d="M92 26l2.6 5.4L100 34l-5.4 2.6L92 42l-2.6-5.4L84 34l5.4-2.6z" />
    <path class="spark spark-b" d="M262 104l2 4.2 4.2 2-4.2 2-2 4.2-2-4.2-4.2-2 4.2-2z" />
    <circle class="dot dot-a" cx="60" cy="112" r="4" />
    <circle class="dot dot-b" cx="284" cy="132" r="3.5" />
    <circle class="dot dot-c" cx="128" cy="18" r="3" />

    <!-- ---------- Karakter ---------- -->
    <g class="person">
      <!-- Bahu & badan, berhenti di garis meja -->
      <path d="M124 158c0-17 12-28 28-28s28 11 28 28z" class="hoodie" />
      <!-- Lengan menjulur ke arah laptop -->
      <path d="M128 142c-8 4-12 10-12 16h13z" class="hoodie-dark" />
      <path d="M176 142c8 4 12 10 12 16h-13z" class="hoodie-dark" />

      <!-- Leher -->
      <rect x="146" y="120" width="12" height="12" rx="5" class="skin" />

      <!-- Kepala -->
      <g class="head">
        <rect x="134" y="86" width="36" height="38" rx="16" class="skin" />
        <path
          d="M134 103c0-13 8-22 18-22s18 9 18 22c-4-7-10-10-18-10s-14 3-18 10z"
          class="hair"
        />
        <circle cx="144" cy="106" r="6" class="glass" />
        <circle cx="160" cy="106" r="6" class="glass" />
        <path d="M150 106h4" class="glass-bridge" />
        <path d="M148 115c2 2.4 6 2.4 8 0" class="smile" />
      </g>
    </g>

    <!-- ---------- Meja ---------- -->
    <rect x="78" y="158" width="156" height="5" rx="2.5" class="desk" />

    <!-- ---------- Laptop, di depan karakter ---------- -->
    <g class="laptop">
      <rect x="126" y="136" width="52" height="22" rx="3" class="laptop-lid" />
      <rect x="130" y="140" width="44" height="14" rx="2" class="laptop-screen" />
      <path d="M118 158h68l4 5h-76z" class="laptop-base" />
    </g>

    <!-- Tanaman kecil -->
    <g class="plant">
      <path d="M96 158v-11" class="stem" />
      <path d="M96 149c-7-2-10-8-9-13 6 0 10 5 9 13z" class="leaf" />
      <path d="M96 151c6-3 8-9 6-14-6 1-9 6-6 14z" class="leaf leaf-b" />
      <path d="M89 158h14l-1.5 9h-11z" class="pot" />
    </g>

    <!-- Cangkir -->
    <g class="mug">
      <path d="M204 144h17v10a4 4 0 01-4 4h-9a4 4 0 01-4-4z" class="mug-body" />
      <path d="M221 147h3a3.5 3.5 0 010 7h-3" class="mug-handle" />
      <path d="M209 140c0-2 2-2 2-4M215 140c0-2 2-2 2-4" class="steam" />
    </g>

  </svg>
</template>

<style scoped>
.art {
  width: 100%;
  height: 100%;
  overflow: visible;
}

/* ---------- Latar ---------- */
.halo {
  fill: var(--brand);
  opacity: 0.05;
}
.wave {
  fill: var(--brand);
  opacity: 0.06;
}

/* ---------- Kartu mengambang ---------- */
.chip {
  fill: var(--bg-surface);
  stroke: var(--brand-border);
  stroke-width: 1.4;
}
.chip-code {
  stroke: var(--brand);
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
  fill: none;
}
.chip-line {
  fill: var(--brand);
  opacity: 0.5;
}
.chip-line.dim {
  fill: var(--n-300);
  opacity: 1;
}

.spark {
  fill: var(--brand);
  opacity: 0.35;
}
.dot {
  fill: var(--brand);
  opacity: 0.2;
}
.dot-b {
  fill: var(--ok);
  opacity: 0.25;
}
.dot-c {
  fill: var(--warn);
  opacity: 0.25;
}

/* ---------- Meja & karakter ---------- */
.desk {
  fill: var(--n-200);
}

.hoodie {
  fill: var(--brand);
}
.hoodie-dark {
  fill: var(--brand-hover);
}
.skin {
  fill: #f3c9a8;
}
.hair {
  fill: #2c3350;
}
.glass {
  fill: #ffffff;
  fill-opacity: 0.85;
  stroke: #2c3350;
  stroke-width: 1.6;
}
.glass-bridge {
  stroke: #2c3350;
  stroke-width: 1.6;
  stroke-linecap: round;
}
.smile {
  stroke: #a9714b;
  stroke-width: 1.6;
  stroke-linecap: round;
  fill: none;
}

.laptop-lid {
  fill: var(--n-300);
}
.laptop-screen {
  fill: var(--brand);
  opacity: 0.28;
}
.laptop-base {
  fill: var(--n-400);
}

/* ---------- Tanaman & cangkir ---------- */
.stem {
  stroke: #3f9a6b;
  stroke-width: 2;
  stroke-linecap: round;
}
.leaf {
  fill: #4fae7c;
}
.leaf-b {
  fill: #3f9a6b;
}
.pot {
  fill: #d98b5f;
}

.mug-body {
  fill: var(--bg-surface);
  stroke: var(--brand-border);
  stroke-width: 1.4;
}
.mug-handle {
  stroke: var(--brand-border);
  stroke-width: 1.4;
  fill: none;
}
.steam {
  stroke: var(--brand);
  stroke-width: 1.4;
  stroke-linecap: round;
  opacity: 0.3;
  fill: none;
}

/* ---------- Gerak ---------- */
@keyframes float-y {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-6px);
  }
}

/* Anggukan sangat kecil — memberi kesan sedang mengetik */
@keyframes nod {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(1.5px);
  }
}

@keyframes twinkle {
  0%,
  100% {
    opacity: 0.15;
    transform: scale(0.9);
  }
  50% {
    opacity: 0.45;
    transform: scale(1.1);
  }
}

.float {
  animation: float-y 6s var(--ease) infinite;
}
.float-b {
  animation-duration: 7.5s;
  animation-direction: reverse;
}
.head {
  animation: nod 3.2s var(--ease) infinite;
  transform-origin: 152px 110px;
}
.spark {
  animation: twinkle 4s var(--ease) infinite;
  transform-origin: center;
}
.spark-b {
  animation-duration: 5.2s;
  animation-delay: -1.5s;
}
.dot-a {
  animation: float-y 5s var(--ease) infinite;
}
.dot-b {
  animation: float-y 6.5s var(--ease) infinite reverse;
}
.dot-c {
  animation: float-y 5.6s var(--ease) infinite;
}

@media (prefers-reduced-motion: reduce) {
  .float,
  .head,
  .spark,
  .dot {
    animation: none;
  }
}
</style>
