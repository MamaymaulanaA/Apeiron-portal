<?php
/**
 * UserLicense Model
 *
 * IMPROVEMENTS v2.0:
 * - Added findByUserId for consistency
 * - Added duplicate validation
 * - Added update/delete methods
 * - Enhanced audit logging
 * - Added license validation functions
 *
 * Maps users to their license keys
 */

class UserLicense
{
    public int $id;
    public int $user_id;
    public int $transaction_id;
    public string $license_key;
    public ?string $api_key;
    public string $product_id;
    public string $assigned_at;
    public string $created_at;

    /**
     * Jawaban terakhir License Server, apa adanya.
     *
     * Ini SALINAN, bukan sumber kebenaran. Status, masa berlaku, dan sisa hari
     * ditentukan server; portal tidak pernah menghitung ulang atau menebak
     * nilainya. null berarti belum pernah berhasil dibaca — bukan berarti
     * lisensinya tidak aktif.
     */
    public ?array $license_status = null;

    /** Kapan salinan di atas diambil (UTC). null = belum pernah. */
    public ?string $license_checked_at = null;

    /**
     * Find by ID
     */
    public static function find(int $id): ?self
    {
        $row = Database::fetchOne("SELECT * FROM user_licenses WHERE id = ?", [$id]);
        return $row ? self::fromArray($row) : null;
    }

    /**
     * Find by user ID
     */
    public static function findByUser(int $userId): array
    {
        $rows = Database::fetchAll(
            "SELECT * FROM user_licenses WHERE user_id = ? ORDER BY assigned_at DESC",
            [$userId]
        );
        return array_map([self::class, 'fromArray'], $rows);
    }

    /**
     * Find by user ID (alias for consistency)
     */
    public static function findByUserId(int $userId): array
    {
        return self::findByUser($userId);
    }

    /**
     * Find by license key
     */
    public static function findByLicenseKey(string $licenseKey): ?self
    {
        $row = Database::fetchOne(
            "SELECT * FROM user_licenses WHERE license_key = ?",
            [$licenseKey]
        );
        return $row ? self::fromArray($row) : null;
    }

    /**
     * Find by transaction ID
     */
    public static function findByTransaction(int $transactionId): ?self
    {
        $row = Database::fetchOne(
            "SELECT * FROM user_licenses WHERE transaction_id = ?",
            [$transactionId]
        );
        return $row ? self::fromArray($row) : null;
    }

    /**
     * Create new user license mapping
     *
     * IMPROVEMENTS:
     * - Added duplicate check
     * - Added audit logging
     */
    public static function create(array $data): self
    {
        // Check for duplicate before insert
        $existing = self::findByUser($data['user_id']);
        foreach ($existing as $lic) {
            if ($lic->product_id === ($data['product_id'] ?? 'apeiron-kit')) {
                error_log("[PORTAL][AUDIT] License CREATE ABORTED: User {$data['user_id']} already has license #{$lic->id} for product {$lic->product_id}");
                throw new Exception('User sudah memiliki lisensi aktif untuk produk ini');
            }
        }

        // Check for duplicate transaction
        $existingTx = self::findByTransaction($data['transaction_id']);
        if ($existingTx) {
            error_log("[PORTAL][AUDIT] License CREATE ABORTED: Transaction {$data['transaction_id']} already has license #{$existingTx->id}");
            throw new Exception('Transaksi ini sudah memiliki lisensi');
        }

        $id = Database::insert('user_licenses', [
            'user_id' => $data['user_id'],
            'transaction_id' => $data['transaction_id'],
            'license_key' => $data['license_key'],
            'api_key' => $data['api_key'] ?? null,
            'product_id' => $data['product_id'] ?? 'apeiron-kit',
            'assigned_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s')
        ]);

        error_log("[PORTAL][AUDIT] License CREATED #{$id}: TX={$data['transaction_id']}, User={$data['user_id']}, Product=" . ($data['product_id'] ?? 'apeiron-kit'));

