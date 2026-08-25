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
  /**
   * Angka kecil di sebelah judul. Tempatnya di header supaya badan kartu bisa
   * berisi satu hal saja — daftar, deretan avatar — tanpa baris angka yang
   * ikut memakan tinggi.
   */
  badge: { type: [String, Number], default: null },
  tabs: { type: Array, default: () => [] },
  activeTab: { type: String, default: '' },
  /**
   * Hilangkan padding badan kartu. Dipakai bila isi slot sudah mengatur
   * paddingnya sendiri — daftar berbaris dan empty state, misalnya —
   * supaya tidak menumpuk dua lapis jarak.
   */
  flush: { type: Boolean, default: false },
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
        <span v-if="badge !== null && badge !== ''" class="badge tone-neutral">{{ badge }}</span>
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

    <div class="rail-body" :class="{ flush }">
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

/* Padding badan kartu. Tanpa ini isi slot menempel ke tepi kartu — persis
   yang terjadi pada kartu Lisensi dan Plugin. */
.rail-body {
  padding: var(--sp-5);
}

.rail-body.flush {
  padding: 0;
}

/* Jarak antar-blok di dalam badan kartu diseragamkan dari satu tempat,
   sehingga tiap kartu punya ritme vertikal yang sama. */
.rail-body > :deep(* + *) {
  margin-top: var(--sp-4);
}
</style>
