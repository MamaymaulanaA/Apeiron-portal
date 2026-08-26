<?php
/**
 * Uji bahwa role dan status aktif untuk otorisasi dibaca dari database,
 * bukan dari salinan yang disimpan sesi saat login.
 *
 *   php tests/auth_role_test.php
 *
 * Ini inti perbaikan bug dashboard admin. Selama role hanya dibaca dari
 * $_SESSION['portal_user'], /auth/me melaporkan role saat login — sehingga
 * admin yang baru diangkat masih dianggap user, dan admin yang diturunkan
 * masih dianggap admin. Frontend memakai role itu untuk memilih navigasi,
 * jadi role yang basi langsung menjadi salah kamar.
 *
 * Kode yang diuji adalah kode produksi apa adanya: badan fungsinya dibaca
 * dari api/helpers.php, bukan disalin ke sini. Tiap skenario meng-eval salinan
 * dengan nama berbeda supaya cache `static` di dalamnya ikut baru — cache itu
 * memang disengaja (satu permintaan = satu query), dan tanpa penamaan ulang
 * skenario kedua akan membaca hasil skenario pertama.
 */

class Database
{
    private static ?PDO $pdo = null;
    public static bool $lumpuh = false;   // meniru database yang tidak terbaca
    public static int $jumlahQuery = 0;

