<?php
/**
 * Tutorial Model
 *
 * Isi seksi Forum: tutorial, panduan, dan informasi penggunaan produk.
 * Bukan forum diskusi — tidak ada utas, balasan, maupun penulis, karena
 * alurnya satu arah dari admin ke user.
 */

class Tutorial
{
    public int $id;
    public string $title;
    public ?string $excerpt;
    public ?string $content;
    public ?string $category;
    public ?string $icon;
    public ?string $external_url;
    public bool $is_published;
    public int $sort_order;
    public string $created_at;
    public string $updated_at;

    /**
     * Ikon yang boleh dipakai — nama komponen lucide yang benar-benar diimpor
     * di frontend. Daftar tertutup, bukan teks bebas: kalau admin mengetik nama
     * ikon yang tidak ada, kartunya akan kosong dan tidak ada yang tahu
     * penyebabnya. Nilai di luar daftar jatuh ke BookOpen.
     */
    public const ICONS = [
        'BookOpen', 'Download', 'KeyRound', 'Package', 'LayoutTemplate',
        'Rocket', 'Settings', 'CircleHelp', 'Video', 'FileText', 'Wrench', 'Zap',
    ];

    public static function normalizeIcon(?string $icon): string
    {
        return in_array($icon, self::ICONS, true) ? $icon : 'BookOpen';
    }

    /**
     * Id video YouTube dari berbagai bentuk URL, atau null bila bukan YouTube.
     * Dipakai frontend untuk memilih antara tombol "Tonton di YouTube" dan
     * "Lihat Tutorial".
     */
    public static function youtubeId(?string $url): ?string
    {
        if (!$url) return null;

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host);

        if ($host === 'youtu.be') {
            $id = trim((string) parse_url($url, PHP_URL_PATH), '/');
            return preg_match('/^[A-Za-z0-9_-]{6,}$/', $id) ? $id : null;
        }

        if ($host === 'youtube.com' || $host === 'm.youtube.com') {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $id = $query['v'] ?? null;
            if ($id && preg_match('/^[A-Za-z0-9_-]{6,}$/', $id)) return $id;

            // Bentuk /embed/<id> dan /shorts/<id>
            $path = (string) parse_url($url, PHP_URL_PATH);
            if (preg_match('#^/(embed|shorts)/([A-Za-z0-9_-]{6,})#', $path, $m)) {
                return $m[2];
            }
        }

        return null;
    }

    public static function find(int $id): ?self
    {
        $row = Database::fetchOne("SELECT * FROM tutorials WHERE id = ?", [$id]);
        return $row ? self::fromArray($row) : null;
    }

    /** Termasuk draf — untuk panel admin. */
    public static function all(): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM tutorials ORDER BY sort_order ASC, created_at DESC"
        );
        return array_map([self::class, 'fromArray'], $rows);
    }

    /** Hanya yang terbit — untuk user. */
    public static function allPublished(): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM tutorials WHERE is_published = 1 ORDER BY sort_order ASC, created_at DESC"
        );
        return array_map([self::class, 'fromArray'], $rows);
    }

    public static function findPublished(int $id): ?self
    {
        $row = Database::fetchOne("SELECT * FROM tutorials WHERE id = ? AND is_published = 1", [$id]);
        return $row ? self::fromArray($row) : null;
    }

    public static function count(): int
    {
        $result = Database::fetchOne("SELECT COUNT(*) as count FROM tutorials");
        return (int) $result['count'];
    }

    public static function create(array $data): int
    {
        return Database::insert('tutorials', [
            'title' => $data['title'],
            'excerpt' => $data['excerpt'] ?? null,
            'content' => $data['content'] ?? null,
            'category' => $data['category'] ?? null,
            'icon' => self::normalizeIcon($data['icon'] ?? null),
            'external_url' => $data['external_url'] ?? null,
            'is_published' => $data['is_published'] ?? 1,
            'sort_order' => $data['sort_order'] ?? 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function update(array $data): bool
    {
        $updateData = [];
        if (isset($data['title'])) $updateData['title'] = $data['title'];
        if (array_key_exists('excerpt', $data)) $updateData['excerpt'] = $data['excerpt'];
        if (array_key_exists('content', $data)) $updateData['content'] = $data['content'];
        if (array_key_exists('category', $data)) $updateData['category'] = $data['category'];
        if (isset($data['icon'])) $updateData['icon'] = self::normalizeIcon($data['icon']);
        if (array_key_exists('external_url', $data)) $updateData['external_url'] = $data['external_url'];
        if (isset($data['is_published'])) $updateData['is_published'] = $data['is_published'];
        if (isset($data['sort_order'])) $updateData['sort_order'] = $data['sort_order'];

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        // Nol baris terpengaruh berarti nilainya memang sudah sama, bukan gagal.
        Database::update('tutorials', $updateData, 'id = ?', [$this->id]);
        return true;
    }

    public function delete(): bool
    {
        return Database::delete('tutorials', 'id = ?', [$this->id]) > 0;
    }

    /**
     * Bentuk ringkas untuk daftar kartu — tanpa `content`, yang bisa panjang
     * dan tidak dipakai sampai detailnya dibuka.
     */
    public function toCard(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'category' => $this->category,
            'icon' => self::normalizeIcon($this->icon),
            'external_url' => $this->external_url,
            'youtube_id' => self::youtubeId($this->external_url),
            'has_content' => $this->content !== null && trim($this->content) !== '',
            'is_published' => $this->is_published,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    public function toArray(): array
    {
        return $this->toCard() + ['content' => $this->content];
    }

    public static function fromArray(array $row): self
    {
        $t = new self();
        $t->id = (int) $row['id'];
        $t->title = $row['title'];
        $t->excerpt = $row['excerpt'] ?? null;
        $t->content = $row['content'] ?? null;
        $t->category = $row['category'] ?? null;
        $t->icon = $row['icon'] ?? null;
        $t->external_url = $row['external_url'] ?? null;
        $t->is_published = (bool) $row['is_published'];
        $t->sort_order = (int) ($row['sort_order'] ?? 0);
        $t->created_at = $row['created_at'];
        $t->updated_at = $row['updated_at'];
        return $t;
    }
}
