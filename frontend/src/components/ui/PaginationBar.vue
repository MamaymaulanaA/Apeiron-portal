<script setup>
/**
 * Paginasi.
 * Backend memakai bentuk { current_page, per_page, total, total_pages }.
 */
import { computed } from 'vue'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'
import { formatNumber } from '@/lib/format'

const props = defineProps({
  pagination: { type: Object, required: true },
})
const emit = defineEmits(['change'])

const page = computed(() => Number(props.pagination?.current_page) || 1)
const totalPages = computed(() => Number(props.pagination?.total_pages) || 1)
const total = computed(() => Number(props.pagination?.total) || 0)
const perPage = computed(() => Number(props.pagination?.per_page) || 15)

const from = computed(() => (total.value === 0 ? 0 : (page.value - 1) * perPage.value + 1))
const to = computed(() => Math.min(page.value * perPage.value, total.value))

/** Deret nomor halaman dengan elipsis untuk dataset besar. */
const pages = computed(() => {
  const last = totalPages.value
  const cur = page.value
  if (last <= 7) return Array.from({ length: last }, (_, i) => i + 1)

  const out = [1]
  const start = Math.max(2, cur - 1)
  const end = Math.min(last - 1, cur + 1)
  if (start > 2) out.push('…')
  for (let i = start; i <= end; i++) out.push(i)
  if (end < last - 1) out.push('…')
  out.push(last)
  return out
})

function go(p) {
  if (p === '…' || p === page.value || p < 1 || p > totalPages.value) return
  emit('change', p)
}
</script>

<template>
  <nav v-if="totalPages > 1 || total > 0" class="pager" aria-label="Paginasi">
    <p class="pager-info">
      Menampilkan <strong>{{ formatNumber(from) }}–{{ formatNumber(to) }}</strong>
      dari <strong>{{ formatNumber(total) }}</strong>
    </p>

    <div v-if="totalPages > 1" class="pager-controls">
      <button
        class="pager-btn"
        type="button"
        :disabled="page <= 1"
        aria-label="Halaman sebelumnya"
        @click="go(page - 1)"
      >
        <ChevronLeft :size="16" />
      </button>

      <button
        v-for="(p, i) in pages"
        :key="`${p}-${i}`"
        class="pager-btn"
        :class="{ active: p === page, gap: p === '…' }"
        type="button"
        :disabled="p === '…'"
        @click="go(p)"
      >
        {{ p }}
      </button>

      <button
        class="pager-btn"
        type="button"
        :disabled="page >= totalPages"
        aria-label="Halaman berikutnya"
        @click="go(page + 1)"
      >
        <ChevronRight :size="16" />
      </button>
    </div>
  </nav>
</template>

<style scoped>
.pager {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-4);
  flex-wrap: wrap;
  padding: var(--sp-4) var(--sp-6);
  border-top: 1px solid var(--border);
}

.pager-info {
  font-size: var(--fs-caption);
  color: var(--text-muted);
}
.pager-info strong {
  color: var(--text-heading);
  font-weight: var(--fw-semibold);
}

.pager-controls {
  display: flex;
  align-items: center;
  gap: var(--sp-1);
}

.pager-btn {
  display: grid;
  place-items: center;
  min-width: 34px;
  height: 34px;
  padding: 0 var(--sp-2);
  border-radius: var(--r-sm);
  font-size: var(--fs-label);
  font-weight: var(--fw-semibold);
  color: var(--text-body);
  transition: background var(--dur) var(--ease), color var(--dur) var(--ease);
}

.pager-btn:hover:not(:disabled):not(.gap) {
  background: var(--bg-hover);
  color: var(--text-heading);
}

.pager-btn.active {
  background: var(--brand);
  color: #fff;
}

.pager-btn:disabled {
  opacity: 0.45;
  cursor: not-allowed;
}
.pager-btn.gap {
  opacity: 1;
  color: var(--text-faint);
  cursor: default;
}

@media (max-width: 640px) {
  .pager {
    justify-content: center;
    padding: var(--sp-4);
  }
}
</style>
