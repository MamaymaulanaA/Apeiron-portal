<?php
/**
 * Plugin API Controller
 *
 * Mengelola plugin dan riwayat versinya.
 *
 * PEMBAGIANNYA
 *   PluginProduct = plugin (identitas). Lisensi menunjuk ke sini lewat slug.
 *   Plugin        = satu versi rilis. Tabelnya bernama `plugins` karena
 *                   sejarah; lihat catatan di modelnya.
 *
 * Merilis versi baru TIDAK menyentuh plugin induknya dan tidak menyentuh
 * lisensi siapa pun. Versi lama tetap ada, tetap punya berkasnya sendiri, dan
 * tetap bisa diunduh.
 *
 * CATATAN: jsonResponse() sengaja dipanggil DI LUAR blok try. Ia mengakhiri
 * request dengan exit(), tetapi di lingkungan uji ia dilempar — dan blok
 * catch (Throwable) akan menangkap responsnya sendiri, membuat setiap
 * panggilan sukses berubah jadi 500. try hanya membungkus kerja yang memang
 * bisa gagal.
 */

class PluginController
{
    private const MAX_ZIP = PLUGIN_MAX_SIZE;

    /**
     * Pindahkan berkas unggahan ke tujuannya.
     *
     * Dipisah jadi satu method supaya jalur berkasnya bisa diuji. Produksi
     * tetap memakai move_uploaded_file(), yang menolak apa pun yang bukan
     * hasil unggahan HTTP — penjagaan itu tidak boleh dilonggarkan hanya demi
     * bisa dites, jadi yang diganti saat uji adalah method-nya, bukan
     * pemeriksaannya. Dipanggil dengan static:: agar subclass benar-benar
     * dipakai.
     */
    protected static function moveUpload(string $tmp, string $dest): bool
    {
        return move_uploaded_file($tmp, $dest);
    }

    /** Direktori berkas plugin. Sama seperti sebelumnya. */
    private static function dir(): string
    {
        $dir = PORTAL_ROOT . '/storage/plugins/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        return $dir;
    }

    // =========================================================================
    // Sisi user
    // =========================================================================

    /**
     * GET /user/plugins
     *
     * Tiap plugin aktif beserta versi terbarunya dan riwayat versinya.
     * Hanya versi aktif yang ikut — menonaktifkan satu versi menyembunyikannya
     * dari user tanpa memengaruhi versi lain.
     */
    public function userPlugins(): void
    {
        requireAuth();

        $out = [];

        foreach (PluginProduct::allActive() as $product) {
            $versions = Plugin::forProduct($product->id, true);
            if (!$versions) continue;

            // Elemen pertama adalah yang tertinggi menurut version_sort —
            // urutannya ditentukan database, bukan tampilan.
            $latest = array_shift($versions);

            $out[] = [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'description' => $product->description,
                'latest' => self::versionPayload($latest),
                'previous' => array_map([self::class, 'versionPayload'], $versions),
            ];
        }

        jsonResponse(['plugins' => $out]);
    }

    /** Bentuk versi yang dikirim ke user. file_path sengaja tidak ikut. */
    private static function versionPayload(Plugin $v): array
    {
        return [
            'id' => $v->id,
            'version' => $v->version,
            'changelog' => $v->changelog,
            'released_at' => $v->released_at,
            'download_url' => '/api/user/download-plugin?version=' . $v->id,
        ];
    }

    // =========================================================================
    // Sisi admin — plugin
    // =========================================================================

    public function adminPlugins(): void
    {
        requireAdmin();

        $out = [];
        foreach (PluginProduct::all() as $product) {
            $latest = Plugin::latestFor($product->id);

            $out[] = $product->toArray() + [
                'version_count' => Plugin::countFor($product->id),
                'latest_version' => $latest?->version,
                'latest_released_at' => $latest?->released_at,
            ];
        }

        jsonResponse(['plugins' => $out]);
    }

    public function createPlugin(): void
    {
        requireAdmin();

        $name = trim((string) ($_POST['name'] ?? ''));
        if ($name === '') jsonResponse(['error' => 'Nama plugin wajib diisi'], 400);
        if (mb_strlen($name) > 255) jsonResponse(['error' => 'Nama plugin maksimal 255 karakter'], 400);

        $slug = trim((string) ($_POST['slug'] ?? ''));
        $slug = PluginProduct::slugify($slug !== '' ? $slug : $name);
        if ($slug === '') jsonResponse(['error' => 'Slug plugin tidak valid'], 400);

        if (PluginProduct::slugTaken($slug)) {
            jsonResponse(['error' => "Slug \"{$slug}\" sudah dipakai plugin lain"], 400);
        }

        try {
            $id = PluginProduct::create([
                'name' => $name,
                'slug' => $slug,
                'description' => self::nullableText($_POST['description'] ?? null),
                'is_active' => $_POST['is_active'] ?? 1,
                'sort_order' => $_POST['sort_order'] ?? 0,
            ]);
        } catch (Throwable $e) {
            self::failed($e, 'Gagal menambahkan plugin');
        }

        jsonResponse(['message' => 'Plugin berhasil ditambahkan', 'id' => $id], 201);
    }

