<script setup>
/**
 * Avatar kartun yang dibangkitkan dari identitas user.
 *
 * KENAPA DIGAMBAR SENDIRI, BUKAN DARI PUSTAKA
 * Pustaka avatar (DiceBear dan sejenisnya) punya dua bentuk: lewat HTTP API,
 * yang berarti mengirim email tiap anggota ke server pihak ketiga pada setiap
 * pemuatan halaman; atau lewat paket npm, yang menambah puluhan kilobyte untuk
 * satu gaya saja. Berkas ini menghasilkan variasi yang cukup dengan beberapa
 * bentuk SVG, tanpa permintaan jaringan dan tanpa dependensi baru.
 *
 * KENAPA TIDAK ADA VERSI "COWOK/CEWEK"
 * Tabel users tidak punya kolom gender — isinya id, username, email, name,
 * role, is_active, dan cap waktu. Menebak gender dari nama akan salah pada
 * sebagian orang sungguhan yang memakai portal ini, dan avatar yang salah
 * gender lebih buruk daripada avatar netral. Variasinya karena itu dibedakan
 * lewat rambut, warna kulit, dan latar — bukan lewat gender.
 *
 * DETERMINISTIK, BUKAN ACAK
 * Benihnya email (jatuh ke nama bila email kosong), jadi satu orang selalu
 * mendapat wajah yang sama di seluruh aplikasi dan tidak berubah tiap muat
 * ulang. Acak sungguhan akan membuat avatar berganti-ganti dan tidak lagi bisa
 * dipakai mengenali orang.
 *
 * Bila suatu saat backend menyimpan foto profil, komponen ini tinggal diberi
 * prop `src` dan menampilkan foto itu bila ada — sisanya jadi cadangan.
 */
import { computed, useId } from 'vue'

const props = defineProps({
  /** Ditampilkan sebagai alt text, dan jadi benih cadangan. */
  name: { type: String, default: '' },
  /** Benih utama — email, karena unik dan stabil per orang. */
  seed: { type: [String, Number], default: '' },
})

/**
 * Palet ilustrasi, bukan token antarmuka. Warna kulit dan rambut memang bukan
 * bagian dari sistem desain — sama seperti gelombang di BannerBackdrop yang
 * juga digambar dengan nilai tetap.
 */
const BG = ['#e8effb', '#eaf6ef', '#fdf1e7', '#f3eefb', '#e9f4f8', '#fbeef3']
const SKIN = ['#f7e0cb', '#f2d3b8', '#e0b596', '#c98d64', '#a06840', '#7d4f33']
const HAIR = ['#2b2724', '#4b3621', '#8a5a2b', '#c9873f', '#6f6f76', '#1f2a44']
const CLOTHES = ['#4b7bec', '#22a06b', '#e08a3c', '#8b6bd4', '#5a6b7a', '#d4547e']
const STYLES = ['flat', 'round', 'side', 'curly', 'bun', 'buzz']
const INK = '#2f2b3a'

/** FNV-1a — pendek, cepat, dan sebarannya cukup rata untuk keperluan ini. */
function hashOf(input) {
  let h = 2166136261
  const s = String(input)
  for (let i = 0; i < s.length; i += 1) {
    h ^= s.charCodeAt(i)
    h = Math.imul(h, 16777619)
  }
  return h >>> 0
}

const h = computed(() => hashOf(props.seed || props.name || '?'))

/** Tiap sifat mengambil potongan bit yang berbeda supaya tidak berkorelasi. */
const pick = (list, shift) => list[(h.value >>> shift) % list.length]

const bg = computed(() => pick(BG, 0))
const skin = computed(() => pick(SKIN, 5))
const hair = computed(() => pick(HAIR, 10))
const clothes = computed(() => pick(CLOTHES, 15))
const style = computed(() => pick(STYLES, 20))

/* clipPath butuh id unik — satu halaman bisa memuat belasan avatar. */
const uid = useId()
const clipId = computed(() => `av-${uid}`)
</script>

<template>
  <svg
    class="face"
    viewBox="0 0 64 64"
    role="img"
    :aria-label="name || 'Avatar'"
  >
    <defs>
      <clipPath :id="clipId">
        <circle cx="32" cy="26" r="14" />
      </clipPath>
    </defs>

    <rect width="64" height="64" :fill="bg" />

    <!-- Badan; sengaja menyentuh tepi bawah karena bingkainya dipotong bulat -->
    <path d="M32 40c-11 0-20 8-21 18v6h42v-6c-1-10-10-18-21-18z" :fill="clothes" />

    <rect x="28" y="33" width="8" height="10" rx="4" :fill="skin" />

    <!-- Telinga digambar sebelum kepala supaya separuh bagian dalamnya tertutup -->
    <circle cx="18" cy="27" r="3" :fill="skin" />
    <circle cx="46" cy="27" r="3" :fill="skin" />
    <circle cx="32" cy="26" r="14" :fill="skin" />

    <!-- Rambut dipotong mengikuti kepala; bentuk di luar kepala digambar
         terpisah supaya tidak ikut terpotong. -->
    <g :clip-path="`url(#${clipId})`" :fill="hair">
      <rect v-if="style === 'flat'" x="16" y="9" width="32" height="10" />
      <ellipse v-else-if="style === 'round'" cx="32" cy="17" rx="16" ry="10" />
      <path v-else-if="style === 'side'" d="M16 9h32v9c-7 1-9-4-14-4s-9 7-18 4z" />
      <g v-else-if="style === 'curly'">
        <circle cx="23" cy="17" r="7" />
        <circle cx="32" cy="13" r="8" />
        <circle cx="41" cy="17" r="7" />
      </g>
      <ellipse v-else-if="style === 'bun'" cx="32" cy="17" rx="16" ry="9" />
      <rect v-else x="16" y="10" width="32" height="7" />
    </g>
    <circle v-if="style === 'bun'" cx="32" cy="9" r="5" :fill="hair" />

    <circle cx="27" cy="27" r="1.7" :fill="INK" />
    <circle cx="37" cy="27" r="1.7" :fill="INK" />
    <path
      d="M28 32.5c1.4 1.9 6.6 1.9 8 0"
      :stroke="INK"
      stroke-width="1.4"
      stroke-linecap="round"
      fill="none"
    />
  </svg>
</template>

<style scoped>
.face {
  width: 100%;
  height: 100%;
  display: block;
}
</style>
