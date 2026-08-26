<?php
/**
 * Uji gerbang akses Bonus Member.
 *
 *   php tests/bonus_access_test.php
 *
 * Yang dijaga: bonus hanya keluar bila transaksi user sudah disetujui, dan
 * penjagaannya ada DI SERVER.
 *
 * Menyembunyikan kartu di layar bukan penjagaan — endpoint-nya bisa dibuka
 * langsung, dan tautan unduh bisa tersimpan di riwayat browser. Karena itu
 * yang diperiksa di sini bukan "apakah tampilannya kosong", melainkan
 * apakah PAYLOAD-nya benar-benar tidak memuat satu pun nama bonus, tautan,
 * atau berkas ketika aksesnya belum diberikan.
 */

// ---------------------------------------------------------------- pengganti
class Database
{
    private static ?PDO $pdo = null;

    public static function boot(): void
    {
        self::$pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        self::$pdo->exec("CREATE TABLE transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            document_path TEXT, document_original_name TEXT,
            product_id TEXT DEFAULT 'apeiron-kit', amount REAL DEFAULT 0,
            proof_image_path TEXT, proof_original_name TEXT,
            status TEXT NOT NULL DEFAULT 'pending',
            admin_notes TEXT, approved_by INTEGER,
            approved_at TEXT, rejected_at TEXT,
            created_at TEXT, updated_at TEXT
        )");

        self::$pdo->exec("CREATE TABLE bonuses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL, category TEXT, description TEXT,
            thumbnail_path TEXT,
            source_type TEXT NOT NULL DEFAULT 'link',
            file_path TEXT, file_original_name TEXT, file_size INTEGER,
            external_url TEXT,
            is_active INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
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

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(', ', array_map(static fn($c) => "{$c} = ?", array_keys($data)));
        return self::query(
            "UPDATE {$table} SET {$set} WHERE {$where}",
            array_merge(array_values($data), $whereParams)
        )->rowCount();
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

define('PORTAL_ROOT', sys_get_temp_dir() . '/apeiron_bonus_access');
define('PLUGIN_MAX_SIZE', 1);

$USER_ID = 7;
function currentUser(): object { return (object) ['id' => $GLOBALS['USER_ID'], 'name' => 'Sinta']; }

Database::boot();
require_once dirname(__DIR__) . '/src/Models/Transaction.php';
require_once dirname(__DIR__) . '/src/Models/Bonus.php';
require_once dirname(__DIR__) . '/api/controllers/BonusController.php';

// ------------------------------------------------------------------ periksa
$pass = 0; $fail = 0;
function check(string $label, $got, $want): void
{
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL  %-58s got=%s want=%s\n", $label, var_export($got, true), var_export($want, true));
}

function call(callable $fn): array
{
    try { $fn(); } catch (ApiResponse $r) { return [$r->status, $r->payload]; }
    return [0, []];
}

/** Ganti transaksi user jadi status tertentu. null = tidak punya transaksi. */
function setStatus(?string $status): void
{
    Database::delete('transactions', 'user_id = ?', [$GLOBALS['USER_ID']]);
    if ($status === null) return;

    Database::insert('transactions', [
        'user_id' => $GLOBALS['USER_ID'],
        'status' => $status,
        'amount' => 0,
        'created_at' => '2026-01-01 00:00:00',
        'updated_at' => '2026-01-01 00:00:00',
    ]);
}

// Dua bonus dengan rahasia yang berbeda bentuk: satu berkas, satu tautan.
Database::insert('bonuses', [
    'name' => 'Pack Ikon Undangan', 'category' => 'Aset',
    'source_type' => 'file', 'file_path' => 'RAHASIA-BERKAS.zip',
    'file_original_name' => 'ikon.zip', 'file_size' => 100,
    'is_active' => 1, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
]);
Database::insert('bonuses', [
    'name' => 'Kelas Online', 'category' => 'Materi',
    'source_type' => 'link', 'external_url' => 'https://RAHASIA-TAUTAN.example/kelas',
    'is_active' => 1, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
]);

$ctl = new BonusController();

// =====================================================================
echo "--- aturan aksesnya satu, dipakai bersama Group ---\n";
// =====================================================================
$state = fn(?string $s) => Transaction::memberAccessState(
    $s === null ? null : Transaction::fromArray([
        'id' => 1, 'user_id' => 1, 'status' => $s, 'amount' => 0,
        'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00',
    ])
);
check('approved -> granted', $state('approved'), 'granted');
// Admin sudah memverifikasi pembayaran; yang tertunda hanya penerbitan
// lisensi karena server lisensi tidak terhubung.
check('pending_license -> granted', $state('pending_license'), 'granted');
check('waiting_verification -> pending', $state('waiting_verification'), 'pending');
check('pending -> pending', $state('pending'), 'pending');
check('rejected -> rejected', $state('rejected'), 'rejected');
check('tanpa transaksi -> none', $state(null), 'none');

// =====================================================================
echo "--- daftar bonus: hanya keluar setelah disetujui ---\n";
// =====================================================================
foreach ([
    ['approved', 'granted', 2],
    ['pending_license', 'granted', 2],
    ['waiting_verification', 'pending', 0],
    ['pending', 'pending', 0],
    ['rejected', 'rejected', 0],
    [null, 'none', 0],
] as [$status, $harapAkses, $harapJumlah]) {
    setStatus($status);
    [$kode, $data] = call(fn() => $ctl->userBonuses());
    $label = $status ?? 'tanpa transaksi';

    check("{$label}: kode 200", $kode, 200);
    check("{$label}: access = {$harapAkses}", $data['access'], $harapAkses);
    check("{$label}: jumlah bonus", count($data['bonuses']), $harapJumlah);

    // INI PEMERIKSAAN INTINYA. Bukan "tampilannya kosong", melainkan
    // payload-nya benar-benar tidak memuat apa pun yang bisa dipakai.
    $json = json_encode($data);
    $bocor = str_contains($json, 'RAHASIA-TAUTAN')
        || str_contains($json, 'RAHASIA-BERKAS')
        || str_contains($json, 'Pack Ikon Undangan')
        || str_contains($json, 'Kelas Online');

    check("{$label}: payload memuat data bonus", $bocor, $harapJumlah > 0);
}

// =====================================================================
echo "--- kategori tidak boleh ikut bocor ---\n";
// =====================================================================
// Nama kategori pun memberi tahu bonus apa saja yang ada.
setStatus('rejected');
[, $data] = call(fn() => $ctl->userBonuses());
check('rejected: kategori kosong', $data['categories'], []);
setStatus('approved');
[, $data] = call(fn() => $ctl->userBonuses());
check('approved: kategori terisi', count($data['categories']), 2);

// =====================================================================
echo "--- unduhan: penjagaan kedua, yang sebenarnya menentukan ---\n";
// =====================================================================
// Tautan unduh bisa tersimpan di riwayat browser dari saat masih disetujui,
// atau id-nya ditebak. Menyembunyikan tombolnya tidak menutup apa pun.
foreach (['waiting_verification', 'pending', 'rejected', null] as $status) {
    setStatus($status);
    [$kode, $data] = call(fn() => $ctl->downloadBonus(['id' => 2]));
    $label = $status ?? 'tanpa transaksi';

    check("{$label}: unduh ditolak 403", $kode, 403);
    check("{$label}: tanpa tautan di pesan galat", str_contains(json_encode($data), 'RAHASIA-TAUTAN'), false);
}

// Setelah disetujui, unduhan berjalan seperti sebelumnya.
setStatus('approved');
[$kode, $data] = call(fn() => $ctl->downloadBonus(['id' => 999]));
check('approved: bonus tak dikenal -> 404, bukan 403', $kode, 404);

// =====================================================================
echo "--- perubahan status langsung berlaku ---\n";
// =====================================================================
// Tidak ada cache di jalur ini: mengubah status lalu memanggil ulang harus
// langsung berbeda, tanpa perlu logout atau menunggu apa pun.
setStatus('waiting_verification');
[, $sebelum] = call(fn() => $ctl->userBonuses());
setStatus('approved');
[, $sesudah] = call(fn() => $ctl->userBonuses());
check('sebelum approve: kosong', count($sebelum['bonuses']), 0);
check('sesudah approve: muncul', count($sesudah['bonuses']), 2);

setStatus('rejected');
[, $dicabut] = call(fn() => $ctl->userBonuses());
check('setelah ditolak: hilang lagi', count($dicabut['bonuses']), 0);

// =====================================================================
echo "--- bonus nonaktif tetap tersembunyi meski sudah disetujui ---\n";
// =====================================================================
// Penjagaan transaksi tidak boleh menimpa aturan yang sudah ada.
Database::update('bonuses', ['is_active' => 0], 'id = ?', [1]);
setStatus('approved');
[, $data] = call(fn() => $ctl->userBonuses());
check('yang nonaktif tidak ikut', count($data['bonuses']), 1);
check('yang aktif tetap ada', $data['bonuses'][0]['name'], 'Kelas Online');

printf("\n%d lulus, %d gagal\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
