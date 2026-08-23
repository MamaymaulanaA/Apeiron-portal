<?php
/**
 * Login Controller
 */

require_once PORTAL_ROOT . '/src/Security/RateLimiter.php';

class LoginController
{
    /**
     * Show login form
     */
    public function show(): void
    {
        // Redirect if already logged in
        if (AuthMiddleware::isLoggedIn()) {
            if (AuthMiddleware::isAdmin()) {
                redirect(base_url('/admin/dashboard'));
            }
            redirect(base_url('/dashboard'));
        }
        
        include PORTAL_ROOT . '/src/Auth/Views/login.php';
    }
    
    /**
     * Handle login
     */
    public function login(): void
    {
        // Validate CSRF
        CsrfProtection::validateOrFail();
        
        // Rate limiting
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $rateLimitKey = "login:{$ip}";
        
        if (!RateLimiter::check($rateLimitKey, 5, 300)) {
            flash('error', 'Terlalu banyak percobaan login. Silakan tunggu 5 menit.');
            redirect(base_url('/login'));
        }
        
        // Get input
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Validate
        $errors = [];
        
        if (empty($email)) {
            $errors[] = 'Email wajib diisi';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid';
        }
        
        if (empty($password)) {
            $errors[] = 'Password wajib diisi';
        }
        
        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            flash('old_email', $email);
            redirect(base_url('/login'));
        }
        
        // Find user
        $user = User::findByEmail($email);
        
        if (!$user || !$user->verifyPassword($password)) {
            RateLimiter::hit($rateLimitKey);
            
            // Log failed attempt
            if ($user) {
                AuditLogger::logLogin($user->id, false);
            }
            
            flash('error', 'Email atau password salah');
            flash('old_email', $email);
            redirect(base_url('/login'));
        }
        
        // Check if active
        if (!$user->is_active) {
            flash('error', 'Akun Anda telah dinonaktifkan. Hubungi administrator.');
            redirect(base_url('/login'));
        }
        
        // Login successful
        RateLimiter::clear($rateLimitKey);
        AuthMiddleware::login($user);
        AuditLogger::logLogin($user->id, true);
        
        flash('success', 'Selamat datang, ' . e($user->name) . '!');
        
        // Redirect based on role
        if ($user->isAdmin()) {
            redirect(base_url('/admin/dashboard'));
        }
        
        redirect(base_url('/dashboard'));
    }
}
