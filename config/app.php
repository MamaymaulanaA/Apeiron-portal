<?php
/**
 * Application Configuration
 * 
 * Konfigurasi umum aplikasi Portal User
 */

// Prevent direct access
if (!defined('PORTAL_ROOT')) {
    die('Direct access not permitted');
}

// Environment
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', APP_ENV === 'development');

// Application Info
define('APP_NAME', 'Apeiron Portal');
define('APP_VERSION', '2.0.0');

/**
 * CONFIGURATION FOR HOSTING:
 * Change these URLs to match your deployment environment.
 * You can also use .htaccess SetEnv or server environment variables.
 */
define('APP_URL', getenv('APP_URL') ?: 'https://portal-apeiron.web.id/');
define('LICENSE_SERVER_URL', getenv('LICENSE_SERVER_URL') ?: 'https://server-apeiron.web.id/api');
// License Server API Key
// PRODUCTION: MUST be set via environment variable. App will halt if missing.
if (APP_ENV === 'production') {
    $licenseApiKey = getenv('LICENSE_SERVER_API_KEY');
    if (!$licenseApiKey || $licenseApiKey === '' || $licenseApiKey === 'change-me') {
        error_log('[PORTAL][FATAL] LICENSE_SERVER_API_KEY not set in production environment!');
        die('Configuration error: LICENSE_SERVER_API_KEY must be set in production.');
    }
    define('LICENSE_SERVER_API_KEY', $licenseApiKey);
} else {
    define('LICENSE_SERVER_API_KEY', getenv('LICENSE_SERVER_API_KEY') ?: 'dev-only-key-not-for-production');
}

// CORS Allowed Origins (comma-separated or use environment variable)
$appUrlOrigin = '';
if (defined('APP_URL')) {
    $parsed = parse_url(APP_URL);
    if (isset($parsed['scheme']) && isset($parsed['host'])) {
        $appUrlOrigin = $parsed['scheme'] . '://' . $parsed['host'] . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
    }
}

if (APP_ENV === 'production') {
    $allowed = getenv('CORS_ALLOWED_ORIGINS') ?: 'https://yourdomain.com,https://www.yourdomain.com';
    if ($appUrlOrigin) {
        $allowed .= ',' . $appUrlOrigin;
    }
    define('CORS_ALLOWED_ORIGINS', $allowed);
} else {
    $allowed = getenv('CORS_ALLOWED_ORIGINS') ?: 'http://localhost,http://localhost:5173,http://localhost:5174,http://127.0.0.1,http://127.0.0.1:5173,http://127.0.0.1:5174';
    if ($appUrlOrigin && strpos($allowed, $appUrlOrigin) === false) {
        $allowed .= ',' . $appUrlOrigin;
    }
    define('CORS_ALLOWED_ORIGINS', $allowed);
}

// Timezone
define('APP_TIMEZONE', 'Asia/Jakarta'); // For display purposes
date_default_timezone_set('UTC'); // Storage and calculation in UTC

// Session Configuration
define('SESSION_NAME', 'apeiron_portal_session');
define('SESSION_LIFETIME', 3600); // 1 hour
// Only use Secure cookies if we are actually on HTTPS
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

define('SESSION_SECURE', $isSecure);
define('SESSION_HTTPONLY', true);
define('SESSION_SAMESITE', 'Lax');

// Security Keys — MUST be set in production!
if (APP_ENV === 'production') {
    $appSecretKey = getenv('APP_SECRET_KEY');
    if (!$appSecretKey || strlen($appSecretKey) < 32 || $appSecretKey === 'change-this-secret-key-in-production-32chars') {
        error_log('[PORTAL][FATAL] APP_SECRET_KEY not set or too short in production!');
        die('Configuration error: APP_SECRET_KEY must be set (min 32 chars) in production.');
    }
    define('APP_SECRET_KEY', $appSecretKey);
} else {
    define('APP_SECRET_KEY', getenv('APP_SECRET_KEY') ?: 'dev-only-secret-key-not-for-production!!');
}
define('CSRF_TOKEN_NAME', 'csrf_token');

// Rate Limiting
define('RATE_LIMIT_ENABLED', true);
define('RATE_LIMIT_MAX_ATTEMPTS', 60); // per window
define('RATE_LIMIT_WINDOW', 60); // seconds

// Email Configuration (optional, for notifications)
define('MAIL_ENABLED', false);
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.example.com');
define('MAIL_PORT', getenv('MAIL_PORT') ?: 587);
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@example.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: APP_NAME);

// Pagination
define('ITEMS_PER_PAGE', 15);

// Password Requirements
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_NUMBER', true);

/**
 * Configure PHP session with security settings
 */
function configure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', SESSION_HTTPONLY ? '1' : '0');
        ini_set('session.cookie_secure', SESSION_SECURE ? '1' : '0');
        ini_set('session.cookie_samesite', SESSION_SAMESITE);
        ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_path', '/');
        
        session_name(SESSION_NAME);
        session_start();
        
        // Regenerate session ID periodically
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } elseif (time() - $_SESSION['_created'] > 1800) {
            // Regenerate every 30 minutes
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }
}

/**
 * Get base URL of the application
 */
function base_url(string $path = ''): string
{
    $baseUrl = rtrim(APP_URL, '/');
    $path = ltrim($path, '/');
    return $path ? "{$baseUrl}/{$path}" : $baseUrl;
}

/**
 * Redirect to a URL
 */
function redirect(string $url, int $statusCode = 302): void
{
    header("Location: {$url}", true, $statusCode);
    exit;
}

/**
 * Get current URL
 */
function current_url(): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

/**
 * Flash message helper
 */
function flash(string $key, ?string $value = null)
{
    if ($value !== null) {
        $_SESSION['_flash'][$key] = $value;
    } else {
        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $message;
    }
}

/**
 * Check if flash message exists
 */
function has_flash(string $key): bool
{
    return isset($_SESSION['_flash'][$key]);
}

/**
 * Escape HTML output
 */
function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

/**
 * Format date for display
 */
function format_date(string $date, string $format = 'd M Y H:i'): string
{
    try {
        $dt = new DateTime($date, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone(APP_TIMEZONE));
        return $dt->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Format currency
 */
function format_currency(float $amount): string
{
    return 'Rp ' . number_format($amount, 0, ',', '.');
}
