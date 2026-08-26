<?php
/**
 * Uji jalur admin menyalakan dan mematikan Percobaan Plugin.
 *
 *   php tests/trial_admin_test.php
 *
 * Bagian inilah yang tidak bisa saya lihat terender di browser saat fiturnya
 * dibuat — mock lokal tidak bisa dijalankan sebagai admin di sesi itu. Jadi
 * yang diperiksa di sini adalah rantai sebenarnya: apa yang dikirim form
 * masuk ke AdminController::updateUser(), tersimpan lewat User::update(), dan
 * terbaca kembali oleh gerbang Bonus.
 */

class Database
{
    private static ?PDO $pdo = null;

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
function requireAdmin(): void {}
function requireAuth(): void {}

/** Isi badan permintaan, seperti dikirim form admin. */
$INPUT = [];
function getJsonInput(): array { return $GLOBALS['INPUT']; }

define('PORTAL_ROOT', sys_get_temp_dir() . '/apeiron_trial_admin');
define('ITEMS_PER_PAGE', 15);

Database::boot();
require_once dirname(__DIR__) . '/src/Models/User.php';

// AdminController menyeret banyak kelas lain saat dimuat utuh, jadi hanya
// method yang diuji yang diambil — kodenya tetap kode produksi apa adanya.
$src = file_get_contents(dirname(__DIR__) . '/api/controllers/AdminController.php');
$i = strpos($src, 'public function updateUser(array $params): void');
$j = strpos($src, "\n    }", $i);
eval('class AdminUser { ' . substr($src, $i, $j - $i + 6) . ' }');

$pass = 0; $fail = 0;
function check(string $label, $got, $want): void
{
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL  %-54s got=%s want=%s\n", $label, var_export($got, true), var_export($want, true));
}

function panggil(array $input, int $id = 1): array
{
    $GLOBALS['INPUT'] = $input;
    $c = new AdminUser();
    try { $c->updateUser(['id' => $id]); } catch (ApiResponse $r) { return [$r->status, $r->payload]; }
    return [0, []];
}

Database::insert('users', [
    'email' => 'sinta@example.com', 'password_hash' => 'x', 'name' => 'Sinta Dewi',
    'role' => 'user', 'trial_enabled' => 0, 'is_active' => 1,
    'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
]);

// =====================================================================
echo "--- bawaan: user baru bukan peserta percobaan ---\n";
// =====================================================================
// Menjalankan SQL hanya membuat kolomnya ada; tidak menyalakan apa pun.
$u = User::find(1);
check('trial_enabled default mati', $u->trial_enabled, false);
check('tanpa durasi', $u->trial_duration, null);
check('label null', $u->trialLabel(), null);
check('bukan peserta percobaan', $u->onPluginTrial(), false);

// =====================================================================
echo "--- admin menyalakan percobaan ---\n";
// =====================================================================
// Bentuk payload-nya sama persis dengan yang dikirim form admin.
[$kode] = panggil(['trial_enabled' => 1, 'trial_duration' => 2, 'trial_unit' => 'bulan']);
check('tersimpan', $kode, 200);

$u = User::find(1);
check('trial_enabled menyala', $u->trial_enabled, true);
check('durasi tersimpan', $u->trial_duration, 2);
check('satuan tersimpan', $u->trial_unit, 'bulan');
check('label untuk ditampilkan', $u->trialLabel(), '2 Bulan');
check('dianggap peserta percobaan', $u->onPluginTrial(), true);

echo "--- satuan hari ---\n";
panggil(['trial_enabled' => 1, 'trial_duration' => 14, 'trial_unit' => 'hari']);
check('label 14 Hari', User::find(1)->trialLabel(), '14 Hari');

// =====================================================================
echo "--- admin mematikan percobaan ---\n";
// =====================================================================
[$kode] = panggil(['trial_enabled' => 0]);
check('tersimpan', $kode, 200);

$u = User::find(1);
check('trial_enabled mati', $u->trial_enabled, false);
// Dikosongkan supaya angka lama tidak muncul lagi saat dinyalakan kembali.
check('durasi ikut dikosongkan', $u->trial_duration, null);
check('satuan ikut dikosongkan', $u->trial_unit, null);

// =====================================================================
echo "--- validasi ---\n";
// =====================================================================
check('durasi 0 ditolak', panggil(['trial_enabled' => 1, 'trial_duration' => 0, 'trial_unit' => 'bulan'])[0], 400);
check('durasi negatif ditolak', panggil(['trial_enabled' => 1, 'trial_duration' => -3, 'trial_unit' => 'bulan'])[0], 400);
check('durasi kelewat besar ditolak', panggil(['trial_enabled' => 1, 'trial_duration' => 1000, 'trial_unit' => 'bulan'])[0], 400);
check('satuan asing ditolak', panggil(['trial_enabled' => 1, 'trial_duration' => 2, 'trial_unit' => 'tahun'])[0], 400);
// Yang ditolak tidak boleh setengah tersimpan.
check('tidak ada yang tersimpan diam-diam', User::find(1)->trial_enabled, false);

echo "--- satuan kosong dianggap bulan ---\n";
// Form selalu mengirim satuannya, tetapi permintaan dari luar bisa saja tidak.
check('tanpa satuan lolos', panggil(['trial_enabled' => 1, 'trial_duration' => 5])[0], 200);
check('jadi bulan', User::find(1)->trial_unit, 'bulan');

// =====================================================================
echo "--- menyunting hal lain tidak mengganggu percobaan ---\n";
// =====================================================================
// Admin mengubah nama saja; form tidak selalu mengirim field percobaan.
panggil(['name' => 'Sinta Dewi Pratama']);
$u = User::find(1);
check('nama berubah', $u->name, 'Sinta Dewi Pratama');
check('percobaan tetap menyala', $u->trial_enabled, true);
check('durasinya utuh', $u->trial_duration, 5);

printf("\n%d lulus, %d gagal\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
