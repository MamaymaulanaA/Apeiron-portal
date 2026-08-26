-- =============================================================================
-- Panduan, Group, dan Bonus — seluruh perubahan database dalam satu berkas
-- =============================================================================
--
-- Gabungan dari tujuh migration di folder ini, disusun berurutan. Jalankan
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


-- =============================================================================
-- 7. Plugin dan riwayat versinya
-- =============================================================================
-- Tabel `plugins` selama ini menyimpan VERSI, bukan plugin, dan menambah versi
-- baru mematikan seluruh versi sebelumnya. Bagian ini menambahkan induknya dan
-- kolom penghubungnya; tidak ada tabel yang diganti nama dan tidak ada baris
-- yang dibuang. Penjelasan lengkapnya ada di
-- database/migrations/2026_08_25_plugin_versions.sql.

-- -----------------------------------------------------------------------------
-- 1. Plugin (identitas produk)
-- -----------------------------------------------------------------------------
-- `slug` dipakai untuk mencocokkan plugin dengan product_id pada lisensi,
-- sehingga lisensi tetap menunjuk plugin yang sama sementara versi datang dan
-- pergi. Lisensi TIDAK menunjuk versi.

CREATE TABLE IF NOT EXISTS `plugin_products` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_slug` (`slug`),
  KEY `idx_active_order` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- -----------------------------------------------------------------------------
-- 2. Kolom baru pada tabel versi
-- -----------------------------------------------------------------------------
-- version_sort: kunci urut yang membuat "versi terbaru" ditentukan data, bukan
-- urutan tampilan. Perbandingan teks biasa salah pada 1.0.10 vs 1.0.9 — "9"
-- lebih besar daripada "1" secara leksikografis. Tiap ruas dipadkan nol
-- sehingga urutan string sama dengan urutan angka.

ALTER TABLE `plugins`
  ADD COLUMN IF NOT EXISTS `plugin_id` int(10) UNSIGNED DEFAULT NULL AFTER `id`;

ALTER TABLE `plugins`
  ADD COLUMN IF NOT EXISTS `version_sort` varchar(64) DEFAULT NULL AFTER `version`;

ALTER TABLE `plugins`
  ADD COLUMN IF NOT EXISTS `released_at` timestamp NULL DEFAULT NULL AFTER `changelog`;

ALTER TABLE `plugins`
  ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();


-- -----------------------------------------------------------------------------
-- 3. Plugin bawaan untuk versi yang sudah ada
-- -----------------------------------------------------------------------------
-- Semua versi yang sudah ada adalah rilis dari satu produk yang sama, dan
-- slug-nya mengikuti product_id yang dipakai lisensi ('apeiron-kit').

INSERT INTO `plugin_products` (`name`, `slug`, `description`, `is_active`)
SELECT 'Apeiron Kit', 'apeiron-kit', 'Plugin utama Apeiron.', 1
WHERE NOT EXISTS (SELECT 1 FROM `plugin_products` WHERE `slug` = 'apeiron-kit');


-- -----------------------------------------------------------------------------
-- 4. Sambungkan versi lama ke plugin bawaan
-- -----------------------------------------------------------------------------

UPDATE `plugins`
SET `plugin_id` = (SELECT `id` FROM `plugin_products` WHERE `slug` = 'apeiron-kit')
WHERE `plugin_id` IS NULL;


-- -----------------------------------------------------------------------------
-- 5. Tanggal rilis
-- -----------------------------------------------------------------------------
-- Belum pernah ada kolomnya, jadi diisi dari created_at — waktu versi itu
-- masuk ke portal, yang paling mendekati tanggal rilisnya.

UPDATE `plugins`
SET `released_at` = `created_at`
WHERE `released_at` IS NULL;


-- -----------------------------------------------------------------------------
-- 6. Kunci urut untuk versi yang sudah ada
-- -----------------------------------------------------------------------------
-- Rumusnya harus sama persis dengan PluginVersion::sortKey() di PHP, yang
-- dipakai saat menyimpan versi baru. Kalau keduanya berbeda, urutan versi lama
-- dan versi baru tidak akan konsisten.
--
-- Angka diambil SEBELUM karakter non-angka pertama, lalu baru di-CAST.
--
-- CAST('3-beta' AS UNSIGNED) tidak memaafkan: pada MariaDB dengan strict mode
-- ia melempar "Truncated incorrect INTEGER value" dan MEMBATALKAN seluruh
-- UPDATE, sehingga tidak ada satu pun version_sort yang terisi. Ini teruji,
-- bukan dugaan.
--
-- REGEXP_REPLACE(x, '[^0-9].*$', '') memotong dari karakter non-angka pertama
-- sampai habis — perilaku yang sama persis dengan (int) di PHP, sehingga
-- '3-beta' menjadi 3 di kedua sisi. NULLIF menangani ruas yang tak menyisakan
-- angka sama sekali.

UPDATE `plugins`
SET `version_sort` = CONCAT_WS('.',
  LPAD(COALESCE(CAST(NULLIF(REGEXP_REPLACE(SUBSTRING_INDEX(`version`, '.', 1), '[^0-9].*$', ''), '') AS UNSIGNED), 0), 5, '0'),
  LPAD(CASE WHEN LENGTH(`version`) - LENGTH(REPLACE(`version`, '.', '')) >= 1
            THEN COALESCE(CAST(NULLIF(REGEXP_REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(`version`, '.', 2), '.', -1), '[^0-9].*$', ''), '') AS UNSIGNED), 0)
            ELSE 0 END, 5, '0'),
  LPAD(CASE WHEN LENGTH(`version`) - LENGTH(REPLACE(`version`, '.', '')) >= 2
            THEN COALESCE(CAST(NULLIF(REGEXP_REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(`version`, '.', 3), '.', -1), '[^0-9].*$', ''), '') AS UNSIGNED), 0)
            ELSE 0 END, 5, '0'),
  LPAD(CASE WHEN LENGTH(`version`) - LENGTH(REPLACE(`version`, '.', '')) >= 3
            THEN COALESCE(CAST(NULLIF(REGEXP_REPLACE(SUBSTRING_INDEX(SUBSTRING_INDEX(`version`, '.', 4), '.', -1), '[^0-9].*$', ''), '') AS UNSIGNED), 0)
            ELSE 0 END, 5, '0')
)
WHERE `version_sort` IS NULL;


-- -----------------------------------------------------------------------------
-- 7. Kembalikan versi yang dimatikan otomatis
-- -----------------------------------------------------------------------------
-- is_active = 0 pada baris lama BUKAN pilihan admin. Tidak pernah ada cara
-- untuk menyembunyikan satu versi saja: mengaktifkan satu versi selalu
-- mematikan semuanya. Jadi nilai 0 di sini adalah efek samping kode, dan
-- membiarkannya berarti riwayat versi tetap tersembunyi dari user — persis
-- masalah yang hendak diperbaiki.
--
-- Setelah ini, is_active kembali ke artinya yang sebenarnya: versi ini
-- tersedia untuk diunduh atau tidak. Menandai satu versi tidak lagi
-- memengaruhi versi lain.

UPDATE `plugins` SET `is_active` = 1 WHERE `is_active` = 0;


-- -----------------------------------------------------------------------------
-- 8. Indeks
-- -----------------------------------------------------------------------------
-- Foreign key sengaja TIDAK dipasang ke plugins.plugin_id: baris lama bisa
-- saja sudah punya nilai yang tidak cocok pada database yang sudah berjalan,
-- dan migration ini tidak boleh gagal karenanya. Keutuhan relasinya dijaga di
-- controller, yang selalu memeriksa plugin induknya ada sebelum menyimpan.

ALTER TABLE `plugins`
  ADD INDEX IF NOT EXISTS `idx_plugin_version` (`plugin_id`, `version_sort`);

ALTER TABLE `plugins`
  ADD INDEX IF NOT EXISTS `idx_plugin_active` (`plugin_id`, `is_active`);


-- =============================================================================
-- 8. Percobaan Plugin pada profil user
-- =============================================================================
-- Setelan manual milik admin. Durasinya tidak pernah dihitung dari lisensi; ia
-- hanya angka untuk ditampilkan, dan trial_enabled yang menentukan Bonus
-- Member terkunci atau tidak. Default 0 — user lama tetap Member biasa.
-- Penjelasan lengkap: database/migrations/2026_08_26_plugin_trial.sql

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `trial_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `role`;

ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `trial_duration` int(11) DEFAULT NULL AFTER `trial_enabled`;

-- Disimpan sebagai teks, bukan enum: satuan yang bertambah kelak (mis. minggu)
-- hanya perlu ditangani di validasi, tanpa ALTER pada tabel yang sudah berisi
-- data pengguna.
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `trial_unit` varchar(10) DEFAULT NULL AFTER `trial_duration`;