    public function updatePlugin(array $params): void
    {
        requireAdmin();

        $product = PluginProduct::find((int) ($params['id'] ?? 0));
        if (!$product) jsonResponse(['error' => 'Plugin tidak ditemukan'], 404);

        $name = array_key_exists('name', $_POST) ? trim((string) $_POST['name']) : $product->name;
        if ($name === '') jsonResponse(['error' => 'Nama plugin wajib diisi'], 400);

        $slug = array_key_exists('slug', $_POST) && trim((string) $_POST['slug']) !== ''
            ? PluginProduct::slugify((string) $_POST['slug'])
            : $product->slug;

        if ($slug === '') jsonResponse(['error' => 'Slug plugin tidak valid'], 400);
        if (PluginProduct::slugTaken($slug, $product->id)) {
            jsonResponse(['error' => "Slug \"{$slug}\" sudah dipakai plugin lain"], 400);
        }

        try {
            $data = ['name' => $name, 'slug' => $slug];
            if (array_key_exists('description', $_POST)) {
                $data['description'] = self::nullableText($_POST['description']);
            }
            if (array_key_exists('is_active', $_POST)) $data['is_active'] = $_POST['is_active'];
            if (array_key_exists('sort_order', $_POST)) $data['sort_order'] = (int) $_POST['sort_order'];

            $product->update($data);
        } catch (Throwable $e) {
            self::failed($e, 'Gagal memperbarui plugin');
        }

        jsonResponse(['message' => 'Plugin berhasil diperbarui']);
    }

    /**
     * DELETE /admin/plugins/{id}
     *
     * Menghapus plugin beserta SELURUH versinya. Berkas dibaca sebelum
     * barisnya hilang, karena setelah dihapus tidak ada lagi cara mengetahui
     * berkas mana yang menjadi miliknya.
     */
    public function deletePlugin(array $params): void
    {
        requireAdmin();

        $product = PluginProduct::find((int) ($params['id'] ?? 0));
        if (!$product) jsonResponse(['error' => 'Plugin tidak ditemukan'], 404);

        try {
            $versions = Plugin::forProduct($product->id);
            $product->delete();

            // Baris sudah hilang, jadi fileSharedWithOthers() tidak lagi
            // relevan untuk versi milik plugin ini; yang perlu diperiksa hanya
            // apakah berkasnya masih dipegang plugin LAIN.
            foreach ($versions as $v) {
                self::removeFileIfUnused($v->filename);
            }
        } catch (Throwable $e) {
            self::failed($e, 'Gagal menghapus plugin');
        }

        jsonResponse(['message' => 'Plugin beserta seluruh versinya berhasil dihapus']);
    }

    // =========================================================================
    // Sisi admin — versi
    // =========================================================================

    public function adminVersions(array $params): void
    {
        requireAdmin();

        $product = PluginProduct::find((int) ($params['id'] ?? 0));
        if (!$product) jsonResponse(['error' => 'Plugin tidak ditemukan'], 404);

        $versions = Plugin::forProduct($product->id);
        $latestId = null;
        foreach ($versions as $v) {
            if ($v->is_active) { $latestId = $v->id; break; }
        }

        jsonResponse([
            'plugin' => $product->toArray(),
            'versions' => array_map(
                static fn(Plugin $v) => $v->toArray() + ['is_latest' => $v->id === $latestId],
                $versions
            ),
        ]);
    }

    /**
     * POST /admin/plugins/{id}/versions
     *
     * Menambah versi baru. Versi lain tidak disentuh sama sekali — itulah
     * perbedaan pokok dengan perilaku lama, yang mematikan semua versi
     * sebelumnya setiap kali versi baru diunggah.
     */
    public function createVersion(array $params): void
    {
        requireAdmin();
        applyRateLimit('admin_upload_plugin', 5, 60);

        $product = PluginProduct::find((int) ($params['id'] ?? 0));
        if (!$product) jsonResponse(['error' => 'Plugin tidak ditemukan'], 404);

        $version = trim((string) ($_POST['version'] ?? ''));
        $error = self::validateVersion($product, $version, null);
        if ($error) jsonResponse(['error' => $error], 400);

        if (!isset($_FILES['plugin']) || $_FILES['plugin']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['error' => 'Berkas plugin wajib diunggah'], 400);
        }

        $fileError = self::validateZip($_FILES['plugin']);
        if ($fileError) jsonResponse(['error' => $fileError], 400);

