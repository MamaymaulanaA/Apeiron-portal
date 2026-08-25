-- =============================================================================
-- Bonus member
-- =============================================================================
--
-- Menambah SATU tabel baru di database yang sama. Tidak ada tabel lain yang
-- disentuh, dan tidak ada baris yang diubah.
--
-- KENAPA TABEL BARU, BUKAN MEMAKAI `resources`
-- Tabel `resources` memang sudah ada dan bentuknya mirip — judul, deskripsi,
-- file_path, file_size. Tetapi ia tidak dipakai satu baris kode pun di seluruh
-- aplikasi dan isinya kosong, sementara Bonus butuh lima kolom yang tidak
-- dimilikinya (kategori, thumbnail, tipe sumber, URL eksternal, status aktif)
-- dan tidak butuh `version` yang dimilikinya. Membengkokkan tabel bernama
-- "resources" berisi kolom "version" untuk menampung bonus hanya akan membuat
-- nama tabelnya berbohong tentang isinya. `resources` dibiarkan apa adanya.
--
-- `source_type` menentukan mana di antara `file_path` dan `external_url` yang
-- berarti. Keduanya dibiarkan nullable karena satu baris hanya pernah memakai
-- salah satunya; controller yang menjaga agar yang dipakai selalu terisi.
-- =============================================================================

CREATE TABLE IF NOT EXISTS `bonuses` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `thumbnail_path` varchar(255) DEFAULT NULL,
  `source_type` enum('file','link') NOT NULL DEFAULT 'link',
  `file_path` varchar(255) DEFAULT NULL,
  `file_original_name` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
