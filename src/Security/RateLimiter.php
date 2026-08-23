<?php
/**
 * Rate Limiter
 * 
 * Simple file-based rate limiting
 */

class RateLimiter
{
    private static string $cacheDir;
    
    /**
     * Initialize cache directory
     */
    private static function init(): void
    {
        if (!isset(self::$cacheDir)) {
            self::$cacheDir = STORAGE_CACHE . '/rate_limit';
            if (!is_dir(self::$cacheDir)) {
                mkdir(self::$cacheDir, 0755, true);
            }
        }
    }
    
    /**
     * Check if action is rate limited
     */
    public static function check(string $key, int $maxAttempts = null, int $window = null): bool
    {
        if (!RATE_LIMIT_ENABLED) {
            return true;
        }
        
        self::init();
        
        $maxAttempts = $maxAttempts ?? RATE_LIMIT_MAX_ATTEMPTS;
        $window = $window ?? RATE_LIMIT_WINDOW;
        
        $data = self::getData($key);
        $now = time();
        
        // Clean old entries
        $data = array_filter($data, fn($timestamp) => $timestamp > ($now - $window));
        
        return count($data) < $maxAttempts;
    }
    
    /**
     * Record an action attempt
     */
    public static function hit(string $key): void
    {
        if (!RATE_LIMIT_ENABLED) {
            return;
        }
        
        self::init();
        
        $data = self::getData($key);
        $data[] = time();
        
        self::saveData($key, $data);
    }
    
    /**
     * Check and hit in one call
     */
    public static function attempt(string $key, int $maxAttempts = null, int $window = null): bool
    {
        if (self::check($key, $maxAttempts, $window)) {
            self::hit($key);
            return true;
        }
        return false;
    }
    
    /**
     * Get remaining attempts
     */
    public static function remaining(string $key, int $maxAttempts = null, int $window = null): int
    {
        if (!RATE_LIMIT_ENABLED) {
            return PHP_INT_MAX;
        }
        
        self::init();
        
        $maxAttempts = $maxAttempts ?? RATE_LIMIT_MAX_ATTEMPTS;
        $window = $window ?? RATE_LIMIT_WINDOW;
        
        $data = self::getData($key);
        $now = time();
        
        $data = array_filter($data, fn($timestamp) => $timestamp > ($now - $window));
        
        return max(0, $maxAttempts - count($data));
    }
    
    /**
     * Get seconds until rate limit resets
     */
    public static function retryAfter(string $key, int $window = null): int
    {
        self::init();
        
        $window = $window ?? RATE_LIMIT_WINDOW;
        $data = self::getData($key);
        
        if (empty($data)) {
            return 0;
        }
        
        $oldestTimestamp = min($data);
        $resetAt = $oldestTimestamp + $window;
        
        return max(0, $resetAt - time());
    }
    
    /**
     * Clear rate limit for a key
     */
    public static function clear(string $key): void
    {
        self::init();
        
        $file = self::getFilePath($key);
        if (file_exists($file)) {
            unlink($file);
        }
    }
    
    /**
     * Rate limit by IP address
     */
    public static function checkByIp(string $action = 'default', int $maxAttempts = null, int $window = null): bool
    {
        $ip = self::getClientIp();
        $key = "ip:{$ip}:{$action}";
        return self::check($key, $maxAttempts, $window);
    }
    
    /**
     * Hit rate limit by IP
     */
    public static function hitByIp(string $action = 'default'): void
    {
        $ip = self::getClientIp();
        $key = "ip:{$ip}:{$action}";
        self::hit($key);
    }
    
    /**
     * Check and respond with 429 if rate limited
     */
    public static function throttle(string $key, int $maxAttempts = null, int $window = null): void
    {
        if (!self::attempt($key, $maxAttempts, $window)) {
            $retryAfter = self::retryAfter($key, $window);
            
            http_response_code(429);
            header("Retry-After: {$retryAfter}");
            
            if (self::isAjax()) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'message' => 'Terlalu banyak permintaan. Coba lagi dalam ' . $retryAfter . ' detik.',
                    'retry_after' => $retryAfter
                ]);
            } else {
                flash('error', 'Terlalu banyak permintaan. Silakan tunggu beberapa saat.');
                redirect($_SERVER['HTTP_REFERER'] ?? base_url('/'));
            }
            exit;
        }
    }
    
    /**
     * Get data from cache file
     */
    private static function getData(string $key): array
    {
        $file = self::getFilePath($key);
        
        if (!file_exists($file)) {
            return [];
        }
        
        $content = file_get_contents($file);
        $data = json_decode($content, true);
        
        return is_array($data) ? $data : [];
    }
    
    /**
     * Save data to cache file
     */
    private static function saveData(string $key, array $data): void
    {
        $file = self::getFilePath($key);
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
    
    /**
     * Get cache file path for key
     */
    private static function getFilePath(string $key): string
    {
        $hash = md5($key);
        return self::$cacheDir . '/' . $hash . '.json';
    }
    
    /**
     * Get client IP
     */
    private static function getClientIp(): string
    {
        $keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'];
        
        foreach ($keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
    
    /**
     * Check if AJAX request
     */
    private static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
    
    /**
     * Cleanup old cache files
     */
    public static function cleanup(int $maxAge = 86400): void
    {
        self::init();
        
        $files = glob(self::$cacheDir . '/*.json');
        $now = time();
        
        foreach ($files as $file) {
            if ($now - filemtime($file) > $maxAge) {
                unlink($file);
            }
        }
    }
}
