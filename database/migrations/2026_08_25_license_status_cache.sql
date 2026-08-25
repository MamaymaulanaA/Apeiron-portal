-- =============================================================================
-- Cache status lisensi dari License Server
-- =============================================================================
--
-- Aman dijalankan berulang (MariaDB mendukung IF NOT EXISTS untuk ADD COLUMN).
--
-- INI CACHE, BUKAN SUMBER KEBENARAN.
-- Status, masa berlaku, dan sisa hari tetap ditentukan License Server. Dua
-- kolom ini hanya menyimpan jawaban terakhir server supaya:
--   1. halaman Plugin tidak menggantung menunggu server pada setiap kali buka;
--   2. ketika server sedang tidak terjangkau, user masih melihat keadaan
--      terakhir yang diketahui — ditandai jelas sebagai data tersimpan,
--      bukan disajikan seolah-olah baru.
--
-- KENAPA SATU KOLOM TEKS, BUKAN SEPULUH KOLOM
-- Isi cache tidak pernah dicari, disaring, atau diurutkan lewat SQL; ia hanya
-- diambil utuh lalu dikirim ulang ke tampilan. Memecahnya menjadi sepuluh
-- kolom hanya menambah sepuluh tempat yang harus ikut diubah setiap kali
-- server menambah satu field, tanpa memberi kemampuan query apa pun.
--
-- Konsekuensinya disadari: isi kolom ini tidak bisa di-query per field. Kalau
-- suatu saat butuh laporan "berapa lisensi kedaluwarsa bulan depan", angkanya
-- harus diminta ke server — yang memang pemiliknya.
--
-- Tidak ada kolom lama yang diubah dan tidak ada baris yang disentuh.
-- =============================================================================

ALTER TABLE `user_licenses`
  ADD COLUMN IF NOT EXISTS `license_status_json` text DEFAULT NULL AFTER `assigned_at`;

ALTER TABLE `user_licenses`
  ADD COLUMN IF NOT EXISTS `license_checked_at` timestamp NULL DEFAULT NULL AFTER `license_status_json`;
