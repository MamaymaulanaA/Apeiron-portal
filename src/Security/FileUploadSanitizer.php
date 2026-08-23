<?php
/**
 * File Upload Sanitizer
 * 
 * Validates and sanitizes uploaded files
 */

class FileUploadSanitizer
{
    /**
     * Validate proof image upload
     */
    public static function validateProof(array $file): array
    {
        $errors = [];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = self::getUploadErrorMessage($file['error']);
            return $errors;
        }
        
        // Check file size
        if ($file['size'] > PROOF_MAX_SIZE) {
            $errors[] = 'File terlalu besar. Maksimal ' . format_file_size(PROOF_MAX_SIZE);
        }
        
        // Check extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, PROOF_ALLOWED_EXTENSIONS)) {
            $errors[] = 'Tipe file tidak diizinkan. Hanya: ' . implode(', ', PROOF_ALLOWED_EXTENSIONS);
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, PROOF_ALLOWED_TYPES)) {
            $errors[] = 'Tipe file tidak valid';
        }
        
        // Additional security checks for images
        if (strpos($mimeType, 'image/') === 0) {
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                $errors[] = 'File bukan gambar yang valid';
            }
        }
        
        return $errors;
    }
    
    /**
     * Validate plugin file upload
     */
    public static function validatePlugin(array $file): array
    {
        $errors = [];
        
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = self::getUploadErrorMessage($file['error']);
            return $errors;
        }
        
        if ($file['size'] > PLUGIN_MAX_SIZE) {
            $errors[] = 'File terlalu besar. Maksimal ' . format_file_size(PLUGIN_MAX_SIZE);
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, PLUGIN_ALLOWED_EXTENSIONS)) {
            $errors[] = 'Hanya file ZIP yang diizinkan';
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, PLUGIN_ALLOWED_TYPES)) {
            $errors[] = 'File bukan ZIP yang valid';
        }
        
        return $errors;
    }
    
    /**
     * Sanitize filename
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Remove path info
        $filename = basename($filename);
        
        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove multiple underscores
        $filename = preg_replace('/_+/', '_', $filename);
        
        // Limit length
        if (strlen($filename) > 200) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = pathinfo($filename, PATHINFO_FILENAME);
            $filename = substr($name, 0, 190) . '.' . $ext;
        }
        
        return $filename;
    }
    
    /**
     * Generate unique filename
     */
    public static function generateUniqueFilename(string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $uuid = bin2hex(random_bytes(16));
        
        return $uuid . '.' . $extension;
    }
    
    /**
     * Save uploaded proof file
     */
    public static function saveProof(array $file): ?array
    {
        $errors = self::validateProof($file);
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        $originalName = self::sanitizeFilename($file['name']);
        $newFilename = self::generateUniqueFilename($originalName);
        $storagePath = get_proof_storage_path();
        $fullPath = $storagePath . '/' . $newFilename;
        
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            return ['success' => false, 'errors' => ['Gagal menyimpan file']];
        }
        
        // Get relative path from storage root
        $relativePath = str_replace(STORAGE_PATH . '/', '', $fullPath);
        
        return [
            'success' => true,
            'path' => $relativePath,
            'original_name' => $originalName,
            'size' => $file['size']
        ];
    }
    
    /**
     * Save uploaded plugin file
     */
    public static function savePlugin(array $file, string $version): ?array
    {
        $errors = self::validatePlugin($file);
        
        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }
        
        $originalName = self::sanitizeFilename($file['name']);
        $storagePath = get_plugin_storage_path($version);
        $fullPath = $storagePath . '/' . $originalName;
        
        if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
            return ['success' => false, 'errors' => ['Gagal menyimpan file']];
        }
        
        $relativePath = str_replace(STORAGE_PATH . '/', '', $fullPath);
        
        return [
            'success' => true,
            'path' => $relativePath,
            'original_name' => $originalName,
            'size' => $file['size'],
            'version' => $version
        ];
    }
    
    /**
     * Delete file
     */
    public static function deleteFile(string $relativePath): bool
    {
        $fullPath = STORAGE_PATH . '/' . $relativePath;
        
        if (file_exists($fullPath) && is_file($fullPath)) {
            return unlink($fullPath);
        }
        
        return false;
    }
    
    /**
     * Get upload error message
     */
    private static function getUploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File melebihi batas ukuran server',
            UPLOAD_ERR_FORM_SIZE => 'File melebihi batas ukuran form',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak tersedia',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
            UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
        ];
        
        return $messages[$errorCode] ?? 'Error upload tidak diketahui';
    }
}
