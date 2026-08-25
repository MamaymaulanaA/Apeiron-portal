-- =============================================================================
-- Langkah tutorial bergambar
-- =============================================================================
--
-- Menambah SATU tabel baru. Tidak menyentuh `tutorials` maupun tabel lain, dan
-- tidak mengubah baris yang sudah ada — tutorial yang sudah dibuat tetap utuh,
-- hanya belum punya langkah.
--
-- Jalankan setelah 2026_08_25_forum_and_groups.sql.
--
-- KENAPA TABEL TERPISAH, BUKAN KOLOM JSON DI `tutorials`
-- Langkah punya urutan, teks, dan gambarnya sendiri. Disimpan sebagai JSON,
-- gambar-gambar itu tidak bisa dicari, dihitung, atau dibersihkan lewat SQL —
-- dan saat satu langkah dihapus tidak ada yang tahu berkas mana yang jadi
-- yatim. Sebagai baris, tiap gambar punya identitasnya sendiri.
--
-- ON DELETE CASCADE: menghapus tutorial ikut menghapus barisnya. Berkas
-- gambarnya dihapus terpisah oleh controller sebelum baris induknya hilang,
-- karena database tidak tahu apa-apa tentang isi folder storage.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `tutorial_steps` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `tutorial_id` int(10) UNSIGNED NOT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `body` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tutorial_position` (`tutorial_id`, `position`),
  CONSTRAINT `fk_tutorial_steps_tutorial`
    FOREIGN KEY (`tutorial_id`) REFERENCES `tutorials` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
