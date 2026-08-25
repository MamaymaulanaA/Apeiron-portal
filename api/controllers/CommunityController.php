<?php
/**
 * Community API Controller
 *
 * Dua fitur, satu berkas: Group (grup komunitas) dan Forum (tutorial).
 *
 * Sengaja terpisah dari UserController dan AdminController yang sudah berjalan.
 * Keduanya menangani autentikasi, transaksi, lisensi, dan unggahan — hal-hal
 * yang tidak boleh ikut berubah hanya karena ada dua fitur baru. Berkas baru
 * berarti tidak ada satu baris pun di jalur itu yang tersentuh.
 */

class CommunityController
{
    // =========================================================================
    // Group — sisi user
    // =========================================================================

    /**
     * GET /user/groups
     *
     * Gerbangnya di sini, di server, bukan di tampilan. Ketika akses belum
     * terbuka, respons TIDAK memuat satu pun link — daftar grupnya kosong,
     * bukan disembunyikan dengan CSS. Membuka DevTools, mengubah state Vue,
     * atau memanggil endpoint ini langsung sama-sama tidak menghasilkan link.
     */
    public function userGroups(): void
    {
        requireAuth();
        $user = currentUser();

        $transaction = Transaction::findActiveByUser($user->id);
        $access = self::accessStateFor($transaction);

        // Hanya keadaan 'granted' yang memuat data grup.
        $groups = [];
        if ($access === 'granted') {
            $groups = array_map(
                static fn(CommunityGroup $g) => $g->toArray(),
                CommunityGroup::allActive()
            );
        }

        jsonResponse([
            'access' => $access,
            'transaction_status' => $transaction->status ?? null,
            'admin_notes' => $access === 'rejected' ? ($transaction->admin_notes ?? null) : null,
            'groups' => $groups,
        ]);
    }

    /**
     * Menerjemahkan transaksi terakhir user menjadi keadaan akses.
     *
     *   none      — belum pernah mengunggah bukti transaksi
     *   pending   — sudah mengunggah, masih menunggu verifikasi admin
     *   rejected  — ditolak admin, perlu unggah ulang
     *   granted   — sudah diverifikasi admin
     *
     * 'granted' mencakup pending_license, bukan hanya approved. Pada status itu
     * admin SUDAH memverifikasi pembayaran — setPendingLicense() mengisi
     * approved_by — dan yang belum selesai hanyalah penerbitan lisensi karena
     * server lisensi sedang tidak bisa dihubungi. Menahan akses grup karena
     * gangguan di sisi kami menghukum user atas hal yang bukan urusannya.
     * Ini memang berbeda dari can_download, yang menahan berkas plugin sampai
     * lisensinya benar-benar terbit; grup bukan aset berlisensi.
     */
    private static function accessStateFor(?Transaction $transaction): string
    {
        if (!$transaction) return 'none';
        if ($transaction->isApproved() || $transaction->isPendingLicense()) return 'granted';
        if ($transaction->isRejected()) return 'rejected';
        return 'pending';
    }

    // =========================================================================
    // Group — sisi admin
    // =========================================================================

    public function adminGroups(): void
    {
        requireAdmin();

        jsonResponse([
            'groups' => array_map(
                static fn(CommunityGroup $g) => $g->toArray(),
                CommunityGroup::all()
            ),
        ]);
    }

    public function createGroup(): void
    {
        requireAdmin();

        $input = self::input();
        $name = trim((string) ($input['name'] ?? ''));
        $link = trim((string) ($input['link'] ?? ''));

        $error = self::validateGroup($name, $link);
        if ($error) jsonResponse(['error' => $error], 400);

        try {
            $id = CommunityGroup::create([
                'name' => $name,
                'description' => self::nullableText($input['description'] ?? null),
                'link' => $link,
                'is_active' => isset($input['is_active']) ? (int) (bool) $input['is_active'] : 1,
                'sort_order' => (int) ($input['sort_order'] ?? 0),
            ]);

            $group = CommunityGroup::find($id);
            jsonResponse(['message' => 'Group berhasil ditambahkan', 'group' => $group?->toArray()], 201);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal menambahkan group'], 500);
        }
    }

