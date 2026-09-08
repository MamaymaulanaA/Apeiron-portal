<?php
/**
 * Uji plugin dan riwayat versinya, lewat model dan controller yang sama
 * dengan yang berjalan di produksi.
 *
 *   php tests/plugin_version_test.php
 *
 * Yang dijaga di sini adalah cacat yang membuat admin harus menghapus baris
 * database secara manual:
 *
 *   1. Plugin::create() dulu menjalankan `UPDATE plugins SET is_active = 0`
 *      sebelum menyisipkan, sehingga mengunggah versi baru mematikan SEMUA
 *      versi sebelumnya. Karena getLatest() menyaring is_active = 1, versi
 *      lama hilang dari jangkauan user.
 *   2. Nama berkas diturunkan dari nomor versi saja dan tidak ada pemeriksaan
 *      duplikat, sehingga dua baris bisa menunjuk satu berkas — menghapus
 *      salah satunya mematikan unduhan baris satunya.
 *
 * Alur yang diuji sama persis dengan yang diminta:
 * tambah plugin -> tambah versi -> user melihat versi terbaru -> tambah versi
 * baru -> versi terbaru berubah -> versi lama tetap ada dan tetap terunduh.
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

        self::$pdo->exec("CREATE TABLE plugin_products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL, slug TEXT NOT NULL UNIQUE,
            description TEXT, is_active INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT, updated_at TEXT
        )");

        self::$pdo->exec("CREATE TABLE plugins (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            plugin_id INTEGER,
            version TEXT NOT NULL, version_sort TEXT,
            filename TEXT NOT NULL, file_path TEXT NOT NULL,
            changelog TEXT, released_at TEXT,
            is_active INTEGER NOT NULL DEFAULT 1,
            created_at TEXT, updated_at TEXT
        )");

        self::$pdo->exec("CREATE TABLE user_licenses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER, transaction_id INTEGER,
            license_key TEXT, api_key TEXT, product_id TEXT,
            assigned_at TEXT, license_status_json TEXT,
            license_checked_at TEXT, created_at TEXT
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

/** Turunan Error, BUKAN Exception — controller membungkus jalur suksesnya
 *  dengan catch (Throwable), jadi respons sukses yang turunan Exception akan
 *  tertangkap sendiri dan tiap panggilan berubah jadi 500. */
class ApiResponse extends Error
{
    public function __construct(public array $payload, public int $status) { parent::__construct('response'); }
}
function jsonResponse(array $data, int $status = 200): void { throw new ApiResponse($data, $status); }
function requireAuth(): void {}
function requireAdmin(): void {}
function applyRateLimit(string $k, int $n, int $s): void {}

define('PORTAL_ROOT', sys_get_temp_dir() . '/apeiron_plugin_test');
define('PLUGIN_MAX_SIZE', 50 * 1024 * 1024);

class Notification
{
    public static array $sent = [];
    public static function notifyAllLicensedUsers(string $t, string $title, string $m, ?int $ref = null): int
    {
        self::$sent[] = ['type' => $t, 'title' => $title, 'message' => $m, 'ref' => $ref];
        return count(Database::fetchAll("SELECT DISTINCT user_id FROM user_licenses"));
    }
}

Database::boot();
require_once dirname(__DIR__) . '/src/Models/PluginProduct.php';
require_once dirname(__DIR__) . '/src/Models/Plugin.php';
require_once dirname(__DIR__) . '/api/controllers/PluginController.php';

// Folder berkas untuk uji.
$dir = PORTAL_ROOT . '/storage/plugins/';
if (!is_dir($dir)) mkdir($dir, 0777, true);
array_map('unlink', glob($dir . '*.zip') ?: []);

// ------------------------------------------------------------------ periksa
$pass = 0; $fail = 0;
function check(string $label, $got, $want): void
{
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL  %-56s got=%s want=%s\n", $label, var_export($got, true), var_export($want, true));
}

/** Panggil aksi controller dan kembalikan [status, payload]. */
function call(callable $fn): array
{
    try { $fn(); } catch (ApiResponse $r) { return [$r->status, $r->payload]; }
    return [0, []];
}