    public static function boot(): void
    {
        self::$pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        self::$pdo->exec("CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT, email TEXT, password_hash TEXT, name TEXT,
            role TEXT DEFAULT 'user',
            trial_enabled INTEGER NOT NULL DEFAULT 0,
            trial_duration INTEGER, trial_unit TEXT,
            is_active INTEGER DEFAULT 1,
            email_verified_at TEXT, remember_token TEXT,
            last_login_at TEXT, last_login_ip TEXT,
            created_at TEXT, updated_at TEXT
        )");
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        self::$jumlahQuery++;
        if (self::$lumpuh) {
            throw new PDOException('SQLSTATE[HY000] server has gone away');
        }
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetchOne(string $sql, array $params = []): ?array
    {
        return self::query($sql, $params)->fetch() ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = implode(', ', array_keys($data));
        $ph = implode(', ', array_fill(0, count($data), '?'));
        self::query("INSERT INTO {$table} ({$cols}) VALUES ({$ph})", array_values($data));
        return (int) self::$pdo->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $wp = []): int
    {
        $set = implode(', ', array_map(static fn($c) => "{$c} = ?", array_keys($data)));
        return self::query("UPDATE {$table} SET {$set} WHERE {$where}", array_merge(array_values($data), $wp))->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int
    {
        return self::query("DELETE FROM {$table} WHERE {$where}", $params)->rowCount();
    }
}

class ApiResponse extends Error
{
    public function __construct(public array $payload, public int $status) { parent::__construct('r'); }
}
function jsonResponse(array $d, int $s = 200): void { throw new ApiResponse($d, $s); }

define('PORTAL_ROOT', dirname(__DIR__));
define('ITEMS_PER_PAGE', 15);

Database::boot();
require_once PORTAL_ROOT . '/src/Models/User.php';

// ---------------------------------------------------------------------------
// Ambil fungsi autentikasi dari berkas produksi, lalu buat salinan bernama unik
// per skenario supaya `static`-nya ikut baru.
// ---------------------------------------------------------------------------
$sumber = file_get_contents(PORTAL_ROOT . '/api/helpers.php');
$awal = strpos($sumber, 'function refreshCurrentUser()');
$akhir = strpos($sumber, 'function requireAdmin()');
if ($awal === false || $akhir === false) {
    fwrite(STDERR, "Tidak menemukan fungsi autentikasi di api/helpers.php\n");
    exit(1);
}
$akhir = strpos($sumber, "\n}", $akhir) + 2;
$potongan = substr($sumber, $awal, $akhir - $awal);

$nomor = 0;
/** Satu "permintaan HTTP": salinan fungsi yang baru, dengan cache kosong. */
function permintaan(): array
{
    global $potongan, $nomor;
    $n = ++$nomor;
    $kode = $potongan;
    foreach (['refreshCurrentUser', 'currentUser', 'isLoggedIn', 'isAdmin', 'requireAuth', 'requireAdmin'] as $fn) {
        $kode = preg_replace('/\b' . $fn . '\b/', $fn . '_' . $n, $kode);
    }
    eval($kode);
    return [
        'user' => "currentUser_{$n}",
        'login' => "isLoggedIn_{$n}",
        'admin' => "isAdmin_{$n}",
        'butuhAuth' => "requireAuth_{$n}",
        'butuhAdmin' => "requireAdmin_{$n}",
    ];
}

$pass = 0; $fail = 0;
function check(string $label, $got, $want): void
{
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL  %-56s got=%s want=%s\n", $label, var_export($got, true), var_export($want, true));
}

/** Kode status yang dikembalikan gerbang, atau 200 bila diloloskan. */
function status(callable $gerbang): int
{
    try { $gerbang(); return 200; } catch (ApiResponse $r) { return $r->status; }
}

$idAdmin = Database::insert('users', [
    'email' => 'admin@apeiron.id', 'password_hash' => 'x', 'name' => 'Admin Apeiron',
    'role' => 'admin', 'is_active' => 1,
    'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
]);
$idUser = Database::insert('users', [
    'email' => 'sinta@example.com', 'password_hash' => 'x', 'name' => 'Sinta Dewi',
    'role' => 'user', 'is_active' => 1,
    'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
]);

// =====================================================================
echo "--- role dibaca dari database, bukan dari sesi ---\n";
// =====================================================================
// INI BUG-NYA. Sesi menyimpan role 'user' — keadaan saat login — sementara
// database sudah menyebutnya admin. Sebelum diperbaiki, isAdmin() ikut sesi,
// /auth/me melaporkan 'user', dan frontend mengirim admin ke dashboard member.
$_SESSION = [
    'user_id' => $idAdmin,
    'portal_user' => (object) ['id' => $idAdmin, 'role' => 'user', 'is_active' => 1],
];
$f = permintaan();
check('role dari database yang dipakai', $f['user']()->role, 'admin');
check('dikenali sebagai admin', $f['admin'](), true);
check('gerbang admin meloloskan', status($f['butuhAdmin']), 200);
// Sesi ikut disegarkan supaya kode lain yang membacanya tidak tertinggal.
check('sesi ikut diperbarui', $_SESSION['portal_user']->role, 'admin');
check('user_role di sesi ikut', $_SESSION['user_role'], 'admin');

// =====================================================================
echo "--- admin yang diturunkan langsung kehilangan hak ---\n";
// =====================================================================
// Arah sebaliknya, dan yang paling berbahaya: sesi menyebut 'admin' padahal
// database sudah menurunkannya.
$_SESSION = [
    'user_id' => $idUser,
    'portal_user' => (object) ['id' => $idUser, 'role' => 'admin', 'is_active' => 1],
];
$f = permintaan();
check('bukan admin lagi', $f['admin'](), false);
check('gerbang admin menolak', status($f['butuhAdmin']), 403);
check('tetap boleh sebagai user biasa', status($f['butuhAuth']), 200);

// =====================================================================
echo "--- user yang dinonaktifkan tidak bisa memakai sesi lamanya ---\n";
// =====================================================================
Database::update('users', ['is_active' => 0], 'id = ?', [$idUser]);
$_SESSION = [
    'user_id' => $idUser,
    'portal_user' => (object) ['id' => $idUser, 'role' => 'user', 'is_active' => 1],
];
$f = permintaan();
check('tidak dianggap login', $f['login'](), false);
check('gerbang auth menolak', status($f['butuhAuth']), 401);
Database::update('users', ['is_active' => 1], 'id = ?', [$idUser]);

echo "--- barisnya dihapus ---\n";
$_SESSION = [
    'user_id' => 9999,
    'portal_user' => (object) ['id' => 9999, 'role' => 'admin', 'is_active' => 1],
];
$f = permintaan();
check('user hilang -> tidak login', $f['login'](), false);
check('gerbang auth menolak', status($f['butuhAuth']), 401);

// =====================================================================
echo "--- gangguan database bukan alasan me-logout semua orang ---\n";
// =====================================================================
// Kalau kegagalan query diperlakukan sebagai "tidak login", satu gangguan
// database akan mengeluarkan seluruh pengguna sekaligus. Permintaannya
// diteruskan dengan salinan lama dan gagal di tempat yang memang bermasalah.
$_SESSION = [
    'user_id' => $idAdmin,
    'portal_user' => (object) ['id' => $idAdmin, 'role' => 'admin', 'is_active' => 1],
];
Database::$lumpuh = true;
$f = permintaan();
check('tetap dianggap login', $f['login'](), true);
check('masih memakai salinan lama', $f['user']()->role, 'admin');
Database::$lumpuh = false;

// =====================================================================
echo "--- tanpa sesi: tidak ada query sama sekali ---\n";
// =====================================================================
// Endpoint publik seperti /auth/csrf tidak boleh menimbulkan query hanya
// karena gerbangnya memanggil isLoggedIn().
$_SESSION = [];
Database::$jumlahQuery = 0;
$f = permintaan();
check('tamu tidak login', $f['login'](), false);
check('tidak menyentuh database', Database::$jumlahQuery, 0);
check('gerbang auth menolak', status($f['butuhAuth']), 401);

// =====================================================================
echo "--- satu permintaan = satu query ---\n";
// =====================================================================
$_SESSION = [
    'user_id' => $idAdmin,
    'portal_user' => (object) ['id' => $idAdmin, 'role' => 'admin', 'is_active' => 1],
];
Database::$jumlahQuery = 0;
$f = permintaan();
$f['user'](); $f['user'](); $f['admin'](); $f['login'](); $f['butuhAdmin']();
check('dibaca sekali lalu ditahan', Database::$jumlahQuery, 1);

printf("\n%d lulus, %d gagal\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
