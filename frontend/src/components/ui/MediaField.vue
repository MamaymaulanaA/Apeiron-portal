<script setup>
/**
 * Kolom media: tarik-lepas atau klik, dengan pratinjau, ganti, dan hapus.
 *
 * Dipakai untuk gambar sampul panduan dan untuk gambar tiap langkah di
 * dalamnya. Dijadikan komponen karena satu form bisa memuat sepuluh kolom
 * seperti ini sekaligus — menyalin blok tarik-lepasnya sepuluh kali berarti
 * sepuluh tempat yang harus diubah setiap kali perilakunya disentuh.
 *
 * BENTUK MODEL
 *   { path, file, preview, action }
 *
 * `action` yang dibaca backend, dan ketiganya memang berbeda maksudnya:
 *   keep    — biarkan gambar yang tersimpan
 *   replace — pakai `file` yang baru dipilih
 *   remove  — hapus gambarnya
 * Satu field nullable tidak bisa membedakan "biarkan" dari "hapus", jadi
 * niatnya dinyatakan terpisah dari nilainya.
 */
import { ref, computed, onBeforeUnmount } from 'vue'
import { ImagePlus, X } from 'lucide-vue-next'
import { toast } from '@/stores/toast'

const props = defineProps({
  modelValue: { type: Object, required: true },
  /** Tinggi pratinjau; langkah memakai yang lebih pendek dari sampul. */
  maxHeight: { type: Number, default: 220 },
  label: { type: String, default: 'Tarik gambar ke sini, atau klik untuk memilih' },
  compact: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const dragging = ref(false)

/** Pratinjau berkas baru, atau gambar tersimpan, atau kosong. */
const preview = computed(() => {
  const m = props.modelValue
  if (m.preview) return m.preview
  if (m.action !== 'remove' && m.path) return `/storage/tutorials/${m.path}`
  return ''
})

function revoke(url) {
  if (url && url.startsWith('blob:')) URL.revokeObjectURL(url)
}

function accept(file) {
  if (!file) return
  // Batas dicerminkan dari backend; backend tetap penentu akhirnya.
  if (!file.type.startsWith('image/')) {
    toast.error('Berkas harus berupa gambar')
    return
  }
  if (file.size > 2 * 1024 * 1024) {
    toast.error('Ukuran gambar maksimal 2MB')
    return
  }

  revoke(props.modelValue.preview)
  emit('update:modelValue', {
    ...props.modelValue,
    file,
    preview: URL.createObjectURL(file),
    action: 'replace',
  })
}

function onPick(e) {
  accept(e.target.files?.[0])
  // Dikosongkan supaya memilih berkas yang sama dua kali tetap memicu change.
  e.target.value = ''
}

function onDrop(e) {
  dragging.value = false
  accept(e.dataTransfer?.files?.[0])
}

function clear() {
  revoke(props.modelValue.preview)
  emit('update:modelValue', {
    ...props.modelValue,
    file: null,
    preview: '',
    // Hanya perlu memberi tahu server bila memang ada yang tersimpan.
    action: props.modelValue.path ? 'remove' : 'keep',
  })
}

onBeforeUnmount(() => revoke(props.modelValue.preview))
</script>

<template>
  <div
    class="media"
    :class="{ dragging, filled: !!preview, compact }"
    @dragover.prevent="dragging = true"
    @dragleave.prevent="dragging = false"
    @drop.prevent="onDrop"
  >
    <template v-if="preview">
      <img class="media-img" :src="preview" :style="{ maxHeight: maxHeight + 'px' }" alt="Pratinjau gambar">
      <div class="media-actions">
        <label class="btn btn-sm btn-secondary">
          <ImagePlus :size="14" />
          Ganti
          <input class="media-input" type="file" accept="image/*" @change="onPick">
        </label>
        <button
          class="btn btn-sm btn-danger btn-icon"
          type="button"
          aria-label="Hapus gambar"
          @click="clear"
        >
          <X :size="14" />
        </button>
      </div>
    </template>

    <label v-else class="media-drop">
      <span class="icon-chip tone-sky"><ImagePlus :size="compact ? 16 : 20" /></span>
      <span class="media-title">{{ label }}</span>
      <span v-if="!compact" class="media-hint">JPG, PNG, GIF, atau WEBP · maksimal 2MB</span>
      <input class="media-input" type="file" accept="image/*" @change="onPick">
    </label>
  </div>
</template>

<style scoped>
.media {
  position: relative;
  border: 1px dashed var(--border-strong);
  border-radius: var(--r);
  background: var(--bg-subtle);
  overflow: hidden;
  transition: border-color var(--dur) var(--ease), background var(--dur) var(--ease);
}
.media.dragging {
  border-color: var(--brand);
  background: var(--brand-soft);
}
/* Setelah terisi ia bukan lagi target jatuh yang kosong, jadi garis putus
   diganti garis utuh seperti kontrol lain. */
.media.filled {
  border-style: solid;
  border-color: var(--border);
}

.media-drop {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: var(--sp-2);
  padding: var(--sp-6) var(--sp-5);
  text-align: center;
  cursor: pointer;
}
.media.compact .media-drop {
  flex-direction: row;
  justify-content: flex-start;
  gap: var(--sp-3);
  padding: var(--sp-3) var(--sp-4);
  text-align: left;
}

.media-title {
  font-size: var(--fs-label);
  font-weight: var(--fw-medium);
  color: var(--text-heading);
}
.media.compact .media-title {
  font-size: var(--fs-caption);
  font-weight: var(--fw-regular);
  color: var(--text-muted);
}

.media-hint {
  font-size: var(--fs-caption);
  color: var(--text-muted);
}

/* Disembunyikan dari mata, tetap ada untuk keyboard dan pembaca layar. */
.media-input {
  position: absolute;
  width: 1px;
  height: 1px;
  padding: 0;
  margin: -1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  white-space: nowrap;
  border: 0;
}

.media-img {
  display: block;
  width: 100%;
  object-fit: cover;
}

.media-actions {
  display: flex;
  gap: var(--sp-2);
  padding: var(--sp-3);
  border-top: 1px solid var(--border);
  background: var(--bg-surface);
}
.media-actions .btn {
  cursor: pointer;
}
</style>
