<script setup>
/**
 * Kartu kolom kanan: ikon + judul, tautan "Lihat semua" opsional, tab opsional.
 * Isi kartu diberikan lewat slot default.
 */
defineProps({
  title: { type: String, required: true },
  icon: { type: [Object, Function], default: null },
  tone: { type: String, default: 'tone-blue' },
  to: { type: String, default: '' },
  tabs: { type: Array, default: () => [] },
  activeTab: { type: String, default: '' },
})
defineEmits(['tab'])
</script>

<template>
  <section class="card rail-card">
    <header class="rail-head">
      <h3>
        <span v-if="icon" class="icon-chip icon-chip-sm" :class="tone">
          <component :is="icon" :size="16" />
        </span>
        {{ title }}
      </h3>

      <RouterLink v-if="to" :to="to" class="rail-more">Lihat semua</RouterLink>
    </header>

    <div v-if="tabs.length" class="rail-tabs" role="tablist">
      <button
        v-for="t in tabs"
        :key="t"
        class="rail-tab"
        :class="{ active: t === activeTab }"
        type="button"
        role="tab"
        :aria-selected="t === activeTab"
        @click="$emit('tab', t)"
      >
        {{ t }}
      </button>
    </div>

    <div class="rail-body scroll-area">
      <slot />
    </div>
  </section>
</template>

<style scoped>
.rail-card {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.rail-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  padding: var(--sp-5) var(--sp-5) var(--sp-4);
}

.rail-head h3 {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  font-size: var(--fs-h3);
}

.rail-more {
  font-size: var(--fs-caption);
  font-weight: var(--fw-semibold);
  color: var(--c-primary);
  white-space: nowrap;
}
.rail-more:hover {
  color: var(--c-primary-hover);
}

/* ---- Tab ---- */
.rail-tabs {
  display: flex;
  gap: var(--sp-4);
  padding: 0 var(--sp-5);
  border-bottom: 1px solid var(--border);
}

.rail-tab {
  position: relative;
  padding-bottom: var(--sp-3);
  font-size: var(--fs-label);
  font-weight: var(--fw-semibold);
  color: var(--text-muted);
  transition: color var(--dur) var(--ease);
}
.rail-tab:hover {
  color: var(--text-heading);
}
.rail-tab.active {
  color: var(--c-primary);
}
.rail-tab.active::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: -1px;
  height: 2px;
  border-radius: 2px 2px 0 0;
  background: var(--c-primary);
}

.rail-body {
  padding: var(--sp-4) var(--sp-5) var(--sp-5);
  max-height: 340px;
}
</style>
