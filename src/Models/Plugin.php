<?php
/**
 * Plugin — SATU VERSI dari sebuah plugin, bukan pluginnya.
 *
 * Nama kelas dan tabelnya (`plugins`) sudah begitu sejak sebelum plugin dan
 * versinya dipisah; identitas pluginnya kini ada di PluginProduct. Lihat
 * database/migrations/2026_08_25_plugin_versions.sql.
 *
 * YANG BERUBAH DAN KENAPA
 * create() dulu menjalankan `UPDATE plugins SET is_active = 0` sebelum
 * menyisipkan, dan update() melakukan hal serupa. Artinya is_active dipakai
 * sebagai "ini versi yang berlaku" — hanya satu baris yang boleh menyala. Dan
 * karena getLatest() menyaring is_active = 1, setiap versi lama langsung
 * hilang dari jangkauan user begitu versi baru diunggah.
 *
 * Sekarang is_active berarti apa adanya: versi ini tersedia diunduh atau
 * tidak. Menandai satu versi tidak lagi memengaruhi versi lain. "Versi
 * terbaru" ditentukan version_sort, bukan oleh mematikan yang lain.
 */

class Plugin
{
    public int $id;
    public ?int $plugin_id;
    public string $version;
    public ?string $version_sort;
    public string $filename;
    public string $file_path;
    public ?string $changelog;
    public ?string $released_at;
    public bool $is_active;
    public string $created_at;

    /** Lebar tiap ruas pada kunci urut. Harus sama dengan LPAD(...,5,'0') di migration. */
    private const SORT_PAD = 5;
    private const SORT_PARTS = 4;

    /**
     * Kunci urut versi.
     *
     * Perbandingan teks biasa salah: '1.0.9' > '1.0.10' secara leksikografis.
     * Tiap ruas diambil angkanya lalu dipadkan nol, sehingga urutan string
     * sama dengan urutan angka.
     *
     * Ruas yang tidak ada bernilai nol — '1.0' menjadi 1.0.0.0 — supaya
     * '1.0.10' tidak pernah terhitung lebih baru daripada '1.0.10.1'.
     *
     * Rumus ini dicerminkan di migration untuk mengisi baris lama; keduanya
     * harus menghasilkan nilai yang sama persis.
     */
    public static function sortKey(string $version): string
    {
        $parts = explode('.', trim($version));
        $out = [];

        for ($i = 0; $i < self::SORT_PARTS; $i++) {
            // (int) pada '3-beta' menghasilkan 3, sama seperti CAST AS UNSIGNED
            // di MariaDB — jadi akhiran non-angka tidak menggagalkan urutan.
            $n = isset($parts[$i]) ? (int) $parts[$i] : 0;
            $out[] = str_pad((string) max(0, $n), self::SORT_PAD, '0', STR_PAD_LEFT);
        }

        return implode('.', $out);
    }

    public static function find(int $id): ?self
    {
        $row = Database::fetchOne("SELECT * FROM plugins WHERE id = ?", [$id]);
        return $row ? self::fromArray($row) : null;
    }

    /**
     * Versi terbaru sebuah plugin.
     *
     * Diurut version_sort, BUKAN created_at: admin bisa saja menambahkan versi
     * lama belakangan, dan urutan unggah tidak sama dengan urutan rilis.
     */
    public static function latestFor(int $pluginId): ?self
    {
        $row = Database::fetchOne(
            "SELECT * FROM plugins
             WHERE plugin_id = ? AND is_active = 1
             ORDER BY version_sort DESC, id DESC
             LIMIT 1",
            [$pluginId]
        );
        return $row ? self::fromArray($row) : null;
    }

    /** Seluruh versi sebuah plugin, terbaru lebih dulu. $activeOnly untuk sisi user. */
    public static function forProduct(int $pluginId, bool $activeOnly = false): array
    {
        $sql = "SELECT * FROM plugins WHERE plugin_id = ?";
        if ($activeOnly) $sql .= " AND is_active = 1";
        $sql .= " ORDER BY version_sort DESC, id DESC";

        return array_map([self::class, 'fromArray'], Database::fetchAll($sql, [$pluginId]));
    }

    /**
     * Apakah nomor versi ini sudah dipakai pada plugin yang sama?
     *
     * Dulu tidak ada pemeriksaan ini sama sekali. Karena nama berkas diturunkan
     * dari nomor versi, mengunggah versi yang sama dua kali menimpa berkasnya
     * DAN menyisipkan baris kedua — dua baris menunjuk satu berkas. Menghapus
     * salah satunya lalu menghapus berkas milik keduanya.
     */
    public static function versionExists(int $pluginId, string $version, ?int $exceptId = null): bool
    {
        $sql = "SELECT COUNT(*) AS c FROM plugins WHERE plugin_id = ? AND version = ?";
        $params = [$pluginId, $version];

        if ($exceptId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }

        return (int) (Database::fetchOne($sql, $params)['c'] ?? 0) > 0;
    }

