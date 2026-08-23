<?php
/**
 * User Dashboard Controller
 */

class DashboardController
{
    /**
     * Show user dashboard
     */
    public function index(): void
    {
        $user = auth();

        // Get user stats
        $stats = [
            'total_transactions' => Transaction::countByUser($user->id),
            'pending_transactions' => Transaction::countByUser($user->id, Transaction::STATUS_WAITING),
            'approved_transactions' => Transaction::countByUser($user->id, Transaction::STATUS_APPROVED),
            'total_licenses' => UserLicense::countByUser($user->id),
            'total_downloads' => DownloadLog::countByUser($user->id)
        ];

        // Get recent transactions
        $recentTransactions = Transaction::findByUser($user->id);
        $recentTransactions = array_slice($recentTransactions, 0, 5);

        // Get user licenses
        require_once PORTAL_ROOT . '/src/Services/LicenseAssignmentService.php';
        $licenses = LicenseAssignmentService::getUserLicensesWithStatus($user->id);

        // Check API Key Template status
        // Modify this query based on your actual database structure
        $apiKeyStatus = $this->checkApiKeyTemplateStatus($user->id);

        include PORTAL_ROOT . '/src/User/Views/dashboard.php';
    }

    /**
     * Check if user has API Key template configured
     * Adjust this method based on your actual API key storage structure
     */
    private function checkApiKeyTemplateStatus(int $userId): array
    {
        // Check if user has API key template in user_settings table
        $apiKeySetting = Database::fetchOne(
            "SELECT * FROM user_settings WHERE user_id = ? AND setting_key = 'api_key_template'",
            [$userId]
        );

        $hasTemplate = !empty($apiKeySetting) && !empty($apiKeySetting['setting_value']);

        return [
            'has_template' => $hasTemplate,
            'template' => $hasTemplate ? '***' . substr($apiKeySetting['setting_value'], -4) : null
        ];
    }
}
