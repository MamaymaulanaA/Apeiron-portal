<?php
/**
 * Storage Configuration
 * 
 * Konfigurasi penyimpanan file (bukti transfer, plugin, dll)
 */

// Prevent direct access
if (!defined('PORTAL_ROOT')) {
    die('Direct access not permitted');
}

// Base storage path (outside public folder for security)
define('STORAGE_PATH', PORTAL_ROOT . '/storage');

// Storage directories
define('STORAGE_PROOFS', STORAGE_PATH . '/proofs');
define('STORAGE_PLUGINS', STORAGE_PATH . '/plugins');
define('STORAGE_LOGS', STORAGE_PATH . '/logs');
define('STORAGE_CACHE', STORAGE_PATH . '/cache');

// File upload settings for proof images
define('PROOF_MAX_SIZE', 5 * 1024 * 1024); // 5MB
define('PROOF_ALLOWED_TYPES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'application/pdf'
]);
define('PROOF_ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'pdf']);

// Plugin file settings
define('PLUGIN_ALLOWED_TYPES', [
    'application/zip',
    'application/x-zip-compressed'
]);
define('PLUGIN_ALLOWED_EXTENSIONS', ['zip']);
define('PLUGIN_MAX_SIZE', 50 * 1024 * 1024); // 50MB

// Signed URL settings for downloads
define('SIGNED_URL_EXPIRY', 300); // 5 minutes
define('SIGNED_URL_SECRET', getenv('SIGNED_URL_SECRET') ?: APP_SECRET_KEY);

/**
 * Get storage path for proofs organized by year/month
 */
function get_proof_storage_path(?string $date = null): string
{
    $date = $date ?? date('Y-m');
    [$year, $month] = explode('-', $date);
    
    $path = STORAGE_PROOFS . "/{$year}/{$month}";
    
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    
    return $path;
}

/**
 * Get storage path for plugin versions
 */
function get_plugin_storage_path(string $version): string
{
    $path = STORAGE_PLUGINS . "/{$version}";
    
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    
    return $path;
}

/**
 * Ensure all storage directories exist
 */
function ensure_storage_directories(): void
{
    $directories = [
        STORAGE_PATH,
        STORAGE_PROOFS,
        STORAGE_PLUGINS,
        STORAGE_LOGS,
        STORAGE_CACHE
    ];
    
    foreach ($directories as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

/**
 * Get human-readable file size
 */
function format_file_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}
