<script setup>
/**
 * Avatar dengan cincin progres.
 *
 * Persentasenya BUKAN angka hiasan — di dashboard ia memetakan tahap nyata
 * perjalanan lisensi user (lihat `licenseProgress` di DashboardView), sehingga
 * cincin ini selalu mencerminkan status transaksi yang dikirim backend.
 */
import { computed } from 'vue'
import { initials } from '@/lib/format'

const props = defineProps({
  name: { type: String, default: '' },
  percent: { type: Number, default: 0 },
  size: { type: Number, default: 96 },
  stroke: { type: Number, default: 5 },
})

const clamped = computed(() => Math.max(0, Math.min(100, props.percent)))
const radius = computed(() => (props.size - props.stroke) / 2)
const circumference = computed(() => 2 * Math.PI * radius.value)
const offset = computed(
  () => circumference.value * (1 - clamped.value / 100)
)
const inner = computed(() => props.size - props.stroke * 2 - 6)
</script>

<template>
  <div class="ring" :style="{ width: size + 'px', height: size + 'px' }">
    <svg :width="size" :height="size" aria-hidden="true">
      <defs>
        <linearGradient :id="`ring-${size}`" x1="0" y1="0" x2="1" y2="1">
          <stop offset="0%" stop-color="#2f80ed" />
          <stop offset="100%" stop-color="#56ccf2" />
        </linearGradient>
      </defs>

      <circle
        class="ring-track"
        :cx="size / 2"
        :cy="size / 2"
        :r="radius"
        :stroke-width="stroke"
        fill="none"
      />
      <circle
        class="ring-bar"
        :cx="size / 2"
        :cy="size / 2"
        :r="radius"
        :stroke-width="stroke"
        :stroke="`url(#ring-${size})`"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="offset"
        stroke-linecap="round"
        fill="none"
      />
    </svg>

    <span
      class="ring-face"
      :style="{ width: inner + 'px', height: inner + 'px' }"
    >
      {{ initials(name) }}
    </span>
  </div>
</template>

<style scoped>
.ring {
  position: relative;
  display: grid;
  place-items: center;
  flex-shrink: 0;
}

.ring svg {
  position: absolute;
  inset: 0;
  /* Mulai dari jam 12, bukan jam 3 */
  transform: rotate(-90deg);
}

.ring-track {
  stroke: var(--c-primary-border);
  opacity: 0.55;
}

.ring-bar {
  transition: stroke-dashoffset var(--dur-slow) var(--ease);
}

.ring-face {
  display: grid;
  place-items: center;
  border-radius: var(--r-pill);
  background: var(--bg-surface);
  color: var(--c-primary);
  font-size: var(--fs-h2);
  font-weight: var(--fw-bold);
  box-shadow: var(--sh-xs);
  overflow: hidden;
}
</style>
