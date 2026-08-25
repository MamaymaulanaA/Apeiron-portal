<?php
/**
 * Admin API Controller
 * 
 * Handles admin dashboard, transaction verification, user management
 */
class AdminController
{
    /**
     * Get admin dashboard data
     */
    public function dashboard(): void
    {
        requireAdmin();

        // Get stats by status
        $waitingCount = Transaction::countByStatus(Transaction::STATUS_WAITING);
        $approvedCount = Transaction::countByStatus(Transaction::STATUS_APPROVED);
        $rejectedCount = Transaction::countByStatus(Transaction::STATUS_REJECTED);
        $pendingLicenseCount = Transaction::countByStatus(Transaction::STATUS_PENDING_LICENSE);
        $totalUsers = User::count('user');  // Only regular users, not admins

        // Get recent transactions
        $recentTransactions = Transaction::findRecent(10);

        jsonResponse([
            'stats' => [
                'waiting_verification' => $waitingCount,
                'approved' => $approvedCount,
                'rejected' => $rejectedCount,
                'pending_license' => $pendingLicenseCount,
                'total_users' => $totalUsers,
                'total_templates' => Template::count(),
                'premium_templates' => Template::countByType(false),
                'standard_templates' => Template::countByType(true)
            ],
            'recent_transactions' => $recentTransactions
        ]);
    }
    
