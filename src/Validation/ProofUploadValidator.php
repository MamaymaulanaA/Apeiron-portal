<?php
/**
 * Proof Upload Validator
 * 
 * Validates uploaded proof of payment files
 */

class ProofUploadValidator
{
    /**
     * Validate proof file upload
     */
    public static function validate(array $file): array
    {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'File bukti pembayaran wajib diupload';
            return $errors;
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = self::getUploadErrorMessage($file['error']);
            return $errors;
        }
        
        // Check file size
        if ($file['size'] > PROOF_MAX_SIZE) {
            $errors[] = 'Ukuran file maksimal ' . format_file_size(PROOF_MAX_SIZE);
        }
        
        if ($file['size'] === 0) {
            $errors[] = 'File tidak boleh kosong';
        }
        
        // Check extension
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, PROOF_ALLOWED_EXTENSIONS)) {
            $errors[] = 'Format file tidak didukung. Gunakan: ' . implode(', ', PROOF_ALLOWED_EXTENSIONS);
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, PROOF_ALLOWED_TYPES)) {
            $errors[] = 'Tipe file tidak valid';
        }
        
        // For images, verify it's actually an image
        if (strpos($mimeType, 'image/') === 0) {
            $imageInfo = @getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                $errors[] = 'File gambar tidak valid atau rusak';
            } else {
                // Check image dimensions (optional)
                $maxWidth = 5000;
                $maxHeight = 5000;
                if ($imageInfo[0] > $maxWidth || $imageInfo[1] > $maxHeight) {
                    $errors[] = "Dimensi gambar maksimal {$maxWidth}x{$maxHeight} pixel";
                }
            }
        }
        
        return $errors;
    }
    
    /**
     * Get upload error message
     */
    private static function getUploadErrorMessage(int $errorCode): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi batas server)',
            UPLOAD_ERR_FORM_SIZE => 'File terlalu besar',
            UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
            UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary tidak tersedia',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menyimpan file',
            UPLOAD_ERR_EXTENSION => 'Upload dibatalkan oleh ekstensi'
        ];
        
        return $messages[$errorCode] ?? 'Terjadi kesalahan saat upload';
    }
}
