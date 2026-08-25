<?php
/**
 * Bonus Model
 *
 * Bonus member: berkas atau tautan yang dibagikan admin ke user.
 *
 * Dua sumber, satu baris. `source_type` menentukan mana yang berarti:
 *   'file' → file_path, file_original_name, file_size
 *   'link' → external_url
 * Kolom pasangan yang tidak dipakai dibiarkan NULL, dan controller yang
 * memastikan yang dipakai selalu terisi.
 */

class Bonus
{
    public int $id;
    public string $name;
    public ?string $category;
    public ?string $description;
    public ?string $thumbnail_path;
    public string $source_type;
    public ?string $file_path;
    public ?string $file_original_name;
    public ?int $file_size;
    public ?string $external_url;
    public bool $is_active;
    public int $sort_order;
    public string $created_at;
    public string $updated_at;

    public const SOURCE_FILE = 'file';
    public const SOURCE_LINK = 'link';

    public static function find(int $id): ?self
    {
        $row = Database::fetchOne("SELECT * FROM bonuses WHERE id = ?", [$id]);
        return $row ? self::fromArray($row) : null;
    }

    /** Termasuk yang nonaktif — untuk panel admin. */
    public static function all(): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM bonuses ORDER BY sort_order ASC, created_at DESC"
        );
        return array_map([self::class, 'fromArray'], $rows);
    }

    /** Hanya yang aktif — untuk user. */
    public static function allActive(): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM bonuses WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC"
        );
        return array_map([self::class, 'fromArray'], $rows);
    }

    /** Satu bonus aktif — dipakai endpoint unduh, yang tidak boleh melayani
     *  bonus yang sudah dinonaktifkan admin. */
    public static function findActive(int $id): ?self
    {
        $row = Database::fetchOne("SELECT * FROM bonuses WHERE id = ? AND is_active = 1", [$id]);
        return $row ? self::fromArray($row) : null;
    }

    public static function count(): int
    {
        $result = Database::fetchOne("SELECT COUNT(*) as count FROM bonuses");
        return (int) $result['count'];
    }

    public static function create(array $data): int
    {
        return Database::insert('bonuses', [
            'name' => $data['name'],
            'category' => $data['category'] ?? null,
            'description' => $data['description'] ?? null,
            'thumbnail_path' => $data['thumbnail_path'] ?? null,
            'source_type' => $data['source_type'] ?? self::SOURCE_LINK,
            'file_path' => $data['file_path'] ?? null,
            'file_original_name' => $data['file_original_name'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'external_url' => $data['external_url'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function update(array $data): bool
    {
        $updateData = [];
        foreach ([
            'name', 'category', 'description', 'thumbnail_path', 'source_type',
            'file_path', 'file_original_name', 'file_size', 'external_url',
            'is_active', 'sort_order',
        ] as $field) {
            if (array_key_exists($field, $data)) $updateData[$field] = $data[$field];
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        // Nol baris terpengaruh berarti nilainya memang sudah sama, bukan gagal.
        Database::update('bonuses', $updateData, 'id = ?', [$this->id]);
        return true;
    }

    public function delete(): bool
    {
        return Database::delete('bonuses', 'id = ?', [$this->id]) > 0;
    }

    public function isFile(): bool
    {
        return $this->source_type === self::SOURCE_FILE;
    }

    /**
     * Bentuk yang dikirim ke klien.
     *
     * `file_path` TIDAK ikut dikirim. Berkas bonus dilayani lewat endpoint
     * ber-auth, jadi nama berkasnya di storage tidak perlu — dan tidak boleh —
     * diketahui klien; mengirimkannya hanya memberi peta untuk mencoba
     * mengambilnya langsung.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'thumbnail_path' => $this->thumbnail_path,
            'source_type' => $this->source_type,
            'file_original_name' => $this->file_original_name,
            'file_size' => $this->file_size,
            'external_url' => $this->external_url,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public static function fromArray(array $row): self
    {
        $b = new self();
        $b->id = (int) $row['id'];
        $b->name = $row['name'];
        $b->category = $row['category'] ?? null;
        $b->description = $row['description'] ?? null;
        $b->thumbnail_path = $row['thumbnail_path'] ?? null;
        $b->source_type = $row['source_type'] ?? self::SOURCE_LINK;
        $b->file_path = $row['file_path'] ?? null;
        $b->file_original_name = $row['file_original_name'] ?? null;
        $b->file_size = isset($row['file_size']) ? (int) $row['file_size'] : null;
        $b->external_url = $row['external_url'] ?? null;
        $b->is_active = (bool) $row['is_active'];
        $b->sort_order = (int) ($row['sort_order'] ?? 0);
        $b->created_at = $row['created_at'];
        $b->updated_at = $row['updated_at'];
        return $b;
    }
}
