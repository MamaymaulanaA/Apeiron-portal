<script setup>
/**
 * Panel pengajuan bukti transaksi, tampil di halaman Transaksi.
 *
 * Tahapnya mengikuti status yang dikirim backend — tidak ada keadaan yang
 * dikarang di sisi klien:
 *
 *   belum ada transaksi / ditolak → form unggah
 *   menunggu verifikasi / pending → keterangan menunggu
 *   disetujui                     → panel tidak dirender sama sekali
 *
 * Aturan siapa yang boleh mengunggah tetap dipegang backend lewat flag
 * can_upload; komponen ini hanya menyajikannya. Panel ini TIDAK menutup
 * halaman lain — dashboard tetap bisa dibuka selama menunggu.
 */
import { ref, computed } from 'vue'
import {
  Upload,
  FileText,
  Clock,
  CircleAlert,
  ShieldCheck,
  ServerCrash,
  MessageCircle,
  Copy,
  Check,
} from 'lucide-vue-next'
import { api, errorMessage } from '@/lib/api'
import { formatDate } from '@/lib/format'
import { authState } from '@/stores/auth'
import { ADMIN_CONTACT, buildConfirmationMessage, whatsappHref } from '@/lib/contact'
import { toast } from '@/stores/toast'

const props = defineProps({
  transaction: { type: Object, default: null },
  canUpload: { type: Boolean, default: false },
})
const emit = defineEmits(['uploaded'])

const fileInput = ref(null)
const selectedFile = ref(null)
const uploading = ref(false)

/* ---------- Kontak admin saat menunggu ---------- */

const pesanKonfirmasi = computed(() =>
  buildConfirmationMessage({
    user: authState.user,
    transaction: props.transaction,
    formatDate,
  }),
)

const waHref = computed(() => whatsappHref(pesanKonfirmasi.value))

/**
 * Telegram tidak menerima teks siap-isi pada tautan ke akun pribadi — hanya
 * tautan berbagi yang bisa. Jadi tombolnya membuka obrolan, dan pesannya
 * disediakan lewat tombol salin di sebelahnya.
 */
const tersalin = ref(false)

async function salinPesan() {
  try {
    await navigator.clipboard.writeText(pesanKonfirmasi.value)
    tersalin.value = true
    setTimeout(() => (tersalin.value = false), 1800)
  } catch {
    toast.error('Gagal menyalin pesan')
  }
}

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
  <div class="submission">
    <div class="submission-card card">
      <!-- ============ Tahap unggah ============ -->
      <template v-if="step === 'upload'">
        <span class="sub-icon" :class="isRejected ? 'tone-err' : 'tone-brand'">
          <component :is="isRejected ? CircleAlert : ShieldCheck" :size="24" />
        </span>

        <h1 class="sub-title">
          {{ isRejected ? 'Bukti transaksi ditolak' : 'Aktifkan lisensi Anda' }}
        </h1>

        <p class="sub-text">
          <template v-if="isRejected">
            Pengajuan sebelumnya belum bisa disetujui. Silakan unggah ulang bukti
            transaksi Anda.
          </template>
          <template v-else>
            Unggah bukti pembayaran untuk memulai verifikasi. Lisensi terbit
            setelah bukti Anda disetujui admin.
          </template>
        </p>

        <p v-if="isRejected && transaction?.admin_notes" class="sub-note">
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

        <div v-if="selectedFile" class="sub-picked">
          <FileText :size="16" />
          <span class="sub-file">{{ selectedFile.name }}</span>
          <button
            class="btn btn-sm btn-ghost"
            type="button"
            :disabled="uploading"
            @click="selectedFile = null"
          >
            Ganti
          </button>
        </div>

        <button v-else class="sub-drop" type="button" @click="pickFile">
          <Upload :size="18" />
          <span>
            <strong>Pilih bukti transaksi</strong>
            <small>JPG, PNG, atau PDF — maksimal 5MB</small>
          </span>
        </button>

        <button
          class="btn btn-primary btn-lg sub-cta"
          type="button"
          :disabled="!selectedFile || uploading || !canUpload"
          @click="submit"
        >
          {{ uploading ? 'Mengirim…' : 'Kirim Bukti Transaksi' }}
        </button>
      </template>

      <!-- ============ Tahap menunggu ============ -->
      <template v-else>
        <span class="sub-icon" :class="isPendingLicense ? 'tone-info' : 'tone-warn'">
          <component :is="isPendingLicense ? ServerCrash : Clock" :size="24" />
        </span>

        <h1 class="sub-title">
          {{ isPendingLicense ? 'Lisensi sedang diterbitkan' : 'Menunggu verifikasi' }}
        </h1>

        <p class="sub-text">
          <template v-if="isPendingLicense">
            Pembayaran Anda sudah disetujui. Lisensi akan terbit begitu server
            lisensi tersedia kembali.
          </template>
          <template v-else>
            Bukti transaksi Anda sedang diperiksa admin. Lisensi akan terbit
            otomatis setelah disetujui.
          </template>
        </p>

        <dl v-if="transaction" class="sub-detail">
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
        <p class="sub-pulse">
          <span class="status-dot" />
          Proses sedang berjalan
        </p>

        <!-- ---------- Kontak admin ---------- -->
        <div class="kontak">
          <p class="kontak-lead">
            <template v-if="isPendingLicense">
              Lisensi belum terbit lebih dari <strong>1&times;24 jam</strong>?
              Hubungi admin agar bisa kami periksa.
            </template>
            <template v-else>
              Belum diproses lebih dari <strong>1&times;24 jam</strong>? Hubungi
              admin untuk mempercepat verifikasi.
            </template>
          </p>

          <div class="kontak-aksi">
            <!-- WhatsApp menerima pesan siap-isi lewat parameter text. -->
            <a
              class="btn btn-sm btn-secondary"
              :href="waHref"
              target="_blank"
              rel="noopener noreferrer"
            >
              <MessageCircle :size="15" />
              WhatsApp
            </a>

            <a
              class="btn btn-sm btn-secondary"
              :href="ADMIN_CONTACT.telegram"
              target="_blank"
              rel="noopener noreferrer"
            >
              <MessageCircle :size="15" />
              Telegram
            </a>
          </div>

          <!-- Telegram tidak bisa diisi otomatis, jadi pesannya disediakan
               untuk disalin. Ditampilkan apa adanya supaya jelas apa yang
               akan terkirim. -->
          <div class="kontak-pesan">
            <div class="kontak-pesan-head">
              <span class="text-overline">Format pesan konfirmasi</span>
              <button class="btn btn-sm btn-secondary" type="button" @click="salinPesan">
                <component :is="tersalin ? Check : Copy" :size="14" />
                {{ tersalin ? 'Tersalin' : 'Salin' }}
              </button>
            </div>
            <pre class="kontak-teks">{{ pesanKonfirmasi }}</pre>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<style scoped>

