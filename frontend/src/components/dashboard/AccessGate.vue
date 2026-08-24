<script setup>
/**
 * Gerbang akses dashboard.
 *
 * Menutup dashboard sampai bukti transaksi disetujui admin. Tahapnya
 * mengikuti status yang dikirim backend — tidak ada keadaan yang dikarang
 * di sisi klien:
 *
 *   belum ada transaksi / ditolak → form unggah
 *   menunggu verifikasi / pending → layar menunggu
 *   disetujui                     → gerbang tidak dirender sama sekali
 *
 * Aturan siapa yang boleh mengunggah tetap dipegang backend lewat flag
 * can_upload; komponen ini hanya menyajikannya.
 */
import { ref, computed } from 'vue'
import {
  Upload,
  FileText,
  Clock,
  CircleAlert,
  ShieldCheck,
  ServerCrash,
} from 'lucide-vue-next'
import { api, errorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { toast } from '@/stores/toast'

const props = defineProps({
  transaction: { type: Object, default: null },
  canUpload: { type: Boolean, default: false },
})
const emit = defineEmits(['uploaded'])

const fileInput = ref(null)
const selectedFile = ref(null)
const uploading = ref(false)

const isRejected = computed(() => props.transaction?.status === 'rejected')
const isPendingLicense = computed(
  () => props.transaction?.status === 'pending_license'
)

/** Tahap gerbang: 'upload' bila belum ada transaksi atau ditolak. */
const step = computed(() =>
  !props.transaction || isRejected.value ? 'upload' : 'waiting'
)

function pickFile() {
  fileInput.value?.click()
}

function onFileChange(e) {
  const file = e.target.files?.[0]
  if (!file) return
  // Batas dicerminkan dari backend (5MB, JPG/PNG/GIF/WEBP/PDF).
  if (file.size > 5 * 1024 * 1024) {
    toast.error('Ukuran file maksimal 5MB')
    e.target.value = ''
    return
  }
  selectedFile.value = file
}

async function submit() {
  if (!selectedFile.value || uploading.value) return
  uploading.value = true
  try {
    const fd = new FormData()
    fd.append('document', selectedFile.value)
    await api.post('/user/upload-document', fd)
    toast.success('Bukti transaksi berhasil dikirim')
    selectedFile.value = null
    if (fileInput.value) fileInput.value.value = ''
    emit('uploaded')
  } catch (e) {
    toast.error(errorMessage(e, 'Gagal mengupload dokumen'))
  } finally {
    uploading.value = false
  }
}
</script>

<template>
  <div class="gate">
    <div class="gate-panel card">
      <!-- ============ Tahap unggah ============ -->
      <template v-if="step === 'upload'">
        <span class="gate-icon" :class="isRejected ? 'tone-err' : 'tone-brand'">
          <component :is="isRejected ? CircleAlert : ShieldCheck" :size="24" />
        </span>

        <h1 class="gate-title">
          {{ isRejected ? 'Bukti transaksi ditolak' : 'Aktifkan lisensi Anda' }}
        </h1>

        <p class="gate-text">
          <template v-if="isRejected">
            Pengajuan sebelumnya belum bisa disetujui. Silakan unggah ulang bukti
            transaksi Anda.
          </template>
          <template v-else>
            Unggah bukti pembayaran untuk memulai verifikasi. Dashboard terbuka
            setelah bukti Anda disetujui admin.
          </template>
        </p>

        <p v-if="isRejected && transaction?.admin_notes" class="gate-note">
          <CircleAlert :size="15" />
          <span><strong>Catatan admin:</strong> {{ transaction.admin_notes }}</span>
        </p>

        <input
          ref="fileInput"
          class="sr-only"
          type="file"
          accept=".jpg,.jpeg,.png,.gif,.webp,.pdf"
          @change="onFileChange"
        >

        <div v-if="selectedFile" class="gate-picked">
          <FileText :size="16" />
          <span class="gate-file">{{ selectedFile.name }}</span>
          <button
            class="btn btn-sm btn-ghost"
            type="button"
            :disabled="uploading"
            @click="selectedFile = null"
          >
            Ganti
          </button>
        </div>

        <button v-else class="gate-drop" type="button" @click="pickFile">
          <Upload :size="18" />
          <span>
            <strong>Pilih bukti transaksi</strong>
            <small>JPG, PNG, atau PDF — maksimal 5MB</small>
          </span>
        </button>

        <button
          class="btn btn-primary btn-lg gate-cta"
          type="button"
          :disabled="!selectedFile || uploading || !canUpload"
          @click="submit"
        >
          {{ uploading ? 'Mengirim…' : 'Kirim Bukti Transaksi' }}
        </button>
      </template>

      <!-- ============ Tahap menunggu ============ -->
      <template v-else>
        <span class="gate-icon" :class="isPendingLicense ? 'tone-info' : 'tone-warn'">
          <component :is="isPendingLicense ? ServerCrash : Clock" :size="24" />
        </span>

        <h1 class="gate-title">
          {{ isPendingLicense ? 'Lisensi sedang diterbitkan' : 'Menunggu verifikasi' }}
        </h1>

        <p class="gate-text">
          <template v-if="isPendingLicense">
            Pembayaran Anda sudah disetujui. Lisensi akan terbit begitu server
            lisensi tersedia kembali.
          </template>
          <template v-else>
            Bukti transaksi Anda sedang diperiksa admin. Dashboard akan terbuka
            otomatis setelah disetujui.
          </template>
        </p>

        <dl v-if="transaction" class="gate-detail">
          <div>
            <dt>Dokumen</dt>
            <dd>{{ transaction.document_name || '—' }}</dd>
          </div>
          <div>
            <dt>Diajukan</dt>
            <dd>{{ formatDate(transaction.created_at) }}</dd>
          </div>
        </dl>

        <!-- Titik berdenyut sebagai penanda proses yang masih berjalan -->
        <p class="gate-pulse">
          <span class="status-dot" />
          Proses sedang berjalan
        </p>
      </template>
    </div>
  </div>
</template>

<style scoped>
.gate {
  display: grid;
  place-items: center;
  min-height: 60vh;
  padding: var(--sp-6) 0;
}

.gate-panel {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  max-width: 460px;
  padding: var(--sp-8);
  text-align: center;
}

.gate-icon {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border-radius: var(--r);
  background: var(--tone-bg);
  color: var(--tone-fg);
}

.gate-title {
  margin-top: var(--sp-4);
  font-size: var(--fs-h1);
}

.gate-text {
  margin-top: var(--sp-2);
  color: var(--text-muted);
  line-height: var(--lh-normal);
  max-width: 42ch;
}

.gate-note {
  display: flex;
  align-items: flex-start;
  gap: var(--sp-2);
  width: 100%;
  margin-top: var(--sp-4);
  padding: var(--sp-3);
  background: var(--warn-soft);
  border: 1px solid var(--warn-border);
  border-radius: var(--r);
  font-size: var(--fs-label);
  color: var(--text-body);
  text-align: left;
}
.gate-note svg {
  color: var(--warn);
  flex-shrink: 0;
  margin-top: 2px;
}
.gate-note strong {
  color: var(--text-heading);
  font-weight: var(--fw-medium);
}

/* ---- Unggah ---- */
.gate-drop {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  width: 100%;
  margin-top: var(--sp-5);
  padding: var(--sp-4);
  border: 1px dashed var(--border-strong);
  border-radius: var(--r);
  background: var(--bg-subtle);
  color: var(--text-muted);
  text-align: left;
  transition: border-color var(--dur) var(--ease), background var(--dur) var(--ease);
}
.gate-drop:hover {
  border-color: var(--brand);
  background: var(--bg-surface);
}
.gate-drop span {
  display: flex;
  flex-direction: column;
}
.gate-drop strong {
  font-size: var(--fs-body);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
}
.gate-drop small {
  font-size: var(--fs-caption);
}

.gate-picked {
  display: flex;
  align-items: center;
  gap: var(--sp-3);
  width: 100%;
  margin-top: var(--sp-5);
  padding: var(--sp-3);
  border: 1px solid var(--border);
  border-radius: var(--r);
  color: var(--text-muted);
}
.gate-file {
  flex: 1;
  min-width: 0;
  font-size: var(--fs-label);
  color: var(--text-heading);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: left;
}

.gate-cta {
  width: 100%;
  margin-top: var(--sp-4);
}

/* ---- Menunggu ---- */
.gate-detail {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(150px, 100%), 1fr));
  gap: var(--sp-4);
  width: 100%;
  margin-top: var(--sp-6);
  padding-top: var(--sp-5);
  border-top: 1px solid var(--border);
  text-align: left;
}
.gate-detail dt {
  font-size: var(--fs-caption);
  color: var(--text-muted);
  margin-bottom: 2px;
}
.gate-detail dd {
  font-size: var(--fs-body);
  color: var(--text-heading);
  word-break: break-word;
}

.gate-pulse {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  margin-top: var(--sp-5);
  font-size: var(--fs-caption);
  color: var(--text-muted);
}
.gate-pulse .status-dot {
  background: var(--warn);
  animation: pulse-soft 2s var(--ease) infinite;
}

@media (max-width: 640px) {
  .gate-panel {
    padding: var(--sp-6);
  }
}
</style>