/** Tiru berkas ZIP yang diunggah. */
/**
 * ZIP sungguhan berisi berkas plugin.
 *
 * Sebelumnya hanya empat bita header ZIP — cukup untuk melewati pemeriksaan
 * tipe, tetapi bukan arsip yang bisa dibuka. Begitu unggahan mulai merapikan
 * struktur folder di dalamnya, berkas palsu itu gagal dibuka dan seluruh
 * alur rilis ikut berhenti. Yang diuji harus berbentuk seperti yang
 * sebenarnya diunggah admin.
 *
 * Sengaja TANPA folder pembungkus: itulah bentuk yang bermasalah, dan
 * perapian di controller yang harus membetulkannya.
 */
function fakeZip(string $name = 'kit.zip'): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'zip');

    $z = new ZipArchive();
    $z->open($tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $z->addFromString('apeiron-kit.php', '<?php /* Plugin Name: Apeiron Kit */');
    $z->addFromString('readme.txt', 'Apeiron Kit');
    $z->close();

    return ['name' => $name, 'tmp_name' => $tmp, 'size' => filesize($tmp), 'error' => UPLOAD_ERR_OK];
}

/**
 * Controller uji: satu-satunya yang berbeda adalah cara berkas dipindahkan.
 *
 * move_uploaded_file() menolak berkas yang bukan hasil unggahan HTTP, dan
 * penjagaan itu memang harus tetap ada di produksi. Yang diganti di sini
 * hanya langkah pemindahannya; validasi, penamaan berkas, penyimpanan baris,
 * dan pembersihan berkas tetap kode yang sama persis dengan produksi.
 */
class TestPluginController extends PluginController
{
    protected static function moveUpload(string $tmp, string $dest): bool
    {
        return copy($tmp, $dest);
    }
}

$ctl = new TestPluginController();

/** Rilis satu versi lewat controller. */
function release(PluginController $ctl, int $productId, string $version, string $changelog = null): array
{
    $_POST = ['version' => $version, 'changelog' => $changelog ?? '', 'notify' => '0'];
    $_FILES = ['plugin' => fakeZip()];

    return call(fn() => $ctl->createVersion(['id' => $productId]));
}

// =====================================================================
echo "--- kunci urut versi ---\n";
// =====================================================================
// Perbandingan teks biasa salah pada 1.0.10 vs 1.0.9.
$key = fn($v) => Plugin::sortKey($v);
check('1.0.10 lebih besar dari 1.0.9', $key('1.0.10') > $key('1.0.9'), true);
check('1.0.10.1 lebih besar dari 1.0.10', $key('1.0.10.1') > $key('1.0.10'), true);
check('2.0.0 lebih besar dari 1.99.99', $key('2.0.0') > $key('1.99.99'), true);
check('10.0.0 lebih besar dari 9.0.0', $key('10.0.0') > $key('9.0.0'), true);
// Ruas yang tidak ada harus nol, bukan mengulang ruas sebelumnya.
check('1.0 -> empat ruas', $key('1.0'), '00001.00000.00000.00000');
check('akhiran non-angka terbaca angkanya', $key('1.0.3-beta'), '00001.00000.00003.00000');

// =====================================================================
echo "--- Admin: tambah plugin ---\n";
// =====================================================================
$_POST = ['name' => 'Apeiron Kit', 'description' => 'Plugin utama.'];
[$st, $res] = call(fn() => $ctl->createPlugin());
check('plugin dibuat', $st, 201);
$pid = $res['id'] ?? 0;
check('slug diturunkan dari nama', PluginProduct::find($pid)->slug, 'apeiron-kit');

$_POST = ['name' => 'Apeiron Kit'];
[$st, $res] = call(fn() => $ctl->createPlugin());
check('slug ganda ditolak', $st, 400);
check('pesannya menyebut slug', str_contains($res['error'] ?? '', 'sudah dipakai'), true);

$_POST = ['name' => ''];
check('nama kosong ditolak', call(fn() => $ctl->createPlugin())[0], 400);

