<?php
/**
 * Download Controller
 */

require_once PORTAL_ROOT . '/src/Download/SignedUrlGenerator.php';
require_once PORTAL_ROOT . '/src/Download/DownloadLogger.php';
require_once PORTAL_ROOT . '/src/Services/FileStorageService.php';

class DownloadController
{
    /**
     * Show download page with available versions
     */
    public function index(): void
    {
        $user = auth();
        
        // Get user's licenses
        $licenses = UserLicense::findByUser($user->id);
        
        if (empty($licenses)) {
            flash('error', 'Anda belum memiliki lisensi aktif');
            redirect(base_url('/dashboard'));
        }
        
        // Get available plugin versions
        $versions = FileStorageService::getPluginVersions();
        
        // Generate signed URLs for each license and version
        $downloadLinks = [];
        foreach ($licenses as $license) {
            foreach ($versions as $version) {
                $downloadLinks[$license->id][$version['version']] = SignedUrlGenerator::generate(
                    $license->id,
                    $version['version']
                );
            }
        }
        
        include PORTAL_ROOT . '/src/Download/Views/index.php';
    }
    
    /**
     * Handle file download via signed URL
     */
    public function download(): void
    {
        $payload = $_GET['p'] ?? '';
        $signature = $_GET['s'] ?? '';
        
        if (empty($payload) || empty($signature)) {
            $this->error('Link download tidak valid');
        }
        
        // Validate signed URL
        $data = SignedUrlGenerator::validate($payload, $signature);
        
        if (!$data) {
            $this->error('Link download tidak valid atau sudah kadaluarsa');
        }
        
        // Get license
        $license = UserLicense::find($data['license_id']);
        
        if (!$license) {
            $this->error('Lisensi tidak ditemukan');
        }
        
        // Verify user owns this license
        $user = auth();
        if ($license->user_id !== $user->id) {
            $this->error('Anda tidak memiliki akses ke lisensi ini');
        }
        
        // Optional: Check license status from License Server
        // This can be enabled for stricter validation
        // Note: ServerApiService would need to be extended to support checkLicense
        // try {
        //     $status = ServerApiService::checkLicense($license->license_key);
        //     if (!$status['success'] || ($status['status'] ?? '') !== 'active') {
        //         $this->error('Lisensi tidak aktif');
        //     }
        // } catch (Exception $e) {
        //     // Allow download if License Server is unreachable
        // }
        
        // Get file path
        $version = $data['version'];
        $filePath = FileStorageService::getPluginFilePath($version);
        
        if (!$filePath || !file_exists($filePath)) {
            $this->error('File tidak ditemukan');
        }
        
        // Log download
        $fileName = basename($filePath);
        $fileSize = filesize($filePath);
        
        DownloadLogger::log(
            $user->id,
            $license->id,
            $fileName,
            $version,
            $fileSize
        );
        
        // Serve file
        $this->serveFile($filePath, $fileName);
    }
    
    /**
     * Serve file for download
     */
    private function serveFile(string $filePath, string $fileName): void
    {
        // Clear output buffer
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Prevent script timeout
        set_time_limit(0);
        
        // Stream file
        $handle = fopen($filePath, 'rb');
        while (!feof($handle)) {
            echo fread($handle, 8192);
            flush();
        }
        fclose($handle);
        
        exit;
    }
    
    /**
     * Show error and redirect
     */
    private function error(string $message): void
    {
        flash('error', $message);
        redirect(base_url('/dashboard'));
    }
}
