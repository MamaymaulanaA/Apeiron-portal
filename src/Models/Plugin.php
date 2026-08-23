<?php
/**
 * Plugin Model
 */

class Plugin
{
    public int $id;
    public string $version;
    public string $filename;
    public string $file_path;
    public ?string $changelog;
    public bool $is_active;
    public string $created_at;

    /**
     * Find plugin by ID
     */
    public static function find(int $id): ?self
    {
        $row = Database::fetchOne("SELECT * FROM plugins WHERE id = ?", [$id]);
        return $row ? self::fromArray($row) : null;
    }

    /**
     * Get latest active plugin
     */
    public static function getLatest(): ?self
    {
        $row = Database::fetchOne("SELECT * FROM plugins WHERE is_active = 1 ORDER BY created_at DESC LIMIT 1");
        return $row ? self::fromArray($row) : null;
    }

    /**
     * Get all plugins
     */
    public static function all(): array
    {
        $rows = Database::fetchAll("SELECT * FROM plugins ORDER BY created_at DESC");
        return array_map([self::class, 'fromArray'], $rows);
    }

    /**
     * Create new plugin entry
     */
    public static function create(array $data): int
    {
        // Deactivate other plugins if this one is active
        if ($data['is_active'] ?? 1) {
            Database::query("UPDATE plugins SET is_active = 0");
        }

        return Database::insert('plugins', [
            'version' => $data['version'],
            'filename' => $data['filename'],
            'file_path' => $data['file_path'],
            'changelog' => $data['changelog'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Update plugin
     */
    public function update(array $data): bool
    {
        $updateData = [];
        
        if (isset($data['version'])) $updateData['version'] = $data['version'];
        if (isset($data['changelog'])) $updateData['changelog'] = $data['changelog'];
        if (isset($data['filename'])) $updateData['filename'] = $data['filename'];
        if (isset($data['file_path'])) $updateData['file_path'] = $data['file_path'];
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
            // Deactivate other plugins if this one is set active
            if ($data['is_active']) {
                Database::query("UPDATE plugins SET is_active = 0 WHERE id != ?", [$this->id]);
            }
        }

        if (empty($updateData)) return false;

        return Database::update('plugins', $updateData, 'id = ?', [$this->id]) > 0;
    }

    /**
     * Delete plugin
     */
    public function delete(): bool
    {
        return Database::delete('plugins', 'id = ?', [$this->id]) > 0;
    }

    /**
     * Create from array
     */
    public static function fromArray(array $row): self
    {
        $p = new self();
        $p->id = (int) $row['id'];
        $p->version = $row['version'];
        $p->filename = $row['filename'];
        $p->file_path = $row['file_path'];
        $p->changelog = $row['changelog'] ?? null;
        $p->is_active = (bool) $row['is_active'];
        $p->created_at = $row['created_at'];
        return $p;
    }
}
