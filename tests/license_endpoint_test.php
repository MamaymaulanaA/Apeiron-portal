<?php
/**
 * Uji ketahanan penyusunan baris lisensi di UserController.
 *
 *   php tests/license_endpoint_test.php
 *
 * Yang dijaga di sini satu hal, dan hal ini sudah pernah gagal di produksi:
 * MENAMPILKAN LISENSI TIDAK BOLEH IKUT GAGAL HANYA KARENA CACHE-NYA GAGAL
 * DITULIS.
 *
 * Kejadiannya: migration kolom cache belum dijalankan, cacheStatus() melempar
 * PDOException, penangan exception di router menangkapnya, dan seluruh
 * halaman Plugin berubah jadi "Terjadi kesalahan server" — padahal license
 * key-nya sudah terbaca dengan benar dan tidak ada hubungannya dengan cache.
 *
 * Tabel di bawah sengaja dibuat TANPA kolom cache, meniru persis keadaan
 * database sebelum migration dijalankan.
 */

// ---------------------------------------------------------------- pengganti
class Database
{
    private static ?PDO $pdo = null;

    /** @param bool $withCache false = keadaan sebelum migration dijalankan. */
    public static function boot(bool $withCache): void
    {
        self::$pdo = new PDO('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $cacheCols = $withCache
            ? ', license_status_json TEXT, license_checked_at TEXT'
            : '';

        self::$pdo->exec("CREATE TABLE user_licenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER, transaction_id INTEGER,
            license_key TEXT, api_key TEXT, product_id TEXT,
            assigned_at TEXT, created_at TEXT{$cacheCols}
        )");

        self::$pdo->exec("INSERT INTO user_licenses
            (user_id, transaction_id, license_key, api_key, product_id, assigned_at, created_at)
            VALUES (1, 1, 'APEIRON-NYATA', 'kunci-api', 'apeiron-kit', '2026-01-01 00:00:00', '2026-01-01 00:00:00')");
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

/**
 * Pengganti ServerApiService: jawabannya diatur dari tes, tanpa jaringan.
 *
 * Didefinisikan SEBELUM UserController dimuat, jadi kelas inilah yang dipakai.
 */
class ServerApiService
{
    public static ?array $jawaban = null;

    public static function fetchLicenseStatus(string $key, string $product = 'apeiron-kit'): ?array
    {
        return self::$jawaban;
    }
}

require_once dirname(__DIR__) . '/src/Models/UserLicense.php';
require_once dirname(__DIR__) . '/api/controllers/UserController.php';

// ------------------------------------------------------------------ periksa
$pass = 0; $fail = 0;
function check(string $label, $got, $want): void
{
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL  %-56s got=%s want=%s\n", $label, var_export($got, true), var_export($want, true));
}

$m = new ReflectionMethod('UserController', 'licensePayload');
$m->setAccessible(true);
$payload = static fn(UserLicense $l) => $m->invoke(null, $l);

$SERVER_AKTIF = [
    'status' => 'active',
    'is_lifetime' => false,
    'duration_label' => '1 Tahun',
    'expires_at' => '2027-01-01 00:00:00',
    'days_remaining' => 327,
];

// =====================================================================
echo "--- TANPA kolom cache (persis produksi sebelum migration) ---\n";
// =====================================================================
Database::boot(false);
ServerApiService::$jawaban = $SERVER_AKTIF;

$out = null;
$lempar = null;
try {
    $out = $payload(UserLicense::find(1));
} catch (Throwable $e) {
    $lempar = get_class($e) . ': ' . $e->getMessage();
}

// Inti seluruh berkas ini.
check('tidak melempar', $lempar, null);
check('license key tetap terkirim', $out['license_key'] ?? null, 'APEIRON-NYATA');
check('api key tetap terkirim', $out['api_key'] ?? null, 'kunci-api');
// Statusnya sudah terbaca dari server; gagal menyimpan tidak boleh
// menghapusnya dari tampilan.
check('status dari server tetap tampil', $out['status']['status'] ?? null, 'active');
check('sisa hari tetap tampil', $out['status']['days_remaining'] ?? null, 327);
check('tidak ditandai basi', $out['status_stale'] ?? null, false);
check('checked_at terisi walau gagal simpan', is_string($out['status_checked_at'] ?? null), true);
// Petunjuknya harus menyebut berkas yang perlu dijalankan.
check(
    'petunjuk menyebut migration',
    str_contains($out['status_error'] ?? '', '2026_08_25_all.sql'),
    true
);

// =====================================================================
echo "--- DENGAN kolom cache, server menjawab ---\n";
// =====================================================================
Database::boot(true);
ServerApiService::$jawaban = $SERVER_AKTIF;

$out = $payload(UserLicense::find(1));
check('status dari server', $out['status']['status'] ?? null, 'active');
check('tidak basi', $out['status_stale'], false);
check('tanpa pesan galat', $out['status_error'], null);
// Benar-benar tersimpan, bukan sekadar dikembalikan.
$ulang = UserLicense::find(1);
check('tersimpan ke database', $ulang->license_status['status'] ?? null, 'active');

// =====================================================================
echo "--- DENGAN kolom cache, server diam ---\n";
// =====================================================================
ServerApiService::$jawaban = null;

// Paksa kedaluwarsa supaya jalur penyegaran benar-benar dilewati.
Database::update('user_licenses', ['license_checked_at' => gmdate('Y-m-d H:i:s', time() - 86400)], 'id = ?', [1]);

$out = $payload(UserLicense::find(1));
// Cache lama dipertahankan dan ditandai basi — inilah yang membuat gangguan
// jaringan sesaat tidak menghapus keadaan terakhir yang diketahui.
check('cache lama dipertahankan', $out['status']['status'] ?? null, 'active');
check('ditandai basi', $out['status_stale'], true);
check('tanpa pesan galat', $out['status_error'], null);

// =====================================================================
echo "--- server diam DAN belum pernah ada cache ---\n";
// =====================================================================
Database::boot(true);
ServerApiService::$jawaban = null;

$out = $payload(UserLicense::find(1));
// null = belum diketahui. Kalau ini pernah berubah jadi status yang tampak
// sah, tampilan akan menyebut lisensi berlaku sebagai tidak aktif.
check('status null', $out['status'], null);
// Tidak ada yang lama untuk dipertahankan, jadi bukan "basi" melainkan kosong.
check('bukan basi, memang kosong', $out['status_stale'], false);
check('license key tetap terkirim', $out['license_key'], 'APEIRON-NYATA');

printf("\n%d lulus, %d gagal\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
