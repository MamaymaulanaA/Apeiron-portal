-- =============================================================================
-- Panduan, Group, dan Bonus — seluruh perubahan database dalam satu berkas
-- =============================================================================
--
-- Gabungan dari lima migration di folder ini, disusun berurutan. Jalankan
-- berkas INI saja; tidak perlu menjalankan keempatnya satu per satu.
--
-- Aman dijalankan berulang. Setiap pernyataan memakai IF NOT EXISTS, jadi
-- menjalankannya dua kali tidak menggandakan apa pun dan tidak error — berguna
-- kalau sebagian sudah terlanjur dijalankan dan Anda tidak yakin yang mana.
--
-- Tidak ada tabel lama yang disentuh dan tidak ada baris yang diubah. Yang
-- terjadi hanya penambahan empat tabel baru dan satu kolom baru.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- 1. Group komunitas
-- -----------------------------------------------------------------------------
-- Platform (WhatsApp/Telegram/Discord) TIDAK disimpan sebagai kolom. Ia dibaca
-- dari host tautannya setiap kali baris ini diambil, supaya label platform
-- tidak pernah bisa berbeda dari tautan yang sebenarnya dibuka user.

CREATE TABLE IF NOT EXISTS `community_groups` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `link` varchar(500) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_active_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 2. Panduan
-- -----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `tutorials` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `excerpt` varchar(500) DEFAULT NULL,
  `content` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `icon` varchar(64) DEFAULT NULL,
  `external_url` varchar(500) DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_published_order` (`is_published`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 3. Kolom gambar pada panduan
-- -----------------------------------------------------------------------------
-- Sisa dari versi lama yang masih memakai gambar sampul. Kolomnya dipertahankan
-- agar panduan yang sudah terlanjur punya gambar tidak kehilangan datanya.

ALTER TABLE `tutorials`
  ADD COLUMN IF NOT EXISTS `image_path` varchar(255) DEFAULT NULL AFTER `icon`;


-- -----------------------------------------------------------------------------
-- 4. Isi panduan per paragraf
-- -----------------------------------------------------------------------------
-- Satu baris = satu paragraf, masing-masing boleh punya gambarnya sendiri.
-- Inilah yang membuat gambar bisa berada DI DALAM isi panduan, bukan hanya
-- sebagai sampul di atasnya.
--
-- ON DELETE CASCADE: menghapus panduan ikut menghapus paragrafnya, jadi tidak
-- ada baris yatim yang menunjuk panduan yang sudah tidak ada.

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


-- -----------------------------------------------------------------------------
-- 5. Bonus member
-- -----------------------------------------------------------------------------
-- `source_type` menentukan mana di antara `file_path` dan `external_url` yang
-- berarti. Keduanya nullable karena satu baris hanya pernah memakai salah
-- satunya; controller yang menjaga agar yang dipakai selalu terisi.

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


-- -----------------------------------------------------------------------------
-- 6. Cache status lisensi dari License Server
-- -----------------------------------------------------------------------------
-- INI CACHE, BUKAN SUMBER KEBENARAN. Status, masa berlaku, dan sisa hari tetap
-- ditentukan License Server; dua kolom ini hanya menyimpan jawaban terakhirnya
-- supaya halaman Plugin tidak menggantung menunggu server, dan supaya saat
-- server tidak terjangkau user masih melihat keadaan terakhir yang diketahui —
-- ditandai jelas sebagai data tersimpan.
--
-- Satu kolom teks, bukan sepuluh kolom: isinya tidak pernah dicari atau
-- diurutkan lewat SQL, hanya diambil utuh lalu dikirim ke tampilan.

ALTER TABLE `user_licenses`
  ADD COLUMN IF NOT EXISTS `license_status_json` text DEFAULT NULL AFTER `assigned_at`;

ALTER TABLE `user_licenses`
  ADD COLUMN IF NOT EXISTS `license_checked_at` timestamp NULL DEFAULT NULL AFTER `license_status_json`;
