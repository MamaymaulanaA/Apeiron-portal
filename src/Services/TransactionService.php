<?php
/**
 * Transaction Service
 * 
 * Single source of truth for transaction approval/rejection business logic.
 * Controllers MUST delegate to this service — no duplicate logic.
 */

require_once PORTAL_ROOT . '/src/Services/ServerApiService.php';
require_once PORTAL_ROOT . '/src/Services/LicenseAssignmentService.php';

class TransactionService
{
    /**
     * Approve transaction and generate license via License Server.
     * 
     * Flow:
     * 1. Validate transaction state
     * 2. Fetch user data via User::find() (NOT from transaction properties)
     * 3. Call License Server to generate key
     * 4. Save license to Portal DB
     * 5. Update transaction status to approved
     * 6. Send notification to user
     * 
     * If License Server fails → set status to pending_license (retry later)
     * 
     * @param int $transactionId Transaction ID to approve
     * @param int $adminId Admin user ID performing the approval
     * @param string|null $notes Optional admin notes
     * @return array Result with success status, license_key, and message
     */
    public static function approve(int $transactionId, int $adminId, ?string $notes = null): array
    {
        // ── APPLICATION-LEVEL LOCKING (Prevent Race Condition) ──────────
        $lockKey = "license_approve_{$transactionId}";
        $lockFile = sys_get_temp_dir() . "/portal_{$lockKey}.lock";
        $lockHandle = null;
        $lockAcquired = false;
        $lockWaitMax = 30; // Max 30 seconds waiting for lock

        // Try to acquire lock
        for ($i = 0; $i < $lockWaitMax; $i++) {
            $lockHandle = fopen($lockFile, 'w');
            if (flock($lockHandle, LOCK_EX | LOCK_NB)) {
                $lockAcquired = true;
                break;
            }
            fclose($lockHandle);
            if (!$lockAcquired) {
                usleep(100000); // Wait 100ms before retry
            }
        }

        if (!$lockAcquired) {
            error_log("[PORTAL][ERROR] Could not acquire lock for approve TX#{$transactionId} after {$lockWaitMax}s");
            return [
                'success' => false,
                'error' => 'Sedang memproses transaksi lain. Silakan coba lagi dalam beberapa saat.'
            ];
        }

        error_log("[PORTAL][AUDIT] Lock acquired for TX#{$transactionId} by Admin#{$adminId}");

        // ── Step 0: Validate transaction ─────────────────────────────────
        $transaction = Transaction::find($transactionId);

        if (!$transaction) {
            // Release lock if acquired
            if ($lockHandle) fclose($lockHandle);
            @unlink($lockFile);

            return ['success' => false, 'error' => 'Transaksi tidak ditemukan'];
        }

        if (!$transaction->isWaiting()) {
            if ($lockHandle) fclose($lockHandle);
            @unlink($lockFile);
            return ['success' => false, 'error' => 'Transaksi tidak dalam status menunggu verifikasi'];
        }

        // Check if document is uploaded
        if (empty($transaction->document_path)) {
            if ($lockHandle) fclose($lockHandle);
            @unlink($lockFile);
            return ['success' => false, 'error' => 'Dokumen belum diupload'];
        }
        
        // ── Step 1: Fetch user data properly ──────────────────────
        $userObj = User::find($transaction->user_id);
        if (!$userObj) {
            if ($lockHandle) fclose($lockHandle);
            @unlink($lockFile);
            return ['success' => false, 'error' => 'User tidak ditemukan'];
        }

        $productId = 'apeiron-kit';

        // Log approval attempt for audit
        error_log("[PORTAL][AUDIT] Admin#{$adminId} approving TX#{$transactionId} for user={$userObj->email}"); // Default product

        // =====================================
        // CHECK EXISTING LICENSE (Database-Level Check)
        // =====================================
        // First check at application level (fast check)
        $existingLicense = UserLicense::findByUser($transaction->user_id);
        if (!empty($existingLicense)) {
            foreach ($existingLicense as $existing) {
                if ($existing->product_id === $productId) {
                    if ($lockHandle) fclose($lockHandle);
                    @unlink($lockFile);
                    return [
                        'success' => false,
                        'error' => 'User sudah memiliki lisensi aktif untuk produk ini. Satu user hanya boleh memiliki satu lisensi per produk.',
                        'existing_license_id' => $existing->id
                    ];
                }
            }
        }

        // Also check if transaction already has a license (double approve protection)
        $existingByTransaction = UserLicense::findByTransaction($transactionId);
        if ($existingByTransaction) {
            if ($lockHandle) fclose($lockHandle);
            @unlink($lockFile);
            error_log("[PORTAL] WARNING: TX#{$transactionId} already has license #{$existingByTransaction->id}, rejecting duplicate approve");
            return [
                'success' => false,
                'error' => 'Transaksi ini sudah memiliki lisensi. Tidak dapat approve ulang.',
                'existing_license_id' => $existingByTransaction->id
            ];
        }

        // ── Step 2: Call License Server ───────────────────────────
        error_log("[PORTAL] Approving TX#{$transactionId} for user={$userObj->email}");

        try {
            Database::beginTransaction();

            // Generate license via Server API
            $licenseData = ServerApiService::generateLicense(
                ['name' => $userObj->name, 'email' => $userObj->email],
                "TX-{$transactionId}",
                $productId
            );

            $licenseKey = $licenseData['license_key'];
            $apiKey = $licenseData['api_key'] ?? null;

            error_log("[PORTAL] Server response TX#{$transactionId}: " . json_encode($licenseData));

            // ── Step 3: Update transaction status ─────────────────
            $transaction->approve($adminId, $notes);

            // ── Step 4: Save license to Portal DB ─────────────────
            UserLicense::create([
                'user_id' => $transaction->user_id,
                'transaction_id' => $transactionId,
                'license_key' => $licenseKey,
                'api_key' => $apiKey,
                'product_id' => $productId
            ]);

            error_log("[PORTAL] License saved TX#{$transactionId}: key={$licenseKey}");

            Database::commit();

            // ── Step 5: Notify user ───────────────────────────────
            try {
                Notification::notifyUser(
                    $transaction->user_id,
                    'payment_approved',
                    'Pembayaran Disetujui',
                    'Pembayaran Anda telah disetujui dan lisensi berhasil dibuat. Silakan cek halaman dashboard untuk melihat lisensi Anda.',
                    $transactionId
                );
            } catch (Exception $notifErr) {
                error_log('[PORTAL] Notification error: ' . $notifErr->getMessage());
            }

            return [
                'success' => true,
                'license_key' => $licenseKey,
                'message' => 'Transaksi berhasil disetujui & Lisensi dibuat'
            ];
            
        } catch (Exception $e) {
            Database::rollback();

            // Log error with context for debugging
            error_log("[PORTAL][ERROR] Approve TX#{$transactionId} Failed: " . $e->getMessage());
            error_log("[PORTAL][ERROR] Stack trace: " . $e->getTraceAsString());

            // ── Fallback: Set to pending_license ──────────────────
            try {
                $transaction->setPendingLicense($adminId, $notes);

                try {
                    Notification::notifyUser(
                        $transaction->user_id,
                        'payment_approved',
                        'Pembayaran Disetujui',
                        'Pembayaran Anda telah disetujui. Lisensi sedang diproses dan akan segera tersedia.',
                        $transactionId
                    );
                } catch (Exception $notifErr) {
                    error_log('[PORTAL][ERROR] Notification error: ' . $notifErr->getMessage());
                }

                return [
                    'success' => true,
                    'license_key' => null,
                    'message' => 'Transaksi disetujui namun lisensi gagal dibuat (server offline)',
                    'warning' => 'Status: PENDING_LICENSE. Lisensi akan dibuat saat server tersedia.',
                    'transaction_status' => 'pending_license',
                    'retry_available' => true
                ];

            } catch (Exception $fallbackError) {
                error_log("[PORTAL][ERROR] Fallback error for TX#{$transactionId}: " . $fallbackError->getMessage());

                return [
                    'success' => false,
                    'error' => 'Gagal approve transaksi: ' . $e->getMessage(),
                    'details' => 'Status transaksi TIDAK berubah. Error: ' . $fallbackError->getMessage()
                ];
            }
        } finally {
            // =====================================
            // RELEASE LOCK (ALWAYS EXECUTED)
            // =====================================
            if ($lockHandle) {
                fclose($lockHandle);
                $lockHandle = null;
            }
            @unlink($lockFile);

            // Log lock release for debugging
            error_log("[PORTAL][DEBUG] Lock released for TX#{$transactionId}");
        }
    }
    
