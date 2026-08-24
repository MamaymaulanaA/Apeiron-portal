<script setup>
/**
 * Kartu ringkasan — mengikuti pola stat card pada referensi: chip ikon
 * berwarna, angka besar, lalu label.
 *
 * Latar mengikuti `tone`. Aturan design system tetap berlaku: radius 8px,
 * border 1px, tanpa bayangan.
 */
import { computed } from 'vue'
import { formatNumber } from '@/lib/format'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  icon: { type: [Object, Function], default: null },
  tone: { type: String, default: 'tone-sky' },
  hint: { type: String, default: '' },
  to: { type: String, default: '' },
})

const isNumeric = computed(() => typeof props.value === 'number')
const display = computed(() =>
  isNumeric.value ? formatNumber(props.value) : String(props.value)
)
</script>

<template>
  <component
    :is="to ? 'RouterLink' : 'div'"
    :to="to || undefined"
    class="stat"
    :class="[tone, { clickable: !!to }]"
  >
    <span v-if="icon" class="stat-icon">
      <component :is="icon" :size="18" :stroke-width="2" />
    </span>

    <span class="stat-text">
      <span class="stat-value">{{ display }}</span>
      <span class="stat-label">{{ label }}</span>
      <span v-if="hint" class="stat-hint">{{ hint }}</span>
    </span>
  </component>
</template>

<style scoped>
.stat {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  padding: var(--sp-4);
  background: var(--tone-bg);
  border: 1px solid var(--tone-border);
  border-radius: var(--r);
  transition: border-color var(--dur) var(--ease);
}

.stat.clickable:hover {
  border-color: var(--tone-fg);
}

.stat-icon {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  border-radius: var(--r);
  background: var(--bg-surface);
  color: var(--tone-fg);
}

.stat-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  line-height: 1.25;
}

/* Satu ukuran untuk semua nilai, apa pun isinya.
   Sebelumnya angka memakai 26px dan teks 18px, sehingga keempat kartu pada
   baris yang sama terlihat tidak seragam. */
.stat-value {
  font-size: var(--fs-h1);
  font-weight: var(--fw-bold);
  color: var(--text-heading);
  letter-spacing: var(--tracking-tight);
  line-height: 1.3;
  overflow-wrap: anywhere;
}

.stat-label {
  margin-top: 2px;
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  color: var(--text-body);
  line-height: var(--lh-snug);
}

.stat-hint {
  margin-top: 2px;
  font-size: var(--fs-caption);
  color: var(--text-muted);
}
</style>
