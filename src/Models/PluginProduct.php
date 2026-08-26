<?php
/**
 * PluginProduct — identitas plugin, bukan rilisnya.
 *
 * Satu plugin punya banyak versi. Versinya disimpan di tabel `plugins`, yang
 * namanya sudah begitu sejak sebelum ada pemisahan ini; lihat catatan di
 * database/migrations/2026_08_25_plugin_versions.sql.
 *
 * `slug` adalah penghubung ke lisensi: user_licenses.product_id menyimpan slug
 * yang sama. Lisensi menunjuk PLUGIN, tidak pernah menunjuk versi — supaya
 * merilis versi baru tidak menyentuh lisensi siapa pun.
 */

class PluginProduct
{
    public int $id;
    public string $name;
    public string $slug;
    public ?string $description;
    public bool $is_active;
    public int $sort_order;
    public string $created_at;
    public ?string $updated_at;

    /** Slug plugin bawaan, sama dengan product_id default pada lisensi. */
    public const DEFAULT_SLUG = 'apeiron-kit';

    public static function find(int $id): ?self
    {
        $row = Database::fetchOne("SELECT * FROM plugin_products WHERE id = ?", [$id]);
        return $row ? self::fromArray($row) : null;
    }

    public static function findBySlug(string $slug): ?self
    {
        $row = Database::fetchOne("SELECT * FROM plugin_products WHERE slug = ?", [$slug]);
        return $row ? self::fromArray($row) : null;
    }

    /** Semua plugin, aktif maupun tidak — untuk halaman admin. */
    public static function all(): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM plugin_products ORDER BY sort_order ASC, name ASC"
        );
        return array_map([self::class, 'fromArray'], $rows);
    }

    /** Hanya yang aktif — untuk sisi user. */
    public static function allActive(): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM plugin_products WHERE is_active = 1 ORDER BY sort_order ASC, name ASC"
        );
        return array_map([self::class, 'fromArray'], $rows);
    }

    /**
     * Ubah nama jadi slug.
     *
     * Slug dipakai sebagai kunci unik dan dicocokkan dengan product_id lisensi,
     * jadi ia harus stabil dan tidak mengandung spasi atau huruf besar.
     */
    public static function slugify(string $text): string
    {
        $slug = strtolower(trim($text));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }

    /** Apakah slug ini sudah dipakai plugin lain? */
    public static function slugTaken(string $slug, ?int $exceptId = null): bool
    {
        $sql = "SELECT COUNT(*) AS c FROM plugin_products WHERE slug = ?";
        $params = [$slug];

        if ($exceptId !== null) {
            $sql .= " AND id != ?";
            $params[] = $exceptId;
        }

        return (int) (Database::fetchOne($sql, $params)['c'] ?? 0) > 0;
    }

    public static function create(array $data): int
    {
        return Database::insert('plugin_products', [
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_active' => isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function update(array $data): bool
    {
        $fields = [];
        foreach (['name', 'slug', 'description', 'sort_order'] as $key) {
            if (array_key_exists($key, $data)) $fields[$key] = $data[$key];
        }
        if (array_key_exists('is_active', $data)) {
            $fields['is_active'] = (int) (bool) $data['is_active'];
        }

        if (!$fields) return false;

        return Database::update('plugin_products', $fields, 'id = ?', [$this->id]) > 0;
    }

    /**
     * Hapus plugin beserta seluruh versinya.
     *
     * Versinya dihapus di sini, bukan lewat foreign key cascade: migration
     * sengaja tidak memasang FK karena database yang sudah berjalan bisa punya
     * baris lama dengan plugin_id yang tidak cocok, dan migration tidak boleh
     * gagal karenanya. Berkasnya dibersihkan controller sebelum memanggil ini.
     */
    public function delete(): bool
    {
        Database::delete('plugins', 'plugin_id = ?', [$this->id]);
        return Database::delete('plugin_products', 'id = ?', [$this->id]) > 0;
    }

    public static function fromArray(array $row): self
    {
        $p = new self();
        $p->id = (int) $row['id'];
        $p->name = $row['name'];
        $p->slug = $row['slug'];
        $p->description = $row['description'] ?? null;
        $p->is_active = (bool) $row['is_active'];
        $p->sort_order = (int) ($row['sort_order'] ?? 0);
        $p->created_at = $row['created_at'];
        $p->updated_at = $row['updated_at'] ?? null;
        return $p;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
        ];
    }
}
