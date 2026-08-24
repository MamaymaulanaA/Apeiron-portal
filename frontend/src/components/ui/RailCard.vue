<script setup>
/**
 * Kartu kolom kanan: chip ikon + judul, tautan "Lihat semua" opsional,
 * dan tab opsional. Isi diberikan lewat slot default.
 */
defineProps({
  title: { type: String, required: true },
  icon: { type: [Object, Function], default: null },
  tone: { type: String, default: 'tone-sky' },
  to: { type: String, default: '' },
  tabs: { type: Array, default: () => [] },
  activeTab: { type: String, default: '' },
})
defineEmits(['tab'])
</script>

<template>
  <section class="card rail-card">
    <header class="card-header">
      <h2>
        <span v-if="icon" class="icon-chip icon-chip-sm" :class="tone">
          <component :is="icon" :size="15" />
        </span>
        {{ title }}
      </h2>

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

    <div class="rail-body">
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

.rail-more {
  font-size: var(--fs-caption);
  font-weight: var(--fw-medium);
  color: var(--brand);
  white-space: nowrap;
}
.rail-more:hover {
  color: var(--brand-hover);
}

.rail-tabs {
  display: flex;
  gap: var(--sp-4);
  padding: 0 var(--sp-5);
  border-bottom: 1px solid var(--border);
}

.rail-tab {
  position: relative;
  padding: var(--sp-3) 0;
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  color: var(--text-muted);
  transition: color var(--dur) var(--ease);
}
.rail-tab:hover {
  color: var(--text-heading);
}
.rail-tab.active {
  color: var(--brand);
}
.rail-tab.active::after {
  content: '';
  position: absolute;
  left: 0;
  right: 0;
  bottom: -1px;
  height: 2px;
  background: var(--brand);
}
</style>
