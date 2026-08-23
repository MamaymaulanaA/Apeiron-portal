<?php
/**
 * AdminLog Model
 * 
 * Wrapper around AuditLogger for model consistency
 */

class AdminLog
{
    public int $id;
    public int $admin_id;
    public string $action;
    public string $target_type;
    public ?int $target_id;
    public ?string $old_value;
    public ?string $new_value;
    public ?string $description;
    public string $ip_address;
    public ?string $user_agent;
    public string $created_at;
    
    // Eager loaded
    public ?string $admin_name = null;
    
    /**
     * Get recent logs with pagination
     */
    public static function paginate(int $page = 1, int $perPage = ITEMS_PER_PAGE): array
    {
        $offset = ($page - 1) * $perPage;
        
        $sql = "SELECT al.*, u.name as admin_name, u.email as admin_email
                FROM admin_logs al
                LEFT JOIN users u ON al.admin_id = u.id
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?";
        
        $rows = Database::fetchAll($sql, [$perPage, $offset]);
        
        $total = (int) Database::fetchOne("SELECT COUNT(*) as count FROM admin_logs")['count'];
        
        return [
            'data' => array_map([self::class, 'fromArray'], $rows),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }
    
    /**
     * Get logs by action
     */
    public static function findByAction(string $action, int $limit = 50): array
    {
        $sql = "SELECT al.*, u.name as admin_name
                FROM admin_logs al
                LEFT JOIN users u ON al.admin_id = u.id
                WHERE al.action = ?
                ORDER BY al.created_at DESC
                LIMIT ?";
        
        $rows = Database::fetchAll($sql, [$action, $limit]);
        return array_map([self::class, 'fromArray'], $rows);
    }
    
    /**
     * Get action label
     */
    public function getActionLabel(): string
    {
        return match($this->action) {
            'approve' => 'Menyetujui Transaksi',
            'reject' => 'Menolak Transaksi',
            'upload' => 'Upload File',
            'activate_user' => 'Mengaktifkan User',
            'deactivate_user' => 'Menonaktifkan User',
            'login_success' => 'Login Berhasil',
            'login_failed' => 'Login Gagal',
            default => ucfirst(str_replace('_', ' ', $this->action))
        };
    }
    
    /**
     * Create from array
     */
    public static function fromArray(array $row): self
    {
        $log = new self();
        $log->id = (int) $row['id'];
        $log->admin_id = (int) $row['admin_id'];
        $log->action = $row['action'];
        $log->target_type = $row['target_type'];
        $log->target_id = $row['target_id'] ? (int) $row['target_id'] : null;
        $log->old_value = $row['old_value'];
        $log->new_value = $row['new_value'];
        $log->description = $row['description'];
        $log->ip_address = $row['ip_address'];
        $log->user_agent = $row['user_agent'];
        $log->created_at = $row['created_at'];
        $log->admin_name = $row['admin_name'] ?? null;
        
        return $log;
    }
}
