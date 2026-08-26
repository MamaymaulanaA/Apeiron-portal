<script setup>
/**
 * Bonus — halaman user.
 *
 * Bentuknya mengikuti halaman Panduan: toolbar cari + saringan + pilihan
 * tampilan, kisi kartu, dan empty state yang membedakan "tidak cocok" dari
 * "belum ada". Komponen, spacing, dan token warnanya dipakai ulang apa adanya.
 *
 * Hanya bonus aktif yang sampai ke sini — penyaringnya di server, bukan di
 * tampilan.
 */
import { ref, computed, onMounted } from 'vue'
import {
  Gift,
  Search,
  Download,
  ExternalLink,
  RotateCcw,
  MessageCircle,
  LayoutGrid,
  List,
  Lock,
} from 'lucide-vue-next'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import { api, errorMessage } from '@/lib/api'
import { authState } from '@/stores/auth'
import { ADMIN_CONTACT, buildUpgradeMessage, whatsappHref } from '@/lib/contact'
import { bonusHref, formatFileSize, BONUS_ACCESS_COPY, bonusTrialCopy } from '@/lib/community'

const loading = ref(true)
const loadError = ref('')
const bonuses = ref([])
const categories = ref([])
/** Keadaan akses menurut server; menjelaskan kenapa daftarnya kosong. */
const access = ref('granted')
const trial = ref(null)

/**
 * Pesan upgrade siap kirim.
 *
 * Kalimat di layar menyebut "member berlangganan", jadi harus ada jalan
 * menuju ke sana — tanpa tombol ini user yang ingin berlangganan justru
 * buntu di halaman yang menyuruhnya berlangganan.
 */
const pesanUpgrade = computed(() =>
  buildUpgradeMessage({ user: authState.user, trialLabel: trial.value?.label }),
)
const waUpgrade = computed(() => whatsappHref(pesanUpgrade.value))

const accessCopy = computed(() =>
  access.value === 'trial'
    ? bonusTrialCopy(trial.value?.label, { detail: true })
    : BONUS_ACCESS_COPY[access.value] || BONUS_ACCESS_COPY.none,
)

const search = ref('')
const category = ref('all')
const view = ref('grid')

const filtered = computed(() => {
  const q = search.value.trim().toLowerCase()
  return bonuses.value.filter((b) => {
    if (category.value !== 'all' && b.category !== category.value) return false
    if (!q) return true
    return (
      b.name.toLowerCase().includes(q) ||
      (b.description || '').toLowerCase().includes(q) ||
      (b.category || '').toLowerCase().includes(q)
    )
  })
})

