<?php
/**
 * Bonus API Controller
 *
 * Bonus member: berkas atau tautan yang dibagikan admin.
 *
 * Berkas bonus TIDAK dilayani sebagai berkas statis. .htaccess menolak
 * storage/bonuses, dan satu-satunya jalan keluar adalah endpoint di bawah yang
 * memeriksa login lebih dulu — pola yang sama dengan bukti transaksi. Nama
 * berkas tidak pernah datang dari klien: yang dikirim id bonus, dan nama
 * berkasnya dibaca dari baris itu.
 */

class BonusController
{
    /** Batas unggahan berkas bonus. Sama dengan batas plugin (ZIP besar). */
    private const MAX_FILE = 50 * 1024 * 1024;
    private const MAX_THUMB = 2 * 1024 * 1024;

    private const THUMB_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * Ekstensi berkas bonus yang boleh diunggah.
     *
     * Daftar tertutup, bukan "apa saja kecuali php". Daftar larangan selalu
     * ketinggalan satu ekstensi — phtml, phar, htaccess — dan satu yang lolos
     * sudah cukup untuk menjalankan kode di server.
     */
    private const FILE_EXT = [
        'zip', 'rar', '7z', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'mp4', 'mp3', 'txt', 'csv', 'json',
    ];

    private const MIME = [
        'zip' => 'application/zip', 'rar' => 'application/vnd.rar', '7z' => 'application/x-7z-compressed',
        'pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv', 'json' => 'application/json',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
        'mp4' => 'video/mp4', 'mp3' => 'audio/mpeg',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];

    // =========================================================================
    // Sisi user
    // =========================================================================

    /**
     * GET /user/bonuses — hanya yang aktif, dan hanya bila transaksinya sudah
     * disetujui.
     *
     * Penyaringnya di sini, bukan di tampilan. Ketika belum disetujui,
     * payload-nya tidak memuat satu bonus pun — bukan memuat semuanya lalu
     * menyembunyikannya. Menyembunyikan di layar bukan penjagaan: siapa pun
     * bisa membuka endpoint ini langsung.
     *
     * Dashboard dan halaman Bonus sama-sama memakai endpoint ini, jadi satu
     * penjagaan di sini menutup keduanya sekaligus.
     */
    public function userBonuses(): void
    {
        requireAuth();
        $user = currentUser();

        $transaction = Transaction::findActiveByUser($user->id);
        $access = Transaction::memberAccessState($transaction);

        $bonuses = $access === 'granted'
            ? array_map(static fn(Bonus $b) => $b->toArray(), Bonus::allActive())
            : [];

        $categories = array_values(array_unique(array_filter(
            array_column($bonuses, 'category'),
            static fn($c) => $c !== null && $c !== ''
        )));

        jsonResponse([
            'access' => $access,
            'transaction_status' => $transaction->status ?? null,
            'bonuses' => $bonuses,
            'categories' => $categories,
        ]);
    }

    /**
     * GET /user/bonuses/{id}/download
     *
     * findActive(), bukan find(): bonus yang baru dinonaktifkan admin harus
     * berhenti bisa diunduh, bukan hanya berhenti tampil. Kalau endpoint ini
     * memakai find(), tautan unduh yang sudah tersimpan di riwayat browser
     * tetap bekerja setelah bonusnya dicabut.
     */
    public function downloadBonus(array $params): void
    {
        requireAuth();

        // Penjagaan yang sama seperti daftarnya, dan ini yang sebenarnya
        // menentukan. Tanpa pemeriksaan di sini, tautan unduh yang sempat
        // tersimpan di riwayat browser — atau id yang ditebak — tetap bekerja
        // meski transaksinya kemudian ditolak.
        if (Transaction::memberAccessState(Transaction::findActiveByUser(currentUser()->id)) !== 'granted') {
            jsonResponse(['error' => 'Bonus tersedia setelah transaksi Anda disetujui'], 403);
        }

        $bonus = Bonus::findActive((int) ($params['id'] ?? 0));
        if (!$bonus) jsonResponse(['error' => 'Bonus tidak ditemukan'], 404);

        if (!$bonus->isFile()) {
            // Bonus bertipe tautan: arahkan ke URL-nya. Skema sudah dibatasi
            // http/https saat disimpan, jadi Location tidak bisa berisi
            // javascript: atau data:.
            if (!$bonus->external_url) jsonResponse(['error' => 'Bonus tidak punya tautan'], 404);
            header('Location: ' . $bonus->external_url, true, 302);
            exit;
        }

        if (!$bonus->file_path) jsonResponse(['error' => 'Bonus tidak punya berkas'], 404);

        $dir = PORTAL_ROOT . '/storage/bonuses/';
        // basename() lalu realpath(): nilai dari database pun tidak dipercaya
        // menunjuk ke dalam folder ini.
        $real = realpath($dir . basename($bonus->file_path));
        $base = realpath($dir);
        if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            jsonResponse(['error' => 'Berkas bonus tidak ditemukan'], 404);
        }

        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        $name = $bonus->file_original_name ?: basename($real);

