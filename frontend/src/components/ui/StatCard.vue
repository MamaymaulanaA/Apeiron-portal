<script setup>
/**
 * Kartu statistik.
 *
 * Kartu selalu berlatar netral. Warna hanya muncul lewat `tone` bila angkanya
 * memang menyatakan status (mis. transaksi ditolak), bukan untuk membedakan
 * satu kartu dari kartu lain.
 */
import { computed } from 'vue'
import { formatNumber } from '@/lib/format'

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [Number, String], default: 0 },
  icon: { type: [Object, Function], default: null },
  /** Beri nada hanya bila angka ini menyatakan keadaan. */
  tone: { type: String, default: 'tone-neutral' },
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
    <div class="stat-top">
      <span class="stat-label">{{ label }}</span>
      <span v-if="icon" class="stat-icon">
        <component :is="icon" :size="16" :stroke-width="2" />
      </span>
    </div>

    <span class="stat-value" :class="{ 'is-text': !isNumeric }">{{ display }}</span>
    <span v-if="hint" class="stat-hint">{{ hint }}</span>
  </component>
</template>

<style scoped>
.stat {
  display: flex;
  flex-direction: column;
  gap: var(--sp-2);
  padding: var(--sp-4);
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r);
  transition: border-color var(--dur) var(--ease);
}

.stat.clickable:hover {
  border-color: var(--n-400);
}

.stat-top {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-2);
}

/* Label dibiarkan membungkus (maks. 2 baris) alih-alih dipotong — pada kartu
   sempit, memotong label membuat artinya hilang. */
.stat-label {
  font-size: var(--fs-label);
  color: var(--text-muted);
  min-width: 0;
  line-height: var(--lh-snug);
  display: -webkit-box;
  -webkit-line-clamp: 2;
  line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Ikon netral secara bawaan; mengambil warna nada hanya bila diberi. */
.stat-icon {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  flex-shrink: 0;
  border-radius: var(--r-sm);
  background: var(--tone-bg, var(--n-100));
  color: var(--tone-fg, var(--n-500));
}

.stat-value {
  font-size: var(--fs-display);
  font-weight: var(--fw-semibold);
  color: var(--text-heading);
  letter-spacing: var(--tracking-tight);
  line-height: 1.15;
}

/* Nilai berupa teks memakai skala lebih kecil agar tidak membungkus. */
.stat-value.is-text {
  font-size: var(--fs-h2);
  line-height: var(--lh-snug);
}

.stat-hint {
  font-size: var(--fs-caption);
  color: var(--text-faint);
}
</style>