    /**
     * Retry license generation for pending_license transactions.
     *
     * IMPROVEMENTS:
     * - Added race condition protection with application-level locking
     * - Enhanced audit logging for all operations
     * - Better error handling with context
     * - Log server response details for debugging
     *
     * @param int $transactionId Transaction ID
     * @param int $adminId Admin user ID
     * @return array Result
     */
    public static function retryLicense(int $transactionId, int $adminId): array
    {
        // =====================================
        // APPLICATION-LEVEL LOCKING (Prevent Race Condition)
        // =====================================
        $lockKey = "license_retry_{$transactionId}";
        $lockFile = sys_get_temp_dir() . "/portal_{$lockKey}.lock";
        $lockHandle = null;
        $lockWaitMax = 30; // Max 30 seconds waiting for lock
        $lockAcquired = false;

        // Try to acquire lock
        for ($i = 0; $i < $lockWaitMax; $i++) {
            $lockHandle = fopen($lockFile, 'w');
            if (flock($lockHandle, LOCK_EX | LOCK_NB)) {
                $lockAcquired = true;
                break;
            }
            fclose($lockHandle);
            if (!$lockAcquired) {
                usleep(100000); // Wait 100ms before retry
            }
        }

        if (!$lockAcquired) {
            error_log("[PORTAL][ERROR] Could not acquire lock for retry TX#{$transactionId} after {$lockWaitMax}s");
            return [
                'success' => false,
                'error' => 'Sedang memproses transaksi lain. Silakan coba lagi dalam beberapa saat.'
            ];
        }

        error_log("[PORTAL][AUDIT] Admin#{$adminId} retrying license for TX#{$transactionId}");

        try {
            $transaction = Transaction::find($transactionId);

            if (!$transaction) {
                return [
                    'success' => false,
                    'error' => 'Transaksi tidak ditemukan',
                    'audit_id' => null
                ];
            }

            if (!$transaction->canRetryLicense()) {
                return [
                    'success' => false,
                    'error' => 'Transaksi ini tidak bisa retry lisensi',
                    'audit_id' => null
                ];
            }

            // Fetch user data properly via User::find()
            $userObj = User::find($transaction->user_id);
            if (!$userObj) {
                return [
                    'success' => false,
                    'error' => 'User tidak ditemukan',
                    'audit_id' => null
                ];
            }

            $productId = 'apeiron-kit';

            // Check for duplicate license again (double protection)
            $existingByTransaction = UserLicense::findByTransaction($transactionId);
            if ($existingByTransaction) {
                error_log("[PORTAL][WARNING] TX#{$transactionId} already has license #{$existingByTransaction->id}, skipping retry");

                return [
                    'success' => false,
                    'error' => 'Transaksi ini sudah memiliki lisensi.',
                    'license_key' => $existingByTransaction->license_key,
                    'audit_id' => $existingByTransaction->id
                ];
            }

            error_log("[PORTAL][SERVER] Calling Server API for TX#{$transactionId}, user={$userObj->email}");

            try {
                $licenseData = ServerApiService::generateLicense(
                    ['name' => $userObj->name, 'email' => $userObj->email],
                    "TX-{$transactionId}",
                    $productId
                );

                $licenseKey = $licenseData['license_key'];
                $apiKey = $licenseData['api_key'] ?? null;

                error_log("[PORTAL][SERVER] Server response SUCCESS: " . json_encode($licenseData));

                // Update to approved status
                $transaction->approve($transaction->approved_by ?? $adminId, $transaction->admin_notes);

                // Save license
                $licenseRecord = UserLicense::create([
                    'user_id' => $transaction->user_id,
                    'transaction_id' => $transactionId,
                    'license_key' => $licenseKey,
                    'api_key' => $apiKey,
                    'product_id' => $productId
                ]);

                error_log("[PORTAL][SUCCESS] License created #{$licenseRecord->id} for TX#{$transactionId}");

                return [
                    'success' => true,
                    'license_key' => $licenseKey,
                    'api_key' => $apiKey,
                    'message' => 'Lisensi berhasil dibuat',
                    'audit_id' => $licenseRecord->id
                ];

            } catch (Exception $serverError) {
                error_log("[PORTAL][SERVER] Server API ERROR: " . $serverError->getMessage());
                error_log("[PORTAL][SERVER] Stack trace: " . $serverError->getTraceAsString());

                return [
                    'success' => false,
                    'error' => 'Gagal membuat lisensi: ' . $serverError->getMessage(),
                    'details' => 'Server lisensi merespons error. Status HTTP: N/A',
                    'audit' => 'server_unavailable'
                ];
            }

        } finally {
            // =====================================
            // RELEASE LOCK (ALWAYS EXECUTED)
            // =====================================
            if ($lockHandle) {
                fclose($lockHandle);
                $lockHandle = null;
            }
            @unlink($lockFile);

            error_log("[PORTAL][DEBUG] Lock released for retry TX#{$transactionId}");
        }
    }

