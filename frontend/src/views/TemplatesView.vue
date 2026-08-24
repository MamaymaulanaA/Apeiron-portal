<script setup>
/**
 * Katalog template — GET /user/templates.
 * checkout_url dibuka di tab baru, sama seperti perilaku sebelumnya.
 */
import { ref, computed, onMounted } from 'vue'
import { LayoutTemplate, ExternalLink, Star } from 'lucide-vue-next'
import EmptyState from '@/components/ui/EmptyState.vue'
import { api, errorMessage } from '@/lib/api'
import { toast } from '@/stores/toast'

const loading = ref(true)
const items = ref([])
const filter = ref('all') // all | standard | premium

const filtered = computed(() => {
  if (filter.value === 'standard') return items.value.filter((t) => Number(t.is_standard))
  if (filter.value === 'premium') return items.value.filter((t) => !Number(t.is_standard))
  return items.value
})

const counts = computed(() => ({
  all: items.value.length,
  standard: items.value.filter((t) => Number(t.is_standard)).length,
  premium: items.value.filter((t) => !Number(t.is_standard)).length,
}))

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/user/templates')
    items.value = data.templates || []
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat template'))
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>Template Undangan</h1>
        <p class="text-muted">Pilih template yang sesuai dengan kebutuhan Anda.</p>
      </div>

      <div v-if="items.length" class="tabs" role="tablist">
        <button
          v-for="tab in [
            { key: 'all', label: 'Semua' },
            { key: 'standard', label: 'Standard' },
            { key: 'premium', label: 'Premium' },
          ]"
          :key="tab.key"
          class="tab"
          :class="{ active: filter === tab.key }"
          type="button"
          role="tab"
          :aria-selected="filter === tab.key"
          @click="filter = tab.key"
        >
          {{ tab.label }}
          <span class="tab-count">{{ counts[tab.key] }}</span>
        </button>
      </div>
    </header>

    <!-- Skeleton kartu -->
    <div v-if="loading" class="grid cards">
      <div v-for="i in 6" :key="i" class="card skeleton-card" />
    </div>

    <div v-else-if="filtered.length" class="grid cards">
      <article v-for="t in filtered" :key="t.id" class="tpl card">
        <div class="tpl-thumb">
          <img
            v-if="t.thumbnail_path"
            :src="`/storage/templates/thumbnails/${t.thumbnail_path}`"
            :alt="t.name"
            loading="lazy"
          >
          <LayoutTemplate v-else :size="30" />

          <span v-if="Number(t.is_recommended)" class="tpl-flag">
            <Star :size="12" fill="currentColor" />
            Rekomendasi
          </span>
        </div>

        <div class="tpl-body">
          <header class="row-between">
            <h3 class="tpl-name">{{ t.name }}</h3>
            <span
              class="badge"
              :class="Number(t.is_standard) ? 'tone-blue' : 'tone-purple'"
            >
              {{ Number(t.is_standard) ? 'Standard' : 'Premium' }}
            </span>
          </header>

          <p v-if="t.description" class="tpl-desc">{{ t.description }}</p>

          <a
            v-if="t.checkout_url"
            class="btn btn-primary btn-sm tpl-cta"
            :href="t.checkout_url"
            target="_blank"
            rel="noopener noreferrer"
          >
            Pesan Template
            <ExternalLink :size="14" />
          </a>
        </div>
      </article>
    </div>

    <div v-else class="card">
      <EmptyState
        :icon="LayoutTemplate"
        tone="tone-orange"
        title="Tidak ada template"
        :description="
          filter === 'all'
            ? 'Template akan muncul di sini setelah ditambahkan admin.'
            : 'Tidak ada template pada kategori ini.'
        "
      />
    </div>
  </div>
</template>

<style scoped>
.cards {
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
}

.skeleton-card {
  height: 300px;
  background: linear-gradient(
    90deg,
    var(--bg-subtle) 25%,
    var(--bg-hover) 50%,
    var(--bg-subtle) 75%
  );
  background-size: 200% 100%;
  animation: shimmer 1.4s var(--ease) infinite;
  border: none;
}

/* ---- Tab ---- */
.tabs {
  display: flex;
  gap: var(--sp-1);
  padding: var(--sp-1);
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r-md);
}

.tab {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-2) var(--sp-4);
  border-radius: var(--r-sm);
  font-size: var(--fs-label);
  font-weight: var(--fw-semibold);
  color: var(--text-muted);
  transition: background var(--dur) var(--ease), color var(--dur) var(--ease);
}
.tab:hover {
  background: var(--bg-hover);
  color: var(--text-heading);
}
.tab.active {
  background: var(--c-primary);
  color: #fff;
}

.tab-count {
  padding: 0 var(--sp-2);
  border-radius: var(--r-pill);
  background: var(--bg-subtle);
  font-size: var(--fs-micro);
  color: var(--text-muted);
}
.tab.active .tab-count {
  background: rgba(255, 255, 255, 0.22);
  color: #fff;
}

/* ---- Kartu template ---- */
.tpl {
  display: flex;
  flex-direction: column;
  overflow: hidden;
  transition: transform var(--dur) var(--ease), box-shadow var(--dur) var(--ease);
}
.tpl:hover {
  transform: translateY(-3px);
  box-shadow: var(--sh-md);
}

.tpl-thumb {
  position: relative;
  display: grid;
  place-items: center;
  aspect-ratio: 16 / 10;
  background: var(--c-accent-soft);
  color: var(--c-accent);
  overflow: hidden;
}
.tpl-thumb img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.tpl-flag {
  position: absolute;
  top: var(--sp-3);
  left: var(--sp-3);
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  padding: var(--sp-1) var(--sp-3);
  border-radius: var(--r-pill);
  background: var(--c-warning);
  color: #fff;
  font-size: var(--fs-micro);
  font-weight: var(--fw-bold);
}

.tpl-body {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  padding: var(--sp-5);
  flex: 1;
}

.tpl-name {
  font-size: var(--fs-h3);
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.tpl-desc {
  font-size: var(--fs-label);
  color: var(--text-muted);
  line-height: var(--lh-normal);
  display: -webkit-box;
  -webkit-line-clamp: 3;
  line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.tpl-cta {
  margin-top: auto;
  align-self: flex-start;
}

@media (max-width: 640px) {
  .tabs {
    width: 100%;
    overflow-x: auto;
  }
  .tab {
    flex: 1;
    justify-content: center;
    white-space: nowrap;
  }
}
</style>
