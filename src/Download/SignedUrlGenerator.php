<?php
/**
 * Signed URL Generator
 * 
 * Generates time-limited signed URLs for secure downloads
 */

class SignedUrlGenerator
{
    /**
     * Generate signed URL for download
     */
    public static function generate(int $userLicenseId, string $version, int $expiry = null): string
    {
        $expiry = $expiry ?? SIGNED_URL_EXPIRY;
        $expiresAt = time() + $expiry;
        
        // Create payload
        $payload = [
            'license_id' => $userLicenseId,
            'version' => $version,
            'expires' => $expiresAt
        ];
        
        $payloadBase64 = base64_encode(json_encode($payload));
        $signature = self::sign($payloadBase64);
        
        return base_url('/download/file') . '?' . http_build_query([
            'p' => $payloadBase64,
            's' => $signature
        ]);
    }
    
    /**
     * Validate signed URL
     */
    public static function validate(string $payload, string $signature): ?array
    {
        // Verify signature
        $expectedSignature = self::sign($payload);
        
        if (!hash_equals($expectedSignature, $signature)) {
            return null;
        }
        
        // Decode payload
        $data = json_decode(base64_decode($payload), true);
        
        if (!$data) {
            return null;
        }
        
        // Check expiry
        if (!isset($data['expires']) || time() > $data['expires']) {
            return null;
        }
        
        return $data;
    }
    
    /**
     * Create HMAC signature
     */
    private static function sign(string $data): string
    {
        return hash_hmac('sha256', $data, SIGNED_URL_SECRET);
    }
}
