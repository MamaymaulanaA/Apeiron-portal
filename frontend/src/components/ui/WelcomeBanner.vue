<script setup>
/**
 * Banner sambutan — dipakai dashboard user dan dashboard admin.
 *
 * Sebelumnya banner hanya ada di dashboard user dan seluruh gayanya tertulis
 * scoped di view itu. Begitu dashboard admin butuh banner yang sama, menyalin
 * ~80 baris CSS ke view kedua berarti dua tempat yang harus diubah setiap kali
 * bannernya disentuh. Isinya dipindah ke sini; kedua view tinggal mengisi
 * slot-nya.
 *
 * Judul, sub, dan tombol lewat slot karena isinya berbeda jauh: user melihat
 * kemajuan lisensinya, admin melihat antrean verifikasi.
 */
import BannerArt from './BannerArt.vue'
import BannerBackdrop from './BannerBackdrop.vue'

defineProps({
  /** Teks pil kecil di atas judul. Dikosongkan bila tidak ada yang perlu. */
  pill: { type: String, default: '' },
})
</script>

<template>
  <section class="banner">
    <BannerBackdrop />

    <div class="banner-text">
      <span v-if="pill" class="banner-pill">{{ pill }}</span>

      <h1 class="banner-title">
        <slot name="title" />
      </h1>

      <p class="banner-sub">
        <slot name="sub" />
      </p>

      <div class="banner-actions">
        <slot name="actions" />
      </div>
    </div>

    <BannerArt class="banner-art" />
  </section>
</template>

<style scoped>
.banner {
  position: relative;
  display: flex;
  align-items: center;
  gap: var(--sp-5);
  padding: var(--sp-6) var(--sp-7);
  border-radius: var(--r);
  background: linear-gradient(105deg, #dbe8ff 0%, #eaf1ff 52%, #dde9ff 100%);
  border: 1px solid #c7dbff;
  overflow: hidden;
}

.banner-text {
  position: relative;
  z-index: 1;
  min-width: 0;
  flex: 1;
}

.banner-pill {
  display: inline-flex;
  align-items: center;
  height: 26px;
  padding: 0 var(--sp-3);
  border-radius: var(--r-pill);
  background: var(--bg-surface);
  color: var(--brand);
  font-size: var(--fs-caption);
  font-weight: var(--fw-medium);
}

.banner-title {
  font-size: var(--fs-display);
  font-weight: var(--fw-bold);
  line-height: 1.25;
  text-wrap: balance;
  margin-top: var(--sp-3);
  margin-bottom: var(--sp-2);
}

/* Isi slot membawa scope view pemanggil, bukan scope komponen ini, jadi
   selektor biasa tidak akan mengenainya — :slotted() yang menjangkaunya. */
.banner-title :slotted(span) {
  color: var(--brand);
}

.banner-sub {
  color: var(--text-body);
  max-width: 60ch;
  line-height: var(--lh-snug);
}

.banner-actions {
  display: flex;
  flex-wrap: wrap;
  gap: var(--sp-2);
  margin-top: var(--sp-4);
}

/* Ilustrasi hanya muncul saat ada ruang lebih; di layar sempit ia
   disembunyikan agar tidak menekan teks. */
.banner-art {
  display: none;
  position: relative;
  z-index: 1;
  width: 320px;
  flex-shrink: 0;
  align-self: flex-end;
  /* Hanya sisi bawah dan kanan yang ditarik keluar padding; sisi atas
     dibiarkan agar ayunan tidak memotong rambut karakter. */
  margin: 0 calc(var(--sp-7) * -1) calc(var(--sp-6) * -1) 0;
}

@media (min-width: 1060px) {
  .banner-art {
    display: block;
  }
}

@media (max-width: 900px) {
  .banner {
    flex-direction: column;
    align-items: flex-start;
  }
  .banner-title {
    font-size: var(--fs-h1);
  }
}

@media (max-width: 640px) {
  .banner-actions,
  .banner-actions :slotted(.btn) {
    width: 100%;
  }
}
</style>