async function load() {
  loading.value = true
  loadError.value = ''
  try {
    const { data } = await api.get('/user/bonuses')
    bonuses.value = data.bonuses || []
    categories.value = data.categories || []
    access.value = data.access || 'granted'
    trial.value = data.trial || null
  } catch (e) {
    loadError.value = errorMessage(e, 'Gagal memuat bonus')
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
        <h1>Bonus</h1>
        <p class="text-caption">
          Berkas dan tautan tambahan yang disediakan untuk member.
        </p>
      </div>
    </header>

    <div v-if="!loading && !loadError && bonuses.length" class="control-row">
      <div class="input-group toolbar-search">
        <Search class="input-icon" :size="16" />
        <input v-model="search" class="input" type="search" placeholder="Cari bonus…">
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

    <section v-else-if="loadError" class="card">
      <EmptyState :icon="Gift" title="Gagal memuat bonus" :description="loadError">
        <template #action>
          <button class="btn btn-secondary" type="button" @click="load">
            <RotateCcw :size="15" />
            Coba lagi
          </button>
        </template>
      </EmptyState>
    </section>

    <!-- Dibedakan dengan jujur: belum disetujui BUKAN sama dengan belum ada
         bonus. Menulis "belum ada bonus" pada user yang transaksinya masih
         menunggu adalah keterangan yang salah, dan ia akan menunggu sesuatu
         yang sebenarnya sudah ada. -->
    <section v-else-if="access !== 'granted'" class="card">
      <EmptyState
        :icon="Lock"
        :title="accessCopy.title"
        :description="accessCopy.description"
      >
        <template v-if="access !== 'pending' && access !== 'trial'" #action>
          <RouterLink to="/transactions" class="btn btn-primary">
            {{ access === 'rejected' ? 'Unggah Ulang' : 'Ke Transaksi' }}
          </RouterLink>
        </template>

        <!-- Saat percobaan, yang dibutuhkan bukan halaman Transaksi melainkan
             cara menghubungi admin untuk naik jadi Member. -->
        <template v-if="access === 'trial'" #action>
          <span class="upgrade-aksi">
            <a
              class="btn btn-primary"
              :href="waUpgrade"
              target="_blank"
              rel="noopener noreferrer"
            >
              <MessageCircle :size="16" />
              Hubungi Admin via WhatsApp
            </a>

            <a
              class="btn btn-secondary"
              :href="ADMIN_CONTACT.telegram"
              target="_blank"
              rel="noopener noreferrer"
            >
              <MessageCircle :size="16" />
              Telegram
            </a>
          </span>
        </template>
      </EmptyState>
    </section>

    <section v-else-if="!bonuses.length" class="card">
      <EmptyState
        :icon="Gift"
        title="Belum ada bonus"
        description="Bonus akan tampil di sini setelah admin menambahkannya."
      />
    </section>

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

    <ul v-else class="bonus-grid" :class="view === 'grid' ? 'grid' : 'bonus-list-view'">
      <li v-for="b in filtered" :key="b.id" class="card bonus">
        <div class="bonus-main">
          <span v-if="b.thumbnail_path" class="bonus-media">
            <img
              :src="`/storage/bonus-thumbnails/${b.thumbnail_path}`"
              :alt="b.name"
              loading="lazy"
            >
          </span>
          <span v-else class="icon-chip tone-peach bonus-icon">
            <Gift :size="20" />
          </span>

          <div class="bonus-body">
            <span v-if="b.category" class="badge tone-neutral">{{ b.category }}</span>
            <h3 class="bonus-name">{{ b.name }}</h3>
            <p v-if="b.description" class="bonus-desc">{{ b.description }}</p>
          </div>
        </div>

        <footer class="bonus-foot">
          <span class="bonus-meta">
            <component :is="b.source_type === 'file' ? Download : ExternalLink" :size="13" />
            {{ b.source_type === 'file' ? formatFileSize(b.file_size) : 'Tautan eksternal' }}
          </span>

          <!-- Berkas dilayani endpoint ber-auth; tautan langsung ke URL-nya.
               Keduanya tetap satu tombol yang sama bagi user. -->
          <a
            class="btn btn-sm btn-primary"
            :href="bonusHref(b)"
            :target="b.source_type === 'file' ? '_self' : '_blank'"
            rel="noopener noreferrer"
          >
            <Download :size="14" />
            Unduh
          </a>
        </footer>
      </li>
    </ul>
  </div>
</template>

<style scoped>

/* ---- Tombol upgrade saat percobaan ---- */
.upgrade-aksi {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: var(--sp-2);
}

@media (max-width: 480px) {
  /* Menumpuk penuh supaya keduanya mudah ditekan. */
  .upgrade-aksi {
    width: 100%;
  }
  .upgrade-aksi .btn {
    flex: 1 1 100%;
  }
}

.toolbar-search {
  flex: 1;
  min-width: 160px;
}
.toolbar-select {
  width: auto;
  min-width: 150px;
}

/* minmax(min(…), 1fr) — bukan 1fr — supaya kolom boleh menyusut di layar
   sempit; 1fr menolak lebih kecil dari konten terlebarnya dan menyeret
   halaman ikut melebar. */
.bonus-grid {
  grid-template-columns: repeat(auto-fill, minmax(min(300px, 100%), 1fr));
}

.bonus-list-view {
  display: flex;
  flex-direction: column;
  gap: var(--sp-4);
}
.bonus-list-view .bonus-main {
  align-items: center;
}
.bonus-list-view .bonus-media {
  width: 96px;
  height: 64px;
  aspect-ratio: auto;
}

.bonus {
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.bonus-main {
  display: flex;
  gap: var(--sp-4);
  flex: 1;
  padding: var(--sp-5);
}
.grid.bonus-grid .bonus-main {
  flex-direction: column;
}

/* aspect-ratio menjaga tinggi seragam berapa pun ukuran asli berkasnya, jadi
   kartu dalam satu baris tidak saling menjulang. */
.bonus-media {
  flex-shrink: 0;
  width: 100%;
  aspect-ratio: 16 / 9;
  border-radius: var(--r-sm);
  overflow: hidden;
  background: var(--bg-subtle);
  border: 1px solid var(--border);
}
.bonus-media img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.bonus-icon {
  flex-shrink: 0;
}

.bonus-body {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: var(--sp-2);
  min-width: 0;
}

.bonus-name {
  font-size: var(--fs-body-lg);
  font-weight: var(--fw-semibold);
  color: var(--text-heading);
  line-height: var(--lh-snug);
}

.bonus-desc {
  font-size: var(--fs-caption);
  color: var(--text-muted);
  line-height: var(--lh-snug);
  display: -webkit-box;
  -webkit-line-clamp: 3;
  line-clamp: 3;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.bonus-foot {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-5);
  border-top: 1px solid var(--border);
}

.bonus-meta {
  display: inline-flex;
  align-items: center;
  gap: var(--sp-1);
  font-size: var(--fs-caption);
  color: var(--text-muted);
}

@media (max-width: 640px) {
  .toolbar-search,
  .toolbar-select {
    flex: 1 1 100%;
    width: 100%;
  }
  .bonus-list-view .bonus-main {
    flex-direction: column;
    align-items: flex-start;
  }
  .bonus-list-view .bonus-media {
    width: 100%;
  }
}
</style>
