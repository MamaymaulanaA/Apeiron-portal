<?php
/**
 * Password Reset Controller
 * 
 * Note: Email functionality requires proper SMTP configuration
 */

class PasswordResetController
{
    /**
     * Show forgot password form
     */
    public function show(): void
    {
        if (AuthMiddleware::isLoggedIn()) {
            redirect(base_url('/dashboard'));
        }
        
        include PORTAL_ROOT . '/src/Auth/Views/forgot-password.php';
    }
    
    /**
     * Handle password reset request
     */
    public function request(): void
    {
        CsrfProtection::validateOrFail();
        
        // Rate limiting
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        RateLimiter::throttle("password_reset:{$ip}", 3, 3600);
        
        $email = trim($_POST['email'] ?? '');
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Email tidak valid');
            redirect(base_url('/forgot-password'));
        }
        
        $user = User::findByEmail($email);
        
        // Always show success to prevent email enumeration
        flash('success', 'Jika email terdaftar, instruksi reset password akan dikirim.');
        
        if ($user && MAIL_ENABLED) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token (would need a password_resets table)
            // For now, this is a placeholder
            // TODO: Implement password reset token storage and email sending
        }
        
        redirect(base_url('/login'));
    }
}