// =====================================================================
echo "--- Admin: rilis tiga versi berturut-turut ---\n";
// =====================================================================
check('v1.0.1 dirilis', release($ctl, $pid, '1.0.1', 'rilis awal')[0], 201);
check('v1.0.2 dirilis', release($ctl, $pid, '1.0.2', 'perbaikan')[0], 201);
check('v1.0.3 dirilis', release($ctl, $pid, '1.0.3', 'fitur baru')[0], 201);

// INI CACAT LAMANYA. Dulu hanya satu baris yang bertahan aktif.
$aktif = (int) Database::fetchOne("SELECT COUNT(*) c FROM plugins WHERE is_active = 1")['c'];
check('KETIGA versi tetap aktif', $aktif, 3);
check('jumlah versi tersimpan', Plugin::countFor($pid), 3);

check('versi terbaru = 1.0.3', Plugin::latestFor($pid)->version, '1.0.3');

// =====================================================================
echo "--- Admin: nomor versi ganda ditolak ---\n";
// =====================================================================
$r = release($ctl, $pid, '1.0.3');
check('versi ganda ditolak', $r[0], 400);
check('pesannya menyebut versinya', str_contains($r[1]['error'] ?? '', '1.0.3'), true);
check('tidak menambah baris', Plugin::countFor($pid), 3);

echo "--- nomor versi tidak valid ---\n";
check('kosong ditolak', release($ctl, $pid, '')[0], 400);
check('spasi ditolak', release($ctl, $pid, '1.0 4')[0], 400);
check('garis miring ditolak', release($ctl, $pid, '../1.0.4')[0], 400);
check('diawali huruf ditolak', release($ctl, $pid, 'v1.0.4')[0], 400);

// =====================================================================
echo "--- tiap versi punya berkasnya sendiri ---\n";
// =====================================================================
$files = array_map(fn($v) => $v->filename, Plugin::forProduct($pid));
check('tiga berkas berbeda', count(array_unique($files)), 3);
check('nama berkas memuat slug', str_starts_with($files[0], 'apeiron-kit-'), true);
$adaSemua = true;
foreach ($files as $f) if (!is_file($dir . $f)) $adaSemua = false;
check('semua berkas ada di disk', $adaSemua, true);

// =====================================================================
echo "--- User: versi terbaru dan riwayatnya ---\n";
// =====================================================================
[$st, $res] = call(fn() => $ctl->userPlugins());
check('endpoint user 200', $st, 200);
$p = $res['plugins'][0] ?? [];
check('nama plugin', $p['name'] ?? null, 'Apeiron Kit');
check('versi utama = 1.0.3', $p['latest']['version'] ?? null, '1.0.3');
check('riwayat berisi 2 versi', count($p['previous'] ?? []), 2);
check('riwayat urut turun', array_map(fn($v) => $v['version'], $p['previous']), ['1.0.2', '1.0.1']);
// Tautan unduh menunjuk versi tertentu, bukan "yang terbaru saja".
check('unduhan per versi', str_contains($p['previous'][0]['download_url'], 'version='), true);
check('id versi ikut di tautan', str_contains($p['previous'][0]['download_url'], (string) $p['previous'][0]['id']), true);

// =====================================================================
echo "--- Admin merilis v1.0.10: yang terbaru ikut berubah ---\n";
// =====================================================================
check('v1.0.10 dirilis', release($ctl, $pid, '1.0.10', 'sepuluh')[0], 201);
// Kalau urutannya memakai teks biasa, 1.0.3 akan menang atas 1.0.10.
check('terbaru jadi 1.0.10', Plugin::latestFor($pid)->version, '1.0.10');

[$st, $res] = call(fn() => $ctl->userPlugins());
$p = $res['plugins'][0];
check('user melihat 1.0.10', $p['latest']['version'], '1.0.10');
check('riwayat kini 3 versi', count($p['previous']), 3);
check('1.0.3 turun ke riwayat', $p['previous'][0]['version'], '1.0.3');
// Versi lama tetap ada berkasnya — inilah inti "versi lama tetap terunduh".
$lama = Plugin::find($p['previous'][2]['id']);
check('berkas v1.0.1 masih ada', is_file($dir . $lama->filename), true);
check('versi tertua tetap 1.0.1', $lama->version, '1.0.1');