/* ---- Kontak admin saat menunggu ---- */
.kontak {
  width: 100%;
  margin-top: var(--sp-6);
  padding-top: var(--sp-5);
  border-top: 1px solid var(--border);
  text-align: left;
}

.kontak-lead {
  font-size: var(--fs-label);
  color: var(--text-body);
  line-height: var(--lh-normal);
}
.kontak-lead strong {
  color: var(--text-heading);
  font-weight: var(--fw-semibold);
}

.kontak-aksi {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-2);
  margin-top: var(--sp-3);
}

.kontak-pesan {
  margin-top: var(--sp-4);
  border: 1px solid var(--border);
  border-radius: var(--r-sm);
  overflow: hidden;
}

.kontak-pesan-head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--sp-3);
  padding: var(--sp-2) var(--sp-3);
  background: var(--bg-subtle);
  border-bottom: 1px solid var(--border);
}

/* pre, bukan p: pesannya memang berbaris-baris dan harus terlihat persis
   seperti yang akan terkirim. white-space: pre-wrap supaya baris panjang
   tetap membungkus alih-alih menggeser panel. */
.kontak-teks {
  margin: 0;
  padding: var(--sp-3);
  font-family: var(--font-mono);
  font-size: var(--fs-caption);
  line-height: var(--lh-normal);
  color: var(--text-body);
  white-space: pre-wrap;
  word-break: break-word;
}

@media (max-width: 480px) {
  .kontak-aksi .btn {
    flex: 1;
  }
}

.submission {
  display: block;
}

.submission-card {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  padding: var(--sp-7);
  text-align: center;
}

/* Kartunya selebar halaman, tetapi isinya dijaga tetap sempit agar baris
   teks tidak terlalu panjang untuk dibaca. */
.submission-card > * {
  width: 100%;
  max-width: 460px;
}

.sub-icon {
  display: grid;
  place-items: center;
  width: 52px;
  height: 52px;
  border-radius: var(--r);
  background: var(--tone-bg);
  color: var(--tone-fg);
}

.sub-title {
  margin-top: var(--sp-4);
  font-size: var(--fs-h1);
}

.sub-text {
  margin-top: var(--sp-2);
  color: var(--text-muted);
  line-height: var(--lh-normal);
  max-width: 42ch;
}

.sub-note {
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
.sub-note svg {
  color: var(--warn);
  flex-shrink: 0;
  margin-top: 2px;
}
.sub-note strong {
  color: var(--text-heading);
  font-weight: var(--fw-medium);
}

/* ---- Unggah ---- */
.sub-drop {
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
.sub-drop:hover {
  border-color: var(--brand);
  background: var(--bg-surface);
}
.sub-drop span {
  display: flex;
  flex-direction: column;
}
.sub-drop strong {
  font-size: var(--fs-body);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
}
.sub-drop small {
  font-size: var(--fs-caption);
}

.sub-picked {
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
.sub-file {
  flex: 1;
  min-width: 0;
  font-size: var(--fs-label);
  color: var(--text-heading);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  text-align: left;
}

.sub-cta {
  width: 100%;
  margin-top: var(--sp-4);
}

/* ---- Menunggu ---- */
.sub-detail {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(min(150px, 100%), 1fr));
  gap: var(--sp-4);
  width: 100%;
  margin-top: var(--sp-6);
  padding-top: var(--sp-5);
  border-top: 1px solid var(--border);
  text-align: left;
}
.sub-detail dt {
  font-size: var(--fs-caption);
  color: var(--text-muted);
  margin-bottom: 2px;
}
.sub-detail dd {
  font-size: var(--fs-body);
  color: var(--text-heading);
  word-break: break-word;
}

.sub-pulse {
  display: flex;
  align-items: center;
  gap: var(--sp-2);
  margin-top: var(--sp-5);
  font-size: var(--fs-caption);
  color: var(--text-muted);
}
.sub-pulse .status-dot {
  background: var(--warn);
  animation: pulse-soft 2s var(--ease) infinite;
}

@media (max-width: 640px) {
  .submission-card {
    padding: var(--sp-6);
  }
}
</style>
