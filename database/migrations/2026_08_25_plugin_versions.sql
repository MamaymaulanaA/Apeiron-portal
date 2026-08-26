-- =============================================================================
-- Plugin dan riwayat versinya
-- =============================================================================
--
-- Aman dijalankan berulang. Tidak ada tabel yang dihapus atau diganti nama,
-- dan tidak ada baris yang dibuang.
--
-- MASALAH YANG DIPERBAIKI
-- Tabel `plugins` selama ini menyimpan VERSI, bukan plugin: kolomnya version,
-- filename, file_path, changelog. Tidak ada nama plugin di mana pun. Akibatnya
-- "menambah versi" berarti menambah baris plugin baru, dan Plugin::create()
-- menjalankan `UPDATE plugins SET is_active = 0` lebih dulu — mematikan semua
-- versi sebelumnya. Karena getLatest() menyaring is_active = 1, hanya satu
-- versi yang pernah bisa diunduh; sisanya jadi baris mati.
--
-- KENAPA `plugins` TIDAK DIGANTI NAMA
-- Ia memang sudah berisi data versi, dan mengganti namanya berarti menyentuh
-- setiap query yang ada plus memindahkan data produksi. Yang ditambahkan di
-- sini hanya induknya (`plugin_products`) dan kolom penghubungnya. Konsekuensi
-- yang disadari: tabel bernama `plugins` berisi versi. Kelas modelnya bernama
-- PluginVersion supaya kode tetap menyebutnya apa adanya.
-- =============================================================================


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