        // Nama berkas memuat slug plugin, bukan hanya nomor versi. Dulu
        // namanya 'apeiron-kit-<versi>.zip' untuk semua plugin, jadi dua plugin
        // berbeda dengan nomor versi sama akan saling menimpa berkasnya.
        $filename = self::filenameFor($product, $version);

        if (!static::moveUpload($_FILES['plugin']['tmp_name'], self::dir() . $filename)) {
            jsonResponse(['error' => 'Gagal mengunggah berkas'], 500);
        }

        try {
            $id = Plugin::create([
                'plugin_id' => $product->id,
                'version' => $version,
                'filename' => $filename,
                'file_path' => $filename,
                'changelog' => self::nullableText($_POST['changelog'] ?? null),
                'released_at' => self::releasedAt($_POST['released_at'] ?? null),
                'is_active' => $_POST['is_active'] ?? 1,
            ]);
        } catch (Throwable $e) {
            // Berkasnya sudah terlanjur pindah; buang lagi supaya tidak
            // tertinggal tanpa baris yang memilikinya.
            @unlink(self::dir() . $filename);
            self::failed($e, 'Gagal menyimpan versi');
        }

        // Notifikasi ke pemegang lisensi, seperti perilaku lama.
        //
        // Dulu ini terpasang pada "update plugin", karena memperbarui plugin
        // memang satu-satunya cara merilis versi. Sekarang pemicunya jelas:
        // versi baru yang dirilis. Mengubah nama atau deskripsi plugin tidak
        // lagi mengirim pemberitahuan rilis.
        //
        // Gagal mengirim tidak boleh membatalkan rilisnya — versinya sudah
        // tersimpan dan berkasnya sudah ada.
        $notified = 0;
        if (($_POST['notify'] ?? '1') !== '0') {
            try {
                $notified = Notification::notifyAllLicensedUsers(
                    'plugin_update',
                    'Update Plugin Tersedia!',
                    "{$product->name} versi {$version} telah tersedia. "
                    . (self::nullableText($_POST['changelog'] ?? null) ? 'Changelog: ' . trim((string) $_POST['changelog']) . '. ' : '')
                    . 'Silakan unduh versi terbaru dari dashboard Anda.',
                    $id
                );
            } catch (Throwable $notifErr) {
                error_log('[PORTAL][PLUGIN] Notifikasi rilis gagal: ' . $notifErr->getMessage());
            }
        }

