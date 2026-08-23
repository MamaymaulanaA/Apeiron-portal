<?php
/**
 * Logout Controller
 */

class LogoutController
{
    /**
     * Handle logout
     */
    public function logout(): void
    {
        AuthMiddleware::logout();
        flash('success', 'Anda telah berhasil logout');
        redirect(base_url('/login'));
    }
}
