<?php
/**
 * Admin File Controller
 */

require_once PORTAL_ROOT . '/src/Services/FileStorageService.php';

class FileController
{
    /**
     * Show files management
     */
    public function index(): void
    {
        $versions = FileStorageService::getPluginVersions();
        
        include PORTAL_ROOT . '/src/Admin/Views/files.php';
    }
    
    /**
     * Upload new plugin version
     */
    public function upload(): void
    {
        CsrfProtection::validateOrFail();
        
        $admin = auth();
        $version = trim($_POST['version'] ?? '');
        
        // Validate version
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            flash('error', 'Format versi tidak valid. Gunakan format: x.x.x (contoh: 1.0.0)');
            redirect(base_url('/admin/files'));
        }
        
        // Check if version exists
        if (FileStorageService::versionExists($version)) {
            flash('error', 'Versi ini sudah ada. Hapus terlebih dahulu jika ingin mengupload ulang.');
            redirect(base_url('/admin/files'));
        }
        
        // Check file upload
        if (!isset($_FILES['plugin']) || $_FILES['plugin']['error'] === UPLOAD_ERR_NO_FILE) {
            flash('error', 'File plugin wajib diupload');
            redirect(base_url('/admin/files'));
        }
        
        // Upload file
        $result = FileStorageService::uploadPlugin($_FILES['plugin'], $version);
        
        if (!$result['success']) {
            flash('error', implode('<br>', $result['errors']));
            redirect(base_url('/admin/files'));
        }
        
        // Log action
        AuditLogger::logFileUpload($admin->id, $result['original_name'], $version);
        
        flash('success', 'Plugin versi ' . e($version) . ' berhasil diupload');
        redirect(base_url('/admin/files'));
    }
    
    /**
     * Delete plugin version
     */
    public function delete(): void
    {
        CsrfProtection::validateOrFail();
        
        $admin = auth();
        $version = trim($_POST['version'] ?? '');
        
        if (empty($version)) {
            flash('error', 'Versi tidak valid');
            redirect(base_url('/admin/files'));
        }
        
        if (FileStorageService::deletePluginVersion($version)) {
            AuditLogger::log(
                $admin->id,
                'delete',
                'plugin_file',
                null,
                $version,
                null,
                "Deleted plugin version {$version}"
            );
            flash('success', 'Plugin versi ' . e($version) . ' berhasil dihapus');
        } else {
            flash('error', 'Gagal menghapus plugin');
        }
        
        redirect(base_url('/admin/files'));
    }
}