        jsonResponse([
            'message' => "Versi {$version} berhasil ditambahkan",
            'id' => $id,
            'notified_users' => $notified,
        ], 201);
    }

    public function updateVersion(array $params): void
    {
        requireAdmin();

        $ver = Plugin::find((int) ($params['id'] ?? 0));
        if (!$ver) jsonResponse(['error' => 'Versi tidak ditemukan'], 404);

        $product = $ver->plugin_id ? PluginProduct::find($ver->plugin_id) : null;
        if (!$product) jsonResponse(['error' => 'Plugin induk tidak ditemukan'], 404);

        $version = array_key_exists('version', $_POST) ? trim((string) $_POST['version']) : $ver->version;
        $error = self::validateVersion($product, $version, $ver->id);
        if ($error) jsonResponse(['error' => $error], 400);

        $data = ['version' => $version];
        if (array_key_exists('changelog', $_POST)) {
            $data['changelog'] = self::nullableText($_POST['changelog']);
        }
        if (array_key_exists('released_at', $_POST)) {
            $data['released_at'] = self::releasedAt($_POST['released_at']);
        }
        if (array_key_exists('is_active', $_POST)) $data['is_active'] = $_POST['is_active'];

        $oldFile = $ver->filename;
        $newFile = self::filenameFor($product, $version);

        if (isset($_FILES['plugin']) && $_FILES['plugin']['error'] === UPLOAD_ERR_OK) {
            $fileError = self::validateZip($_FILES['plugin']);
            if ($fileError) jsonResponse(['error' => $fileError], 400);

            if (!static::moveUpload($_FILES['plugin']['tmp_name'], self::dir() . $newFile)) {
                jsonResponse(['error' => 'Gagal mengunggah berkas'], 500);
            }

            $data['filename'] = $newFile;
            $data['file_path'] = $newFile;
        } elseif ($newFile !== $oldFile && is_file(self::dir() . $oldFile)) {
            // Nomor versinya berubah tanpa unggahan baru: berkasnya ikut
            // diganti nama supaya nama berkas tidak berbohong soal versinya.
            if (@rename(self::dir() . $oldFile, self::dir() . $newFile)) {
                $data['filename'] = $newFile;
                $data['file_path'] = $newFile;
            }
        }

        try {
            $ver->update($data);

            // Berkas lama hanya dibuang kalau namanya memang berubah DAN tidak
            // ada baris lain yang masih memegangnya. Database lama bisa punya
            // dua baris menunjuk satu berkas; menghapusnya begitu saja akan
            // mematikan unduhan versi yang lain.
            if (($data['filename'] ?? $oldFile) !== $oldFile) {
                self::removeFileIfUnused($oldFile);
            }
        } catch (Throwable $e) {
            self::failed($e, 'Gagal memperbarui versi');
        }

        jsonResponse(['message' => 'Versi berhasil diperbarui']);
    }

    /**
     * DELETE /admin/plugin-versions/{id}
     *
     * Hanya versi itu yang hilang. Plugin induknya dan versi lain tetap utuh.
     */
    public function deleteVersion(array $params): void
    {
        requireAdmin();

        $ver = Plugin::find((int) ($params['id'] ?? 0));
        if (!$ver) jsonResponse(['error' => 'Versi tidak ditemukan'], 404);

        try {
            $filename = $ver->filename;
            $ver->delete();
            self::removeFileIfUnused($filename);
        } catch (Throwable $e) {
            self::failed($e, 'Gagal menghapus versi');
        }

        jsonResponse(['message' => "Versi {$ver->version} berhasil dihapus"]);
    }

    // =========================================================================
    // Pembantu
    // =========================================================================

    private static function filenameFor(PluginProduct $product, string $version): string
    {
        // basename() menahan nilai yang mencoba keluar dari folder lewat
        // nomor versi, meski validateVersion() sudah membatasi karakternya.
        return basename($product->slug . '-' . $version . '.zip');
    }

    /**
     * Buang berkas hanya bila tidak ada baris versi mana pun yang memakainya.
     */
    private static function removeFileIfUnused(string $filename): void
    {
        if ($filename === '') return;

        $used = (int) (Database::fetchOne(
            "SELECT COUNT(*) AS c FROM plugins WHERE filename = ?",
            [$filename]
        )['c'] ?? 0);

        if ($used > 0) return;

        $path = self::dir() . basename($filename);
        if (is_file($path)) @unlink($path);
    }

    private static function validateVersion(PluginProduct $product, string $version, ?int $exceptId): ?string
    {
        if ($version === '') return 'Nomor versi wajib diisi';
        if (mb_strlen($version) > 50) return 'Nomor versi maksimal 50 karakter';

        // Dibatasi angka, titik, huruf, dan tanda hubung. Selain menjaga nama
        // berkas tetap aman, ini juga menahan nomor versi yang tidak bisa
        // diurutkan.
        if (!preg_match('/^[0-9A-Za-z.\-]+$/', $version)) {
            return 'Nomor versi hanya boleh berisi angka, huruf, titik, dan tanda hubung';
        }
        if (!preg_match('/^[0-9]/', $version)) {
            return 'Nomor versi harus diawali angka, misalnya 1.0.3';
        }

        if (Plugin::versionExists($product->id, $version, $exceptId)) {
            return "Versi {$version} sudah ada pada plugin ini";
        }

        return null;
    }

    private static function validateZip(array $file): ?string
    {
        if ($file['size'] > self::MAX_ZIP) {
            return 'Ukuran berkas maksimal ' . (int) (self::MAX_ZIP / 1048576) . 'MB';
        }

        if (strtolower((string) pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'zip') {
            return 'Berkas harus berformat ZIP';
        }

        // Tipe dibaca dari isi berkas, bukan dari namanya.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'], true)) {
            return 'Berkas bukan ZIP yang valid';
        }

        return null;
    }

    private static function releasedAt($value): string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') return date('Y-m-d H:i:s');

        $ts = strtotime($value);
        return $ts === false ? date('Y-m-d H:i:s') : date('Y-m-d H:i:s', $ts);
    }

    private static function nullableText($value): ?string
    {
        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    /**
     * Terjemahkan kegagalan jadi sesuatu yang bisa dikerjakan.
     *
     * Tabel atau kolom yang belum ada hanya berarti satu hal: migration belum
     * dijalankan. Menyebut nama berkasnya lebih berguna daripada "gagal".
     */
    private static function failed(Throwable $e, string $fallback): void
    {
        error_log('[PORTAL][PLUGIN] ' . $e->getMessage());

        $msg = $e->getMessage();
        $missing = str_contains($msg, '42S02') || str_contains($msg, '42S22')
            || stripos($msg, 'no such table') !== false
            || stripos($msg, 'no such column') !== false
            || stripos($msg, "doesn't exist") !== false
            || stripos($msg, 'Unknown column') !== false;

        if ($missing) {
            jsonResponse([
                'error' => 'Struktur database plugin belum lengkap. Jalankan '
                    . 'database/migrations/2026_08_25_plugin_versions.sql, lalu coba lagi.',
            ], 500);
        }

        jsonResponse(['error' => $fallback], 500);
    }
}
