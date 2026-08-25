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
    /**
     * Susun satu baris lisensi beserta keadaannya menurut License Server.
     *
     * Portal TIDAK menghitung status, masa berlaku, maupun sisa hari. Semua
     * angka itu datang apa adanya dari server; di sini hanya diteruskan.
     *
     * Tiga keadaan yang mungkin, dan ketiganya dibedakan dengan jujur:
     *   - terbaca        → status dari server, status_stale = false
     *   - server diam    → status dari cache terakhir, status_stale = true
     *   - belum pernah   → status = null, tampilan menyebutnya belum diketahui
     *
     * Yang sengaja TIDAK dilakukan: menyimpulkan "aktif" hanya karena barisnya
     * ada di database portal. Adanya baris berarti lisensi pernah diterbitkan,
     * bukan bahwa ia masih berlaku hari ini.
     */
    private static function licensePayload(UserLicense $l): array
    {
        $state = self::licenseStatusFor($l);

        return [
            'id' => $l->id,
            'license_key' => $l->license_key,
            'api_key' => $l->api_key ?? null,
            'product_id' => $l->product_id,
            'assigned_at' => $l->assigned_at,

            // null = keadaannya belum diketahui, BUKAN tidak aktif.
            'status' => $state['status'],
            'status_stale' => $state['stale'],
            'status_checked_at' => $state['checked_at'],
            // Diisi hanya bila ada yang perlu dikerjakan admin, mis. migration
            // yang belum dijalankan. null pada keadaan normal.
            'status_error' => $state['error'],
        ];
    }

    /**
     * Baca keadaan satu lisensi: dari server bila perlu disegarkan, kalau
     * tidak dari cache.
     *
     * Dipakai bersama oleh halaman Plugin dan invoice. Sengaja satu jalur:
     * kalau keduanya membaca sendiri-sendiri, invoice bisa menyebut status
     * yang berbeda dari yang dilihat user di layar — dan yang mana yang benar
     * jadi tidak bisa dipastikan.
     *
     * @return array{status: ?array, stale: bool, checked_at: ?string, error: ?string}
     */
    private static function licenseStatusFor(UserLicense $l, bool $force = false): array
    {
        $status = $l->license_status;
        $stale = false;
        $checkedAt = $l->license_checked_at;
        $error = null;

        // $force dipakai invoice: dokumen itu diunduh lalu disimpan, jadi
        // angkanya harus benar pada detik diunduh — bukan sisa cache sepuluh
        // menit lalu. Halaman Plugin tetap memakai cache karena ia dibuka
        // berkali-kali dan servernya punya rate limit.
        if ($force || $l->needsRefresh()) {
            $fresh = ServerApiService::fetchLicenseStatus($l->license_key, $l->product_id);

            if ($fresh !== null) {
                $status = $fresh;

                // Menyimpan cache TIDAK BOLEH menjatuhkan halaman.
                //
                // Cache hanyalah percepatan; lisensinya sendiri sudah terbaca
                // dengan benar di baris atas. Membiarkan kegagalan menulis
                // naik ke router berarti satu kolom yang belum ada — atau satu
                // gangguan database sesaat — mematikan seluruh halaman Plugin
                // beserta license key yang sebenarnya sudah di tangan. Persis
                // itu yang terjadi sebelum penjagaan ini ada.
                try {
                    $l->cacheStatus($fresh);
                    $checkedAt = $l->license_checked_at;
                } catch (Throwable $e) {
                    error_log('[PORTAL][LICENSE] Gagal menyimpan cache status: ' . $e->getMessage());
                    $error = self::cacheErrorHint($e);
                    // Statusnya baru saja dibaca dari server, jadi waktunya
                    // sekarang — meski tidak berhasil tersimpan.
                    $checkedAt = gmdate('Y-m-d H:i:s');
                }
            } else {
                // Gagal membaca. Cache lama dipertahankan apa adanya dan
                // ditandai basi — menimpanya dengan null akan mengubah
                // gangguan jaringan sesaat menjadi kehilangan data.
                $stale = $status !== null;
            }
        }

        return ['status' => $status, 'stale' => $stale, 'checked_at' => $checkedAt, 'error' => $error];
    }

    /**
     * Terjemahkan kegagalan menulis cache jadi petunjuk yang bisa dikerjakan.
     *
     * Kolom yang belum ada adalah satu-satunya penyebab yang benar-benar
     * mungkin di sini, dan penyebabnya selalu sama: migration belum
     * dijalankan. Menyebut nama berkasnya jauh lebih berguna daripada
     * "terjadi kesalahan".
     */
    private static function cacheErrorHint(Throwable $e): string
    {
        $msg = $e->getMessage();
        $missingColumn = str_contains($msg, '42S22')
            || stripos($msg, 'no such column') !== false
            || stripos($msg, 'Unknown column') !== false;

        if ($missingColumn) {
            return 'Kolom cache status belum ada. Jalankan database/migrations/2026_08_25_all.sql.';
        }

        return 'Status terbaca dari server, tetapi gagal disimpan sebagai cache.';
    }

    public function licenses(): void
    {
        requireAuth();
        
        $user = currentUser();
        $licenses = UserLicense::findByUser($user->id);
        
        $latestPlugin = Plugin::getLatest();
        
        jsonResponse([
            'licenses' => array_map([self::class, 'licensePayload'], $licenses),
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
     * GET /user/download-invoice
     *
     * Invoice dibuat dari data yang benar-benar tersimpan: nominal transaksi,
     * tanggal persetujuan, license key, dan keadaan lisensi menurut License
     * Server. Tidak ada nilai contoh di dalamnya.
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

        $license = UserLicense::findByTransaction($transaction->id);

        // Keadaan lisensi dibaca lewat jalur yang sama dengan halaman Plugin,
        // supaya invoice tidak pernah menyebut status yang berbeda dari yang
        // dilihat user di layar.
        $status = $license ? (self::licenseStatusFor($license, true)['status'] ?? null) : null;

        $invoiceId = 'INV-' . str_pad((string) $transaction->id, 6, '0', STR_PAD_LEFT);

        $html = self::generateInvoiceHtml($invoiceId, $user, $transaction, $license, $status);

        $filename = 'invoice_' . $invoiceId . '.html';
        header('Content-Type: text/html; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($html));
        header('Cache-Control: private, no-store');

        echo $html;
        exit;
    }

    /** Label status lisensi, mencerminkan LICENSE_STATUS di lib/license.js. */
    private const LICENSE_LABELS = [
        'active' => 'Aktif',
        'expired' => 'Expired',
        'suspended' => 'Ditangguhkan',
        'expired_replaced' => 'Digantikan',
        'inactive' => 'Belum Diaktifkan',
    ];

    /**
     * Ringkasan keadaan lisensi, mis. "Aktif • 327 hari lagi".
     *
     * Sama persis dengan aturan di layar: Lifetime dan Expired tidak pernah
     * mendapat hitung mundur, dan sisa 0 hari tetap ditampilkan karena 0
     * adalah angka yang sah — habis hari ini.
     */
    private static function licenseSummary(?array $s): string
    {
        if (!$s) return 'Belum diketahui';

        $label = self::LICENSE_LABELS[$s['status'] ?? ''] ?? ($s['status'] ?? 'Belum diketahui');

        if (!empty($s['is_lifetime'])) return $label;
        if (($s['status'] ?? '') === 'expired') return $label;
        if (!isset($s['days_remaining'])) return $label;

        return $label . ' • ' . (int) $s['days_remaining'] . ' hari lagi';
    }

    /**
     * Tanggal untuk invoice; '-' bila datanya memang tidak ada.
     *
     * Memakai format_date(), BUKAN date(). Aplikasi menyetel zona waktu PHP ke
     * UTC untuk penyimpanan dan perhitungan, jadi date() akan mencetak jam UTC
     * — dan invoice ini memberi label "WIB" di belakangnya. Hasilnya jam yang
     * meleset tujuh jam sambil mengaku waktu Jakarta. format_date() yang
     * melakukan konversinya, sama seperti bagian aplikasi lain.
     */
    private static function invoiceDate(?string $value, bool $withTime = false): string
    {
        if (!$value) return '-';
        if (strtotime($value) === false) return '-';

        return format_date($value, $withTime ? 'd M Y, H:i' : 'd M Y') . ($withTime ? ' WIB' : '');
    }

    /**
     * Susun invoice sebagai satu berkas HTML utuh.
     *
     * YANG SENGAJA TIDAK ADA DI SINI: license key, API key, dan nominal.
     *
     * Kunci dihilangkan bukan karena tidak muat, tetapi karena invoice adalah
     * berkas yang diunduh, disimpan, diteruskan lewat email, dan kadang
     * dicetak. Menaruh kunci di dalamnya membuat setiap salinan dokumen ini
     * menjadi salinan kredensial. Kuncinya tetap tersedia di halaman Plugin,
     * di balik login, yang memang tempatnya.
     *
     * Nominal dihilangkan atas permintaan: dokumen ini dipakai sebagai bukti
     * kepemilikan lisensi, bukan rincian tagihan.
     *
     * Versi sebelumnya juga menyusun HTML-nya dengan `return` lalu menambahkan
     * sisanya dengan `$html .=` di bawahnya — kode yang tidak pernah berjalan,
     * sehingga berkas yang terunduh terpotong tepat setelah tabel. Di sini
     * seluruh dokumen disusun dalam satu rangkaian.
     */
    private static function generateInvoiceHtml(
        string $invoiceId,
        $user,
        $transaction,
        $license,
        ?array $status
    ): string {
        $e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $app = $e(APP_NAME);
        $product = $e($transaction->product_id ?? 'apeiron-kit');

        // Periode diambil dari server. Tidak lagi ditulis "Lifetime" begitu
        // saja — sebelumnya invoice selalu mengklaim Lifetime padahal server
        // menerbitkan lisensi satu tahun.
        $period = $status
            ? $e($status['duration_label'] ?? (!empty($status['is_lifetime']) ? 'Lifetime' : 'Berjangka'))
            : '-';

        $body = '';
        if ($license) {
            $facts = [
                ['Produk', $product],
                ['Tipe lisensi', $period],
                ['Status', $e(self::licenseSummary($status))],
                ['Tanggal aktivasi', $e(self::invoiceDate($status['activated_at'] ?? null))],
            ];

            if ($status) {
                // Lifetime tidak punya tanggal berakhir, jadi barisnya berbunyi
                // "Selamanya" alih-alih tanggal kosong.
                $facts[] = !empty($status['is_lifetime'])
                    ? ['Berlaku hingga', 'Selamanya']
                    : [
                        ($status['status'] ?? '') === 'expired' ? 'Berakhir' : 'Berlaku hingga',
                        $e(self::invoiceDate($status['expires_at'] ?? null)),
                    ];

                if (
                    empty($status['is_lifetime'])
                    && ($status['status'] ?? '') !== 'expired'
                    && isset($status['days_remaining'])
                ) {
                    $facts[] = ['Sisa waktu', (int) $status['days_remaining'] . ' hari'];
                }
            }

            $rows = '';
            foreach ($facts as [$k, $v]) {
                $rows .= '<div class="fact"><dt>' . $k . '</dt><dd>' . $v . '</dd></div>';
            }

            $body = '
      <section class="panel">
        <h2>Lisensi</h2>
        <dl class="facts">' . $rows . '</dl>' .
        ($status ? '' : '
        <p class="note">Status lisensi belum bisa dibaca dari server saat invoice ini dibuat.</p>') . '
        <p class="note">License key dan API key tidak dicantumkan di dokumen ini. Keduanya tersedia di halaman Plugin &amp; Lisensi setelah Anda masuk.</p>
      </section>';
        } else {
            $body = '
      <section class="panel">
        <h2>Lisensi</h2>
        <p class="note">Belum ada lisensi yang terhubung dengan transaksi ini.</p>
      </section>';
        }

        return '<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Invoice ' . $e($invoiceId) . ' — ' . $app . '</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Ubuntu:wght@300;400;500;700&family=Ubuntu+Mono:wght@400;700&display=swap" rel="stylesheet">
<style>
  /* Warna dan ukuran mengikuti token portal supaya invoice terlihat satu
     keluarga dengan aplikasinya. Ditulis sebagai nilai tetap, bukan var(),
     karena berkas ini berdiri sendiri tanpa CSS portal. */
  :root {
    --brand: #2563eb;
    --brand-soft: #eff4ff;
    --brand-border: #b9d0fd;
    --ink: #101828;
    --text: #344054;
    --muted: #667085;
    --line: #e4e7ec;
    --subtle: #f9fafb;
  }

  * { box-sizing: border-box; }

  /* Ubuntu didahulukan; sisanya cadangan agar invoice tetap terbaca wajar
     ketika dibuka tanpa internet dan font Google tidak termuat. */
  body {
    margin: 0;
    padding: 32px 16px;
    background: var(--subtle);
    font-family: "Ubuntu", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
    font-size: 14px;
    line-height: 1.55;
    color: var(--text);
    -webkit-font-smoothing: antialiased;
  }

  .sheet {
    max-width: 720px;
    margin: 0 auto;
    background: #fff;
    border: 1px solid var(--line);
    border-radius: 12px;
    overflow: hidden;
  }

  .head {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    justify-content: space-between;
    align-items: flex-start;
    padding: 28px 32px;
    border-bottom: 1px solid var(--line);
  }
  .brand { font-size: 20px; font-weight: 500; color: var(--ink); }
  .brand span { display: block; font-size: 13px; font-weight: 400; color: var(--muted); }
  .doc { text-align: right; }
  .doc-no { font-size: 20px; font-weight: 500; color: var(--ink); font-family: "Ubuntu Mono", monospace; }
  .doc-date { font-size: 13px; color: var(--muted); }

  .paid {
    display: inline-block;
    margin-top: 6px;
    padding: 2px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 500;
    color: var(--brand);
    background: var(--brand-soft);
    border: 1px solid var(--brand-border);
  }

  .parties {
    display: flex;
    flex-wrap: wrap;
    gap: 24px;
    padding: 24px 32px;
  }
  .party { flex: 1 1 220px; min-width: 0; }
  .party h2 { margin: 0 0 8px; font-size: 11px; font-weight: 500; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }
  .party p { margin: 0 0 2px; }
  .party strong { color: var(--ink); font-weight: 500; }

  .panel { padding: 24px 32px; border-top: 1px solid var(--line); }
  .panel h2 { margin: 0 0 14px; font-size: 11px; font-weight: 500; letter-spacing: .06em; text-transform: uppercase; color: var(--muted); }

  /* auto-fit: jumlah kolom mengikuti berapa baris yang benar-benar ada,
     karena Lifetime dan Expired sengaja menampilkan lebih sedikit. */
  .facts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 14px 20px;
    margin: 0;
    padding: 18px;
    background: var(--subtle);
    border: 1px solid var(--line);
    border-radius: 8px;
  }
  .fact dt { font-size: 12px; color: var(--muted); }
  .fact dd { margin: 2px 0 0; font-weight: 500; color: var(--ink); }

  .note { margin: 12px 0 0; font-size: 12px; color: var(--muted); }

  .foot { padding: 22px 32px; text-align: center; font-size: 12px; color: var(--muted); background: var(--subtle); border-top: 1px solid var(--line); }
  .foot p { margin: 0 0 4px; }

  @media print {
    body { padding: 0; background: #fff; }
    .sheet { border: 0; border-radius: 0; max-width: none; }
    .foot { background: #fff; }
    /* Blok lisensi jangan terbelah dua halaman. */
    .panel, .facts { break-inside: avoid; }
  }

  @media (max-width: 560px) {
    .head, .parties, .panel, .foot { padding-left: 20px; padding-right: 20px; }
    .doc { text-align: left; }
  }
</style>
</head>
<body>
  <main class="sheet">
    <header class="head">
      <div class="brand">
        ' . $app . '
        <span>Bukti kepemilikan lisensi</span>
      </div>
      <div class="doc">
        <div class="doc-no">' . $e($invoiceId) . '</div>
        <div class="doc-date">' . $e(self::invoiceDate($transaction->approved_at ?? null, true)) . '</div>
        <span class="paid">LUNAS</span>
      </div>
    </header>

    <section class="parties">
      <div class="party">
        <h2>Diterbitkan untuk</h2>
        <p><strong>' . $e($user->name) . '</strong></p>
        <p>' . $e($user->email) . '</p>
        <p>ID Pelanggan #' . $e(str_pad((string) $user->id, 5, '0', STR_PAD_LEFT)) . '</p>
      </div>
      <div class="party">
        <h2>Rincian transaksi</h2>
        <p>No. Transaksi <strong>#' . $e($transaction->id) . '</strong></p>
        <p>Diajukan ' . $e(self::invoiceDate($transaction->created_at ?? null)) . '</p>
        <p>Disetujui ' . $e(self::invoiceDate($transaction->approved_at ?? null)) . '</p>
      </div>
    </section>
' . $body . '
    <footer class="foot">
      <p>Terima kasih telah menggunakan ' . $app . '.</p>
      <p>Status lisensi di atas menunjukkan keadaan pada ' . $e(self::invoiceDate(gmdate('Y-m-d H:i:s'), true)) . ', saat dokumen ini diunduh.</p>
      <p>Dokumen ini dibuat otomatis dan sah tanpa tanda tangan.</p>
      <p>&copy; ' . date('Y') . ' ' . $app . '</p>
    </footer>
  </main>
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