// =====================================================================
echo "--- menonaktifkan satu versi tidak menyentuh yang lain ---\n";
// =====================================================================
$v102 = null;
foreach (Plugin::forProduct($pid) as $v) if ($v->version === '1.0.2') $v102 = $v;
$_POST = ['version' => '1.0.2', 'is_active' => '0'];
$_FILES = [];
check('nonaktifkan 1.0.2', call(fn() => $ctl->updateVersion(['id' => $v102->id]))[0], 200);

check('yang lain tetap aktif', (int) Database::fetchOne("SELECT COUNT(*) c FROM plugins WHERE is_active = 1")['c'], 3);
check('terbaru tidak berubah', Plugin::latestFor($pid)->version, '1.0.10');

[$st, $res] = call(fn() => $ctl->userPlugins());
$versiUser = array_map(fn($v) => $v['version'], $res['plugins'][0]['previous']);
check('1.0.2 hilang dari sisi user', in_array('1.0.2', $versiUser, true), false);
check('1.0.1 masih tampil', in_array('1.0.1', $versiUser, true), true);
// Barisnya tetap ada; hanya disembunyikan. Empat versi tersimpan —
// 1.0.1, 1.0.2, 1.0.3, 1.0.10 — karena semua percobaan versi ganda dan tidak
// valid ditolak tanpa menyisipkan apa pun.
check('barisnya tidak dihapus', Plugin::countFor($pid), 4);

// =====================================================================
echo "--- hapus satu versi: hanya versi itu ---\n";
// =====================================================================
$berkas102 = Plugin::find($v102->id)->filename;
check('hapus 1.0.2', call(fn() => $ctl->deleteVersion(['id' => $v102->id]))[0], 200);
check('barisnya hilang', Plugin::find($v102->id), null);
check('berkasnya ikut hilang', is_file($dir . $berkas102), false);
check('versi lain utuh', Plugin::countFor($pid), 3);
check('plugin induk utuh', PluginProduct::find($pid)->name, 'Apeiron Kit');
check('terbaru tetap 1.0.10', Plugin::latestFor($pid)->version, '1.0.10');

// =====================================================================
echo "--- berkas bersama tidak ikut terhapus ---\n";
// =====================================================================
// Database lama bisa punya dua baris menunjuk satu berkas. Menghapus salah
// satunya tidak boleh mematikan unduhan baris satunya.
$bersama = 'apeiron-kit-bersama.zip';
file_put_contents($dir . $bersama, "PK\x03\x04");
$a = Plugin::create(['plugin_id' => $pid, 'version' => '9.0.0', 'filename' => $bersama, 'file_path' => $bersama]);
$b = Plugin::create(['plugin_id' => $pid, 'version' => '9.0.1', 'filename' => $bersama, 'file_path' => $bersama]);
check('hapus salah satu', call(fn() => $ctl->deleteVersion(['id' => $a]))[0], 200);
check('berkas TETAP ada', is_file($dir . $bersama), true);
check('baris satunya masih menunjuk berkas yang ada', is_file($dir . Plugin::find($b)->filename), true);
call(fn() => $ctl->deleteVersion(['id' => $b]));
check('setelah pemakai terakhir hilang, berkas dibuang', is_file($dir . $bersama), false);

// =====================================================================
echo "--- notifikasi rilis ---\n";
// =====================================================================
Database::insert('user_licenses', ['user_id' => 1, 'transaction_id' => 1, 'license_key' => 'K', 'product_id' => 'apeiron-kit']);
Notification::$sent = [];
$_POST = ['version' => '2.0.0', 'changelog' => 'besar'];
$_FILES = ['plugin' => fakeZip()];
$r = call(fn() => $ctl->createVersion(['id' => $pid]));
check('rilis berhasil', $r[0], 201);
check('notifikasi terkirim', count(Notification::$sent), 1);
check('menyebut nama plugin', str_contains(Notification::$sent[0]['message'], 'Apeiron Kit'), true);
check('menyebut nomor versi', str_contains(Notification::$sent[0]['message'], '2.0.0'), true);
// Mengubah nama plugin BUKAN rilis, jadi tidak boleh memberi tahu siapa pun.
Notification::$sent = [];
$_POST = ['name' => 'Apeiron Kit Pro'];
$_FILES = [];
call(fn() => $ctl->updatePlugin(['id' => $pid]));
check('ubah nama plugin tidak mengirim notifikasi', count(Notification::$sent), 0);

