<?php
/**
 * Auth Middleware
 * 
 * Checks if user is logged in
 */

class AuthMiddleware
{
    /**
     * Handle middleware - redirect if not logged in
     */
    public static function handle(): void
    {
        if (!self::isLoggedIn()) {
            flash('error', 'Silakan login terlebih dahulu.');
            redirect(base_url('/login'));
        }
        
        // Check if user is still active
        $user = self::user();
        if ($user && !$user->is_active) {
            self::logout();
            flash('error', 'Akun Anda telah dinonaktifkan.');
            redirect(base_url('/login'));
        }
    }
    
    /**
     * Check if user is logged in
     * - Checks for API session key ($_SESSION['portal_user'])
     * - Falls back to legacy session key ($_SESSION['user_id'])
     */
    public static function isLoggedIn(): bool
    {
        // Primary: Check API session (portal_user)
        if (isset($_SESSION['portal_user']) && is_object($_SESSION['portal_user'])) {
            return true;
        }

        // Fallback: Check legacy session (user_id)
        return isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0;
    }

    /**
     * Get current logged in user
     * - Returns from API session if available
     * - Falls back to legacy session with DB lookup
     */
    public static function user(): ?User
    {
        // Primary: Return user from API session
        if (isset($_SESSION['portal_user']) && is_object($_SESSION['portal_user'])) {
            return $_SESSION['portal_user'];
        }

        // Fallback: Legacy session handling
        if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] <= 0) {
            return null;
        }

        // Cache user in session to avoid repeated DB queries
        if (!isset($_SESSION['_user_cache']) || $_SESSION['_user_cache']['id'] !== $_SESSION['user_id']) {
            $user = User::find($_SESSION['user_id']);
            if ($user) {
                $_SESSION['_user_cache'] = [
                    'id' => $user->id,
                    'user' => serialize($user)
                ];
            } else {
                // User not found, clear session
                self::logout();
                return null;
            }
        }

        return unserialize($_SESSION['_user_cache']['user']);
    }

    /**
     * Get current user ID
     */
    public static function userId(): ?int
    {
        // Primary: From API session
        if (isset($_SESSION['portal_user']) && is_object($_SESSION['portal_user'])) {
            return $_SESSION['portal_user']->id ?? null;
        }

        // Fallback: From legacy session
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Check if current user is admin
     */
    public static function isAdmin(): bool
    {
        $user = self::user();
        return $user && $user->isAdmin();
    }
    
    /**
     * Login user
     */
    public static function login(User $user): void
    {
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_role'] = $user->role;
        $_SESSION['_created'] = time();
        
        // Clear user cache
        unset($_SESSION['_user_cache']);
        
        // Update last login
        $user->updateLastLogin();
    }
    
    /**
     * Logout user
     */
    public static function logout(): void
    {
        // Clear session data
        $_SESSION = [];
        
        // Destroy session cookie
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        // Destroy session
        session_destroy();
        
        // Start new session
        session_start();
        session_regenerate_id(true);
    }
    
    /**
     * Refresh user cache
     */
    public static function refreshUser(): void
    {
        unset($_SESSION['_user_cache']);
    }
}

/**
 * Shortcut to get current user
 */
function auth(): ?User
{
    return AuthMiddleware::user();
}

/**
 * Shortcut to check if logged in
 */
function is_logged_in(): bool
{
    return AuthMiddleware::isLoggedIn();
}

/**
 * Shortcut to check if admin
 */
function is_admin(): bool
{
    return AuthMiddleware::isAdmin();
}