    public function updateGroup(array $params): void
    {
        requireAdmin();

        $group = CommunityGroup::find((int) ($params['id'] ?? 0));
        if (!$group) jsonResponse(['error' => 'Group tidak ditemukan'], 404);

        $input = self::input();
        $name = array_key_exists('name', $input) ? trim((string) $input['name']) : $group->name;
        $link = array_key_exists('link', $input) ? trim((string) $input['link']) : $group->link;

        $error = self::validateGroup($name, $link);
        if ($error) jsonResponse(['error' => $error], 400);

        try {
            $group->update([
                'name' => $name,
                'description' => array_key_exists('description', $input)
                    ? self::nullableText($input['description'])
                    : $group->description,
                'link' => $link,
                'is_active' => isset($input['is_active'])
                    ? (int) (bool) $input['is_active']
                    : (int) $group->is_active,
                'sort_order' => isset($input['sort_order'])
                    ? (int) $input['sort_order']
                    : $group->sort_order,
            ]);

            jsonResponse([
                'message' => 'Group berhasil diperbarui',
                'group' => CommunityGroup::find($group->id)?->toArray(),
            ]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal memperbarui group'], 500);
        }
    }

    public function deleteGroup(array $params): void
    {
        requireAdmin();

        $group = CommunityGroup::find((int) ($params['id'] ?? 0));
        if (!$group) jsonResponse(['error' => 'Group tidak ditemukan'], 404);

        try {
            $group->delete();
            jsonResponse(['message' => 'Group berhasil dihapus']);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal menghapus group'], 500);
        }
    }

    // =========================================================================
    // Forum / tutorial — sisi user
    // =========================================================================

    /**
     * GET /user/tutorials
     *
     * Tidak digerbangi transaksi: panduan pemasangan justru paling dibutuhkan
     * orang yang sedang menimbang atau baru memulai.
     */
    public function userTutorials(): void
    {
        requireAuth();

        $tutorials = array_map(
            static fn(Tutorial $t) => $t->toCard(),
            Tutorial::allPublished()
        );

        // Kategori dikirim sekalian supaya frontend tidak perlu menebaknya dari
        // daftar, dan penyaringnya tetap benar saat daftarnya kosong.
        $categories = array_values(array_unique(array_filter(
            array_column($tutorials, 'category'),
            static fn($c) => $c !== null && $c !== ''
        )));

        jsonResponse(['tutorials' => $tutorials, 'categories' => $categories]);
    }

    /** GET /user/tutorials/{id} — isi lengkap satu tutorial. */
    public function userTutorialDetail(array $params): void
    {
        requireAuth();

        $tutorial = Tutorial::findPublished((int) ($params['id'] ?? 0));
        if (!$tutorial) jsonResponse(['error' => 'Tutorial tidak ditemukan'], 404);

        jsonResponse(['tutorial' => self::withSteps($tutorial)]);
    }

    // =========================================================================
    // Forum / tutorial — sisi admin
    // =========================================================================

    public function adminTutorials(): void
    {
        requireAdmin();

        jsonResponse([
            'tutorials' => array_map(
                static fn(Tutorial $t) => self::withSteps($t),
                Tutorial::all()
            ),
            'icons' => Tutorial::ICONS,
        ]);
    }

    public function createTutorial(): void
    {
        requireAdmin();

        $input = self::input();
        $title = trim((string) ($input['title'] ?? ''));

        $error = self::validateTutorial($title, $input['external_url'] ?? null);
        if ($error) jsonResponse(['error' => $error], 400);

        try {
            // Unggahan diproses SETELAH validasi lolos, supaya permintaan yang
            // ditolak tidak meninggalkan berkas yatim di storage.
            $imagePath = self::storeImage();

            $id = Tutorial::create([
                'title' => $title,
                'excerpt' => self::nullableText($input['excerpt'] ?? null),
                'content' => self::nullableText($input['content'] ?? null),
                'category' => self::nullableText($input['category'] ?? null),
                'icon' => $input['icon'] ?? null,
                'image_path' => $imagePath,
                'external_url' => self::nullableText($input['external_url'] ?? null),
                'is_published' => isset($input['is_published']) ? (int) (bool) $input['is_published'] : 1,
                'sort_order' => (int) ($input['sort_order'] ?? 0),
            ]);

            self::saveSteps($id, []);

            $created = Tutorial::find($id);
            jsonResponse([
                'message' => 'Tutorial berhasil ditambahkan',
                'tutorial' => $created ? self::withSteps($created) : null,
            ], 201);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal menambahkan tutorial'], 500);
        }
    }

    public function updateTutorial(array $params): void
    {
        requireAdmin();

        $tutorial = Tutorial::find((int) ($params['id'] ?? 0));
        if (!$tutorial) jsonResponse(['error' => 'Tutorial tidak ditemukan'], 404);

        $input = self::input();
        $title = array_key_exists('title', $input) ? trim((string) $input['title']) : $tutorial->title;
        $url = array_key_exists('external_url', $input) ? $input['external_url'] : $tutorial->external_url;

        $error = self::validateTutorial($title, $url);
        if ($error) jsonResponse(['error' => $error], 400);

        try {
            // Tiga kemungkinan pada gambar, dan ketiganya berbeda:
            //   berkas baru diunggah → simpan, hapus yang lama
            //   remove_image dikirim → hapus berkas, kosongkan kolom
            //   tidak keduanya      → biarkan apa adanya
            $imagePath = $tutorial->image_path;
            $newImage = self::storeImage();
            if ($newImage !== null) {
                self::deleteImageFile($tutorial->image_path);
                $imagePath = $newImage;
            } elseif (!empty($input['remove_image'])) {
                self::deleteImageFile($tutorial->image_path);
                $imagePath = null;
            }

            $tutorial->update([
                'title' => $title,
                'excerpt' => array_key_exists('excerpt', $input)
                    ? self::nullableText($input['excerpt']) : $tutorial->excerpt,
                'content' => array_key_exists('content', $input)
                    ? self::nullableText($input['content']) : $tutorial->content,
                'category' => array_key_exists('category', $input)
                    ? self::nullableText($input['category']) : $tutorial->category,
                'icon' => $input['icon'] ?? $tutorial->icon,
                'image_path' => $imagePath,
                'external_url' => self::nullableText($url),
                'is_published' => isset($input['is_published'])
                    ? (int) (bool) $input['is_published'] : (int) $tutorial->is_published,
                'sort_order' => isset($input['sort_order'])
                    ? (int) $input['sort_order'] : $tutorial->sort_order,
            ]);

            self::saveSteps($tutorial->id, TutorialStep::forTutorial($tutorial->id));

            $saved = Tutorial::find($tutorial->id);
            jsonResponse([
                'message' => 'Tutorial berhasil diperbarui',
                'tutorial' => $saved ? self::withSteps($saved) : null,
            ]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal memperbarui tutorial'], 500);
        }
    }

    public function deleteTutorial(array $params): void
    {
        requireAdmin();

        $tutorial = Tutorial::find((int) ($params['id'] ?? 0));
        if (!$tutorial) jsonResponse(['error' => 'Tutorial tidak ditemukan'], 404);

        try {
            // Baris hilang tanpa berkasnya ikut hilang berarti storage terus
            // tumbuh oleh gambar yang tidak lagi bisa dijangkau siapa pun.
            // Gambar langkah harus didata SEBELUM baris induknya dihapus:
            // ON DELETE CASCADE akan menghapus barisnya, dan setelah itu tidak
            // ada lagi yang tahu berkas mana yang perlu dibuang.
            foreach (TutorialStep::imagePathsFor($tutorial->id) as $stepImage) {
                self::deleteImageFile($stepImage);
            }
            self::deleteImageFile($tutorial->image_path);
            $tutorial->delete();
            jsonResponse(['message' => 'Tutorial berhasil dihapus']);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal menghapus tutorial'], 500);
        }
    }

    // =========================================================================
    // Pembantu
    // =========================================================================

    /**
     * Menerima JSON maupun form-encoded. Frontend mengirim JSON; menerima
     * $_POST juga membuat endpoint ini bisa diuji cepat dari curl atau form
     * biasa tanpa perlu menyusun body JSON.
     */
    private static function input(): array
    {
        $json = getJsonInput();
        return $json ?: $_POST;
    }

    /** Tutorial beserta langkah-langkahnya. */
    private static function withSteps(Tutorial $tutorial): array
    {
        return $tutorial->toArray() + [
            'steps' => array_map(
                static fn(TutorialStep $s) => $s->toArray(),
                TutorialStep::forTutorial($tutorial->id)
            ),
        ];
    }

    /**
     * Menulis ulang seluruh langkah sebuah tutorial dari field `steps`.
     *
     * BENTUK YANG DIHARAPKAN
     * `steps` adalah string JSON berisi array objek:
     *   [{ body, image_path, image_action }]
     * dengan image_action salah satu dari:
     *   keep    — pertahankan image_path yang dikirim
     *   remove  — kosongkan gambarnya
     *   replace — pakai berkas dari $_FILES['step_image_<i>']
     *
     * KENAPA HAPUS-LALU-TULIS-ULANG, BUKAN DIFF PER BARIS
     * Langkah tidak punya makna sendiri di luar urutannya; yang dikirim admin
     * adalah keadaan akhir yang ia inginkan. Mencocokkan baris satu per satu
     * hanya menambah cabang yang bisa salah, tanpa memberi apa pun. Yang tetap
     * harus hati-hati adalah berkas: gambar yang tidak lagi dipakai dihapus,
     * dan gambar yang masih dipakai TIDAK boleh ikut terhapus.
     *
     * $existing dipakai untuk mencari selisihnya. Saat membuat tutorial baru ia
     * kosong, jadi tidak ada yang perlu dibandingkan.
     */
    private static function saveSteps(int $tutorialId, array $existing): void
    {
        $raw = $_POST['steps'] ?? null;
        if ($raw === null) return;

        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) return;

        $kept = [];
        $rows = [];

        foreach (array_values($decoded) as $i => $step) {
            if (!is_array($step)) continue;

            $body = self::nullableText($step['body'] ?? null);
            $action = $step['image_action'] ?? 'keep';
            $image = null;

            if ($action === 'replace') {
                $image = self::storeImage('step_image_' . $i);
            } elseif ($action === 'keep') {
                // basename() supaya nilai dari klien tidak bisa menunjuk ke
                // luar folder storage.
                $candidate = self::nullableText($step['image_path'] ?? null);
                $image = $candidate ? basename($candidate) : null;
            }

            // Langkah tanpa teks DAN tanpa gambar tidak menyampaikan apa pun.
            if ($body === null && $image === null) continue;

            if ($image !== null) $kept[] = $image;
            $rows[] = ['body' => $body, 'image_path' => $image, 'position' => count($rows)];
        }

        TutorialStep::deleteForTutorial($tutorialId);
        foreach ($rows as $row) {
            TutorialStep::create($row + ['tutorial_id' => $tutorialId]);
        }

        // Berkas lama yang tidak lagi dirujuk langkah mana pun.
        foreach ($existing as $old) {
            if ($old->image_path && !in_array($old->image_path, $kept, true)) {
                self::deleteImageFile($old->image_path);
            }
        }
    }

    /**
     * Menyimpan berkas dari field yang disebut, atau null bila tidak ada unggahan.
     *
     * Pemeriksaannya sama persis dengan thumbnail template yang sudah berjalan:
     * ekstensi dari daftar tertutup, batas 2MB, dan MIME diperiksa dari isi
     * berkas — bukan dari header yang dikirim klien, yang bisa ditulis apa saja.
     * Nama berkas dibuat sendiri, tidak pernah memakai nama asli dari klien.
     */
    private static function storeImage(string $field = 'image'): ?string
    {
        if (!isset($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            jsonResponse(['error' => 'Gambar harus berformat JPG, PNG, GIF, atau WEBP'], 400);
        }

        if ($_FILES[$field]['size'] > 2 * 1024 * 1024) {
            jsonResponse(['error' => 'Ukuran gambar maksimal 2MB'], 400);
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMime = finfo_file($finfo, $_FILES[$field]['tmp_name']);
        finfo_close($finfo);
        if (!str_starts_with((string) $realMime, 'image/')) {
            jsonResponse(['error' => 'File bukan gambar yang valid'], 400);
        }

        $dir = PORTAL_ROOT . '/storage/tutorials/';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            jsonResponse(['error' => 'Gagal menyiapkan folder penyimpanan'], 500);
        }

        $filename = 'tut_' . uniqid('', true) . '.' . $ext;
        if (!move_uploaded_file($_FILES[$field]['tmp_name'], $dir . $filename)) {
            jsonResponse(['error' => 'Gagal menyimpan gambar'], 500);
        }

        return $filename;
    }

    /**
     * Menghapus berkas gambar milik satu tutorial.
     *
     * basename() memastikan nilai dari database hanya pernah diperlakukan
     * sebagai nama berkas. Tanpa itu, isi kolom seperti ../../config/app.php
     * akan menghapus berkas di luar folder storage.
     */
    private static function deleteImageFile(?string $path): void
    {
        if (!$path) return;

        $file = PORTAL_ROOT . '/storage/tutorials/' . basename($path);
        if (is_file($file)) unlink($file);
    }

    /** String kosong disimpan sebagai NULL, bukan ''. */
    private static function nullableText($value): ?string
    {
        if ($value === null) return null;
        $trimmed = trim((string) $value);
        return $trimmed === '' ? null : $trimmed;
    }

    private static function validateGroup(string $name, string $link): ?string
    {
        if ($name === '') return 'Nama group wajib diisi';
        if (mb_strlen($name) > 255) return 'Nama group maksimal 255 karakter';
        if ($link === '') return 'Link group wajib diisi';
        if (mb_strlen($link) > 500) return 'Link group maksimal 500 karakter';
        return self::validateUrl($link, 'Link group');
    }

    private static function validateTutorial(string $title, $url): ?string
    {
        if ($title === '') return 'Judul tutorial wajib diisi';
        if (mb_strlen($title) > 255) return 'Judul maksimal 255 karakter';

        $url = self::nullableText($url);
        if ($url === null) return null;
        if (mb_strlen($url) > 500) return 'Link maksimal 500 karakter';
        return self::validateUrl($url, 'Link');
    }

    /**
     * Hanya http dan https. Tanpa pemeriksaan skema, admin bisa menyimpan
     * `javascript:...` yang lalu dipasang pada href di dashboard user — satu
     * baris database menjadi XSS bagi setiap orang yang mengklik kartunya.
     */
    private static function validateUrl(string $url, string $label): ?string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $label . ' tidak valid';
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return $label . ' harus diawali http:// atau https://';
        }

        return null;
    }
}
