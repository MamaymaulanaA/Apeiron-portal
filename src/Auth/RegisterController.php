<?php
/**
 * Register Controller
 */

require_once PORTAL_ROOT . '/src/Validation/UserValidator.php';

class RegisterController
{
    /**
     * Show register form
     */
    public function show(): void
    {
        // Redirect if already logged in
        if (AuthMiddleware::isLoggedIn()) {
            redirect(base_url('/dashboard'));
        }
        
        include PORTAL_ROOT . '/src/Auth/Views/register.php';
    }
    
    /**
     * Handle registration
     */
    public function register(): void
    {
        // Validate CSRF
        CsrfProtection::validateOrFail();
        
        // Rate limiting
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        RateLimiter::throttle("register:{$ip}", 3, 3600); // 3 per hour
        
        // Get input
        $data = [
            'name' => trim($_POST['name'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? ''
        ];
        
        // Validate
        $errors = UserValidator::validateRegistration($data);
        
        if (!empty($errors)) {
            flash('error', implode('<br>', $errors));
            flash('old_name', $data['name']);
            flash('old_email', $data['email']);
            redirect(base_url('/register'));
        }
        
        // Check if email exists
        if (User::emailExists($data['email'])) {
            flash('error', 'Email sudah terdaftar');
            flash('old_name', $data['name']);
            redirect(base_url('/register'));
        }
        
        // Create user
        try {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'user'
            ]);
            
            // Auto login after registration
            AuthMiddleware::login($user);
            
            flash('success', 'Registrasi berhasil! Selamat datang, ' . e($user->name));
            redirect(base_url('/dashboard'));
            
        } catch (Exception $e) {
            if (APP_DEBUG) {
                flash('error', 'Registrasi gagal: ' . $e->getMessage());
            } else {
                flash('error', 'Registrasi gagal. Silakan coba lagi.');
            }
            flash('old_name', $data['name']);
            flash('old_email', $data['email']);
            redirect(base_url('/register'));
        }
    }
}
