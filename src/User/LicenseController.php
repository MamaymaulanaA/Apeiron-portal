<?php
/**
 * License Controller (User)
 */

require_once PORTAL_ROOT . '/src/Services/LicenseAssignmentService.php';

class LicenseController
{
    /**
     * Show user's licenses
     */
    public function index(): void
    {
        $user = auth();
        
        // Get licenses with status from License Server
        $licenses = LicenseAssignmentService::getUserLicensesWithStatus($user->id);
        
        include PORTAL_ROOT . '/src/User/Views/licenses.php';
    }
}
