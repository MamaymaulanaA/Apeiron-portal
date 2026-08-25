-- =============================================================================
-- Gambar tutorial
-- =============================================================================
--
-- Menambah SATU kolom pada tabel `tutorials` yang dibuat migrasi sebelumnya.
-- Tidak menyentuh tabel lain, dan tidak mengubah baris yang sudah ada — kolom
-- baru berisi NULL, yang berarti "tutorial ini belum punya gambar".
--
-- Jalankan setelah 2026_08_25_forum_and_groups.sql.
--
-- CATATAN: MariaDB/MySQL tidak punya "ADD COLUMN IF NOT EXISTS" di semua versi,
-- jadi menjalankan berkas ini dua kali akan memunculkan error "Duplicate column
-- name 'image_path'". Error itu tidak merusak apa pun — artinya kolomnya memang
-- sudah ada dan tidak perlu ditambahkan lagi.
-- =============================================================================

ALTER TABLE `tutorials`
  ADD COLUMN `image_path` varchar(255) DEFAULT NULL AFTER `icon`;
