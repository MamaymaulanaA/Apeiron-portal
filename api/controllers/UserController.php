<?php
/**
 * User API Controller
 * 
 * Handles user dashboard, profile, licenses, and document upload
 */
class UserController
{
    /**
     * Get user dashboard data
     */
    public function dashboard(): void
    {
        requireAuth();
        
        $user = currentUser();
        
        // Get user's active transaction
        $transaction = Transaction::findActiveByUser($user->id);
        
        // Get license if approved
        $license = null;
        if ($transaction && $transaction->isApproved()) {
            $licenseObj = UserLicense::findByTransaction($transaction->id);
            if ($licenseObj) {
                $license = [
                    'id' => $licenseObj->id,
                    'license_key' => $licenseObj->license_key,
                    'assigned_at' => $licenseObj->assigned_at,
                    'api_key' => $licenseObj->api_key ?? null
                ];
            }
        }
        
        // Get latest plugin info
        $latestPlugin = Plugin::getLatest();
        
        jsonResponse([
            'transaction' => $transaction ? [
                'id' => $transaction->id,
                'status' => $transaction->status,
                'status_label' => $transaction->getStatusLabel(),
                'document_name' => $transaction->document_original_name,
                'admin_notes' => $transaction->admin_notes,
                'created_at' => $transaction->created_at,
                'approved_at' => $transaction->approved_at,
                'rejected_at' => $transaction->rejected_at
            ] : null,
            'license' => $license,
            'templates' => Template::all(),
            'can_upload' => !$transaction || $transaction->isRejected(),
            'can_download' => $transaction && $transaction->isApproved(),
            'latest_plugin' => $latestPlugin ? [
                'version' => $latestPlugin->version,
                'filename' => $latestPlugin->filename,
                'changelog' => $latestPlugin->changelog
            ] : null
        ]);
    }
    
    /**
     * Upload document - THIS IS THE ONLY TRIGGER FOR TRANSACTION
     */
    public function uploadDocument(): void
    {
        requireAuth();
        
        $user = currentUser();
        
        // Check if user can upload
        $existingTransaction = Transaction::findActiveByUser($user->id);
        
        if ($existingTransaction && !$existingTransaction->isRejected()) {
            if ($existingTransaction->isWaiting()) {
                jsonResponse(['error' => 'Dokumen Anda sedang dalam proses verifikasi'], 400);
            }
            if ($existingTransaction->isApproved()) {
                jsonResponse(['error' => 'Dokumen Anda sudah disetujui'], 400);
            }
        }
        
        // Validate file upload
        if (empty($_FILES['document'])) {
            jsonResponse(['error' => 'Dokumen wajib diupload'], 400);
        }
        
        $file = $_FILES['document'];
    
        // Validate file extension first (never trust client MIME alone)
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
        if (!in_array($ext, $allowedExts)) {
            jsonResponse(['error' => 'Format file tidak didukung. Gunakan JPG, PNG, atau PDF'], 400);
        }
        
        // Server-side MIME type verification (cannot be spoofed via filename)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
        if (!in_array($realMimeType, $allowedMimes)) {
            jsonResponse(['error' => 'File bukan dokumen yang valid'], 400);
        }    
        
        // Validate file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            jsonResponse(['error' => 'Ukuran file maksimal 5MB'], 400);
        }
        
        try {
            // Generate unique filename
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'doc_' . $user->id . '_' . time() . '.' . $ext;
            $uploadDir = PORTAL_ROOT . '/storage/documents/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                jsonResponse(['error' => 'Gagal menyimpan file'], 500);
            }
            
            // Create or update transaction
            if ($existingTransaction && $existingTransaction->isRejected()) {
                // Update existing rejected transaction
                $existingTransaction->updateDocument($filename, $file['name']);
                $transaction = Transaction::find($existingTransaction->id);
            } else {
                // Create new transaction
                $transaction = Transaction::createFromUpload($user->id, $filename, $file['name']);
            }
            