        return self::find($id);
    }

    /**
     * Update existing license
     *
     * IMPROVEMENTS:
     * - Added audit logging
     * - Better error handling
     */
    public function update(array $data): bool
    {
        $oldData = self::find($this->id);
        if (!$oldData) {
            return false;
        }

        $updateData = [];
        if (isset($data['license_key'])) $updateData['license_key'] = $data['license_key'];
        if (isset($data['api_key'])) $updateData['api_key'] = $data['api_key'];
        if (isset($data['product_id'])) $updateData['product_id'] = $data['product_id'];

        if (empty($updateData)) {
            return false;
        }

        $result = Database::update('user_licenses', $updateData, 'id = ?', [$this->id]) > 0;

        if ($result) {
            $changes = [];
            foreach ($updateData as $key => $value) {
                $oldValue = $oldData->$key ?? 'null';
                $newValue = is_string($value) ? substr($value, 0, 20) . '...' : 'null';
                if ($key === 'license_key') {
                    $changes[] = "{$key}: {$oldValue} → {$newValue}";
                }
            }
            error_log("[PORTAL][AUDIT] License #{$this->id} UPDATED: " . implode(', ', $changes));
        }

        return $result;
    }

    /**
     * Delete license
     *
     * IMPROVEMENTS:
     * - Added audit logging
     * - Added validation
     */
    public function delete(): bool
    {
        error_log("[PORTAL][AUDIT] License #{$this->id} DELETED: TX={$this->transaction_id}, User={$this->user_id}");

        $result = Database::delete('user_licenses', 'id = ?', [$this->id]) > 0;

        return $result;
    }

    /**
     * Validate license key format
     *
     * @param string $licenseKey License key to validate
     * @return bool True if format is valid
     */
    public static function validateLicenseKey(string $licenseKey): bool
    {
        // License format: APEIRON-XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX (47 chars)
        $pattern = '/^APEIRON-[A-F0-9]{8}-[A-F0-9]{8}-[A-F0-9]{8}-[A-F0-9]{8}-[A-F0-9]{8}$/';

        $isValid = preg_match($pattern, $licenseKey) === 1;

        if (!$isValid) {
            error_log("[PORTAL][AUDIT] Invalid license key format: {$licenseKey}");
        }

        return $isValid;
    }

    /**
     * Validate API key format
     *
     * @param string $apiKey API key to validate
     * @return bool True if format is valid
     */
    public static function validateApiKey(string $apiKey): bool
    {
        // API key format: 64 hex characters
        $pattern = '/^[a-f0-9]{64}$/i';

        $isValid = preg_match($pattern, $apiKey) === 1;

        if (!$isValid) {
            error_log("[PORTAL][AUDIT] Invalid API key format: " . substr($apiKey, 0, 20) . "...");
        }

        return $isValid;
    }

    /**
     * Check if user has any active license
     *
     * IMPROVEMENTS:
     * - Added audit logging
     */
    public static function userHasLicense(int $userId, ?string $productId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM user_licenses WHERE user_id = ?";
        $params = [$userId];

        if ($productId) {
            $sql .= " AND product_id = ?";
            $params[] = $productId;
        }

        $result = Database::fetchOne($sql, $params);
        $hasLicense = (int) $result['count'] > 0;

        error_log("[PORTAL][AUDIT] License check for User #{$userId}: " . ($hasLicense ? 'HAS LICENSE' : 'NO LICENSE') . ", Product=" . ($productId ?? 'any'));

        return $hasLicense;
    }

    /**
     * Count licenses by user
     */
    public static function countByUser(int $userId): int
    {
        $result = Database::fetchOne(
            "SELECT COUNT(*) as count FROM user_licenses WHERE user_id = ?",
            [$userId]
        );
        return (int) $result['count'];
    }

    /**
     * Count active licenses by user (same as countByUser, for API compatibility)
     */
    public static function countActiveByUser(int $userId): int
    {
        $result = Database::fetchOne(
            "SELECT COUNT(*) as count FROM user_licenses WHERE user_id = ? AND is_active = 1",
            [$userId]
        );
        return (int) $result['count'];
    }

    /**
     * Get masked license key for display
     *
     * IMPROVEMENTS:
     * - Added validation
     * - Better masking
     */
    public function getMaskedKey(): string
    {
        // Validate format before masking
        if (!self::validateLicenseKey($this->license_key)) {
            // Invalid format, show first 20 chars only
            return substr($this->license_key, 0, 20);
        }

        if (strlen($this->license_key) <= 20) {
            return $this->license_key;
        }

        // APEIRON-XXXX...XXXX (show first 15 + last 5)
        $prefix = substr($this->license_key, 0, 15);
        $suffix = substr($this->license_key, -5);

        return $prefix . '...' . $suffix;
    }

    /**
     * Get masked API key for display
     *
     * IMPROVEMENTS:
     * - Added validation
     */
    public function getMaskedApiKey(): string
    {
        if (empty($this->api_key)) {
            return 'N/A';
        }

        // Validate format before masking
        if (!self::validateApiKey($this->api_key)) {
            // Invalid format, show first 20 chars only
            return substr($this->api_key, 0, 20);
        }

        // Show first 16 chars + ...
        $prefix = substr($this->api_key, 0, 16);
        $suffix = substr($this->api_key, -4);

        return $prefix . '....' . $suffix;
    }

    /**
     * Simpan jawaban server sebagai cache.
     *
     * Dipanggil hanya setelah pembacaan BERHASIL. Kegagalan membaca tidak
     * boleh menimpa cache — kalau ditimpa, satu gangguan jaringan sesaat akan
     * menghapus keadaan terakhir yang diketahui dan user melihat halaman
     * kosong alih-alih data lama yang masih berguna.
     */
    public function cacheStatus(array $data): void
    {
        $now = gmdate('Y-m-d H:i:s');

        Database::update('user_licenses', [
            'license_status_json' => json_encode($data),
            'license_checked_at' => $now,
        ], 'id = ?', [$this->id]);

        $this->license_status = $data;
        $this->license_checked_at = $now;
    }

    /**
     * Apakah cache-nya sudah waktunya disegarkan?
     *
     * Membatasi seberapa sering portal memanggil server: tanpa ini, setiap
     * kali user menyegarkan halaman akan menjadi satu permintaan keluar, dan
     * server punya rate limit.
     */
    public function needsRefresh(int $ttlSeconds = 600): bool
    {
        if ($this->license_status === null || $this->license_checked_at === null) {
            return true;
        }

        $checked = strtotime($this->license_checked_at . ' UTC');

        return $checked === false || (time() - $checked) > $ttlSeconds;
    }

    /**
     * Create from array
     */
    public static function fromArray(array $row): self
    {
        $ul = new self();
        $ul->id = (int) $row['id'];
        $ul->user_id = (int) $row['user_id'];
        $ul->transaction_id = (int) $row['transaction_id'];
        $ul->license_key = $row['license_key'];
        $ul->api_key = $row['api_key'] ?? null;
        $ul->product_id = $row['product_id'];
        $ul->assigned_at = $row['assigned_at'];
        $ul->created_at = $row['created_at'];

        // Kolom cache baru; dibaca dengan ?? supaya baris lama yang belum
        // pernah tersentuh migration tetap terbaca tanpa error.
        $ul->license_checked_at = $row['license_checked_at'] ?? null;

        if (!empty($row['license_status_json'])) {
            $decoded = json_decode($row['license_status_json'], true);
            // JSON rusak diperlakukan sama dengan belum pernah dibaca —
            // menampilkan setengah data lebih buruk daripada mengakui kosong.
            $ul->license_status = is_array($decoded) ? $decoded : null;
        }

        return $ul;
    }

    /**
     * Convert to array for JSON response
     *
     * IMPROVEMENTS:
     * - Added masked API key
     * - Better data structure
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'transaction_id' => $this->transaction_id,
            'license_key' => $this->license_key,
            'license_key_masked' => $this->getMaskedKey(),
            'api_key_masked' => $this->getMaskedApiKey(),
            'product_id' => $this->product_id,
            'assigned_at' => $this->assigned_at,
            'created_at' => $this->created_at,
            'is_valid_format' => self::validateLicenseKey($this->license_key)
        ];
    }
}
