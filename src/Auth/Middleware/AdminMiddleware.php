<?php
/**
 * Admin Middleware
 * 
 * Checks if user is admin
 */

require_once __DIR__ . '/AuthMiddleware.php';

class AdminMiddleware
{
    /**
     * Handle middleware - redirect if not admin
     */
    public static function handle(): void
    {
        // First check if logged in
        AuthMiddleware::handle();
        
        // Then check if admin
        if (!AuthMiddleware::isAdmin()) {
            flash('error', 'Anda tidak memiliki akses ke halaman ini.');
            redirect(base_url('/dashboard'));
        }
    }
}
