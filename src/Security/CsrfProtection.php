<?php
/**
 * CSRF Protection
 * 
 * Provides CSRF token generation and validation
 */

class CsrfProtection
{
    /**
     * Generate CSRF token
     */
    public static function generate(): string
    {
        if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
            $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
        }
        return $_SESSION[CSRF_TOKEN_NAME];
    }
    
    /**
     * Get current CSRF token
     */
    public static function token(): string
    {
        return self::generate();
    }
    
    /**
     * Validate CSRF token
     */
    public static function validate(?string $token = null): bool
    {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }
        
        if (empty($token) || !isset($_SESSION[CSRF_TOKEN_NAME])) {
            return false;
        }
        
        return hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
    }
    
    /**
     * Validate and throw exception if invalid
     */
    public static function validateOrFail(?string $token = null): void
    {
        if (!self::validate($token)) {
            http_response_code(403);
            if (self::isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid CSRF token'
                ]);
            } else {
                flash('error', 'Invalid security token. Please try again.');
                redirect($_SERVER['HTTP_REFERER'] ?? base_url('/'));
            }
            exit;
        }
    }
    
    /**
     * Regenerate CSRF token (call after successful form submission)
     */
    public static function regenerate(): string
    {
        unset($_SESSION[CSRF_TOKEN_NAME]);
        return self::generate();
    }
    
    /**
     * Get hidden input field with CSRF token
     */
    public static function field(): string
    {
        $token = self::token();
        return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
    }
    
    /**
     * Check if request is AJAX
     */
    private static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

/**
 * Shortcut function for CSRF field
 */
function csrf_field(): string
{
    return CsrfProtection::field();
}

/**
 * Shortcut function for CSRF token
 */
function csrf_token(): string
{
    return CsrfProtection::token();
}
