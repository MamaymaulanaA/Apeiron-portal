<?php
/**
 * Admin User Controller
 */

class UserController
{
    /**
     * Show users list
     */
    public function index(): void
    {
        $page = (int) ($_GET['page'] ?? 1);
        $role = $_GET['role'] ?? null;
        
        $result = User::paginate($page, ITEMS_PER_PAGE, $role);
        
        $users = $result['data'];
        $pagination = $result;
        
        include PORTAL_ROOT . '/src/Admin/Views/users.php';
    }
    
    /**
     * Toggle user status
     */
    public function toggleStatus(): void
    {
        CsrfProtection::validateOrFail();
        
        $admin = auth();
        $userId = (int) ($_POST['user_id'] ?? 0);
        
        $user = User::find($userId);
        
        if (!$user) {
            flash('error', 'User tidak ditemukan');
            redirect(base_url('/admin/users'));
        }
        
        // Cannot deactivate yourself
        if ($user->id === $admin->id) {
            flash('error', 'Tidak dapat menonaktifkan akun sendiri');
            redirect(base_url('/admin/users'));
        }
        
        $newStatus = !$user->is_active;
        $user->update(['is_active' => $newStatus]);
        
        AuditLogger::logUserStatusChange($admin->id, $userId, $newStatus);
        
        $message = $newStatus ? 'User berhasil diaktifkan' : 'User berhasil dinonaktifkan';
        flash('success', $message);
        
        redirect(base_url('/admin/users'));
    }
}
