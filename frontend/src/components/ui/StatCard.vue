<script setup>
/**
 * Kartu statistik pastel — mengikuti pola stat card pada referensi:
 * chip ikon berwarna + angka besar + label.
 */
import { computed } from 'vue'
import { formatNumber } from '@/lib/format'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  icon: { type: [Object, Function], default: null },
  tone: { type: String, default: 'tone-blue' },
  hint: { type: String, default: '' },
  to: { type: String, default: '' },
})

const isNumeric = computed(() => typeof props.value === 'number')

const display = computed(() =>
  isNumeric.value ? formatNumber(props.value) : String(props.value)
)

/** Ukuran display (28px) hanya cocok untuk angka pendek. Nilai berupa teks
 *  seperti "Menunggu Verifikasi" diturunkan agar tidak membungkus dan tetap
 *  sejajar dengan kartu lain. */
const isLongText = computed(() => !isNumeric.value && display.value.length > 8)
</script>

<template>
  <component
    :is="to ? 'RouterLink' : 'div'"
    :to="to || undefined"
    class="stat"
    :class="[tone, { clickable: !!to }]"
  >
    <span class="icon-chip">
      <component :is="icon" v-if="icon" :size="20" :stroke-width="2.2" />
    </span>

    <span class="stat-text">
      <span class="stat-value" :class="{ 'is-text': isLongText }">
        {{ display }}
      </span>
      <span class="stat-label">{{ label }}</span>
      <span v-if="hint" class="stat-hint">{{ hint }}</span>
    </span>
  </component>
</template>

<style scoped>
.stat {
  display: flex;
  align-items: center;
  gap: var(--sp-4);
  padding: var(--sp-5);
  background: var(--tone-bg);
  border: 1px solid var(--tone-border);
  border-radius: var(--r-lg);
  transition: transform var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
}

.stat.clickable:hover {
  transform: translateY(-2px);
  box-shadow: var(--sh-md);
}

.icon-chip {
  background: var(--bg-surface);
  color: var(--tone-fg);
  box-shadow: var(--sh-xs);
}

.stat-text {
  display: flex;
  flex-direction: column;
  min-width: 0;
  line-height: 1.25;
}

.stat-value {
  font-size: var(--fs-display);
  font-weight: var(--fw-bold);
  color: var(--text-heading);
  letter-spacing: var(--tracking-tight);
}

/* Nilai berupa teks panjang: turunkan ke skala judul kartu supaya muat
   satu–dua baris tanpa merusak tinggi kartu. */
.stat-value.is-text {
  font-size: var(--fs-h3);
  line-height: var(--lh-snug);
  letter-spacing: var(--tracking-normal);
}

.stat-label {
  margin-top: 2px;
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  color: var(--text-body);
}

.stat-hint {
  margin-top: var(--sp-1);
  font-size: var(--fs-caption);
  color: var(--text-muted);
}

@media (max-width: 640px) {
  .stat {
    padding: var(--sp-4);
    gap: var(--sp-3);
  }
  .stat-value {
    font-size: var(--fs-h1);
  }
}
</style>
