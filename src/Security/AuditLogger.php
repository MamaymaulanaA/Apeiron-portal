<?php
/**
 * Audit Logger
 * 
 * Logs admin actions for audit trail
 */

class AuditLogger
{
    /**
     * Log an admin action
     */
    public static function log(
        int $adminId,
        string $action,
        string $targetType,
        ?int $targetId = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $description = null
    ): int {
        return Database::insert('admin_logs', [
            'admin_id' => $adminId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
            'ip_address' => self::getClientIp(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Log transaction approval
     */
    public static function logApproval(int $adminId, int $transactionId, string $notes = ''): int
    {
        return self::log(
            $adminId,
            'approve',
            'transaction',
            $transactionId,
            'pending',
            'approved',
            $notes ?: 'Transaction approved'
        );
    }
    
    /**
     * Log transaction rejection
     */
    public static function logRejection(int $adminId, int $transactionId, string $reason): int
    {
        return self::log(
            $adminId,
            'reject',
            'transaction',
            $transactionId,
            'pending',
            'rejected',
            $reason
        );
    }
    
    /**
     * Log file upload
     */
    public static function logFileUpload(int $adminId, string $fileName, string $version): int
    {
        return self::log(
            $adminId,
            'upload',
            'plugin_file',
            null,
            null,
            $fileName,
            "Uploaded plugin file version {$version}"
        );
    }
    
    /**
     * Log user status change
     */
    public static function logUserStatusChange(int $adminId, int $userId, bool $isActive): int
    {
        return self::log(
            $adminId,
            $isActive ? 'activate_user' : 'deactivate_user',
            'user',
            $userId,
            $isActive ? '0' : '1',
            $isActive ? '1' : '0',
            $isActive ? 'User activated' : 'User deactivated'
        );
    }
    
    /**
     * Log login attempt
     */
    public static function logLogin(int $userId, bool $success): int
    {
        return self::log(
            $userId,
            $success ? 'login_success' : 'login_failed',
            'auth',
            null,
            null,
            null,
            $success ? 'Successful login' : 'Failed login attempt'
        );
    }
    
    /**
     * Get recent logs
     */
    public static function getRecent(int $limit = 50, int $offset = 0): array
    {
        $sql = "SELECT al.*, u.name as admin_name, u.email as admin_email
                FROM admin_logs al
                LEFT JOIN users u ON al.admin_id = u.id
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?";
        
        return Database::fetchAll($sql, [$limit, $offset]);
    }
    
    /**
     * Get logs by action type
     */
    public static function getByAction(string $action, int $limit = 50): array
    {
        $sql = "SELECT al.*, u.name as admin_name
                FROM admin_logs al
                LEFT JOIN users u ON al.admin_id = u.id
                WHERE al.action = ?
                ORDER BY al.created_at DESC
                LIMIT ?";
        
        return Database::fetchAll($sql, [$action, $limit]);
    }
    
    /**
     * Get logs by target
     */
    public static function getByTarget(string $targetType, int $targetId): array
    {
        $sql = "SELECT al.*, u.name as admin_name
                FROM admin_logs al
                LEFT JOIN users u ON al.admin_id = u.id
                WHERE al.target_type = ? AND al.target_id = ?
                ORDER BY al.created_at DESC";
        
        return Database::fetchAll($sql, [$targetType, $targetId]);
    }
    
    /**
     * Count total logs
     */
    public static function count(?string $action = null): int
    {
        if ($action) {
            $result = Database::fetchOne(
                "SELECT COUNT(*) as count FROM admin_logs WHERE action = ?",
                [$action]
            );
        } else {
            $result = Database::fetchOne("SELECT COUNT(*) as count FROM admin_logs");
        }
        
        return (int) ($result['count'] ?? 0);
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIp(): string
    {
        $keys = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        ];
        
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}
