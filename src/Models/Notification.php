<?php
/**
 * Notification Model
 */

class Notification
{
    public int $id;
    public int $user_id;
    public string $type;
    public string $title;
    public string $message;
    public ?int $reference_id;
    public bool $is_read;
    public string $created_at;

    /**
     * Find by ID
     */
    public static function find(int $id): ?self
    {
        $row = Database::fetchOne("SELECT * FROM notifications WHERE id = ?", [$id]);
        return $row ? self::fromArray($row) : null;
    }

    /**
     * Get notifications for a user (latest first, max 50)
     */
    public static function findByUser(int $userId, int $limit = 50): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?",
            [$userId, $limit]
        );
        return array_map([self::class, 'fromArray'], $rows);
    }

    /**
     * Count unread notifications for a user
     */
    public static function countUnread(int $userId): int
    {
        $row = Database::fetchOne(
            "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0",
            [$userId]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    /**
     * Get all admin user IDs
     */
    public static function getAdminIds(): array
    {
        $rows = Database::fetchAll("SELECT id FROM users WHERE role = 'admin'");
        return array_column($rows, 'id');
    }

    /**
     * Create a notification
     */
    public static function create(array $data): int
    {
        return Database::insert('notifications', [
            'user_id' => $data['user_id'],
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'reference_id' => $data['reference_id'] ?? null,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Notify all admins (helper)
     */
    public static function notifyAdmins(string $type, string $title, string $message, ?int $referenceId = null): void
    {
        $adminIds = self::getAdminIds();
        foreach ($adminIds as $adminId) {
            self::create([
                'user_id' => (int) $adminId,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'reference_id' => $referenceId
            ]);
        }
    }

    /**
     * Notify a specific user
     */
    public static function notifyUser(int $userId, string $type, string $title, string $message, ?int $referenceId = null): void
    {
        self::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'reference_id' => $referenceId
        ]);
    }

    /**
     * Notify all users who have active licenses (for plugin updates)
     */
    public static function notifyAllLicensedUsers(string $type, string $title, string $message, ?int $referenceId = null): int
    {
        $rows = Database::fetchAll(
            "SELECT DISTINCT user_id FROM user_licenses"
        );
        $count = 0;
        foreach ($rows as $row) {
            try {
                self::create([
                    'user_id' => (int) $row['user_id'],
                    'type' => $type,
                    'title' => $title,
                    'message' => $message,
                    'reference_id' => $referenceId
                ]);
                $count++;
            } catch (Exception $e) {
                error_log("Failed to notify user {$row['user_id']}: " . $e->getMessage());
            }
        }
        return $count;
    }

    /**
     * Mark single notification as read
     */
    public function markAsRead(): bool
    {
        return Database::update('notifications', ['is_read' => 1], 'id = ?', [$this->id]) > 0;
    }

    /**
     * Mark all notifications as read for a user
     */
    public static function markAllAsRead(int $userId): int
    {
        return Database::update(
            'notifications',
            ['is_read' => 1],
            'user_id = ? AND is_read = 0',
            [$userId]
        );
    }

    /**
     * Create from array
     */
    public static function fromArray(array $row): self
    {
        $n = new self();
        $n->id = (int) $row['id'];
        $n->user_id = (int) $row['user_id'];
        $n->type = $row['type'];
        $n->title = $row['title'];
        $n->message = $row['message'];
        $n->reference_id = isset($row['reference_id']) ? (int) $row['reference_id'] : null;
        $n->is_read = (bool) $row['is_read'];
        $n->created_at = $row['created_at'];
        return $n;
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'reference_id' => $this->reference_id,
            'is_read' => $this->is_read,
            'created_at' => $this->created_at
        ];
    }
}
