<script setup>
/**
 * Panduan — isi seksi Forum.
 *
 * Bukan forum diskusi: backend tidak punya utas maupun balasan, dan yang
 * dibutuhkan memang pusat panduan satu arah. Seluruh isinya dari
 * GET /user/tutorials; menambah atau mengubah tutorial di panel admin langsung
 * terlihat di sini tanpa ada yang perlu ditulis ulang di frontend.
 */
import { ref, computed, onMounted } from 'vue'
import {
  BookOpen,
  Search,
  ArrowRight,
  ExternalLink,
  Youtube,
  RotateCcw,
} from 'lucide-vue-next'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import ModalDialog from '@/components/ui/ModalDialog.vue'
import { api, errorMessage } from '@/lib/api'
import { tutorialIcon } from '@/lib/community'
import { toast } from '@/stores/toast'

const loading = ref(true)
const loadError = ref('')
const tutorials = ref([])
const categories = ref([])

const search = ref('')
const category = ref('all')

/** Detail dibuka lewat endpoint terpisah — daftar tidak mengangkut isi panjang. */
const detail = ref(null)
const detailLoading = ref(false)
const detailOpen = ref(false)

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return tutorials.value.filter((t) => {
    if (category.value !== 'all' && t.category !== category.value) return false
    if (!q) return true
    return (
      t.title.toLowerCase().includes(q) ||
      (t.excerpt || '').toLowerCase().includes(q) ||
      (t.category || '').toLowerCase().includes(q)
    )
  })
})

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/user/tutorials')
    tutorials.value = data.tutorials || []
    categories.value = data.categories || []
  } catch (e) {
    // Error state berdiri sendiri: daftar kosong karena gagal memuat berbeda
    // artinya dari daftar kosong karena memang belum ada isinya.
    loadError.value = errorMessage(e, 'Gagal memuat panduan')
  } finally {
    loading.value = false
  }
}

async function openDetail(item) {
  detailOpen.value = true
  detail.value = item
  if (!item.has_content) return

  detailLoading.value = true
  try {
    const { data } = await api.get(`/user/tutorials/${item.id}`)
    detail.value = data.tutorial
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat isi panduan'))
  } finally {
    detailLoading.value = false
  }
}

