-- =============================================================================
-- Forum (tutorial) dan Group
-- =============================================================================
--
-- Migrasi ini HANYA MENAMBAH dua tabel baru. Tidak ada satu pun tabel yang
-- sudah ada disentuh — users, transactions, templates, plugins, user_licenses,
-- notifications, admin_logs, download_logs, dan resources tetap apa adanya.
-- Aman dijalankan pada database produksi yang sedang berisi data.
--
-- Cara menjalankan (phpMyAdmin: Import, atau lewat CLI):
--   mysql -u <user> -p <nama_database> < 2026_08_25_forum_and_groups.sql
--
-- Keduanya memakai IF NOT EXISTS, jadi menjalankannya dua kali tidak merusak
-- apa pun.
-- =============================================================================


-- -----------------------------------------------------------------------------
-- community_groups — grup komunitas (WhatsApp, Telegram, Discord, dan lainnya)
-- -----------------------------------------------------------------------------
--
-- Namanya bukan `groups`: GROUPS adalah kata kunci cadangan sejak MySQL 8.0.2.
-- Portal ini berjalan di MariaDB yang belum mencadangkannya, tetapi memakai
-- nama yang tidak pernah bentrok menghindarkan masalah bila kelak pindah.
--
-- Platform tidak disimpan sebagai kolom. Ia diturunkan dari host pada `link`
-- setiap kali dibaca, sehingga tidak mungkin ada baris yang platform-nya
-- bertentangan dengan link-nya — misalnya ditandai WhatsApp padahal link-nya
-- Telegram karena admin mengganti link tanpa mengganti platform.
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
-- tutorials — isi Forum: tutorial, panduan, dan informasi produk
-- -----------------------------------------------------------------------------
--
-- Bukan forum diskusi. Tidak ada tabel balasan, penulis, atau utas, karena
-- yang dibutuhkan adalah pusat panduan satu arah dari admin ke user.
--
-- `icon` menyimpan nama ikon lucide (mis. 'Download', 'KeyRound'), bukan
-- berkas gambar. Kartu di aplikasi ini memakai chip ikon di mana-mana, bukan
-- foto, jadi ikon lebih menyatu dengan tampilan yang sudah ada — dan tidak
-- menambah direktori unggahan yang harus dijaga dan dibersihkan.
--
-- `content` untuk isi panjang, `excerpt` untuk ringkasan di kartu. Keduanya
-- terpisah supaya kartu tidak perlu memotong isi panjang di tengah kalimat.
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