    /**
     * Reject transaction
     */
    public static function reject(int $transactionId, int $adminId, string $reason): array
    {
        $transaction = Transaction::find($transactionId);
        
        if (!$transaction) {
            return ['success' => false, 'error' => 'Transaksi tidak ditemukan'];
        }
        
        if (!$transaction->isWaiting() && !$transaction->isPendingLicense()) {
            return ['success' => false, 'error' => 'Transaksi tidak dalam status menunggu verifikasi'];
        }
        
        try {
            $transaction->reject($adminId, $reason);

            // Notify user
            try {
                Notification::notifyUser(
                    $transaction->user_id,
                    'payment_rejected',
                    'Pembayaran Ditolak',
                    'Pembayaran Anda ditolak. Alasan: ' . $reason . '. Silakan upload ulang bukti pembayaran yang valid.',
                    $transactionId
                );
            } catch (Exception $notifErr) {
                error_log('[PORTAL] Notification error: ' . $notifErr->getMessage());
            }
            
            return [
                'success' => true,
                'message' => 'Transaksi berhasil ditolak'
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Gagal memproses penolakan: ' . $e->getMessage()];
        }
    }
    
    /**
     * Create new transaction
     */
    public static function create(int $userId, float $amount, ?string $productId = null): array
    {
        try {
            $transaction = Transaction::create([
                'user_id' => $userId,
                'amount' => $amount,
                'product_id' => $productId ?? 'apeiron-kit'
            ]);
            
            return [
                'success' => true,
                'transaction' => $transaction,
                'message' => 'Transaksi berhasil dibuat'
            ];
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Gagal membuat transaksi: ' . $e->getMessage()];
        }
    }
    
    /**
     * Upload proof for transaction
     */
    public static function uploadProof(int $transactionId, int $userId, array $file): array
    {
        $transaction = Transaction::find($transactionId);
        
        if (!$transaction) {
            return ['success' => false, 'error' => 'Transaksi tidak ditemukan'];
        }
        
        if ($transaction->user_id !== $userId) {
            return ['success' => false, 'error' => 'Anda tidak memiliki akses ke transaksi ini'];
        }
        
        if (!$transaction->isWaiting()) {
            return ['success' => false, 'error' => 'Transaksi sudah diproses, tidak dapat upload ulang'];
        }
        
        // Save file
        $result = FileUploadSanitizer::saveProof($file);
        
        if (!$result['success']) {
            return ['success' => false, 'error' => implode(', ', $result['errors'])];
        }
        
        // Update transaction
        $transaction->updateProof($result['path'], $result['original_name']);
        
        return [
            'success' => true,
            'message' => 'Bukti pembayaran berhasil diupload'
        ];
    }
}
