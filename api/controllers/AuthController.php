<?php
/**
 * Auth API Controller
 */
class AuthController
{
    /**
     * Login user
     */
    public function login(): void
    {
        // Apply rate limiting (5 attempts per 60 seconds)
        applyRateLimit('login', 5, 60);

        $input = getJsonInput();

        if (empty($input['email']) || empty($input['password'])) {
            jsonResponse(['error' => 'Email dan password wajib diisi'], 400);
        }
        
        try {
            $user = User::findByEmail($input['email']);
            
            if (!$user || !password_verify($input['password'], $user->password_hash)) {
                jsonResponse(['error' => 'Email atau password salah'], 401);
            }
            
            if (!$user->is_active) {
                jsonResponse(['error' => 'Akun Anda tidak aktif'], 403);
            }
            
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            // Save to session - unified auth approach
            // Set both keys for compatibility with API and PHP routes
            $_SESSION['portal_user'] = $user;
            $_SESSION['user_id'] = $user->id;
            $_SESSION['user_role'] = $user->role;
            $_SESSION['_created'] = time();

            // Update last login
            User::updateLastLoginById($user->id, $_SERVER['REMOTE_ADDR'] ?? null);
            
            jsonResponse([
                'message' => 'Login berhasil',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role
                ]
            ]);
        } catch (Exception $e) {
            jsonResponse(['error' => 'Terjadi kesalahan'], 500);
        }
    }
    
    /**
     * Register new user
     */
    public function register(): void
    {
        // Apply rate limiting (10 attempts per 60 seconds per IP)
        applyRateLimit('register', 10, 60);

        $input = getJsonInput();
        
        // Validate
        $errors = [];
        if (empty($input['name'])) $errors[] = 'Nama wajib diisi';
        if (empty($input['username'])) $errors[] = 'Username wajib diisi';
        if (empty($input['email'])) $errors[] = 'Email wajib diisi';
        if (empty($input['password'])) $errors[] = 'Password wajib diisi';
        
        // Validate username format
        if (!empty($input['username']) && !preg_match('/^[a-zA-Z0-9_]{3,30}$/', $input['username'])) {
            $errors[] = 'Username hanya boleh huruf, angka, underscore (3-30 karakter)';
        }
        
        if (strlen($input['password'] ?? '') < 8) $errors[] = 'Password minimal 8 karakter';
        
        // Validate email format
        if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Format email tidak valid';
        }
        
        if (!empty($errors)) {
            jsonResponse(['error' => implode(', ', $errors)], 400);
        }
        
        // Check existing email
        if (User::findByEmail($input['email'])) {
            jsonResponse(['error' => 'Email sudah terdaftar'], 400);
        }
        
        // Check existing username
        if (User::findByUsername($input['username'])) {
            jsonResponse(['error' => 'Username sudah digunakan'], 400);
        }
        
        try {
            $userId = User::create([
                'name' => $input['name'],
                'username' => strtolower($input['username']),
                'email' => strtolower($input['email']),
                'password_hash' => password_hash($input['password'], PASSWORD_BCRYPT, ['cost' => 12]),
                'role' => 'user',
                'is_active' => 1
            ]);
            
            jsonResponse([
                'message' => 'Registrasi berhasil',
                'user_id' => $userId
            ], 201);
        } catch (Exception $e) {
            error_log("[REG_ERROR] Exception: " . $e->getMessage());
            error_log("[REG_ERROR] Trace: " . $e->getTraceAsString());
            // Never leak exception details to client
            jsonResponse(['error' => 'Gagal mendaftarkan user. Silakan coba lagi.'], 500);
        }
    }
    
    /**
     * Logout user
     */
    public function logout(): void
    {
        // Clear all auth session keys
        unset($_SESSION['portal_user']);
        unset($_SESSION['user_id']);
        unset($_SESSION['user_role']);
        unset($_SESSION['_user_cache']);

        session_destroy();

        jsonResponse(['message' => 'Logout berhasil']);
    }
    
    /**
     * Get current user
     */
    public function me(): void
    {
        requireAuth();

        $user = currentUser();
        jsonResponse([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role
            ],
            'csrf_token' => getCsrfToken()
        ]);
    }

    /**
     * Get CSRF token (for authenticated and unauthenticated requests)
     */
    public function csrf(): void
    {
        jsonResponse([
            'csrf_token' => getCsrfToken()
        ]);
    }
}
