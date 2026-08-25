<?php
/**
 * Community Group Model
 *
 * Grup komunitas yang dibagikan ke user yang transaksinya sudah disetujui.
 * Tabelnya bernama community_groups, bukan groups — GROUPS kata kunci cadangan
 * sejak MySQL 8.0.2.
 */

class CommunityGroup
{
    public int $id;
    public string $name;
    public ?string $description;
    public string $link;
    public bool $is_active;
    public int $sort_order;
    public string $created_at;
    public string $updated_at;

    /**
     * Platform yang dikenali, dipetakan dari host pada link.
     *
     * Sengaja diturunkan saat dibaca, bukan disimpan sebagai kolom: kalau
     * platform jadi kolom tersendiri, admin bisa mengganti link tanpa
     * mengganti platform dan baris itu akan berbohong tentang dirinya sendiri.
     */
    private const PLATFORM_HOSTS = [
        'whatsapp' => ['chat.whatsapp.com', 'wa.me', 'whatsapp.com', 'api.whatsapp.com'],
        'telegram' => ['t.me', 'telegram.me', 'telegram.org'],
        'discord'  => ['discord.gg', 'discord.com', 'discordapp.com'],
        'facebook' => ['facebook.com', 'fb.me', 'fb.com', 'm.me'],
        'line'     => ['line.me'],
        'slack'    => ['slack.com'],
    ];

    public static function detectPlatform(?string $link): string
    {
        if (!$link) return 'other';

        $host = strtolower((string) parse_url($link, PHP_URL_HOST));
        if ($host === '') return 'other';

        // Buang www. supaya "www.facebook.com" tetap dikenali
        $host = preg_replace('/^www\./', '', $host);

        foreach (self::PLATFORM_HOSTS as $platform => $hosts) {
            foreach ($hosts as $candidate) {
                // Cocok persis atau sebagai subdomain — bukan sekadar substring,
                // supaya "discord.com.phishing.example" tidak lolos sebagai Discord.
                if ($host === $candidate || str_ends_with($host, '.' . $candidate)) {
                    return $platform;
                }
            }
        }

        return 'other';
    }

    public static function find(int $id): ?self
    {
        $row = Database::fetchOne("SELECT * FROM community_groups WHERE id = ?", [$id]);
        return $row ? self::fromArray($row) : null;
    }

    /** Semua grup, termasuk yang nonaktif — untuk panel admin. */
    public static function all(): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM community_groups ORDER BY sort_order ASC, created_at DESC"
        );
        return array_map([self::class, 'fromArray'], $rows);
    }

    /** Hanya yang aktif — untuk user. */
    public static function allActive(): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM community_groups WHERE is_active = 1 ORDER BY sort_order ASC, created_at DESC"
        );
        return array_map([self::class, 'fromArray'], $rows);
    }

    public static function count(): int
    {
        $result = Database::fetchOne("SELECT COUNT(*) as count FROM community_groups");
        return (int) $result['count'];
    }

    public static function create(array $data): int
    {
        return Database::insert('community_groups', [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'link' => $data['link'],
            'is_active' => $data['is_active'] ?? 1,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function update(array $data): bool
    {
        $updateData = [];
        if (isset($data['name'])) $updateData['name'] = $data['name'];
        if (array_key_exists('description', $data)) $updateData['description'] = $data['description'];
        if (isset($data['link'])) $updateData['link'] = $data['link'];
        if (isset($data['is_active'])) $updateData['is_active'] = $data['is_active'];
        if (isset($data['sort_order'])) $updateData['sort_order'] = $data['sort_order'];

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        // Update yang tidak mengubah nilai apa pun mengembalikan 0 baris
        // terpengaruh; itu bukan kegagalan, jadi jangan dianggap gagal.
        Database::update('community_groups', $updateData, 'id = ?', [$this->id]);
        return true;
    }

    public function delete(): bool
    {
        return Database::delete('community_groups', 'id = ?', [$this->id]) > 0;
    }

    /** Bentuk yang dikirim ke klien. */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'link' => $this->link,
            'platform' => self::detectPlatform($this->link),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public static function fromArray(array $row): self
    {
        $g = new self();
        $g->id = (int) $row['id'];
        $g->name = $row['name'];
        $g->description = $row['description'] ?? null;
        $g->link = $row['link'];
        $g->is_active = (bool) $row['is_active'];
        $g->sort_order = (int) ($row['sort_order'] ?? 0);
        $g->created_at = $row['created_at'];
        $g->updated_at = $row['updated_at'];
        return $g;
    }
}
