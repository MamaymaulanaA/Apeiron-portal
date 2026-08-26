<?php
/**
 * Uji ketahanan portal terhadap database yang migrationnya BELUM dijalankan.
 *
 *   php tests/pre_migration_test.php
 *
 * Alasannya konkret. Pemisahan plugin dan versi membuat Plugin::getLatest()
 * memulai kerjanya dengan query ke tabel plugin_products. Tabel itu belum ada
 * sampai migration dijalankan, jadi query-nya melempar — dan karena
 * UserController::dashboard() memanggil getLatest(), SELURUH dashboard user
 * gagal 500. Bukan bagian pluginnya saja: seluruh halaman.
 *
 * Ini lolos ke produksi. Tes ini menjalankan skema lama apa adanya dan
 * memastikan tidak ada endpoint user yang melempar karenanya.
 *
 * Aturannya: fitur yang datanya belum ada boleh KOSONG, tetapi tidak boleh
 * meruntuhkan halaman yang sudah berjalan sebelumnya.
 */

class Database
{
    private static ?PDO $pdo = null;

    /** Skema PERSIS seperti sebelum satu pun migration dijalankan. */
    public static function boot(): void
    {
        self::$pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        self::$pdo->exec("CREATE TABLE plugins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            version TEXT NOT NULL, filename TEXT NOT NULL, file_path TEXT NOT NULL,
            changelog TEXT, is_active INTEGER NOT NULL DEFAULT 1, created_at TEXT
        )");
        self::$pdo->exec("INSERT INTO plugins (version, filename, file_path, changelog, is_active, created_at)
            VALUES ('1.0.3', 'apeiron-kit-1.0.3.zip', 'apeiron-kit-1.0.3.zip', 'Update Gm', 1, '2026-05-19 01:20:16')");

        self::$pdo->exec("CREATE TABLE transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL,
            document_path TEXT, document_original_name TEXT,
            product_id TEXT DEFAULT 'apeiron-kit', amount REAL DEFAULT 0,
            proof_image_path TEXT, proof_original_name TEXT,
            status TEXT NOT NULL DEFAULT 'pending', admin_notes TEXT,
            approved_by INTEGER, approved_at TEXT, rejected_at TEXT,
            created_at TEXT, updated_at TEXT
        )");

        // user_licenses TANPA kolom cache.
        self::$pdo->exec("CREATE TABLE user_licenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, transaction_id INTEGER,
            license_key TEXT, api_key TEXT, product_id TEXT DEFAULT 'apeiron-kit',
            assigned_at TEXT, created_at TEXT
        )");

        self::$pdo->exec("CREATE TABLE templates (
            id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, description TEXT,
            thumbnail_path TEXT, checkout_url TEXT,
            is_standard INTEGER DEFAULT 0, is_recommended INTEGER DEFAULT 0,
            is_active INTEGER DEFAULT 1, sort_order INTEGER DEFAULT 0,
            created_at TEXT, updated_at TEXT
        )");

        // Tabel yang BELUM ADA sama sekali: plugin_products, bonuses,
        // community_groups, tutorials, tutorial_steps.
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
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
    public function __construct(public array $payload, public int $status) { parent::__construct('response'); }
}
function jsonResponse(array $data, int $status = 200): void { throw new ApiResponse($data, $status); }
function requireAuth(): void {}
function requireAdmin(): void {}
function applyRateLimit(string $k, int $n, int $s): void {}
function currentUser(): object { return (object) ['id' => 7, 'name' => 'Sinta', 'email' => 's@x.id']; }

define('PORTAL_ROOT', sys_get_temp_dir() . '/apeiron_premig');
define('PLUGIN_MAX_SIZE', 50 * 1024 * 1024);
define('APP_NAME', 'Apeiron Portal');
define('APP_TIMEZONE', 'Asia/Jakarta');
date_default_timezone_set('UTC');

function format_date(string $d, string $f = 'd M Y H:i'): string
{
    try {
        $dt = new DateTime($d, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone(APP_TIMEZONE));
        return $dt->format($f);
    } catch (Exception $e) { return $d; }
}
function format_currency(float $a): string { return 'Rp ' . number_format($a, 0, ',', '.'); }

class Notification
{
    public static function notifyAllLicensedUsers(...$a): int { return 0; }
    public static function findByUser(int $id): array { return []; }
    public static function countUnread(int $id): int { return 0; }
}
class ServerApiService
{
    public static function fetchLicenseStatus(string $k, string $p = 'apeiron-kit'): ?array { return null; }
}

Database::boot();
foreach (['PluginProduct', 'Plugin', 'Transaction', 'UserLicense', 'Bonus', 'Template', 'CommunityGroup', 'Tutorial', 'TutorialStep', 'User'] as $m) {
    $f = dirname(__DIR__) . "/src/Models/{$m}.php";
    if (is_file($f)) require_once $f;
}
require_once dirname(__DIR__) . '/api/controllers/PluginController.php';
require_once dirname(__DIR__) . '/api/controllers/BonusController.php';
require_once dirname(__DIR__) . '/api/controllers/UserController.php';
require_once dirname(__DIR__) . '/api/controllers/CommunityController.php';

$pass = 0; $fail = 0;
function check(string $label, $got, $want): void
{
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL  %-58s got=%s want=%s\n", $label, var_export($got, true), var_export($want, true));
}

/** Jalankan aksi; kembalikan status HTTP, atau nama exception bila melempar. */
function panggil(callable $fn)
{
    try { $fn(); } catch (ApiResponse $r) { return $r->status; } catch (Throwable $e) { return get_class($e) . ': ' . substr($e->getMessage(), 0, 60); }
    return 0;
}

$user = new UserController();
$plugin = new PluginController();
$bonus = new BonusController();
$komunitas = new CommunityController();

// =====================================================================
echo "--- dashboard user: TIDAK boleh runtuh ---\n";
// =====================================================================
// Inilah regresi yang lolos ke produksi. getLatest() melempar, dan seluruh
// dashboard ikut gagal — bukan hanya bagian pluginnya.
check('getLatest tidak melempar', is_object(Plugin::getLatest()) || Plugin::getLatest() === null, true);
check('getLatest tetap menemukan versi lama', Plugin::getLatest()?->version, '1.0.3');
check('dashboard 200', panggil(fn() => $user->dashboard()), 200);

// =====================================================================
echo "--- endpoint user lain ---\n";
// =====================================================================
check('licenses 200', panggil(fn() => $user->licenses()), 200);
// Plugin dan Bonus boleh kosong, tetapi tidak boleh melempar.
check('user/plugins 200', panggil(fn() => $plugin->userPlugins()), 200);
check('user/bonuses 200', panggil(fn() => $bonus->userBonuses()), 200);

// =====================================================================
echo "--- isinya kosong, bukan error ---\n";
// =====================================================================
try { $plugin->userPlugins(); } catch (ApiResponse $r) { $p = $r->payload; }
check('daftar plugin kosong', $p['plugins'], []);

try { $bonus->userBonuses(); } catch (ApiResponse $r) { $b = $r->payload; }
check('daftar bonus kosong', $b['bonuses'], []);

// =====================================================================
echo "--- dashboard tetap memuat data yang memang ada ---\n";
// =====================================================================
// Yang penting: fitur lama tetap utuh, tidak sekadar tidak melempar.
try { $user->dashboard(); } catch (ApiResponse $r) { $d = $r->payload; }
check('versi plugin tetap terbaca', $d['latest_plugin']['version'] ?? null, '1.0.3');
check('changelog tetap terbaca', $d['latest_plugin']['changelog'] ?? null, 'Update Gm');
check('transaction null untuk user baru', $d['transaction'], null);
check('can_upload true untuk user baru', $d['can_upload'], true);

// =====================================================================
echo "--- user yang SUDAH disetujui menyentuh tabel yang belum ada ---\n";
// Celah yang mudah terlewat: pemeriksaan sebelumnya lolos hanya karena user
// ujinya tidak punya transaksi, sehingga Bonus::allActive() tak pernah
// dipanggil. User yang sudah disetujui — sebagian besar user nyata — justru
// menyentuhnya.
Database::insert('transactions', [
    'user_id' => 7, 'status' => 'approved', 'amount' => 0,
    'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
]);

check('dashboard tetap 200 (approved)', panggil(fn() => $user->dashboard()), 200);
check('user/bonuses tetap 200 (approved)', panggil(fn() => $bonus->userBonuses()), 200);
check('licenses tetap 200 (approved)', panggil(fn() => $user->licenses()), 200);

try { $bonus->userBonuses(); } catch (ApiResponse $r) { $b2 = $r->payload; }
check('bonus kosong, bukan error', $b2['bonuses'] ?? null, []);
check('access tetap dilaporkan', $b2['access'] ?? null, 'granted');

echo "--- Group dan Panduan: halaman user juga ---\n";
// Keduanya menyentuh tabel yang belum ada. Boleh kosong, tidak boleh runtuh.
check('user/groups tidak melempar', is_int(panggil(fn() => $komunitas->userGroups())), true);
check('user/tutorials tidak melempar', is_int(panggil(fn() => $komunitas->userTutorials())), true);

echo "--- admin: memberi petunjuk, bukan diam ---\n";
// =====================================================================
// Admin justru harus tahu penyebabnya, jadi di sini 500 dengan pesan yang
// menyebut berkas migration adalah perilaku yang benar.
$kode = panggil(fn() => $plugin->adminPlugins());
check('admin/plugins 500', $kode, 500);
try { $plugin->adminPlugins(); } catch (ApiResponse $r) { $a = $r->payload; }
check('pesannya menyebut migration', str_contains($a['error'] ?? '', '.sql'), true);

printf("\n%d lulus, %d gagal\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
