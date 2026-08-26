-- =============================================================================
-- Percobaan Plugin pada profil user
-- =============================================================================
--
-- Aman dijalankan berulang. Tiga kolom baru pada tabel `users`; tidak ada
-- kolom lama yang diubah dan tidak ada baris yang disentuh.
--
-- SETELAN MANUAL, BUKAN TURUNAN LISENSI
-- Durasinya tidak pernah dihitung dari masa berlaku atau sisa waktu lisensi.
-- Ia angka yang diketik admin, dan hanya dipakai untuk ditampilkan. Yang
-- menentukan terkunci atau tidaknya Bonus Member adalah `trial_enabled`
-- semata — bukan tanggal, bukan hitung mundur.
--
-- Konsekuensinya disadari: masa percobaan TIDAK berakhir sendiri. Tidak ada
-- tanggal selesai yang disimpan, jadi tidak ada yang bisa membandingkannya
-- dengan hari ini. Admin yang mematikannya. Menyimpan tanggal selesai berarti
-- membuat perhitungan masa berlaku kedua di samping milik lisensi, dan dua
-- perhitungan yang berdiri sendiri akan berbeda cepat atau lambat.
--
-- Default 0: user baru tetap Member biasa, sehingga akses Bonus mengikuti
-- aturan transaksi yang sudah ada tanpa perubahan apa pun.
-- =============================================================================

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `trial_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `role`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `trial_duration` int(11) DEFAULT NULL AFTER `trial_enabled`;

-- Disimpan sebagai teks, bukan enum: satuan yang bertambah kelak (mis. minggu)
-- hanya perlu ditangani di validasi, tanpa ALTER pada tabel yang sudah berisi
-- data pengguna.
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `trial_unit` varchar(10) DEFAULT NULL AFTER `trial_duration`;
