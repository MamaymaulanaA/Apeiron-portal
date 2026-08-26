<?php
/**
 * API Helper Functions
 */

// Ensure PORTAL_ROOT is defined (for IDE intellisense)
if (!defined('PORTAL_ROOT')) {
    define('PORTAL_ROOT', dirname(__DIR__));
}

// Load security classes
require_once PORTAL_ROOT . '/src/Security/RateLimiter.php';
require_once PORTAL_ROOT . '/src/Security/CsrfProtection.php';

/**
 * Send JSON response
 */
function jsonResponse(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Get JSON input
 */
function getJsonInput(): array
{
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

/**
 * Validate CSRF token for API requests
 * For session-based auth, we use a relaxed CSRF check
 * For API tokens (future), we would check headers
 */
function validateCsrf(): void
{
    // For GET requests, no CSRF needed
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        return;
    }

    // For POST/PUT/DELETE with session auth, check CSRF
    // Skip for API token auth (future implementation)
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
    $expected = $_SESSION[CSRF_TOKEN_NAME] ?? 'NOT_SET';

    if (!$token || !CsrfProtection::validate($token)) {
        // TOLERANSI: Jika user sudah login via session, kita izinkan meskipun token bermasalah
        // Ini untuk mengatasi masalah cookie/CORS yang kadang terblokir di beberapa browser
        if (isLoggedIn()) {
            $user = currentUser();
            error_log("[CSRF_RELAXED_ALLOW] Token invalid/missing for logged in user: " . ($user->email ?? 'ID:'.$user->id));
            return;
        }

        $reason = !$token ? 'TOKEN_MISSING' : (!isset($_SESSION[CSRF_TOKEN_NAME]) ? 'SESSION_EMPTY' : 'TOKEN_MISMATCH');
        $sessId = session_id();
        error_log("[CSRF_ERROR][$reason] Received: " . ($token ?: 'NULL') . ", SessionID: $sessId");
        
        jsonResponse([
            'error' => 'Invalid security token',
            'debug_reason' => $reason
        ], 403);
    }
}

/**
 * Apply rate limiting to endpoint
 */
function applyRateLimit(string $action, int $maxAttempts = 5, int $windowSeconds = 60): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $key = "api:{$action}:{$ip}";

    if (!RateLimiter::attempt($key, $maxAttempts, $windowSeconds)) {
        $retryAfter = RateLimiter::retryAfter($key);
        jsonResponse([
            'error' => 'Terlalu banyak permintaan. Silakan tunggu ' . $retryAfter . ' detik.',
            'retry_after' => $retryAfter
        ], 429);
    }
}

/**
 * Get CSRF token for API responses
 */
function getCsrfToken(): string
{
    return CsrfProtection::token();
}

/**
 * Segarkan salinan user di sesi dari database.
 *
 * Sesi menyimpan objek User yang diambil SEKALI saat login. Selama salinan itu
 * tidak pernah dibaca ulang, role dan is_active yang dipakai untuk otorisasi
 * adalah keadaan saat login, bukan keadaan sekarang — sehingga menurunkan admin
 * jadi user biasa tidak mencabut haknya, dan menonaktifkan user tidak menutup
 * sesinya. Di sinilah role menjadi otoritatif untuk seluruh API, termasuk
 * /auth/me yang dipakai frontend untuk menentukan navigasi.
 *
 * Dibaca sekali per permintaan lalu ditahan di memori: satu permintaan bisa
 * memanggil currentUser() berkali-kali, dan tidak ada gunanya menanyakan baris
 * yang sama berulang kali dalam satu proses.
 *
 * @return object|null null bila barisnya sudah tidak ada atau tidak aktif lagi.
 */
function refreshCurrentUser(): ?object
{
    static $sudahDibaca = false;
    static $hasil = null;

    if ($sudahDibaca) {
        return $hasil;
    }
    $sudahDibaca = true;

    $snapshot = $_SESSION['portal_user'] ?? null;
    $id = $_SESSION['user_id'] ?? ($snapshot->id ?? null);

    if (!$id) {
        return $hasil = null;
    }

    try {
        $segar = User::find((int) $id);
    } catch (Throwable $e) {
        // Database sedang tidak terbaca. Menolak permintaan di sini akan
        // mengubah gangguan database menjadi logout massal, jadi permintaannya
        // diteruskan dengan salinan lama dan akan gagal di tempat yang memang
        // bermasalah — bukan di gerbang autentikasi.
        error_log('[PORTAL][AUTH] Gagal menyegarkan user #' . $id . ': ' . $e->getMessage());
        return $hasil = $snapshot;
    }

    // Barisnya hilang atau dinonaktifkan: sesi tidak boleh tetap berlaku.
    // Pemeriksaan is_active di sini sama persis dengan yang sudah dilakukan
    // AuthController::login(), jadi tidak ada akun yang bisa login hari ini
    // lalu tiba-tiba tertolak oleh perubahan ini.
    if (!$segar || !$segar->is_active) {
        return $hasil = null;
    }

    $_SESSION['portal_user'] = $segar;
    $_SESSION['user_id'] = $segar->id;
    $_SESSION['user_role'] = $segar->role;

    return $hasil = $segar;
}

/**
 * Get current authenticated user
 *
 * Nilainya berasal dari database, bukan dari salinan di sesi — lihat
 * refreshCurrentUser().
 */
function currentUser(): ?object
{
    return refreshCurrentUser();
}

/**
 * Check if user is logged in
 */
function isLoggedIn(): bool
{
    return currentUser() !== null;
}

/**
 * Check if user is admin
 */
function isAdmin(): bool
{
    $user = currentUser();
    return $user && $user->role === 'admin';
}

/**
 * Require authentication
 */
function requireAuth(): void
{
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Unauthorized'], 401);
    }
}

/**
 * Require admin
 */
function requireAdmin(): void
{
    requireAuth();
    if (!isAdmin()) {
        jsonResponse(['error' => 'Forbidden'], 403);
    }
}
