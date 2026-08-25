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
  LayoutGrid,
  List,
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
/** Grid atau daftar — pilihan yang sama dengan katalog template. */
const view = ref('grid')

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
    <div v-if="!loading && !loadError && tutorials.length" class="control-row">
      <div class="input-group toolbar-search">
        <Search class="input-icon" :size="16" />
        <input v-model="search" class="input" type="search" placeholder="Cari panduan…">
      </div>

      <select v-model="category" class="select toolbar-select">
        <option value="all">Semua kategori</option>
        <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
      </select>

      <div class="segmented">
        <button
          class="segmented-item"
          :class="{ active: view === 'grid' }"
          type="button"
          aria-label="Tampilan grid"
          @click="view = 'grid'"
        >
          <LayoutGrid :size="15" />
        </button>
        <button
          class="segmented-item"
          :class="{ active: view === 'list' }"
          type="button"
          aria-label="Tampilan daftar"
          @click="view = 'list'"
        >
          <List :size="15" />
        </button>
      </div>
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

    <ul v-else class="tut-grid" :class="view === 'grid' ? 'grid' : 'tut-list-view'">
      <li v-for="t in filtered" :key="t.id" class="card tut">
        <button class="tut-open" type="button" @click="openDetail(t)">
          <!-- Gambar bila ada; kalau tidak, chip ikon supaya tinggi kartunya
               tetap sama dan barisnya tidak bergerigi. -->
          <span v-if="t.image_path" class="tut-media">
            <img :src="`/storage/tutorials/${t.image_path}`" :alt="t.title" loading="lazy">
          </span>
          <span v-else class="icon-chip tone-sky tut-icon">
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
        <img
          v-if="detail?.image_path"
          class="detail-img"
          :src="`/storage/tutorials/${detail.image_path}`"
          :alt="detail.title"
        >

        <p v-if="detail?.excerpt" class="detail-lead">{{ detail.excerpt }}</p>

        <!-- Isi ditampilkan sebagai teks, bukan v-html: apa pun yang diketik
             admin tetap jadi teks dan tidak pernah dieksekusi sebagai markup. -->
        <p v-for="(para, i) in (detail?.content || '').split('\n\n')" :key="i" class="detail-para">
          {{ para }}
        </p>

        <!-- Langkah bergambar. Inilah gambar yang berada DI DALAM isi panduan,
             berbeda dari gambar sampul yang hanya tampil di kartu. -->
        <ol v-if="detail?.steps?.length" class="steps">
          <li v-for="(st, i) in detail.steps" :key="st.id ?? i" class="step">
            <span class="step-no">{{ i + 1 }}</span>
            <div class="step-body">
              <p v-if="st.body" class="step-text">{{ st.body }}</p>
              <img
                v-if="st.image_path"
                class="step-img"
                :src="`/storage/tutorials/${st.image_path}`"
                :alt="`Langkah ${i + 1}`"
                loading="lazy"
              >
            </div>
          </li>
        </ol>

        <p v-if="!detail?.content && !detail?.steps?.length" class="text-caption">
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
/* Tata letaknya dari .control-row di base.css; di sini hanya ukuran anaknya.
   .toolbar-select WAJIB menimpa width: 100% milik .select — tanpa itu select
   memakan satu baris penuh dan ketiga kontrol menumpuk ke bawah, bukan
   berjajar. Nilainya sama dengan toolbar katalog template. */
.toolbar-search {
  flex: 1;
  min-width: 160px;
}
.toolbar-select {
  width: auto;
  min-width: 140px;
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

/* Tampilan daftar: satu kolom, gambar mengecil di sisi kiri. */
.tut-list-view {
  display: flex;
  flex-direction: column;
  gap: var(--sp-4);
}
.tut-list-view .tut-open {
  align-items: center;
}
.tut-list-view .tut-media {
  width: 96px;
  height: 64px;
  aspect-ratio: auto;
}
.tut-list-view .tut-excerpt {
  -webkit-line-clamp: 2;
  line-clamp: 2;
}

/* Gambar kartu. aspect-ratio menjaga tinggi seragam berapa pun ukuran asli
   berkasnya, jadi kartu dalam satu baris tidak saling menjulang. */
.tut-media {
  flex-shrink: 0;
  width: 100%;
  aspect-ratio: 16 / 9;
  border-radius: var(--r-sm);
  overflow: hidden;
  background: var(--bg-subtle);
  border: 1px solid var(--border);
}
.tut-media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.tut-icon {
  flex-shrink: 0;
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
/* Di grid, gambar duduk di atas teks; di daftar ia di sebelah kiri. */
.grid.tut-grid .tut-open {
  flex-direction: column;
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

.detail-img {
  width: 100%;
  max-height: 320px;
  object-fit: cover;
  border-radius: var(--r);
  border: 1px solid var(--border);
  margin-bottom: var(--sp-4);
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

/* ---------- Langkah ---------- */
.steps {
  display: flex;
  flex-direction: column;
  gap: var(--sp-5);
  margin-top: var(--sp-5);
  /* Nomornya digambar sendiri lewat .step-no; penanda bawaan <ol> akan
     menggandakan angkanya. */
  list-style: none;
  padding: 0;
}

.step {
  display: flex;
  gap: var(--sp-4);
}

.step-no {
  flex-shrink: 0;
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  border-radius: var(--r-pill);
  background: var(--brand-soft);
  border: 1px solid var(--brand-border);
  color: var(--brand-ink);
  font-size: var(--fs-caption);
  font-weight: var(--fw-bold);
}

.step-body {
  display: flex;
  flex-direction: column;
  gap: var(--sp-3);
  min-width: 0;
  flex: 1;
}

.step-text {
  color: var(--text-body);
  line-height: var(--lh-normal);
}

.step-img {
  width: 100%;
  border-radius: var(--r);
  border: 1px solid var(--border);
}
.detail-lead + .detail-para {
  margin-top: var(--sp-4);
}
</style>