function closeDetail() {
  detailOpen.value = false
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>Panduan</h1>
        <p class="text-caption">
          Tutorial pemasangan, aktivasi lisensi, dan cara memakai produk.
        </p>
      </div>
    </header>

    <!-- Penyaring hanya tampil bila ada yang bisa disaring -->
    <div v-if="!loading && !loadError && tutorials.length" class="toolbar control-row">
      <div class="input-group toolbar-search">
        <Search class="input-icon" :size="16" />
        <input v-model="search" class="input" type="search" placeholder="Cari panduan…">
      </div>

      <select v-model="category" class="select toolbar-select">
        <option value="all">Semua kategori</option>
        <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
      </select>
    </div>

    <SkeletonRows v-if="loading" :rows="3" :height="140" />

    <!-- Error state -->
    <section v-else-if="loadError" class="card">
      <EmptyState :icon="BookOpen" title="Gagal memuat panduan" :description="loadError">
        <template #action>
          <button class="btn btn-secondary" type="button" @click="load">
            <RotateCcw :size="15" />
            Coba lagi
          </button>
        </template>
      </EmptyState>
    </section>

    <!-- Belum ada isi sama sekali -->
    <section v-else-if="!tutorials.length" class="card">
      <EmptyState
        :icon="BookOpen"
        title="Belum ada panduan"
        description="Tutorial dan panduan akan tampil di sini setelah admin menambahkannya."
      />
    </section>

    <!-- Ada isi, tapi tidak ada yang cocok dengan penyaring -->
    <section v-else-if="!filtered.length" class="card">
      <EmptyState
        :icon="Search"
        title="Tidak ada yang cocok"
        description="Coba kata kunci lain atau pilih kategori yang berbeda."
      >
        <template #action>
          <button
            class="btn btn-secondary"
            type="button"
            @click="search = ''; category = 'all'"
          >
            Hapus filter
          </button>
        </template>
      </EmptyState>
    </section>

    <ul v-else class="grid tut-grid">
      <li v-for="t in filtered" :key="t.id" class="card tut">
        <button class="tut-open" type="button" @click="openDetail(t)">
          <span class="icon-chip tone-sky">
            <component :is="tutorialIcon(t.icon)" :size="20" />
          </span>

          <span class="tut-body">
            <span v-if="t.category" class="badge tone-neutral">{{ t.category }}</span>
            <span class="tut-title">{{ t.title }}</span>
            <span v-if="t.excerpt" class="tut-excerpt">{{ t.excerpt }}</span>
          </span>
        </button>

        <footer class="tut-foot">
          <button class="link-more" type="button" @click="openDetail(t)">
            Baca panduan <ArrowRight :size="14" />
          </button>

          <a
            v-if="t.external_url"
            class="btn btn-sm btn-secondary"
            :href="t.external_url"
            target="_blank"
            rel="noopener noreferrer"
          >
            <component :is="t.youtube_id ? Youtube : ExternalLink" :size="14" />
            {{ t.youtube_id ? 'Tonton di YouTube' : 'Lihat Tutorial' }}
          </a>
        </footer>
      </li>
    </ul>

    <!-- ---------- Detail ---------- -->
    <ModalDialog
      :open="detailOpen"
      :title="detail?.title || ''"
      :subtitle="detail?.category || ''"
      size="lg"
      @close="closeDetail"
    >
      <SkeletonRows v-if="detailLoading" :rows="4" :height="20" />

      <template v-else>
        <p v-if="detail?.excerpt" class="detail-lead">{{ detail.excerpt }}</p>

        <!-- Isi ditampilkan sebagai teks, bukan v-html: apa pun yang diketik
             admin tetap jadi teks dan tidak pernah dieksekusi sebagai markup. -->
        <p v-for="(para, i) in (detail?.content || '').split('\n\n')" :key="i" class="detail-para">
          {{ para }}
        </p>

        <p v-if="!detail?.content" class="text-caption">
          Panduan ini belum punya isi tertulis.
          <template v-if="detail?.external_url">Silakan buka tautannya di bawah.</template>
        </p>
      </template>

      <template #footer>
        <button class="btn btn-secondary" type="button" @click="closeDetail">Tutup</button>
        <a
          v-if="detail?.external_url"
          class="btn btn-primary"
          :href="detail.external_url"
          target="_blank"
          rel="noopener noreferrer"
        >
          <component :is="detail.youtube_id ? Youtube : ExternalLink" :size="15" />
          {{ detail.youtube_id ? 'Tonton di YouTube' : 'Lihat Tutorial' }}
        </a>
      </template>
    </ModalDialog>
  </div>
</template>

<style scoped>
.toolbar {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-3);
}
.toolbar-search {
  flex: 1 1 260px;
  min-width: 160px;
}
.toolbar-select {
  flex: 0 0 auto;
}

@media (max-width: 640px) {
  .toolbar-search,
  .toolbar-select {
    flex: 1 1 100%;
    width: 100%;
  }
}

/* minmax(min(…), 1fr) — bukan 1fr — supaya kolom boleh menyusut di layar
   sempit; 1fr tidak mau lebih kecil dari konten terlebarnya dan membuat
   halaman ikut melebar. */
.tut-grid {
  grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr));
}

.tut {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* Seluruh badan kartu jadi satu tombol — sasaran kliknya besar, dan pembaca
   layar mendapat satu kontrol, bukan judul dan ringkasan yang berdiri sendiri. */
.tut-open {
  display: flex;
  gap: var(--sp-4);
  flex: 1;
  padding: var(--sp-5);
  text-align: left;
  transition: background var(--dur) var(--ease);
}
.tut-open:hover {
  background: var(--bg-hover);
}

.tut-body {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--sp-2);
  min-width: 0;
}

.tut-title {
  font-size: var(--fs-body-lg);
  font-weight: var(--fw-semibold);
  color: var(--text-heading);
  line-height: var(--lh-snug);
}

.tut-excerpt {
  font-size: var(--fs-caption);
  color: var(--text-muted);
  line-height: var(--lh-snug);
  display: -webkit-box;
  -webkit-line-clamp: 3;
  line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.tut-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-5);
  border-top: 1px solid var(--border);
}

.link-more {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  font-size: var(--fs-caption);
  font-weight: var(--fw-medium);
  color: var(--brand);
}
.link-more:hover {
  color: var(--brand-hover);
}

.detail-lead {
  font-size: var(--fs-body);
  color: var(--text-body);
  line-height: var(--lh-snug);
}

.detail-para {
  color: var(--text-body);
  line-height: var(--lh-normal);
}
.detail-para + .detail-para {
  margin-top: var(--sp-3);
}
.detail-lead + .detail-para {
  margin-top: var(--sp-4);
}
</style>
