<?php
/**
 * Transaction Model
 * 
 * TRANSAKSI = Upload dokumen bukti pembayaran
 * Tidak ada produk/pricing - hanya dokumen upload
 */

class Transaction
{
    public int $id;
    public int $user_id;
    public ?string $document_path;
    public ?string $document_original_name;
    public string $status;
    public ?string $admin_notes;
    public ?int $approved_by;
    public ?string $approved_at;
    public ?string $rejected_at;
    public string $created_at;
    public string $updated_at;
    
    // Eager loaded relations
    public ?User $user = null;
    public ?User $approver = null;
    
    // Status constants - FINAL
    const STATUS_WAITING = 'waiting_verification';
    const STATUS_APPROVED = 'approved';
    const STATUS_REJECTED = 'rejected';
    const STATUS_PENDING_LICENSE = 'pending_license'; // License server unavailable, will retry
    
    /**
     * Find transaction by ID
     */
    public static function find(int $id): ?self
    {
        $row = Database::fetchOne("SELECT * FROM transactions WHERE id = ?", [$id]);
        return $row ? self::fromArray($row) : null;
    }
    
    /**
     * Find active transaction by user (latest non-approved)
     */
    public static function findActiveByUser(int $userId): ?self
    {
        $row = Database::fetchOne(
            "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 1",
            [$userId]
        );
        return $row ? self::fromArray($row) : null;
    }
    
    /**
     * Find by user ID
     */
    public static function findByUser(int $userId, ?int $limit = null): array
    {
        $sql = "SELECT * FROM transactions WHERE user_id = ? ORDER BY created_at DESC";
        if ($limit) {
            $sql .= " LIMIT " . intval($limit);
        }
        $rows = Database::fetchAll($sql, [$userId]);
        return array_map([self::class, 'fromArray'], $rows);
    }
    
