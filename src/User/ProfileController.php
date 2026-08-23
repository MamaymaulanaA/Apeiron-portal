<?php
/**
 * Profile Controller
 */

require_once PORTAL_ROOT . '/src/Validation/UserValidator.php';

class ProfileController
{
    /**
     * Show profile page
     */
    public function show(): void
    {
        $user = auth();
        include PORTAL_ROOT . '/src/User/Views/profile.php';
    }
    
    /**
     * Update profile
     */
    public function update(): void
    {
        CsrfProtection::validateOrFail();
        
        $user = auth();
        
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'current_password' => $_POST['current_password'] ?? '',
            'new_password' => $_POST['new_password'] ?? '',
            'new_password_confirm' => $_POST['new_password_confirm'] ?? ''
        ];
        
        // Validate
        $errors = UserValidator::validateProfileUpdate($data, $user->id);
        
        // Verify current password if changing password
        if (!empty($data['new_password'])) {
            if (!$user->verifyPassword($data['current_password'])) {
                $errors[] = 'Password lama tidak benar';
            }
        }
        
        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            redirect(base_url('/profile'));
        }
        
        // Update user
        $updateData = [
            'name' => $data['name'],
            'email' => $data['email']
        ];
        
        if (!empty($data['new_password'])) {
            $updateData['password'] = $data['new_password'];
        }
        
        try {
            $user->update($updateData);
            AuthMiddleware::refreshUser();
            
            flash('success', 'Profil berhasil diperbarui');
        } catch (Exception $e) {
            flash('error', 'Gagal memperbarui profil');
        }
        
        redirect(base_url('/profile'));
    }
}
