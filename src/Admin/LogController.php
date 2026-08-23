<?php
/**
 * Admin Log Controller
 */

class LogController
{
    /**
     * Show audit logs
     */
    public function index(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $action = $_GET['action'] ?? null;
        
        if ($action) {
            $logs = AdminLog::findByAction($action, 100);
            $pagination = null;
        } else {
            $result = AdminLog::paginate($page);
            $logs = $result['data'];
            $pagination = $result;
        }
        
        include PORTAL_ROOT . '/src/Admin/Views/logs.php';
    }
}
