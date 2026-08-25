<?php
/**
 * Apeiron Portal REST API
 * Entry point untuk semua API requests
 */

// Define root path FIRST
define('API_ROOT', __DIR__);
define('PORTAL_ROOT', dirname(__DIR__));

// Load .env file if exists
if (file_exists(PORTAL_ROOT . '/.env')) {
    $lines = file(PORTAL_ROOT . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Load configurations FIRST
require_once PORTAL_ROOT . '/config/app.php';
require_once PORTAL_ROOT . '/config/database.php';
require_once PORTAL_ROOT . '/config/storage.php';

// Get allowed origins
function getAllowedOrigins()
{
    return array_map('trim', explode(',', CORS_ALLOWED_ORIGINS));
}

// Set CORS headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowedOrigins = getAllowedOrigins();

$isAllowedOrigin = in_array($origin, $allowedOrigins);

if ($isAllowedOrigin) {
    // Ensure no duplicate headers from Apache/Server config
    if (!headers_sent()) {
        header_remove('Access-Control-Allow-Origin');
        header_remove('Access-Control-Allow-Credentials');
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
    }
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Load Models required for session
require_once PORTAL_ROOT . '/src/Models/User.php';

// Start session for API
configure_session();

// Load helpers
require_once API_ROOT . '/helpers.php';

// Load Other Models
require_once PORTAL_ROOT . '/src/Models/Transaction.php';
require_once PORTAL_ROOT . '/src/Models/UserLicense.php';
require_once PORTAL_ROOT . '/src/Models/Template.php';
require_once PORTAL_ROOT . '/src/Models/Plugin.php';
require_once PORTAL_ROOT . '/src/Models/Notification.php';
require_once PORTAL_ROOT . '/src/Models/CommunityGroup.php';
require_once PORTAL_ROOT . '/src/Models/Tutorial.php';
require_once PORTAL_ROOT . '/src/Models/TutorialStep.php';

// Load Services
require_once PORTAL_ROOT . '/src/Services/ServerApiService.php';
require_once PORTAL_ROOT . '/src/Services/LicenseAssignmentService.php';
require_once PORTAL_ROOT . '/src/Services/TransactionService.php';

// Load API Controllers
require_once API_ROOT . '/controllers/AuthController.php';
require_once API_ROOT . '/controllers/UserController.php';
require_once API_ROOT . '/controllers/AdminController.php';
require_once API_ROOT . '/controllers/CommunityController.php';
require_once API_ROOT . '/controllers/DocumentController.php';

// Get request info
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove base path
$basePath = '/api';
if (strpos($requestUri, $basePath) === 0) {
    $requestUri = substr($requestUri, strlen($basePath));
}
$requestUri = '/' . trim($requestUri, '/');

// Simple Router
$routes = [
    // Auth routes
    'POST:/auth/login' => ['AuthController', 'login'],
    'POST:/auth/register' => ['AuthController', 'register'],
    'POST:/auth/logout' => ['AuthController', 'logout'],
    'GET:/auth/me' => ['AuthController', 'me'],
    'GET:/auth/csrf' => ['AuthController', 'csrf'],

    // User routes
    'GET:/user/dashboard' => ['UserController', 'dashboard'],
    'GET:/user/profile' => ['UserController', 'profile'],
    'PUT:/user/profile' => ['UserController', 'updateProfile'],
    'GET:/user/licenses' => ['UserController', 'licenses'],
    'GET:/user/transactions' => ['UserController', 'transactions'],
    'POST:/user/upload-document' => ['UserController', 'uploadDocument'],
    'GET:/user/templates' => ['UserController', 'templates'],
    'GET:/user/download-plugin' => ['UserController', 'downloadPlugin'],
    'GET:/user/download-invoice' => ['UserController', 'downloadInvoice'],

    // Admin routes
    'GET:/admin/dashboard' => ['AdminController', 'dashboard'],
    'GET:/admin/transactions' => ['AdminController', 'transactions'],
    'GET:/admin/transactions/{id}' => ['AdminController', 'transactionDetail'],
    'GET:/admin/transactions/{id}/document' => ['DocumentController', 'adminTransactionDocument'],
    'POST:/admin/transactions/{id}/approve' => ['AdminController', 'approve'],
    'POST:/admin/transactions/{id}/reject' => ['AdminController', 'reject'],
    'POST:/admin/transactions/{id}/retry-license' => ['AdminController', 'retryLicense'],
    'GET:/admin/users' => ['AdminController', 'users'],
    'GET:/admin/users/{id}' => ['AdminController', 'userDetail'],
    'POST:/admin/users' => ['AdminController', 'createUser'],
    'PUT:/admin/users/{id}' => ['AdminController', 'updateUser'],
    'DELETE:/admin/users/{id}' => ['AdminController', 'deleteUser'],
    'POST:/admin/users/{id}/toggle-status' => ['AdminController', 'toggleUserStatus'],

    // Admin - Templates
    'GET:/admin/templates' => ['AdminController', 'templates'],
    'POST:/admin/templates' => ['AdminController', 'createTemplate'],
    'POST:/admin/templates/{id}/update' => ['AdminController', 'updateTemplate'],
    'PUT:/admin/templates/{id}' => ['AdminController', 'updateTemplate'],
    'DELETE:/admin/templates/{id}' => ['AdminController', 'deleteTemplate'],

    // Admin - Plugins
    'GET:/admin/plugins' => ['AdminController', 'plugins'],
    'POST:/admin/plugins' => ['AdminController', 'uploadPlugin'],
    'POST:/admin/plugins/{id}/update' => ['AdminController', 'updatePlugin'],
    'DELETE:/admin/plugins/{id}' => ['AdminController', 'deletePlugin'],

    // Group & Forum - user
    'GET:/user/groups' => ['CommunityController', 'userGroups'],
    'GET:/user/active-members' => ['CommunityController', 'activeMembers'],
    'GET:/user/tutorials' => ['CommunityController', 'userTutorials'],
    'GET:/user/tutorials/{id}' => ['CommunityController', 'userTutorialDetail'],

    // Group & Forum - admin
    'GET:/admin/groups' => ['CommunityController', 'adminGroups'],
    'POST:/admin/groups' => ['CommunityController', 'createGroup'],
    'PUT:/admin/groups/{id}' => ['CommunityController', 'updateGroup'],
    'DELETE:/admin/groups/{id}' => ['CommunityController', 'deleteGroup'],
    'GET:/admin/tutorials' => ['CommunityController', 'adminTutorials'],
    'POST:/admin/tutorials' => ['CommunityController', 'createTutorial'],
    'PUT:/admin/tutorials/{id}' => ['CommunityController', 'updateTutorial'],
    'POST:/admin/tutorials/{id}/update' => ['CommunityController', 'updateTutorial'],
    'DELETE:/admin/tutorials/{id}' => ['CommunityController', 'deleteTutorial'],

    // Notifications (shared - works for both user and admin)
    'GET:/notifications' => ['UserController', 'notifications'],
    'GET:/notifications/unread-count' => ['UserController', 'notificationsUnreadCount'],
    'POST:/notifications/{id}/read' => ['UserController', 'markNotificationRead'],
    'POST:/notifications/read-all' => ['UserController', 'markAllNotificationsRead'],
];

// Match route with parameters
$matchedRoute = null;
$params = [];

foreach ($routes as $routePattern => $handler) {
    list($method, $path) = explode(':', $routePattern, 2);

    if ($method !== $requestMethod) continue;

    // Convert {param} to regex
    $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
    $regex = '#^' . $regex . '$#';

    if (preg_match($regex, $requestUri, $matches)) {
        $matchedRoute = $handler;
        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $params[$key] = $value;
            }
        }
        break;
    }
}

// Handle 404
if (!$matchedRoute) {
    jsonResponse(['error' => 'Not found'], 404);
}

// CSRF validation for state-changing requests
// Exempt: login, register, csrf (these don't need CSRF token)
$csrfExemptRoutes = [
    'POST:/auth/login',
    'POST:/auth/register',
    'GET:/auth/csrf',
];
$currentRouteKey = "{$requestMethod}:{$requestUri}";

if (in_array($requestMethod, ['POST', 'PUT', 'DELETE'])) {
    // Check if this route pattern is exempt
    $isExempt = false;
    foreach ($csrfExemptRoutes as $exemptRoute) {
        list($exemptMethod, $exemptPath) = explode(':', $exemptRoute, 2);
        if ($requestMethod === $exemptMethod && $requestUri === $exemptPath) {
            $isExempt = true;
            break;
        }
    }

    if (!$isExempt) {
        validateCsrf();
    }
}

// Execute controller
try {
    $controllerClass = $matchedRoute[0];
    $action = $matchedRoute[1];

    $controller = new $controllerClass();
    $controller->$action($params);
} catch (Exception $e) {
    // Never leak exception details to client — log server-side only
    error_log('[PORTAL][API_ERROR] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    jsonResponse([
        'error' => 'Terjadi kesalahan server. Silakan coba lagi.'
    ], 500);
}