    /**
     * Get all transactions with filter
     */
    public function transactions(): void
    {
        requireAdmin();
        
        $status = $_GET['status'] ?? null;
        $page = (int)($_GET['page'] ?? 1);
        $perPage = 15;
        
        $search = trim((string) ($_GET['search'] ?? ''));

        $transactions = Transaction::findAll($perPage, ($page - 1) * $perPage, $status, $search);
        // countMatching(), bukan countByStatus(): jumlah halaman harus dihitung
        // dengan saringan yang sama dengan isinya, termasuk kata pencarian.
        $total = Transaction::countMatching($status, $search);
        
        jsonResponse([
            'transactions' => $transactions,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage)
            ]
        ]);
    }
    
    /**
     * Get transaction detail
     */
    public function transactionDetail(array $params): void
    {
        requireAdmin();
        
        $id = $params['id'] ?? 0;
        $transaction = Transaction::findWithUser($id);
        
        if (!$transaction) {
            jsonResponse(['error' => 'Transaksi tidak ditemukan'], 404);
        }
        
        // Get document URL if exists
        $documentUrl = null;
        $documentPath = $transaction['document_path'] ?? null;
        if ($transaction['document_path']) {
            // Lewat endpoint ber-auth, bukan berkas statis: /storage/documents
            // disajikan .htaccess tanpa memeriksa siapa yang meminta.
            $documentUrl = '/api/admin/transactions/' . $transaction['id'] . '/document';
        }
        
        jsonResponse([
            'transaction' => [
                'id' => $transaction['id'],
                'user_id' => $transaction['user_id'],
                'user_name' => $transaction['user_name'],
                'user_email' => $transaction['user_email'],
                'document_path' => $documentPath,
                'document_url' => $documentUrl,
                'document_original_name' => $transaction['document_original_name'] ?? null,
                'status' => $transaction['status'],
                'admin_notes' => $transaction['admin_notes'],
                'approved_at' => $transaction['approved_at'],
                'rejected_at' => $transaction['rejected_at'],
                'created_at' => $transaction['created_at']
            ]
        ]);
    }
    
    /**
     * Approve transaction
     * IMPROVEMENTS:
     * - Added audit logging for all approvals
     * - Better error context
     * - Log admin who approved
     *
     * Delegates logic to TransactionService
     */
    public function approve(array $params): void
    {
        requireAdmin();
        applyRateLimit('admin_approve', 10, 60);

        $id = $params['id'] ?? 0;
        $admin = currentUser();
        $input = getJsonInput();

        // Log approval attempt for audit
        error_log("[PORTAL][AUDIT] Admin#{$admin->id} ({$admin->email}) approving TX#{$id}");

        $result = TransactionService::approve($id, $admin->id, $input['notes'] ?? null);

        // Log result for audit
        if ($result['success']) {
            $logMsg = "[PORTAL][AUDIT] TX#{$id} APPROVED by Admin#{$admin->id}";
            if (!empty($result['license_key'])) {
                $logMsg .= ", License: " . substr($result['license_key'], 0, 20) . "...";
            }
            if (isset($result['transaction_status']) && $result['transaction_status'] === 'pending_license') {
                $logMsg .= ", Status: PENDING_LICENSE";
            }
            error_log($logMsg);
            jsonResponse($result);
        } else {
            error_log("[PORTAL][AUDIT] TX#{$id} APPROVE FAILED: " . ($result['error'] ?? 'Unknown error'));
            jsonResponse($result, 400);
        }
    }

    /**
     * Retry license generation for pending_license transactions
     * IMPROVEMENTS:
     * - Added audit logging for retry operations
     * - Better error context
     *
     * Delegates logic to TransactionService
     */
    public function retryLicense(array $params): void
    {
        requireAdmin();
        applyRateLimit('admin_retry_license', 5, 60);

        $id = $params['id'] ?? 0;
        $admin = currentUser();

        // Log retry attempt for audit
        error_log("[PORTAL][AUDIT] Admin#{$admin->id} ({$admin->email}) retrying license for TX#{$id}");

        $result = TransactionService::retryLicense($id, $admin->id);

        // Log result for audit
        if ($result['success']) {
            $logMsg = "[PORTAL][AUDIT] TX#{$id} RETRY SUCCESS by Admin#{$admin->id}";
            if (!empty($result['license_key'])) {
                $logMsg .= ", License: " . substr($result['license_key'], 0, 20) . "...";
            }
            error_log($logMsg);
            jsonResponse($result);
        } else {
            error_log("[PORTAL][AUDIT] TX#{$id} RETRY FAILED by Admin#{$admin->id}: " . ($result['error'] ?? 'Unknown error'));
            jsonResponse($result, 400);
        }
    }

    /**
     * Reject transaction
     * IMPROVEMENTS:
     * - Added audit logging for reject operations
     * - Better error context
     *
     * - Status → rejected
     * - Save rejection reason
     */
    public function reject(array $params): void
    {
        requireAdmin();
        applyRateLimit('admin_reject', 10, 60);

        $id = $params['id'] ?? 0;
        $admin = currentUser();
        $input = getJsonInput();

        if (empty($input['notes'])) {
            error_log("[PORTAL][AUDIT] Admin#{$admin->id} attempted to reject TX#{$id} WITHOUT reason");
            jsonResponse(['error' => 'Alasan penolakan wajib diisi'], 400);
        }

        // Log rejection attempt for audit
        error_log("[PORTAL][AUDIT] Admin#{$admin->id} ({$admin->email}) rejecting TX#{$id}, Reason: {$input['notes']}");

        $result = TransactionService::reject($id, $admin->id, $input['notes']);

        // Log result for audit
        if ($result['success']) {
            error_log("[PORTAL][AUDIT] TX#{$id} REJECTED SUCCESS by Admin#{$admin->id}");
            jsonResponse($result);
        } else {
            error_log("[PORTAL][AUDIT] TX#{$id} REJECT FAILED: " . ($result['error'] ?? 'Unknown error'));
            jsonResponse($result, 400);
        }
    }

    /**
     * Get all templates
     */
    public function templates(): void
    {
        requireAdmin();
        jsonResponse(['templates' => Template::all()]);
    }

    /**
     * Create new template
     */
    public function createTemplate(): void
    {
        requireAdmin();
        
        $name = $_POST['name'] ?? '';
        $description = $_POST['description'] ?? '';
        $checkout_url = $_POST['checkout_url'] ?? '';
        $is_standard = isset($_POST['is_standard']) ? (int)$_POST['is_standard'] : 1;
        $is_recommended = isset($_POST['is_recommended']) ? (int)$_POST['is_recommended'] : 0;

        if (empty($name)) {
            jsonResponse(['error' => 'Nama template wajib diisi'], 400);
        }

        $thumbnailPath = null;
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            // Validate thumbnail extension
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($ext, $allowedExts)) {
                jsonResponse(['error' => 'Thumbnail harus berformat gambar (JPG, PNG, GIF, WEBP)'], 400);
            }
            // Validate file size (max 2MB)
            if ($_FILES['thumbnail']['size'] > 2 * 1024 * 1024) {
                jsonResponse(['error' => 'Ukuran thumbnail maksimal 2MB'], 400);
            }
            // Server-side MIME verification
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $realMime = finfo_file($finfo, $_FILES['thumbnail']['tmp_name']);
            finfo_close($finfo);
            if (!str_starts_with($realMime, 'image/')) {
                jsonResponse(['error' => 'File bukan gambar yang valid'], 400);
            }

            $filename = 'thumb_' . uniqid() . '.' . $ext;
            $uploadDir = PORTAL_ROOT . '/storage/templates/thumbnails/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $filename)) {
                $thumbnailPath = $filename;
            }
        }

        try {
            $id = Template::create([
                'name' => $name,
                'description' => $description,
                'thumbnail_path' => $thumbnailPath,
                'checkout_url' => $checkout_url,
                'is_standard' => $is_standard,
                'is_recommended' => $is_recommended
            ]);
            
            jsonResponse(['message' => 'Template berhasil dibuat', 'id' => $id]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal membuat template'], 500);
        }
    }

    /**
     * Update template (supports file upload)
     */
    public function updateTemplate(array $params): void
    {
        requireAdmin();
        $id = $params['id'] ?? 0;
        $template = Template::find($id);

        if (!$template) {
            jsonResponse(['error' => 'Template tidak ditemukan'], 404);
        }

        // Get data from $_POST (for multipart support) or fall back to JSON
        $input = !empty($_POST) ? $_POST : getJsonInput();
        
        $updateData = [];
        if (isset($input['name'])) $updateData['name'] = $input['name'];
        if (isset($input['description'])) $updateData['description'] = $input['description'];
        if (isset($input['checkout_url'])) $updateData['checkout_url'] = $input['checkout_url'];
        
        // Handle numeric conversion for boolean-like flags from POST
        if (isset($input['is_standard'])) $updateData['is_standard'] = (int)$input['is_standard'];
        if (isset($input['is_recommended'])) $updateData['is_recommended'] = (int)$input['is_recommended'];

        // Handle Thumbnail Update
        if (isset($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            // Validate thumbnail extension
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($ext, $allowedExts)) {
                jsonResponse(['error' => 'Thumbnail harus berformat gambar (JPG, PNG, GIF, WEBP)'], 400);
            }
            // Validate file size (max 2MB)
            if ($_FILES['thumbnail']['size'] > 2 * 1024 * 1024) {
                jsonResponse(['error' => 'Ukuran thumbnail maksimal 2MB'], 400);
            }
            // Server-side MIME verification
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $realMime = finfo_file($finfo, $_FILES['thumbnail']['tmp_name']);
            finfo_close($finfo);
            if (!str_starts_with($realMime, 'image/')) {
                jsonResponse(['error' => 'File bukan gambar yang valid'], 400);
            }

            $filename = 'thumb_' . uniqid() . '.' . $ext;
            $uploadDir = PORTAL_ROOT . '/storage/templates/thumbnails/';
            
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $uploadDir . $filename)) {
                // Delete old thumbnail if exists
                if ($template->thumbnail_path) {
                    $oldFile = $uploadDir . $template->thumbnail_path;
                    if (file_exists($oldFile)) @unlink($oldFile);
                }
                $updateData['thumbnail_path'] = $filename;
            }
        }

        try {
            $template->update($updateData);
            jsonResponse(['message' => 'Template berhasil diupdate']);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal mengupdate template'], 500);
        }
    }

    /**
     * Delete template
     */
    public function deleteTemplate(array $params): void
    {
        requireAdmin();
        $id = $params['id'] ?? 0;
        $template = Template::find($id);

        if (!$template) {
            jsonResponse(['error' => 'Template tidak ditemukan'], 404);
        }

        // Delete thumbnail if exists
        if ($template->thumbnail_path) {
            $file = PORTAL_ROOT . '/storage/templates/thumbnails/' . $template->thumbnail_path;
            if (file_exists($file)) unlink($file);
        }

        try {
            $template->delete();
            jsonResponse(['message' => 'Template berhasil dihapus']);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal menghapus template'], 500);
        }
    }

    /**
     * Get all plugins
     */
    public function plugins(): void
    {
        requireAdmin();
        jsonResponse(['plugins' => Plugin::all()]);
    }

    /**
     * Upload new plugin
     */
    public function uploadPlugin(): void
    {
        requireAdmin();
        applyRateLimit('admin_upload_plugin', 5, 60);
        
        $version = $_POST['version'] ?? '';
        $changelog = $_POST['changelog'] ?? '';

        if (empty($version)) {
            jsonResponse(['error' => 'Versi plugin wajib diisi'], 400);
        }

        if (!isset($_FILES['plugin']) || $_FILES['plugin']['error'] !== UPLOAD_ERR_OK) {
            jsonResponse(['error' => 'File plugin wajib diupload'], 400);
        }

        $ext = pathinfo($_FILES['plugin']['name'], PATHINFO_EXTENSION);
        if (strtolower($ext) !== 'zip') {
            jsonResponse(['error' => 'File harus berformat ZIP'], 400);
        }

        // Validate file size (max 50MB)
        if ($_FILES['plugin']['size'] > PLUGIN_MAX_SIZE) {
            jsonResponse(['error' => 'Ukuran file maksimal 50MB'], 400);
        }

        // Server-side MIME type verification
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $realMimeType = finfo_file($finfo, $_FILES['plugin']['tmp_name']);
        finfo_close($finfo);
        if (!in_array($realMimeType, ['application/zip', 'application/x-zip-compressed', 'application/octet-stream'])) {
            jsonResponse(['error' => 'File bukan ZIP yang valid'], 400);
        }

        $filename = 'apeiron-kit-' . $version . '.zip';
        $uploadDir = PORTAL_ROOT . '/storage/plugins/';
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        if (move_uploaded_file($_FILES['plugin']['tmp_name'], $uploadDir . $filename)) {
            try {
                $id = Plugin::create([
                    'version' => $version,
                    'filename' => $filename,
                    'file_path' => $filename,
                    'changelog' => $changelog,
                    'is_active' => 1
                ]);
                jsonResponse(['message' => 'Plugin berhasil diupload', 'id' => $id]);
            } catch (Exception $e) {
                jsonResponse(['error' => 'Gagal menyimpan data plugin'], 500);
            }
        } else {
            jsonResponse(['error' => 'Gagal mengupload file'], 500);
        }
    }

    /**
     * Delete plugin
     */
    public function deletePlugin(array $params): void
    {
        requireAdmin();
        $id = $params['id'] ?? 0;
        $plugin = Plugin::find($id);

        if (!$plugin) {
            jsonResponse(['error' => 'Plugin tidak ditemukan'], 404);
        }

        try {
            // Delete the physical file
            $filePath = PORTAL_ROOT . '/storage/plugins/' . $plugin->filename;
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Delete database record
            $plugin->delete();
            jsonResponse(['message' => 'Plugin berhasil dihapus']);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal menghapus plugin'], 500);
        }
    }

    /**
     * Update plugin (version, changelog, file)
     * Sends notification to all licensed users
     */
    public function updatePlugin(array $params): void
    {
        requireAdmin();
        $id = $params['id'] ?? 0;
        $plugin = Plugin::find($id);

        if (!$plugin) {
            jsonResponse(['error' => 'Plugin tidak ditemukan'], 404);
        }

        $version = $_POST['version'] ?? '';
        $changelog = $_POST['changelog'] ?? '';

        if (empty($version)) {
            jsonResponse(['error' => 'Versi plugin wajib diisi'], 400);
        }

        $updateData = [
            'version' => $version,
            'changelog' => $changelog,
            'is_active' => 1
        ];

        // Handle new file upload (optional)
        if (isset($_FILES['plugin']) && $_FILES['plugin']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['plugin']['name'], PATHINFO_EXTENSION);
            if (strtolower($ext) !== 'zip') {
                jsonResponse(['error' => 'File harus berformat ZIP'], 400);
            }

            $filename = 'apeiron-kit-' . $version . '.zip';
            $uploadDir = PORTAL_ROOT . '/storage/plugins/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Delete old file if exists and different
            if ($plugin->filename !== $filename) {
                $oldFile = $uploadDir . $plugin->filename;
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }

            if (!move_uploaded_file($_FILES['plugin']['tmp_name'], $uploadDir . $filename)) {
                jsonResponse(['error' => 'Gagal mengupload file'], 500);
            }

            $updateData['filename'] = $filename;
            $updateData['file_path'] = $filename;
        }

        try {
            $plugin->update($updateData);

            // Send notification to all users with licenses
            $notifiedCount = 0;
            try {
                $notifiedCount = Notification::notifyAllLicensedUsers(
                    'plugin_update',
                    'Update Plugin Tersedia!',
                    "Plugin Apeiron Kit versi {$version} telah tersedia. " . 
                    ($changelog ? "Changelog: {$changelog}. " : '') .
                    "Silakan download versi terbaru dari dashboard Anda.",
                    $plugin->id
                );
            } catch (Exception $notifErr) {
                error_log('Plugin update notification error: ' . $notifErr->getMessage());
            }

            jsonResponse([
                'success' => true,
                'message' => 'Plugin berhasil diupdate',
                'notified_users' => $notifiedCount
            ]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal mengupdate plugin: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get all users
     */
    public function users(): void
    {
        requireAdmin();
        
        $page = (int)($_GET['page'] ?? 1);
        $perPage = 15;
        
        $search = trim((string) ($_GET['search'] ?? ''));

        $users = User::findAll($perPage, ($page - 1) * $perPage, $search);
        $total = User::countMatching($search);
        
        jsonResponse([
            'users' => $users,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => ceil($total / $perPage)
            ]
        ]);
    }

    /**
     * Toggle user active status
     */
    public function toggleUserStatus(array $params): void
    {
        requireAdmin();
        $id = $params['id'] ?? 0;
        $user = User::find($id);

        if (!$user) {
            jsonResponse(['error' => 'User tidak ditemukan'], 404);
        }

        if ($user->role === 'admin') {
            jsonResponse(['error' => 'Tidak dapat mengubah status admin'], 403);
        }

        try {
            $user->update(['is_active' => !$user->is_active]);
            jsonResponse(['message' => 'Status user berhasil diubah']);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal mengubah status user'], 500);
        }
    }

    /**
     * Get user detail
     */
    public function userDetail(array $params): void
    {
        requireAdmin();
        $id = $params['id'] ?? 0;
        $user = User::find($id);

        if (!$user) {
            jsonResponse(['error' => 'User tidak ditemukan'], 404);
        }

        // Get user's transactions
        $transactions = Transaction::findByUser($user->id);
        
        // Get user's license
        $license = UserLicense::findByUserId($user->id);

        // Serialize user data safely (exclude password_hash)
        jsonResponse([
            'user' => [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'last_login_at' => $user->last_login_at,
            ],
            'transactions' => $transactions,
            'license' => $license
        ]);
    }

    /**
     * Create new user
     */
    public function createUser(): void
    {
        requireAdmin();
        applyRateLimit('admin_create_user', 5, 60);
        $input = getJsonInput();

        // Validate
        if (empty($input['name']) || empty($input['email']) || empty($input['password'])) {
            jsonResponse(['error' => 'Nama, email, dan password wajib diisi'], 400);
        }

        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            jsonResponse(['error' => 'Format email tidak valid'], 400);
        }

        // Check if email exists
        if (User::findByEmail($input['email'])) {
            jsonResponse(['error' => 'Email sudah terdaftar'], 400);
        }

        // Check if username exists (if provided)
        if (!empty($input['username'])) {
            $existingUsername = User::findByUsername($input['username']);
            if ($existingUsername) {
                jsonResponse(['error' => 'Username sudah digunakan'], 400);
            }
        }

        try {
            // Pass raw password - User::create() handles hashing
            $id = User::create([
                'name' => $input['name'],
                'email' => $input['email'],
                'password' => $input['password'],
                'username' => $input['username'] ?? null,
                'role' => $input['role'] ?? 'user',
                'is_active' => $input['is_active'] ?? 1
            ]);

            jsonResponse(['message' => 'User berhasil dibuat', 'id' => $id]);
        } catch (Exception $e) {
            error_log('[PORTAL][ADMIN] Create user error: ' . $e->getMessage());
            jsonResponse(['error' => 'Gagal membuat user'], 500);
        }
    }

    /**
     * Update user
     */
    public function updateUser(array $params): void
    {
        requireAdmin();
        $id = $params['id'] ?? 0;
        $user = User::find($id);

        if (!$user) {
            jsonResponse(['error' => 'User tidak ditemukan'], 404);
        }

        $input = getJsonInput();
        $updateData = [];

        if (isset($input['name'])) $updateData['name'] = $input['name'];
        if (isset($input['email'])) {
            if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['error' => 'Format email tidak valid'], 400);
            }
            // Check if email exists for other user
            $existing = User::findByEmail($input['email']);
            if ($existing && $existing->id != $id) {
                jsonResponse(['error' => 'Email sudah digunakan user lain'], 400);
            }
            $updateData['email'] = $input['email'];
        }
        if (isset($input['username'])) {
            // Check if username exists for other user
            if (!empty($input['username'])) {
                $existingUsername = User::findByUsername($input['username']);
                if ($existingUsername && $existingUsername->id != $id) {
                    jsonResponse(['error' => 'Username sudah digunakan user lain'], 400);
                }
            }
            $updateData['username'] = $input['username'] ?: null;
        }
        if (isset($input['role'])) $updateData['role'] = $input['role'];
        if (isset($input['is_active'])) $updateData['is_active'] = $input['is_active'];
        if (!empty($input['password'])) {
            // Pass raw password - User::update() handles hashing
            $updateData['password'] = $input['password'];
        }

        try {
            $user->update($updateData);
            jsonResponse(['message' => 'User berhasil diupdate']);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal mengupdate user: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete user
     */
    public function deleteUser(array $params): void
    {
        requireAdmin();
        $id = $params['id'] ?? 0;
        $user = User::find($id);

        if (!$user) {
            jsonResponse(['error' => 'User tidak ditemukan'], 404);
        }

        if ($user->role === 'admin') {
            jsonResponse(['error' => 'Tidak dapat menghapus admin'], 403);
        }

        try {
            $user->delete();
            jsonResponse(['message' => 'User berhasil dihapus']);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Gagal menghapus user'], 500);
        }
    }
}