            // Notify all admins about new upload BEFORE sending response
            // (jsonResponse calls exit, so anything after it won't run)
            try {
                Notification::notifyAdmins(
                    'transaction_uploaded',
                    'Bukti Transaksi Baru',
                    $user->name . ' telah mengupload bukti transaksi dan menunggu verifikasi.',
                    $transaction->id
                );
            } catch (Exception $notifErr) {
                // Don't fail the upload if notification fails
                error_log('Notification error: ' . $notifErr->getMessage());
            }
            
            jsonResponse([
                'message' => 'Dokumen berhasil dikirim',
                'transaction' => [
                    'id' => $transaction->id,
                    'status' => $transaction->status,
                    'status_label' => $transaction->getStatusLabel(),
                    'document_name' => $transaction->document_original_name,
                    'created_at' => $transaction->created_at
                ]
            ], 201);
            
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal mengupload dokumen'], 500);
        }
    }
    
    /**
     * Get user profile
     */
    public function profile(): void
    {
        requireAuth();
        
        $user = currentUser();
        $fullUser = User::find($user->id);
        
        jsonResponse([
            'user' => [
                'id' => $fullUser->id,
                'name' => $fullUser->name,
                'username' => $fullUser->username,
                'email' => $fullUser->email,
                'role' => $fullUser->role,
                'created_at' => $fullUser->created_at,
                'last_login_at' => $fullUser->last_login_at
            ],
            'stats' => [
                'licenses' => UserLicense::countByUser($user->id),
                'transactions' => Transaction::countByUser($user->id)
            ]
        ]);
    }
    
    /**
     * Update user profile
     */
    public function updateProfile(): void
    {
        requireAuth();
        
        $user = currentUser();
        $input = getJsonInput();
        
        $updateData = [];
        
        if (!empty($input['name'])) {
            $updateData['name'] = $input['name'];
        }
        
        // Change password
        if (!empty($input['current_password']) && !empty($input['new_password'])) {
            $fullUser = User::find($user->id);
            
            if (!password_verify($input['current_password'], $fullUser->password_hash)) {
                jsonResponse(['error' => 'Password lama salah'], 400);
            }
            
            if (strlen($input['new_password']) < 8) {
                jsonResponse(['error' => 'Password baru minimal 8 karakter'], 400);
            }
            
            $updateData['password_hash'] = password_hash($input['new_password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        
        if (empty($updateData)) {
            jsonResponse(['error' => 'Tidak ada data untuk diupdate'], 400);
        }
        
        try {
            User::updateStatic($user->id, $updateData);
            
            // Update session
            $updatedUser = User::find($user->id);
            $_SESSION['portal_user'] = $updatedUser;
            
            jsonResponse(['message' => 'Profil berhasil diupdate']);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal update profil'], 500);
        }
    }
    
    /**
     * Get all user licenses
     */
    public function licenses(): void
    {
        requireAuth();
        
        $user = currentUser();
        $licenses = UserLicense::findByUser($user->id);
        
        $latestPlugin = Plugin::getLatest();
        
        jsonResponse([
            'licenses' => array_map(function($l) {
                return [
                    'id' => $l->id,
                    'license_key' => $l->license_key,
                    'api_key' => $l->api_key ?? null,
                    'product_id' => $l->product_id,
                    'assigned_at' => $l->assigned_at
                ];
            }, $licenses),
            'latest_plugin' => $latestPlugin ? [
                'version' => $latestPlugin->version,
                'filename' => $latestPlugin->filename,
                'changelog' => $latestPlugin->changelog
            ] : null
        ]);
    }

    /**
     * Get all user transactions
     */
    public function transactions(): void
    {
        requireAuth();
        
        $user = currentUser();
        $transactions = Transaction::findByUser($user->id);
        
        jsonResponse([
            'transactions' => array_map(function($t) {
                return [
                    'id' => $t->id,
                    'status' => $t->status,
                    'status_label' => $t->getStatusLabel(),
                    'document_name' => $t->document_original_name,
                    'admin_notes' => $t->admin_notes,
                    'created_at' => $t->created_at,
                    'approved_at' => $t->approved_at,
                    'verified_at' => $t->approved_at ?: $t->rejected_at
                ];
            }, $transactions)
        ]);
    }

    /**
     * Get all templates for user view
     */
    public function templates(): void
    {
        requireAuth();
        
        $templates = Template::all();
        
        jsonResponse([
            'templates' => array_map(function($t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'description' => $t->description,
                    'thumbnail_path' => $t->thumbnail_path,
                    'checkout_url' => $t->checkout_url,
                    'is_standard' => $t->is_standard,
                    'is_recommended' => $t->is_recommended
                ];
            }, $templates)
        ]);
    }

    /**
     * Download latest plugin
     */
    public function downloadPlugin(): void
    {
        requireAuth();
        
        $user = currentUser();
        $transaction = Transaction::findActiveByUser($user->id);
        
        if (!$transaction || !$transaction->isApproved()) {
            jsonResponse(['error' => 'Akses ditolak: Lisensi aktif diperlukan'], 403);
        }
        
        // Find latest active plugin using dedicated method
        $latest = Plugin::getLatest();
        
        if (!$latest) {
            jsonResponse(['error' => 'Tidak ada versi plugin aktif'], 404);
        }
        
        $filePath = PORTAL_ROOT . '/storage/plugins/' . $latest->filename;
        
        if (!file_exists($filePath)) {
            error_log('Plugin file not found: ' . $filePath);
            jsonResponse(['error' => 'File plugin tidak ditemukan di server'], 404);
        }
        
        // Serve file download
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $latest->filename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache');
        header('Pragma: no-cache');
        
        // Clean any output buffers to prevent corruption
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        readfile($filePath);
        exit;
    }

    /**
     * Download Invoice
     * Generates a simple HTML invoice for the approved transaction
     */
    public function downloadInvoice(): void
    {
        requireAuth();

        $user = currentUser();
        $transaction = Transaction::findActiveByUser($user->id);

        if (!$transaction || !$transaction->isApproved()) {
            http_response_code(403);
            die('Access denied: No approved transaction found');
        }

        // Get license info
        $license = UserLicense::findByTransaction($transaction->id);

        // Generate invoice ID
        $invoiceId = 'INV-' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT);

        // Generate HTML invoice
        $html = self::generateInvoiceHtml($invoiceId, $user, $transaction, $license);

        // Set headers for download
        $filename = 'invoice_' . $invoiceId . '.html';
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($html));
        header('Cache-Control: no-cache');

        echo $html;
        exit;
    }

    /**
     * Generate HTML invoice
     */
    private static function generateInvoiceHtml(string $invoiceId, $user, $transaction, $license): string
    {
        $approvedDate = date('d M Y H:i', strtotime($transaction->approved_at));

        return '<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice ' . htmlspecialchars($invoiceId) . ' - ' . htmlspecialchars(APP_NAME) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background: #f5f5f5; }
        .invoice { max-width: 800px; margin: 0 auto; background: white; padding: 40px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { border-bottom: 3px solid #333; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { margin: 0; color: #333; }
        .header p { margin: 5px 0 0; color: #666; }
        .info { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .info-section { flex: 1; }
        .info-section h3 { margin: 0 0 10px; color: #333; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .info-section p { margin: 5px 0; color: #555; }
        .items { margin: 30px 0; }
        .items table { width: 100%; border-collapse: collapse; }
        .items th, .items td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .items th { background: #f8f8f8; font-weight: bold; color: #333; }
        .items .total { font-size: 1.2em; font-weight: bold; color: #2ecc71; }
        .license-box { background: #f8f8f8; padding: 20px; margin: 30px 0; border-left: 4px solid #3498db; }
        .license-box h4 { margin: 0 0 10px; color: #2980b9; }
        .license-box pre { background: #fff; padding: 15px; border: 1px solid #ddd; overflow-x: auto; font-size: 12px; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #888; font-size: 12px; }
        @media print { body { background: white; } .invoice { box-shadow: none; padding: 0; } }
    </style>
</head>
<body>
    <div class="invoice">
        <div class="header">
            <h1>' . htmlspecialchars(APP_NAME) . '</h1>
            <p>Tax Invoice / Bukti Pembayaran</p>
        </div>

        <div class="info">
            <div class="info-section">
                <h3>Invoice Details</h3>
                <p><strong>Invoice No:</strong> ' . htmlspecialchars($invoiceId) . '</p>
                <p><strong>Tanggal:</strong> ' . $approvedDate . '</p>
                <p><strong>Status:</strong> <span style="color: green;">LUNAS</span></p>
            </div>
            <div class="info-section" style="text-align: right;">
                <h3>Customer Details</h3>
                <p><strong>Nama:</strong> ' . htmlspecialchars($user->name) . '</p>
                <p><strong>Email:</strong> ' . htmlspecialchars($user->email) . '</p>
                <p><strong>Customer ID:</strong> #' . str_pad($user->id, 5, '0', STR_PAD_LEFT) . '</p>
            </div>
        </div>

        <div class="items">
            <table>
                <thead>
                    <tr>
                        <th>Deskripsi</th>
                        <th>Periode</th>
                        <th style="text-align: right;">Jumlah</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>Apeiron Kit License</strong><br>Lifetime access to premium plugin</td>
                        <td>Lifetime</td>
                        <td style="text-align: right;" class="total">1 License</td>
                    </tr>
                </tbody>
            </table>
        </div>';

        if ($license) {
            $html .= '
        <div class="license-box">
            <h4>Lisensi Anda</h4>
            <p>Silakan simpan license key Anda dengan aman:</p>
            <pre>' . htmlspecialchars($license->license_key) . '</pre>
            <p style="margin: 10px 0 0 0; font-size: 12px; color: #666;">
                <strong>Product:</strong> ' . htmlspecialchars($license->product_id) . '<br>
                <strong>Assigned:</strong> ' . $approvedDate . '
            </p>
        </div>';
        }

        $html .= '
        <div class="footer">
            <p>Terima kasih atas kepercayaan Anda menggunakan ' . htmlspecialchars(APP_NAME) . '.</p>
            <p>Invoice ini dibuat secara otomatis dan sah sebagai bukti pembayaran.</p>
            <p style="margin-top: 15px;">&copy; ' . date('Y') . ' ' . htmlspecialchars(APP_NAME) . '. All rights reserved.</p>
        </div>
    </div>
</body>
</html>';
    }

    // ============================================
    // NOTIFICATION METHODS
    // ============================================

    /**
     * Get notifications for current user
     */
    public function notifications(): void
    {
        requireAuth();
        $user = currentUser();

        $notifications = Notification::findByUser($user->id);

        jsonResponse([
            'notifications' => array_map(function ($n) {
                return $n->toArray();
            }, $notifications),
            'unread_count' => Notification::countUnread($user->id)
        ]);
    }

    /**
     * Get unread count only (lightweight)
     */
    public function notificationsUnreadCount(): void
    {
        requireAuth();
        $user = currentUser();

        jsonResponse([
            'unread_count' => Notification::countUnread($user->id)
        ]);
    }

    /**
     * Mark a single notification as read
     */
    public function markNotificationRead(array $params): void
    {
        requireAuth();
        $user = currentUser();
        $id = $params['id'] ?? 0;

        $notification = Notification::find($id);

        if (!$notification || $notification->user_id !== $user->id) {
            jsonResponse(['error' => 'Notifikasi tidak ditemukan'], 404);
        }

        $notification->markAsRead();

        jsonResponse(['message' => 'Notifikasi ditandai sudah dibaca']);
    }

    /**
     * Mark all notifications as read
     */
    public function markAllNotificationsRead(): void
    {
        requireAuth();
        $user = currentUser();

        $count = Notification::markAllAsRead($user->id);

        jsonResponse([
            'message' => $count . ' notifikasi ditandai sudah dibaca',
            'count' => $count
        ]);
    }
}
