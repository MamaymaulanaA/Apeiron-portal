<?php
/**
 * DownloadLog Model
 */

class DownloadLog
{
    public int $id;
    public int $user_id;
    public int $user_license_id;
    public string $file_name;
    public string $file_version;
    public ?int $file_size;
    public string $ip_address;
    public ?string $user_agent;
    public string $downloaded_at;
    
    /**
     * Create download log
     */
    public static function create(array $data): int
    {
        return Database::insert('download_logs', [
            'user_id' => $data['user_id'],
            'user_license_id' => $data['user_license_id'],
            'file_name' => $data['file_name'],
            'file_version' => $data['file_version'],
            'file_size' => $data['file_size'] ?? null,
            'ip_address' => $data['ip_address'] ?? ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'),
            'user_agent' => $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null),
            'downloaded_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Get downloads by user
     */
    public static function findByUser(int $userId, int $limit = 50): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM download_logs WHERE user_id = ? ORDER BY downloaded_at DESC LIMIT ?",
            [$userId, $limit]
        );
        return array_map([self::class, 'fromArray'], $rows);
    }
    
    /**
     * Get recent downloads with pagination
     */
    public static function paginate(int $page = 1, int $perPage = ITEMS_PER_PAGE): array
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT dl.*, u.name as user_name, u.email as user_email
                FROM download_logs dl
                LEFT JOIN users u ON dl.user_id = u.id
                ORDER BY dl.downloaded_at DESC
                LIMIT ? OFFSET ?";
        
        $rows = Database::fetchAll($sql, [$perPage, $offset]);
        
        $total = (int) Database::fetchOne("SELECT COUNT(*) as count FROM download_logs")['count'];
        
        return [
            'data' => $rows,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }
    
    /**
     * Count downloads by user
     */
    public static function countByUser(int $userId): int
    {
        $result = Database::fetchOne(
            "SELECT COUNT(*) as count FROM download_logs WHERE user_id = ?",
            [$userId]
        );
        return (int) $result['count'];
    }
    
    /**
     * Count total downloads
     */
    public static function count(): int
    {
        $result = Database::fetchOne("SELECT COUNT(*) as count FROM download_logs");
        return (int) $result['count'];
    }
    
    /**
     * Create from array
     */
    public static function fromArray(array $row): self
    {
        $dl = new self();
        $dl->id = (int) $row['id'];
        $dl->user_id = (int) $row['user_id'];
        $dl->user_license_id = (int) $row['user_license_id'];
        $dl->file_name = $row['file_name'];
        $dl->file_version = $row['file_version'];
        $dl->file_size = $row['file_size'] ? (int) $row['file_size'] : null;
        $dl->ip_address = $row['ip_address'];
        $dl->user_agent = $row['user_agent'];
        $dl->downloaded_at = $row['downloaded_at'];
        
        return $dl;
    }
}
