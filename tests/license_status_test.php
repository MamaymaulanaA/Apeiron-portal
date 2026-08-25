<?php
/**
 * Uji pembacaan status lisensi dari License Server.
 *
 *   php tests/license_status_test.php
 *
 * Databasenya SQLite in-memory dengan tanda tangan Database yang sama persis
 * seperti produksi, jadi yang diuji model dan controller yang sesungguhnya.
 *
 * Yang dijaga di sini satu aturan, dan aturan ini yang paling mahal kalau
 * salah: KEGAGALAN MEMBACA BUKAN BERARTI TIDAK AKTIF. Kalau server sedang
 * diam, portal harus mengakui tidak tahu — bukan menampilkan lisensi yang
 * masih berlaku sebagai mati, dan bukan pula menghapus keadaan terakhir yang
 * sudah diketahui.
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

        self::$pdo->exec("CREATE TABLE user_licenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            transaction_id INTEGER NOT NULL,
            license_key TEXT NOT NULL,
            api_key TEXT,
            product_id TEXT NOT NULL DEFAULT 'apeiron-kit',
            assigned_at TEXT,
            license_status_json TEXT,
            license_checked_at TEXT,
            created_at TEXT
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

Database::boot();

require_once dirname(__DIR__) . '/src/Models/UserLicense.php';

// ------------------------------------------------------------------ periksa
$pass = 0; $fail = 0;
function check(string $label, $got, $want): void
{
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL  %-52s got=%s want=%s\n", $label, var_export($got, true), var_export($want, true));
}

function makeLicense(?string $json = null, ?string $checkedAt = null): UserLicense
{
    $id = Database::insert('user_licenses', [
        'user_id' => 1,
        'transaction_id' => 1,
        'license_key' => 'APEIRON-TEST',
        'product_id' => 'apeiron-kit',
        'assigned_at' => '2026-01-01 00:00:00',
        'license_status_json' => $json,
        'license_checked_at' => $checkedAt,
        'created_at' => '2026-01-01 00:00:00',
    ]);
    return UserLicense::find($id);
}

$SERVER_ACTIVE = [
    'status' => 'active',
    'is_lifetime' => false,
    'duration_label' => '1 Tahun',
    'expires_at' => '2027-01-01 00:00:00',
    'days_remaining' => 327,
];

echo "--- cache dibaca kembali dengan benar ---\n";
$l = makeLicense(json_encode($SERVER_ACTIVE), gmdate('Y-m-d H:i:s'));
check('status tersimpan terbaca', $l->license_status['status'], 'active');
check('sisa hari tersimpan terbaca', $l->license_status['days_remaining'], 327);
check('duration_label terbaca', $l->license_status['duration_label'], '1 Tahun');

echo "--- baris tanpa cache ---\n";
$kosong = makeLicense();
// null berarti "belum diketahui". Kalau ini pernah berubah jadi array kosong
// atau string, tampilan akan menganggapnya status yang sah dan menampilkan
// lisensi berlaku sebagai tidak aktif.
check('tanpa cache -> null', $kosong->license_status, null);
check('tanpa cache -> checked_at null', $kosong->license_checked_at, null);

echo "--- JSON rusak tidak boleh jadi status setengah jadi ---\n";
$rusak = makeLicense('{bukan json', gmdate('Y-m-d H:i:s'));
check('json rusak -> null', $rusak->license_status, null);

echo "--- kapan perlu menyegarkan ---\n";
check('tanpa cache -> perlu', $kosong->needsRefresh(), true);
$baru = makeLicense(json_encode($SERVER_ACTIVE), gmdate('Y-m-d H:i:s'));
check('cache baru -> tidak perlu', $baru->needsRefresh(600), false);
$lama = makeLicense(json_encode($SERVER_ACTIVE), gmdate('Y-m-d H:i:s', time() - 3600));
check('cache 1 jam -> perlu (ttl 600)', $lama->needsRefresh(600), true);
check('cache 1 jam -> tidak perlu (ttl 7200)', $lama->needsRefresh(7200), false);
// Batas TTL diuji dari dua sisi supaya perbandingannya tidak diam-diam
// terbalik saat kode diubah.
$tepat = makeLicense(json_encode($SERVER_ACTIVE), gmdate('Y-m-d H:i:s', time() - 500));
check('cache 500d, ttl 600 -> tidak perlu', $tepat->needsRefresh(600), false);
check('cache 500d, ttl 400 -> perlu', $tepat->needsRefresh(400), true);

echo "--- waktu cache dibaca sebagai UTC ---\n";
// license_checked_at disimpan dengan gmdate(). Kalau dibaca sebagai waktu
// lokal, di WIB (UTC+7) cache berumur 1 menit akan terhitung 7 jam dan
// portal memanggil server pada setiap kali halaman dibuka.
$satuMenit = makeLicense(json_encode($SERVER_ACTIVE), gmdate('Y-m-d H:i:s', time() - 60));
check('cache 1 menit -> tidak perlu (ttl 600)', $satuMenit->needsRefresh(600), false);

echo "--- menyimpan hasil baca ---\n";
$simpan = makeLicense();
$simpan->cacheStatus(['status' => 'expired', 'is_lifetime' => false, 'days_remaining' => 0]);
check('objek langsung ikut terbarui', $simpan->license_status['status'], 'expired');
$ulang = UserLicense::find($simpan->id);
check('tersimpan ke database', $ulang->license_status['status'], 'expired');
check('days_remaining 0 tetap 0', $ulang->license_status['days_remaining'], 0);
check('checked_at ikut terisi', $ulang->license_checked_at !== null, true);
check('setelah disimpan -> tidak perlu segarkan', $ulang->needsRefresh(600), false);

echo "--- lifetime ---\n";
$life = makeLicense();
$life->cacheStatus([
    'status' => 'active',
    'is_lifetime' => true,
    'duration_label' => 'Lifetime',
    'expires_at' => null,
    'days_remaining' => null,
]);
$life = UserLicense::find($life->id);
check('lifetime -> is_lifetime true', $life->license_status['is_lifetime'], true);
check('lifetime -> tanpa tanggal berakhir', $life->license_status['expires_at'], null);
check('lifetime -> tanpa hitung mundur', $life->license_status['days_remaining'], null);

echo "--- membaca dengan key kosong tidak menyentuh jaringan ---\n";
// Dipanggil tanpa konstanta LICENSE_SERVER_URL sengaja: kalau penjaga key
// kosong hilang, baris ini akan error karena konstantanya belum ada — jadi
// tesnya gagal keras, bukan diam-diam lolos.
require_once dirname(__DIR__) . '/src/Services/ServerApiService.php';
check('key kosong -> null', ServerApiService::fetchLicenseStatus(''), null);

printf("\n%d lulus, %d gagal\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
