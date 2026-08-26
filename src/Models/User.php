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

    /**
     * Percobaan Plugin — setelan manual milik admin.
     *
     * trial_enabled yang menentukan Bonus Member terkunci atau tidak. Durasi
     * dan satuannya HANYA untuk ditampilkan; keduanya tidak pernah dihitung
     * terhadap tanggal apa pun, dan tidak diambil dari lisensi.
     */
    public bool $trial_enabled = false;
    public ?int $trial_duration = null;
    public ?string $trial_unit = null;
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

        // array_key_exists, bukan isset: mematikan percobaan mengirim 0, dan
        // isset() menganggap 0 sah — tapi null juga harus bisa lewat untuk
        // mengosongkan durasinya.
        if (array_key_exists('trial_enabled', $data)) {
            $updateData['trial_enabled'] = (int) (bool) $data['trial_enabled'];
        }
        if (array_key_exists('trial_duration', $data)) {
            $updateData['trial_duration'] = $data['trial_duration'];
        }
        if (array_key_exists('trial_unit', $data)) {
            $updateData['trial_unit'] = $data['trial_unit'];
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
    public static function findAll(int $limit = 15, int $offset = 0, ?string $search = null): array
    {
        // Pencarian dijalankan di database, bukan di klien. Daftarnya
        // berpaginasi 15 baris; menyaring di klien hanya akan menelusuri baris
        // yang kebetulan sedang tampil, dan kotak cari yang melewatkan sebagian
        // besar datanya lebih buruk daripada tidak ada kotak cari sama sekali.
        $where = '';
        $params = [];

        if ($search !== null && $search !== '') {
            $where = "WHERE name LIKE ? OR email LIKE ? OR username LIKE ?";
            $like = '%' . self::escapeLike($search) . '%';
            $params = [$like, $like, $like];
        }

        $params[] = $limit;
        $params[] = $offset;

        return Database::fetchAll(
            "SELECT id, username, name, email, role, is_active, created_at, last_login_at,
                    trial_enabled, trial_duration, trial_unit
             FROM users {$where} ORDER BY created_at DESC LIMIT ? OFFSET ?",
            $params
        );
    }

    /**
     * Jumlah baris yang cocok dengan pencarian — dipakai paginasi.
     *
     * Harus memakai kondisi yang sama persis dengan findAll(); kalau berbeda,
     * jumlah halaman tidak akan cocok dengan isinya.
     */
    public static function countMatching(?string $search = null): int
    {
        if ($search === null || $search === '') return self::count();

        $like = '%' . self::escapeLike($search) . '%';
        $row = Database::fetchOne(
            "SELECT COUNT(*) as count FROM users
             WHERE name LIKE ? OR email LIKE ? OR username LIKE ?",
            [$like, $like, $like]
        );
        return (int) $row['count'];
    }

    /**
     * Menetralkan wildcard LIKE.
     *
     * Tanpa ini, mengetik "%" mencocokkan seluruh baris dan "_" mencocokkan
     * karakter apa pun — pencarian berperilaku aneh tanpa sebab yang terlihat
     * oleh yang mengetiknya.
     */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
    
    /**
     * Create instance from array
     */
    /** Apakah user ini sedang dalam masa percobaan plugin? */
    public function onPluginTrial(): bool
    {
        return $this->trial_enabled;
    }

    /**
     * Durasi percobaan sebagaimana ditulis admin, mis. "3 Bulan".
     *
     * Mengembalikan null bila percobaannya mati atau angkanya belum diisi —
     * kalimat "selama  " tanpa angka lebih membingungkan daripada tidak ada
     * kalimatnya sama sekali.
     */
    public function trialLabel(): ?string
    {
        if (!$this->trial_enabled || !$this->trial_duration) return null;

        $satuan = $this->trial_unit === 'hari' ? 'Hari' : 'Bulan';
        return $this->trial_duration . ' ' . $satuan;
    }

    public static function fromArray(array $row): self
    {
        $user = new self();
        $user->id = (int) $row['id'];
        $user->username = $row['username'] ?? null;
        $user->email = $row['email'];
        $user->password_hash = $row['password_hash'];
        $user->name = $row['name'];
        $user->role = $row['role'];
        // ?? — kolom percobaan baru; baris lama yang belum tersentuh
        // migration tetap terbaca tanpa error.
        $user->trial_enabled = (bool) ($row['trial_enabled'] ?? false);
        $user->trial_duration = isset($row['trial_duration']) ? (int) $row['trial_duration'] : null;
        $user->trial_unit = $row['trial_unit'] ?? null;
        $user->is_active = (bool) $row['is_active'];
        $user->email_verified_at = $row['email_verified_at'] ?? null;
        $user->last_login_at = $row['last_login_at'] ?? null;
        $user->last_login_ip = $row['last_login_ip'] ?? null;
        $user->created_at = $row['created_at'];
        $user->updated_at = $row['updated_at'];
        
        return $user;
    }
}
