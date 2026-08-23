<?php
/**
 * Download Logger
 * 
 * Logs download events
 */

class DownloadLogger
{
    /**
     * Log a download
     */
    public static function log(
        int $userId,
        int $userLicenseId,
        string $fileName,
        string $version,
        ?int $fileSize = null
    ): int {
        return DownloadLog::create([
            'user_id' => $userId,
            'user_license_id' => $userLicenseId,
            'file_name' => $fileName,
            'file_version' => $version,
            'file_size' => $fileSize,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    }
}