    /**
     * Create new transaction from document upload
     */
    public static function createFromUpload(int $userId, string $documentPath, string $originalName): self
    {
        $id = Database::insert('transactions', [
            'user_id' => $userId,
            'document_path' => $documentPath,
            'document_original_name' => $originalName,
            'status' => self::STATUS_WAITING,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        return self::find($id);
    }
    
    /**
     * Update document (for re-upload after rejection)
     */
    public function updateDocument(string $documentPath, string $originalName): bool
    {
        return Database::update('transactions', [
            'document_path' => $documentPath,
            'document_original_name' => $originalName,
            'status' => self::STATUS_WAITING,
            'admin_notes' => null,
            'rejected_at' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$this->id]) > 0;
    }
    
    /**
     * Approve transaction
     */
    public function approve(int $adminId, ?string $notes = null): bool
    {
        return Database::update('transactions', [
            'status' => self::STATUS_APPROVED,
            'approved_by' => $adminId,
            'approved_at' => date('Y-m-d H:i:s'),
            'admin_notes' => $notes,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$this->id]) > 0;
    }
    
    /**
     * Reject transaction
     */
    public function reject(int $adminId, string $reason): bool
    {
        return Database::update('transactions', [
            'status' => self::STATUS_REJECTED,
            'approved_by' => $adminId,
            'rejected_at' => date('Y-m-d H:i:s'),
            'admin_notes' => $reason,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$this->id]) > 0;
    }
    
    /**
     * Check status helpers
     */
    public function isWaiting(): bool
    {
        return $this->status === self::STATUS_WAITING || empty($this->status);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isPendingLicense(): bool
    {
        return $this->status === self::STATUS_PENDING_LICENSE;
    }

    /**
     * Set status to pending_license (when license server is unavailable)
     */
    public function setPendingLicense(int $adminId, ?string $notes = null): bool
    {
        return Database::update('transactions', [
            'status' => self::STATUS_PENDING_LICENSE,
            'approved_by' => $adminId,
            'admin_notes' => $notes . ' [License server unavailable - will retry]',
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$this->id]) > 0;
    }

    /**
     * Check if transaction can be retried for license generation
     */
    public function canRetryLicense(): bool
    {
        return $this->status === self::STATUS_PENDING_LICENSE;
    }
    
    /**
     * Can user upload new document?
     * Only if no transaction or last transaction was rejected
     */
    public function canUpload(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }
    
    /**
     * Count by status
     */
    public static function countByStatus(?string $status = null): int
    {
        if ($status) {
            $result = Database::fetchOne(
                "SELECT COUNT(*) as count FROM transactions WHERE status = ?",
                [$status]
            );
        } else {
            $result = Database::fetchOne("SELECT COUNT(*) as count FROM transactions");
        }
        return (int) $result['count'];
    }
    
    /**
     * Count by user, optionally filtered by status
     */
    public static function countByUser(int $userId, ?string $status = null): int
    {
        if ($status) {
            $result = Database::fetchOne(
                "SELECT COUNT(*) as count FROM transactions WHERE user_id = ? AND status = ?",
                [$userId, $status]
            );
        } else {
            $result = Database::fetchOne(
                "SELECT COUNT(*) as count FROM transactions WHERE user_id = ?",
                [$userId]
            );
        }
        return (int) $result['count'];
    }
    
    /**
     * Find all with pagination for admin
     */
    public static function findAll(int $limit = 15, int $offset = 0, ?string $status = null): array
    {
        $where = $status ? "WHERE t.status = ?" : "";
        $params = $status ? [$status, $limit, $offset] : [$limit, $offset];
        
        $sql = "SELECT t.*, u.name as user_name, u.email as user_email
                FROM transactions t
                LEFT JOIN users u ON t.user_id = u.id
                {$where}
                ORDER BY t.created_at DESC
                LIMIT ? OFFSET ?";
        
        return Database::fetchAll($sql, $params);
    }
    
    /**
     * Find recent transactions for admin dashboard
     */
    public static function findRecent(int $limit = 10): array
    {
        $sql = "SELECT t.*, u.name as user_name, u.email as user_email
                FROM transactions t
                LEFT JOIN users u ON t.user_id = u.id
                ORDER BY t.created_at DESC
                LIMIT ?";
        
        return Database::fetchAll($sql, [$limit]);
    }
    
    /**
     * Find with user info
     */
    public static function findWithUser(int $id): ?array
    {
        $sql = "SELECT t.*, u.name as user_name, u.email as user_email
                FROM transactions t
                LEFT JOIN users u ON t.user_id = u.id
                WHERE t.id = ?";
        
        return Database::fetchOne($sql, [$id]);
    }
    
    /**
     * Get status label (Indonesian)
     */
    public function getStatusLabel(): string
    {
        return match($this->status) {
            self::STATUS_WAITING => 'Menunggu Verifikasi',
            self::STATUS_APPROVED => 'Disetujui',
            self::STATUS_REJECTED => 'Ditolak',
            self::STATUS_PENDING_LICENSE => 'Pending Lisensi (Server Offline)',
            default => $this->status
        };
    }
    
    /**
     * Get status badge class
     */
    public function getStatusBadgeClass(): string
    {
        return match($this->status) {
            self::STATUS_WAITING => 'warning',
            self::STATUS_APPROVED => 'success',
            self::STATUS_REJECTED => 'danger',
            self::STATUS_PENDING_LICENSE => 'info',
            default => 'secondary'
        };
    }
    
    /**
     * Create from array
     */
    public static function fromArray(array $row): self
    {
        $t = new self();
        $t->id = (int) $row['id'];
        $t->user_id = (int) $row['user_id'];
        $t->document_path = $row['document_path'] ?? null;
        $t->document_original_name = $row['document_original_name'] ?? null;
        $t->status = $row['status'];
        $t->admin_notes = $row['admin_notes'] ?? null;
        $t->approved_by = isset($row['approved_by']) ? (int) $row['approved_by'] : null;
        $t->approved_at = $row['approved_at'] ?? null;
        $t->rejected_at = $row['rejected_at'] ?? null;
        $t->created_at = $row['created_at'];
        $t->updated_at = $row['updated_at'];
        
        return $t;
    }
}
