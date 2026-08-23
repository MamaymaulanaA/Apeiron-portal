<?php
/**
 * Template Model
 */

class Template
{
    public int $id;
    public string $name;
    public ?string $description;
    public ?string $thumbnail_path;
    public ?string $checkout_url;
    public bool $is_standard;
    public bool $is_recommended;
    public string $created_at;
    public string $updated_at;

    /**
     * Find template by ID
     */
    public static function find(int $id): ?self
    {
        $row = Database::fetchOne("SELECT * FROM templates WHERE id = ?", [$id]);
        return $row ? self::fromArray($row) : null;
    }

    /**
     * Get all templates
     */
    public static function all(): array
    {
        $rows = Database::fetchAll("SELECT * FROM templates ORDER BY is_recommended DESC, created_at DESC");
        return array_map([self::class, 'fromArray'], $rows);
    }

    /**
     * Count all templates
     */
    public static function count(): int
    {
        $result = Database::fetchOne("SELECT COUNT(*) as count FROM templates");
        return (int) $result['count'];
    }

    /**
     * Count templates by type
     */
    public static function countByType(bool $isStandard): int
    {
        $result = Database::fetchOne("SELECT COUNT(*) as count FROM templates WHERE is_standard = ?", [(int)$isStandard]);
        return (int) $result['count'];
    }

    /**
     * Create new template
     */
    public static function create(array $data): int
    {
        return Database::insert('templates', [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'thumbnail_path' => $data['thumbnail_path'] ?? null,
            'checkout_url' => $data['checkout_url'] ?? null,
            'is_standard' => $data['is_standard'] ?? 0,
            'is_recommended' => $data['is_recommended'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Update template
     */
    public function update(array $data): bool
    {
        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (isset($data['description'])) $updateData['description'] = $data['description'];
        if (isset($data['thumbnail_path'])) $updateData['thumbnail_path'] = $data['thumbnail_path'];
        if (isset($data['checkout_url'])) $updateData['checkout_url'] = $data['checkout_url'];
        if (isset($data['is_standard'])) $updateData['is_standard'] = $data['is_standard'];
        if (isset($data['is_recommended'])) $updateData['is_recommended'] = $data['is_recommended'];
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');

        return Database::update('templates', $updateData, 'id = ?', [$this->id]) > 0;
    }

    /**
     * Delete template
     */
    public function delete(): bool
    {
        return Database::delete('templates', 'id = ?', [$this->id]) > 0;
    }

    /**
     * Create from array
     */
    public static function fromArray(array $row): self
    {
        $t = new self();
        $t->id = (int) $row['id'];
        $t->name = $row['name'];
        $t->description = $row['description'] ?? null;
        $t->thumbnail_path = $row['thumbnail_path'] ?? null;
        $t->checkout_url = $row['checkout_url'] ?? null;
        $t->is_standard = (bool) $row['is_standard'];
        $t->is_recommended = (bool) $row['is_recommended'];
        $t->created_at = $row['created_at'];
        $t->updated_at = $row['updated_at'];
        return $t;
    }
}
