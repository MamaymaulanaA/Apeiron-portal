<script setup>
/**
 * Lapisan dekoratif di belakang isi banner: gelombang lembut, butir kecil,
 * belah ketupat, dan kilau.
 *
 * Digambar sebagai SVG inline, bukan lewat pustaka partikel. Pola pada
 * referensi bersifat statis — sekadar gelombang dan bentuk yang tersebar —
 * sehingga menambah pustaka hanya akan membebani halaman tanpa hasil yang
 * berbeda. Gerakannya sangat pelan dan berhenti pada prefers-reduced-motion.
 */
</script>

<template>
  <svg
    class="backdrop"
    viewBox="0 0 1200 260"
    preserveAspectRatio="xMidYMid slice"
    fill="none"
    aria-hidden="true"
    focusable="false"
  >
    <!-- ---------- Gelombang ---------- -->
    <path
      class="wave wave-1"
      d="M0 196c118-54 214-8 330-46s186-118 316-104 214 122 330 108 174-56 224-72v250H0z"
    />
    <path
      class="wave wave-2"
      d="M0 232c140-42 236 4 356-36s206-96 322-84 224 106 330 96 152-40 192-52v268H0z"
    />

    <!-- ---------- Butir kecil ---------- -->
    <g class="drift drift-a">
      <circle class="dot" cx="150" cy="58" r="4" />
      <circle class="dot sm" cx="262" cy="150" r="2.5" />
      <circle class="dot lg" cx="612" cy="42" r="5" />
      <circle class="dot sm" cx="742" cy="196" r="3" />
      <circle class="dot" cx="1042" cy="70" r="4" />
    </g>

    <g class="drift drift-b">
      <circle class="dot sm" cx="96" cy="132" r="3" />
      <circle class="dot" cx="470" cy="212" r="3.5" />
      <circle class="dot sm" cx="880" cy="36" r="2.5" />
      <circle class="dot lg" cx="1136" cy="168" r="4.5" />
    </g>

    <!-- ---------- Belah ketupat ---------- -->
    <g class="drift drift-c">
      <rect class="gem" x="352" y="70" width="13" height="13" rx="2.5" transform="rotate(45 358.5 76.5)" />
      <rect class="gem soft" x="690" y="150" width="11" height="11" rx="2" transform="rotate(45 695.5 155.5)" />
      <rect class="gem" x="988" y="204" width="10" height="10" rx="2" transform="rotate(45 993 209)" />
      <rect class="gem soft" x="196" y="200" width="9" height="9" rx="2" transform="rotate(45 200.5 204.5)" />
    </g>

    <!-- ---------- Kilau ---------- -->
    <path class="spark spark-a" d="M540 96l3.4 7L551 106l-7.6 3.2L540 117l-3.4-7.8L529 106l7.6-3z" />
    <path class="spark spark-b" d="M932 128l2.6 5.4 5.4 2.6-5.4 2.6-2.6 5.4-2.6-5.4-5.4-2.6 5.4-2.6z" />
    <path class="spark spark-c" d="M300 30l2.2 4.6 4.6 2.2-4.6 2.2-2.2 4.6-2.2-4.6-4.6-2.2 4.6-2.2z" />
  </svg>
</template>

<style scoped>
.backdrop {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
}

/* ---------- Gelombang ---------- */
.wave-1 {
  fill: #ffffff;
  opacity: 0.5;
}
.wave-2 {
  fill: #ffffff;
  opacity: 0.75;
}

/* ---------- Butir & bentuk ---------- */
.dot {
  fill: var(--brand);
  opacity: 0.16;
}
.dot.sm {
  opacity: 0.12;
}
.dot.lg {
  opacity: 0.2;
}

.gem {
  fill: var(--brand);
  opacity: 0.2;
}
.gem.soft {
  opacity: 0.12;
}

.spark {
  fill: var(--brand);
  opacity: 0.22;
}

/* ---------- Gerak ---------- */
@keyframes drift {
  0%,
  100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-9px);
  }
}

@keyframes twinkle {
  0%,
  100% {
    opacity: 0.1;
  }
  50% {
    opacity: 0.3;
  }
}

.drift {
  animation: drift 9s var(--ease) infinite;
}
.drift-b {
  animation-duration: 11s;
  animation-direction: reverse;
}
.drift-c {
  animation-duration: 13s;
}

.spark {
  animation: twinkle 5s var(--ease) infinite;
}
.spark-b {
  animation-duration: 6.5s;
  animation-delay: -2s;
}
.spark-c {
  animation-duration: 7.5s;
  animation-delay: -3.5s;
}

@media (prefers-reduced-motion: reduce) {
  .drift,
  .spark {
    animation: none;
  }
}
</style>
