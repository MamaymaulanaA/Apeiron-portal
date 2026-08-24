<script setup>
/**
 * Avatar dengan cincin progres.
 *
 * Persentasenya bukan angka hiasan — di dashboard ia memetakan tahap nyata
 * perjalanan lisensi user, diturunkan dari status transaksi yang dikirim
 * backend.
 */
import { computed } from 'vue'
import { initials } from '@/lib/format'

const props = defineProps({
  name: { type: String, default: '' },
  percent: { type: Number, default: 0 },
  size: { type: Number, default: 96 },
  stroke: { type: Number, default: 4 },
})

const clamped = computed(() => Math.max(0, Math.min(100, props.percent)))
const radius = computed(() => (props.size - props.stroke) / 2)
const circumference = computed(() => 2 * Math.PI * radius.value)
const offset = computed(() => circumference.value * (1 - clamped.value / 100))
const inner = computed(() => props.size - props.stroke * 2 - 8)
</script>

<template>
  <div class="ring" :style="{ width: size + 'px', height: size + 'px' }">
    <svg :width="size" :height="size" aria-hidden="true" focusable="false">
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
        :stroke-dasharray="circumference"
        :stroke-dashoffset="offset"
        stroke-linecap="round"
        fill="none"
      />
    </svg>

    <span class="ring-face" :style="{ width: inner + 'px', height: inner + 'px' }">
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
  stroke: var(--n-200);
}

.ring-bar {
  stroke: var(--brand);
  transition: stroke-dashoffset var(--dur) var(--ease);
}

.ring-face {
  display: grid;
  place-items: center;
  border-radius: var(--r-pill);
  background: var(--bg-surface);
  border: 1px solid var(--border);
  color: var(--brand);
  font-size: var(--fs-h2);
  font-weight: var(--fw-bold);
  overflow: hidden;
}
</style>
