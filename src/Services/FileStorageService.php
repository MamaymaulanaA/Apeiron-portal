<?php
/**
 * File Storage Service
 * 
 * Handles plugin file management
 */

class FileStorageService
{
    /**
     * Get list of available plugin versions
     */
    public static function getPluginVersions(): array
    {
        $versions = [];
        $pluginDir = STORAGE_PLUGINS;
        
        if (!is_dir($pluginDir)) {
            return $versions;
        }
        
        $directories = glob($pluginDir . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            $version = basename($dir);
            $files = glob($dir . '/*.zip');
            
            if (!empty($files)) {
                $file = $files[0];
                $versions[] = [
                    'version' => $version,
                    'file_name' => basename($file),
                    'file_path' => str_replace(STORAGE_PATH . '/', '', $file),
                    'file_size' => filesize($file),
                    'uploaded_at' => date('Y-m-d H:i:s', filemtime($file))
                ];
            }
        }
        
        // Sort by version descending
        usort($versions, function($a, $b) {
            return version_compare($b['version'], $a['version']);
        });
        
        return $versions;
    }
    
    /**
     * Get latest plugin version
     */
    public static function getLatestVersion(): ?array
    {
        $versions = self::getPluginVersions();
        return $versions[0] ?? null;
    }
    
    /**
     * Upload new plugin version
     */
    public static function uploadPlugin(array $file, string $version): array
    {
        // Validate version format
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
            return ['success' => false, 'errors' => ['Format versi tidak valid. Gunakan format: x.x.x']];
        }
        
        return FileUploadSanitizer::savePlugin($file, $version);
    }
    
    /**
     * Delete plugin version
     */
    public static function deletePluginVersion(string $version): bool
    {
        $versionDir = STORAGE_PLUGINS . '/' . $version;
        
        if (!is_dir($versionDir)) {
            return false;
        }
        
        // Delete all files in the directory
        $files = glob($versionDir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        
        // Remove directory
        return rmdir($versionDir);
    }
    
    /**
     * Get plugin file path
     */
    public static function getPluginFilePath(string $version): ?string
    {
        $versionDir = STORAGE_PLUGINS . '/' . $version;
        
        if (!is_dir($versionDir)) {
            return null;
        }
        
        $files = glob($versionDir . '/*.zip');
        
        return $files[0] ?? null;
    }
    
    /**
     * Check if version exists
     */
    public static function versionExists(string $version): bool
    {
        return is_dir(STORAGE_PLUGINS . '/' . $version);
    }
}
