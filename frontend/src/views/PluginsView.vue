<script setup>
/**
 * Plugin & Lisensi — GET /user/licenses.
 * Unduhan memakai tautan langsung ke endpoint agar cookie sesi ikut terkirim,
 * sama seperti perilaku sebelumnya.
 */
import { ref, computed, onMounted } from 'vue'
import {
  Package,
  KeyRound,
  Download,
  Copy,
  Check,
  CircleAlert,
  Receipt,
} from 'lucide-vue-next'
import EmptyState from '@/components/ui/EmptyState.vue'
import SkeletonRows from '@/components/ui/SkeletonRows.vue'
import { api, errorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { toast } from '@/stores/toast'

const loading = ref(true)
const licenses = ref([])
const plugin = ref(null)
const copied = ref('')

const hasLicense = computed(() => licenses.value.length > 0)

async function load() {
  loading.value = true
  try {
    const { data } = await api.get('/user/licenses')
    licenses.value = data.licenses || []
    plugin.value = data.latest_plugin || null
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal memuat data lisensi'))
  } finally {
    loading.value = false
  }
}

async function copyText(value, key) {
  try {
    await navigator.clipboard.writeText(value)
    copied.value = key
    setTimeout(() => (copied.value = ''), 1800)
  } catch {
    toast.error('Gagal menyalin')
  }
}

onMounted(load)
</script>

<template>
  <div class="page">
    <header class="page-head">
      <div>
        <h1>Plugin &amp; Lisensi</h1>
        <p class="text-muted">Kelola lisensi Anda dan unduh plugin versi terbaru.</p>
      </div>
    </header>

    <!-- ---------- Kartu rilis plugin ---------- -->
    <section v-if="plugin" class="release card">
      <span class="icon-chip release-icon tone-neutral">
        <Package :size="24" />
      </span>

      <div class="release-text">
        <p class="text-overline">Versi Terbaru</p>
        <h2>Apeiron Kit v{{ plugin.version }}</h2>
        <p v-if="plugin.changelog" class="release-log">{{ plugin.changelog }}</p>
      </div>

      <div class="release-action">
        <a v-if="hasLicense" class="btn btn-primary" href="/api/user/download-plugin">
          <Download :size="17" />
          Unduh Plugin
        </a>
        <p v-else class="locked">
          <CircleAlert :size="15" />
          Lisensi aktif diperlukan.
        </p>
      </div>
    </section>

    <!-- ---------- Daftar lisensi ---------- -->
    <section class="card">
      <header class="card-header">
        <h3>
          <span class="icon-chip icon-chip-sm tone-ok"><KeyRound :size="16" /></span>
          Lisensi Saya
        </h3>
        <span v-if="hasLicense" class="badge tone-ok">
          {{ licenses.length }} aktif
        </span>
      </header>

      <SkeletonRows v-if="loading" :rows="2" :height="88" />

      <div v-else-if="hasLicense" class="lic-list">
        <article v-for="l in licenses" :key="l.id" class="lic">
          <header class="lic-head">
            <span class="row">
              <span class="icon-chip icon-chip-sm tone-ok"><KeyRound :size="15" /></span>
              <span>
                <span class="lic-product">{{ l.product_id || 'Apeiron Kit' }}</span>
                <span class="text-caption">
                  Diterbitkan {{ formatDate(l.assigned_at, false) }}
                </span>
              </span>
            </span>
            <a class="btn btn-sm btn-secondary" href="/api/user/download-invoice">
              <Receipt :size="14" />
              Invoice
            </a>
          </header>

          <div class="lic-keys">
            <div class="field">
              <span class="text-overline">License Key</span>
              <div class="key-row">
                <code class="key">{{ l.license_key }}</code>
                <button
                  class="btn btn-sm btn-secondary btn-icon"
                  type="button"
                  aria-label="Salin license key"
                  @click="copyText(l.license_key, `lic-${l.id}`)"
                >
                  <component :is="copied === `lic-${l.id}` ? Check : Copy" :size="15" />
                </button>
              </div>
            </div>

            <div v-if="l.api_key" class="field">
              <span class="text-overline">API Key</span>
              <div class="key-row">
                <code class="key">{{ l.api_key }}</code>
                <button
                  class="btn btn-sm btn-secondary btn-icon"
                  type="button"
                  aria-label="Salin API key"
                  @click="copyText(l.api_key, `api-${l.id}`)"
                >
                  <component :is="copied === `api-${l.id}` ? Check : Copy" :size="15" />
                </button>
              </div>
            </div>
          </div>
        </article>
      </div>

      <EmptyState
        v-else
        :icon="KeyRound"
        title="Belum ada lisensi"
        description="Lisensi diterbitkan otomatis setelah bukti transaksi Anda disetujui admin."
      >
        <template #action>
          <RouterLink to="/dashboard" class="btn btn-primary">
            Unggah Bukti Transaksi
          </RouterLink>
        </template>
      </EmptyState>
    </section>
  </div>
</template>

<style scoped>
/* ---- Rilis ---- */
.release {
  display: flex;
  align-items: center;
  gap: var(--sp-5);
  padding: var(--sp-6);
}

.release-icon {
  width: 56px;
  height: 56px;
  border-radius: var(--r);
  background: var(--tone-bg);
  flex-shrink: 0;
}

.release-text {
  flex: 1;
  min-width: 0;
}
.release-text h2 {
  margin-top: 2px;
  font-size: var(--fs-h2);
}
.release-log {
  margin-top: var(--sp-2);
  font-size: var(--fs-label);
  color: var(--text-muted);
  line-height: var(--lh-normal);
}

.release-action {
  flex-shrink: 0;
}

.locked {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-3) var(--sp-4);
  background: var(--warn-soft);
  border-radius: var(--r);
  font-size: var(--fs-caption);
  color: var(--text-body);
}

/* ---- Lisensi ---- */
.lic-list {
  display: flex;
  flex-direction: column;
  gap: var(--sp-4);
  padding: var(--sp-5) var(--sp-6);
}

.lic {
  padding: var(--sp-5);
  border: 1px solid var(--border);
  border-radius: var(--r);
  background: var(--bg-subtle);
}

.lic-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  flex-wrap: wrap;
  margin-bottom: var(--sp-4);
}
.lic-head span span {
  display: flex;
  flex-direction: column;
  line-height: 1.3;
}

.lic-product {
  font-size: var(--fs-body);
  font-weight: var(--fw-semibold);
  color: var(--text-heading);
}

.lic-keys {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
  gap: var(--sp-4);
}

.key-row {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
}
.key {
  flex: 1;
  min-width: 0;
  padding: var(--sp-2) var(--sp-3);
  background: var(--bg-surface);
  border: 1px solid var(--border);
  border-radius: var(--r-sm);
  font-family: var(--font-mono);
  font-size: var(--fs-caption);
  color: var(--text-heading);
  overflow-x: auto;
  white-space: nowrap;
}

@media (max-width: 780px) {
  .release {
    flex-direction: column;
    align-items: flex-start;
    gap: var(--sp-4);
  }
  .release-action,
  .release-action .btn {
    width: 100%;
  }
}

@media (max-width: 640px) {
  .lic-list {
    padding: var(--sp-4);
  }
  .lic {
    padding: var(--sp-4);
  }
  .lic-keys {
    grid-template-columns: 1fr;
  }
}
</style>
