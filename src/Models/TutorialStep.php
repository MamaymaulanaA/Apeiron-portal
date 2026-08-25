<?php
/**
 * Tutorial Step Model
 *
 * Satu langkah bernomor di dalam sebuah panduan: teks, dan gambarnya sendiri.
 * Inilah yang membuat gambar bisa berada DI DALAM isi panduan, bukan cuma
 * satu gambar sampul di kartunya.
 */

class TutorialStep
{
    public int $id;
    public int $tutorial_id;
    public int $position;
    public ?string $body;
    public ?string $image_path;
    public string $created_at;
    public string $updated_at;

    /** Langkah satu tutorial, sudah urut. */
    public static function forTutorial(int $tutorialId): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM tutorial_steps WHERE tutorial_id = ? ORDER BY position ASC, id ASC",
            [$tutorialId]
        );
        return array_map([self::class, 'fromArray'], $rows);
    }

    /**
     * Nama berkas gambar milik satu tutorial.
     *
     * Dipakai sebelum menghapus tutorial: barisnya akan ikut terhapus oleh
     * ON DELETE CASCADE, jadi daftarnya harus diambil selagi masih ada —
     * setelah itu tidak ada lagi yang tahu berkas mana yang perlu dibuang.
     */
    public static function imagePathsFor(int $tutorialId): array
    {
        $rows = Database::fetchAll(
            "SELECT image_path FROM tutorial_steps WHERE tutorial_id = ? AND image_path IS NOT NULL",
            [$tutorialId]
        );
        return array_column($rows, 'image_path');
    }

    public static function create(array $data): int
    {
        return Database::insert('tutorial_steps', [
            'tutorial_id' => $data['tutorial_id'],
            'position' => $data['position'] ?? 0,
            'body' => $data['body'] ?? null,
            'image_path' => $data['image_path'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public static function deleteForTutorial(int $tutorialId): void
    {
        Database::delete('tutorial_steps', 'tutorial_id = ?', [$tutorialId]);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'position' => $this->position,
            'body' => $this->body,
            'image_path' => $this->image_path,
        ];
    }

    public static function fromArray(array $row): self
    {
        $s = new self();
        $s->id = (int) $row['id'];
        $s->tutorial_id = (int) $row['tutorial_id'];
        $s->position = (int) $row['position'];
        $s->body = $row['body'] ?? null;
        $s->image_path = $row['image_path'] ?? null;
        $s->created_at = $row['created_at'] ?? '';
        $s->updated_at = $row['updated_at'] ?? '';
        return $s;
    }
}