        header('Content-Type: ' . (self::MIME[$ext] ?? 'application/octet-stream'));
        header('Content-Length: ' . filesize($real));
        // attachment: bonus memang untuk diunduh, bukan ditonton di tab.
        // Ini juga yang menahan SVG agar tidak dirender sebagai halaman —
        // SVG bisa memuat skrip, dan inline berarti skrip itu berjalan di
        // origin portal.
        header('Content-Disposition: attachment; filename="' . self::safeName($name) . '"');
        header('Cache-Control: private, no-store');

        readfile($real);
        exit;
    }

    // =========================================================================
    // Sisi admin
    // =========================================================================

    public function adminBonuses(): void
    {
        requireAdmin();
        jsonResponse(['bonuses' => array_map(static fn(Bonus $b) => $b->toArray(), Bonus::all())]);
    }

    public function createBonus(): void
    {
        requireAdmin();

        $input = $_POST;
        $name = trim((string) ($input['name'] ?? ''));
        $sourceType = ($input['source_type'] ?? Bonus::SOURCE_LINK) === Bonus::SOURCE_FILE
            ? Bonus::SOURCE_FILE
            : Bonus::SOURCE_LINK;
        $url = self::nullableText($input['external_url'] ?? null);

        $error = self::validate($name, $sourceType, $url, isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK);
        if ($error) jsonResponse(['error' => $error], 400);

        try {
            // Unggahan diproses setelah validasi lolos supaya permintaan yang
            // ditolak tidak meninggalkan berkas yatim.
            $thumb = self::storeThumbnail();
            $file = $sourceType === Bonus::SOURCE_FILE ? self::storeFile() : null;

            $id = Bonus::create([
                'name' => $name,
                'category' => self::nullableText($input['category'] ?? null),
                'description' => self::nullableText($input['description'] ?? null),
                'thumbnail_path' => $thumb,
                'source_type' => $sourceType,
                'file_path' => $file['path'] ?? null,
                'file_original_name' => $file['name'] ?? null,
                'file_size' => $file['size'] ?? null,
                'external_url' => $sourceType === Bonus::SOURCE_LINK ? $url : null,
                'is_active' => isset($input['is_active']) ? (int) (bool) $input['is_active'] : 1,
                'sort_order' => (int) ($input['sort_order'] ?? 0),
            ]);

            jsonResponse(['message' => 'Bonus berhasil ditambahkan', 'bonus' => Bonus::find($id)?->toArray()], 201);
        } catch (Throwable $e) {
            self::failed($e, 'Gagal menambahkan bonus');
        }
    }

    public function updateBonus(array $params): void
    {
        requireAdmin();

        $bonus = Bonus::find((int) ($params['id'] ?? 0));
        if (!$bonus) jsonResponse(['error' => 'Bonus tidak ditemukan'], 404);

        $input = $_POST;
        $name = array_key_exists('name', $input) ? trim((string) $input['name']) : $bonus->name;
        $sourceType = array_key_exists('source_type', $input)
            ? (($input['source_type'] === Bonus::SOURCE_FILE) ? Bonus::SOURCE_FILE : Bonus::SOURCE_LINK)
            : $bonus->source_type;
        $url = array_key_exists('external_url', $input)
            ? self::nullableText($input['external_url'])
            : $bonus->external_url;

        $hasNewFile = isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK;
        // Berkas lama tetap dihitung sebagai "ada berkas" — mengubah nama bonus
        // tidak boleh menuntut unggah ulang.
        $error = self::validate($name, $sourceType, $url, $hasNewFile || ($bonus->isFile() && $bonus->file_path));
        if ($error) jsonResponse(['error' => $error], 400);

        try {
            $thumb = $bonus->thumbnail_path;
            $newThumb = self::storeThumbnail();
            if ($newThumb !== null) {
                self::deleteFile('thumbnails', $bonus->thumbnail_path);
                $thumb = $newThumb;
            } elseif (!empty($input['remove_thumbnail'])) {
                self::deleteFile('thumbnails', $bonus->thumbnail_path);
                $thumb = null;
            }

            $filePath = $bonus->file_path;
            $fileName = $bonus->file_original_name;
            $fileSize = $bonus->file_size;

            if ($sourceType === Bonus::SOURCE_FILE) {
                if ($hasNewFile) {
                    $stored = self::storeFile();
                    self::deleteFile('', $bonus->file_path);
                    $filePath = $stored['path'];
                    $fileName = $stored['name'];
                    $fileSize = $stored['size'];
                }
            } else {
                // Berpindah ke tipe tautan: berkasnya tidak lagi bisa dijangkau
                // siapa pun, jadi ikut dibuang daripada mengendap di storage.
                self::deleteFile('', $bonus->file_path);
                $filePath = $fileName = $fileSize = null;
            }

            $bonus->update([
                'name' => $name,
                'category' => array_key_exists('category', $input)
                    ? self::nullableText($input['category']) : $bonus->category,
                'description' => array_key_exists('description', $input)
                    ? self::nullableText($input['description']) : $bonus->description,
                'thumbnail_path' => $thumb,
                'source_type' => $sourceType,
                'file_path' => $filePath,
                'file_original_name' => $fileName,
                'file_size' => $fileSize,
                'external_url' => $sourceType === Bonus::SOURCE_LINK ? $url : null,
                'is_active' => isset($input['is_active'])
                    ? (int) (bool) $input['is_active'] : (int) $bonus->is_active,
                'sort_order' => isset($input['sort_order'])
                    ? (int) $input['sort_order'] : $bonus->sort_order,
            ]);

            jsonResponse([
                'message' => 'Bonus berhasil diperbarui',
                'bonus' => Bonus::find($bonus->id)?->toArray(),
            ]);
        } catch (Throwable $e) {
            self::failed($e, 'Gagal memperbarui bonus');
        }
    }

    public function deleteBonus(array $params): void
    {
        requireAdmin();

        $bonus = Bonus::find((int) ($params['id'] ?? 0));
        if (!$bonus) jsonResponse(['error' => 'Bonus tidak ditemukan'], 404);

        try {
            // Baris hilang tanpa berkasnya ikut hilang berarti storage terus
            // tumbuh oleh berkas yang tidak lagi bisa dijangkau siapa pun.
            self::deleteFile('', $bonus->file_path);
            self::deleteFile('thumbnails', $bonus->thumbnail_path);
            $bonus->delete();

            jsonResponse(['message' => 'Bonus berhasil dihapus']);
        } catch (Throwable $e) {
            self::failed($e, 'Gagal menghapus bonus');
        }
    }

    // =========================================================================
    // Pembantu
    // =========================================================================

    private static function validate(string $name, string $sourceType, ?string $url, bool $hasFile): ?string
    {
        if ($name === '') return 'Nama bonus wajib diisi';
        if (mb_strlen($name) > 255) return 'Nama bonus maksimal 255 karakter';

        if ($sourceType === Bonus::SOURCE_FILE) {
            return $hasFile ? null : 'Berkas bonus wajib diunggah untuk tipe Upload File';
        }

        if ($url === null) return 'Link bonus wajib diisi untuk tipe Link';
        if (mb_strlen($url) > 500) return 'Link bonus maksimal 500 karakter';

        if (!filter_var($url, FILTER_VALIDATE_URL)) return 'Link bonus tidak valid';

        // Hanya http/https. Tanpa ini admin bisa menyimpan javascript:… yang
        // lalu dipasang pada href — satu baris database jadi XSS bagi setiap
        // orang yang mengklik tombol Unduh.
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return 'Link bonus harus diawali http:// atau https://';
        }

        return null;
    }

    /** Menyimpan thumbnail, atau null bila tidak ada unggahan. */
    private static function storeThumbnail(): ?string
    {
        if (!isset($_FILES['thumbnail']) || $_FILES['thumbnail']['error'] !== UPLOAD_ERR_OK) return null;

        $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, self::THUMB_EXT, true)) {
            jsonResponse(['error' => 'Thumbnail harus JPG, PNG, GIF, atau WEBP'], 400);
        }
        if ($_FILES['thumbnail']['size'] > self::MAX_THUMB) {
            jsonResponse(['error' => 'Ukuran thumbnail maksimal 2MB'], 400);
        }

        // MIME dibaca dari isi berkas, bukan dari header yang dikirim klien —
        // header itu bisa ditulis apa saja.
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['thumbnail']['tmp_name']);
        finfo_close($finfo);
        if (!str_starts_with((string) $mime, 'image/')) {
            jsonResponse(['error' => 'Thumbnail bukan gambar yang valid'], 400);
        }

        // Folder terpisah, BUKAN subfolder storage/bonuses. Folder itu
        // ditolak seluruhnya, dan mengizinkan kembali satu subfolder di
        // dalamnya menuntut berkas aturan yang justru tidak bisa ikut
        // ter-commit — git tidak dapat memasukkan kembali isi direktori
        // yang sudah dikecualikan .gitignore.
        $dir = PORTAL_ROOT . '/storage/bonus-thumbnails/';
        self::ensureDir($dir);

        $filename = 'bonus_thumb_' . uniqid('', true) . '.' . $ext;
        if (!move_uploaded_file($_FILES['thumbnail']['tmp_name'], $dir . $filename)) {
            jsonResponse(['error' => 'Gagal menyimpan thumbnail'], 500);
        }
        return $filename;
    }

    /** Menyimpan berkas bonus. Mengembalikan path, nama asli, dan ukuran. */
    private static function storeFile(): array
    {
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['error' => 'Berkas bonus gagal diunggah'], 400);
        }

        $original = (string) $_FILES['file']['name'];
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, self::FILE_EXT, true)) {
            jsonResponse(['error' => 'Format berkas tidak didukung: .' . $ext], 400);
        }
        if ($_FILES['file']['size'] > self::MAX_FILE) {
            jsonResponse(['error' => 'Ukuran berkas maksimal 50MB'], 400);
        }

        $dir = PORTAL_ROOT . '/storage/bonuses/';
        self::ensureDir($dir);

        // Nama berkas dibuat sendiri; nama dari klien hanya disimpan sebagai
        // label untuk unduhan, tidak pernah menyentuh filesystem.
        $filename = 'bonus_' . uniqid('', true) . '.' . $ext;
        if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir . $filename)) {
            jsonResponse(['error' => 'Gagal menyimpan berkas bonus'], 500);
        }

        return [
            'path' => $filename,
            'name' => mb_substr($original, 0, 255),
            'size' => (int) $_FILES['file']['size'],
        ];
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            jsonResponse(['error' => 'Gagal menyiapkan folder penyimpanan'], 500);
        }
    }

    /**
     * Menghapus berkas milik bonus.
     *
     * $sub 'thumbnails' menunjuk storage/bonus-thumbnails, bukan subfolder di
     * dalam storage/bonuses — keduanya sengaja terpisah karena yang satu
     * ditolak akses langsung dan yang lain justru harus terbaca <img>.
     */
    private static function deleteFile(string $sub, ?string $path): void
    {
        if (!$path) return;

        $dir = PORTAL_ROOT . ($sub === 'thumbnails' ? '/storage/bonus-thumbnails/' : '/storage/bonuses/');
        $file = $dir . basename($path);
        if (is_file($file)) unlink($file);
    }

    private static function nullableText($value): ?string
    {
        if ($value === null) return null;
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    private static function safeName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name)) ?: 'bonus';
    }

    /**
     * Sama seperti di CommunityController: kegagalan yang paling mungkin di
     * sini adalah migrasi yang belum dijalankan, dan itu hal yang bisa
     * diperbaiki sendiri kalau disebutkan namanya.
     */
    private static function failed(Throwable $e, string $fallback): void
    {
        error_log('[BonusController] ' . $fallback . ' — ' . $e->getMessage());

        $sqlState = ($e instanceof PDOException && isset($e->errorInfo[0])) ? $e->errorInfo[0] : null;
        $message = $e->getMessage();

        if ($sqlState === '42S02' || str_contains($message, "doesn't exist")
            || $sqlState === '42S22' || str_contains($message, 'Unknown column')) {
            jsonResponse([
                'error' => 'Tabel bonus belum ada. Jalankan '
                    . 'database/migrations/2026_08_25_bonuses.sql, lalu coba lagi.',
            ], 500);
        }

        jsonResponse(['error' => $fallback], 500);
    }
}