// =====================================================================
echo "--- hapus plugin: seluruh versinya ikut ---\n";
// =====================================================================
$sisa = array_map(fn($v) => $v->filename, Plugin::forProduct($pid));
check('hapus plugin', call(fn() => $ctl->deletePlugin(['id' => $pid]))[0], 200);
check('plugin hilang', PluginProduct::find($pid), null);
check('versinya ikut hilang', Plugin::countFor($pid), 0);
$adaSisa = false;
foreach ($sisa as $f) if (is_file($dir . $f)) $adaSisa = true;
check('berkasnya ikut dibersihkan', $adaSisa, false);

// =====================================================================
echo "--- lisensi tidak tersentuh sama sekali ---\n";
// =====================================================================
// Lisensi menunjuk plugin lewat product_id (slug), bukan lewat versi. Merilis,
// menyembunyikan, atau menghapus versi tidak boleh mengubah satu baris pun.
check('baris lisensi utuh', count(Database::fetchAll("SELECT * FROM user_licenses")), 1);
check('product_id lisensi utuh', Database::fetchOne("SELECT product_id FROM user_licenses")['product_id'], 'apeiron-kit');

array_map('unlink', glob($dir . '*.zip') ?: []);

// =====================================================================
echo "--- zip yang tersimpan siap dipasang WordPress ---\n";
// =====================================================================
// Unggahan tadi TIDAK punya folder pembungkus. WordPress akan memakai nama
// berkas zip — apeiron-kit-1.0.9.zip — sebagai nama folder plugin, sehingga
// tiap rilis mendarat di direktori berbeda dan tombol Perbarui tidak pernah
// muncul. Perapian di controller yang mencegahnya.
// Produk sendiri: bagian sebelumnya menghapus $pid beserta seluruh
// versinya, jadi blok ini tidak boleh bergantung padanya.
$_POST = ['name' => 'Apeiron Kit', 'slug' => 'apeiron-kit'];
call(fn() => $ctl->createPlugin());
$pidZip = (int) Database::fetchOne("SELECT id FROM plugin_products WHERE slug = 'apeiron-kit'")['id'];

$rilis = release($ctl, $pidZip, '1.0.9');
check('rilis berhasil', $rilis[0], 201);

// Barisnya diambil berdasarkan nomor versi, bukan lewat latestFor():
// 1.0.10 sudah dirilis di bagian sebelumnya dan secara semver ia lebih
// baru daripada 1.0.9, jadi latestFor() akan menunjuk berkas yang salah.
$baris = Database::fetchOne(
    'SELECT filename FROM plugins WHERE plugin_id = ? AND version = ?',
    [$pidZip, '1.0.9']
);
check('barisnya tersimpan', is_array($baris), true);

$berkas = PORTAL_ROOT . '/storage/plugins/' . ($baris['filename'] ?? '');
check('berkasnya ada', is_file($berkas), true);

$z = new ZipArchive();
check('bisa dibuka sebagai zip', $z->open($berkas), true);

$akar = [];
for ($i = 0; $i < $z->numFiles; $i++) {
    $n = $z->getNameIndex($i);
    $akar[strtok($n, '/')] = true;
}
$z->close();

// Tepat satu folder di tingkat atas, dan namanya slug produk — bukan versi.
check('satu folder di tingkat atas', count($akar), 1);
check('namanya slug produk', array_key_first($akar), 'apeiron-kit');
check('bukan nama berversi', str_contains(array_key_first($akar), '1.0.9'), false);
printf("\n%d lulus, %d gagal\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