    public static function countFor(int $pluginId): int
    {
        return (int) (Database::fetchOne(
            "SELECT COUNT(*) AS c FROM plugins WHERE plugin_id = ?",
            [$pluginId]
        )['c'] ?? 0);
    }

    /**
     * Versi terbaru dari plugin bawaan.
     *
     * Dipertahankan supaya kode lama yang memanggilnya tetap jalan tanpa
     * diubah. Dulu ia mengambil baris aktif mana pun yang paling baru dibuat;
     * kini ia mengambil versi tertinggi dari plugin bawaan.
     */
    public static function getLatest(): ?self
    {
        $product = PluginProduct::findBySlug(PluginProduct::DEFAULT_SLUG);

        if ($product) {
            $latest = self::latestFor($product->id);
            if ($latest) return $latest;
        }

        // Cadangan untuk database yang migrationnya belum dijalankan: tanpa
        // kolom version_sort, urutan rilis adalah satu-satunya yang tersedia.
        $row = Database::fetchOne(
            "SELECT * FROM plugins WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1"
        );
        return $row ? self::fromArray($row) : null;
    }

    public static function all(): array
    {
        $rows = Database::fetchAll("SELECT * FROM plugins ORDER BY version_sort DESC, id DESC");
        return array_map([self::class, 'fromArray'], $rows);
    }

    /**
     * Simpan satu versi baru.
     *
     * Tidak ada lagi `UPDATE plugins SET is_active = 0` di sini. Versi lain
     * dibiarkan apa adanya — itulah yang membuat riwayat versi bertahan.
     */
    public static function create(array $data): int
    {
        return Database::insert('plugins', [
            'plugin_id' => $data['plugin_id'],
            'version' => $data['version'],
            'version_sort' => self::sortKey($data['version']),
            'filename' => $data['filename'],
            'file_path' => $data['file_path'],
            'changelog' => $data['changelog'] ?? null,
            'released_at' => $data['released_at'] ?? date('Y-m-d H:i:s'),
            'is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function update(array $data): bool
    {
        $fields = [];

        if (array_key_exists('version', $data)) {
            $fields['version'] = $data['version'];
            // Kunci urut selalu ikut nomor versinya; kalau tertinggal, urutan
            // "versi terbaru" akan memakai nilai lama tanpa terlihat salah.
            $fields['version_sort'] = self::sortKey($data['version']);
        }

        foreach (['filename', 'file_path', 'changelog', 'released_at'] as $key) {
            if (array_key_exists($key, $data)) $fields[$key] = $data[$key];
        }

        // Tidak ada efek samping ke baris lain. Menonaktifkan satu versi hanya
        // menyembunyikan versi itu.
        if (array_key_exists('is_active', $data)) {
            $fields['is_active'] = (int) (bool) $data['is_active'];
        }

        if (!$fields) return false;

        return Database::update('plugins', $fields, 'id = ?', [$this->id]) > 0;
    }

    public function delete(): bool
    {
        return Database::delete('plugins', 'id = ?', [$this->id]) > 0;
    }

    /**
     * Apakah berkas versi ini masih dipakai baris lain?
     *
     * Database lama bisa punya dua baris dengan nomor versi sama yang menunjuk
     * satu berkas. Menghapus salah satunya tidak boleh menghapus berkas yang
     * masih dipegang baris lain.
     */
    public function fileSharedWithOthers(): bool
    {
        return (int) (Database::fetchOne(
            "SELECT COUNT(*) AS c FROM plugins WHERE filename = ? AND id != ?",
            [$this->filename, $this->id]
        )['c'] ?? 0) > 0;
    }

    public static function fromArray(array $row): self
    {
        $p = new self();
        $p->id = (int) $row['id'];
        $p->plugin_id = isset($row['plugin_id']) ? (int) $row['plugin_id'] : null;
        $p->version = $row['version'];
        $p->version_sort = $row['version_sort'] ?? null;
        $p->filename = $row['filename'];
        $p->file_path = $row['file_path'];
        $p->changelog = $row['changelog'] ?? null;
        $p->released_at = $row['released_at'] ?? ($row['created_at'] ?? null);
        $p->is_active = (bool) $row['is_active'];
        $p->created_at = $row['created_at'];
        return $p;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'plugin_id' => $this->plugin_id,
            'version' => $this->version,
            'filename' => $this->filename,
            'changelog' => $this->changelog,
            'released_at' => $this->released_at,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
        ];
    }
}
