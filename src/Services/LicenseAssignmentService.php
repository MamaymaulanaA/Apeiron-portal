<?php
/**
 * License Assignment Service
 * 
 * Handles assigning licenses to users after approval
 */

class LicenseAssignmentService
{
    /**
     * Assign license to user
     */
    public static function assign(
        int $userId,
        int $transactionId,
        string $licenseKey,
        ?string $apiKey = null,
        ?string $productId = null
    ): UserLicense {
        // Check if already assigned for this transaction
        $existing = UserLicense::findByTransaction($transactionId);
        if ($existing) {
            throw new Exception('License sudah di-assign untuk transaksi ini');
        }

        return UserLicense::create([
            'user_id' => $userId,
            'transaction_id' => $transactionId,
            'license_key' => $licenseKey,
            'api_key' => $apiKey,
            'product_id' => $productId ?? DEFAULT_PRODUCT_ID
        ]);
    }
    
    /**
     * Get all licenses for user
     */
    public static function getUserLicensesWithStatus(int $userId): array
    {
        $licenses = UserLicense::findByUser($userId);

        foreach ($licenses as $license) {
            // For now, set basic status based on local data
            // The portal stores licenses assigned to users
            $license->license_status = [
                'success' => true,
                'status' => 'active', // Portal assumes active if assigned
                'license_key' => $license->license_key
            ];
        }

        return $licenses;
    }

    /**
     * Check if user has valid license for product
     */
    public static function userHasValidLicense(int $userId, ?string $productId = null): bool
    {
        $licenses = UserLicense::findByUser($userId);

        foreach ($licenses as $license) {
            if ($productId && $license->product_id !== $productId) {
                continue;
            }

            // Check if license exists (portal side validation)
            return true;
        }

        return false;
    }
}
