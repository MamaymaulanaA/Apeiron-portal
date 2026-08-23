<?php
/**
 * User Model
 */

class User
{
    public int $id;
    public ?string $username = null;
    public string $email;
    public string $password_hash;
    public string $name;
    public string $role;
    public bool $is_active;
    public ?string $email_verified_at;
    public ?string $last_login_at;
    public ?string $last_login_ip;
    public string $created_at;
    public string $updated_at;
    
    /**
     * Find user by ID
     */
    public static function find(int $id): ?self
    {
        $row = Database::fetchOne("SELECT * FROM users WHERE id = ?", [$id]);
        return $row ? self::fromArray($row) : null;
    }
    
    /**
     * Find user by email
     */
    public static function findByEmail(string $email): ?self
    {
        $row = Database::fetchOne("SELECT * FROM users WHERE email = ?", [strtolower($email)]);
        return $row ? self::fromArray($row) : null;
    }
    
    /**
     * Find user by username
     */
    public static function findByUsername(string $username): ?self
    {
        $row = Database::fetchOne("SELECT * FROM users WHERE username = ?", [strtolower($username)]);
        return $row ? self::fromArray($row) : null;
    }
    
    /**
     * Get all users with pagination
     */
    public static function paginate(int $page = 1, int $perPage = ITEMS_PER_PAGE, ?string $role = null): array
    {
        $offset = ($page - 1) * $perPage;
        
        $where = $role ? "WHERE role = ?" : "";
        $params = $role ? [$role, $perPage, $offset] : [$perPage, $offset];
        
        $sql = "SELECT * FROM users {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $rows = Database::fetchAll($sql, $params);
        
        $countSql = "SELECT COUNT(*) as count FROM users {$where}";
        $countParams = $role ? [$role] : [];
        $total = (int) Database::fetchOne($countSql, $countParams)['count'];
        
        return [
            'data' => array_map([self::class, 'fromArray'], $rows),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }
    
    /**
     * Create new user
     */
    public static function create(array $data): int
    {
        $insertData = [
            'email' => strtolower($data['email']),
            'name' => $data['name'],
            'role' => $data['role'] ?? 'user',
            'is_active' => $data['is_active'] ?? 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Handle password - can be either password or password_hash
        if (isset($data['password_hash'])) {
            $insertData['password_hash'] = $data['password_hash'];
        } elseif (isset($data['password'])) {
            $insertData['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        
        // Add username if provided
        if (!empty($data['username'])) {
            $insertData['username'] = strtolower($data['username']);
        }
        
        return Database::insert('users', $insertData);
    }
    
    /**
     * Update user
     */
    public function update(array $data): bool
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['email'])) {
            $updateData['email'] = strtolower($data['email']);
        }
        if (array_key_exists('username', $data)) {
            $updateData['username'] = $data['username'] ? strtolower($data['username']) : null;
        }
        if (isset($data['password'])) {
            $updateData['password_hash'] = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
        }
        if (isset($data['is_active'])) {
            $updateData['is_active'] = $data['is_active'];
        }
        if (isset($data['role'])) {
            $updateData['role'] = $data['role'];
        }

        $updateData['updated_at'] = date('Y-m-d H:i:s');

        return Database::update('users', $updateData, 'id = ?', [$this->id]) > 0;
    }
    
    /**
     * Delete user
     */
    public function delete(): bool
    {
        return Database::delete('users', 'id = ?', [$this->id]) > 0;
    }

    /**
     * Verify password
     */
    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password_hash);
    }
    
    /**
     * Update last login
     */
    public function updateLastLogin(): void
    {
        Database::update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ], 'id = ?', [$this->id]);
    }
    
    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
    
    /**
     * Check if email exists
     */
    public static function emailExists(string $email, ?int $excludeId = null): bool
    {
        $sql = "SELECT COUNT(*) as count FROM users WHERE email = ?";
        $params = [$email];
        
        if ($excludeId) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }
        
        $result = Database::fetchOne($sql, $params);
        return (int) $result['count'] > 0;
    }
    
    /**
     * Count users
     */
    public static function count(?string $role = null): int
    {
        if ($role) {
            $result = Database::fetchOne("SELECT COUNT(*) as count FROM users WHERE role = ?", [$role]);
        } else {
            $result = Database::fetchOne("SELECT COUNT(*) as count FROM users");
        }
        return (int) $result['count'];
    }
    
    /**
     * Update last login by ID (static version for API)
     */
    public static function updateLastLoginById(int $userId, ?string $ip = null): void
    {
        Database::update('users', [
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip' => $ip
        ], 'id = ?', [$userId]);
    }
    
    /**
     * Static update method for API
     */
    public static function updateStatic(int $id, array $data): bool
    {
        $updateData = [];
        
        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }
        if (isset($data['password_hash'])) {
            $updateData['password_hash'] = $data['password_hash'];
        }
        
        $updateData['updated_at'] = date('Y-m-d H:i:s');
        
        return Database::update('users', $updateData, 'id = ?', [$id]) > 0;
    }
    
    /**
     * Find all users with pagination for API
     */
    public static function findAll(int $limit = 15, int $offset = 0): array
    {
        $rows = Database::fetchAll(
            "SELECT id, username, name, email, role, is_active, created_at, last_login_at 
             FROM users ORDER BY created_at DESC LIMIT ? OFFSET ?",
            [$limit, $offset]
        );
        return $rows;
    }
    
    /**
     * Create instance from array
     */
    public static function fromArray(array $row): self
    {
        $user = new self();
        $user->id = (int) $row['id'];
        $user->username = $row['username'] ?? null;
        $user->email = $row['email'];
        $user->password_hash = $row['password_hash'];
        $user->name = $row['name'];
        $user->role = $row['role'];
        $user->is_active = (bool) $row['is_active'];
        $user->email_verified_at = $row['email_verified_at'] ?? null;
        $user->last_login_at = $row['last_login_at'] ?? null;
        $user->last_login_ip = $row['last_login_ip'] ?? null;
        $user->created_at = $row['created_at'];
        $user->updated_at = $row['updated_at'];
        
        return $user;
    }
}
