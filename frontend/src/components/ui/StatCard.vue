<script setup>
/**
 * Kartu ringkasan — chip ikon berwarna, angka besar, lalu label.
 *
 * Kartunya putih dan bergaris netral seperti kartu lain di dashboard.
 * SATU-SATUNYA bagian berwarna adalah chip ikon; mewarnai seluruh latar
 * membuat empat kartu sebaris terbaca sebagai hiasan tanpa alasan.
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
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r);
  transition: border-color var(--dur) var(--ease);
}

/* Hover cukup menegaskan garis netral. Melompat ke warna penuh membuat satu
   kartu terlihat menyala sendiri di antara yang lain. */
.stat.clickable:hover {
  border-color: var(--border-strong);
}

.stat-icon {
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  flex-shrink: 0;
  border-radius: var(--r);
  background: var(--tone-bg);
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
