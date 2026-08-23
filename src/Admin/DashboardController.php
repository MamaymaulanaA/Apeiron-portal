<?php
/**
 * Admin Dashboard Controller
 */

class DashboardController
{
    /**
     * Show admin dashboard
     */
    public function index(): void
    {
        $admin = auth();

        // Get statistics
        $waitingCount = Transaction::countByStatus(Transaction::STATUS_WAITING);
        $approvedCount = Transaction::countByStatus(Transaction::STATUS_APPROVED);
        $rejectedCount = Transaction::countByStatus(Transaction::STATUS_REJECTED);
        $pendingLicenseCount = Transaction::countByStatus(Transaction::STATUS_PENDING_LICENSE);

        $stats = [
            'total_users' => User::count('user'),  // Only regular users, not admins
            'total_transactions' => $waitingCount + $approvedCount + $rejectedCount + $pendingLicenseCount,
            'pending_transactions' => $waitingCount,       // Alias for view compatibility
            'waiting_transactions' => $waitingCount,       // Waiting verification
            'approved_transactions' => $approvedCount,
            'rejected_transactions' => $rejectedCount,
            'pending_license_transactions' => $pendingLicenseCount,
            'total_downloads' => DownloadLog::count()
        ];

        // Get recent pending transactions
        $pendingResult = Transaction::findByStatus(Transaction::STATUS_WAITING, 1, 5);
        $recentPending = $pendingResult['data'];

        // Get recent logs
        $recentLogs = AuditLogger::getRecent(10);

        include PORTAL_ROOT . '/src/Admin/Views/dashboard.php';
    }
}
