<script setup>
/**
 * Empty state.
 *
 * Dipakai apa adanya ketika memang tidak ada data — bukan tempat menaruh
 * contoh. Varian `compact` untuk kartu sempit di kolom kanan.
 */
import { Inbox } from 'lucide-vue-next'

defineProps({
  icon: { type: [Object, Function], default: () => Inbox },
  title: { type: String, default: 'Belum ada data' },
  description: { type: String, default: '' },
  compact: { type: Boolean, default: false },
})
</script>

<template>
  <div class="empty" :class="{ compact }">
    <span class="empty-mark">
      <!-- Latar dekoratif ringan, digambar langsung agar tidak menarik aset -->
      <svg
        class="empty-bg"
        viewBox="0 0 64 64"
        fill="none"
        aria-hidden="true"
        focusable="false"
      >
        <circle cx="32" cy="32" r="31" stroke="currentColor" stroke-dasharray="3 4" />
      </svg>
      <component :is="icon" :size="compact ? 18 : 20" :stroke-width="1.75" />
    </span>

    <p class="empty-title">{{ title }}</p>
    <p v-if="description" class="empty-desc">{{ description }}</p>

    <div v-if="$slots.action" class="empty-action">
      <slot name="action" />
    </div>
  </div>
</template>

<style scoped>
.empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-1);
  padding: var(--sp-10) var(--sp-5);
  text-align: center;
}

.empty.compact {
  padding: var(--sp-6) var(--sp-5);
}

.empty-mark {
  position: relative;
  display: grid;
  place-items: center;
  width: 44px;
  height: 44px;
  margin-bottom: var(--sp-3);
  color: var(--n-400);
}
.empty.compact .empty-mark {
  width: 38px;
  height: 38px;
  margin-bottom: var(--sp-2);
}

.empty-bg {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  color: var(--n-200);
}

.empty-title {
  font-size: var(--fs-body);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
}

.empty-desc {
  font-size: var(--fs-caption);
  color: var(--text-muted);
  line-height: var(--lh-snug);
  max-width: 40ch;
}

.empty-action {
  margin-top: var(--sp-4);
}
</style>
