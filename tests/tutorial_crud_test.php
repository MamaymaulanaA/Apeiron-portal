<?php
/**
 * Uji CRUD panduan dari ujung ke ujung, lewat model dan controller yang sama
 * dengan yang dipakai produksi.
 *
 *   php tests/tutorial_crud_test.php
 *
 * Databasenya SQLite in-memory, bukan MySQL: kelas Database di sini adalah
 * pengganti yang memakai PDO SQLite dengan tanda tangan method yang sama persis
 * dengan config/database.php. Model dan controller tidak tahu bedanya, jadi
 * yang diuji tetap kode yang sesungguhnya berjalan — bukan tiruannya.
 *
 * Yang dijaga di sini adalah alur yang sempat gagal saat dipakai: menambah
 * panduan, menambah panduan berikut langkah-langkahnya, mengubahnya, dan
 * menghapusnya.
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
        self::$pdo->exec('PRAGMA foreign_keys = ON');

        self::$pdo->exec("CREATE TABLE tutorials (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            excerpt TEXT, content TEXT, category TEXT,
            icon TEXT, image_path TEXT, external_url TEXT,
            is_published INTEGER NOT NULL DEFAULT 1,
            sort_order INTEGER NOT NULL DEFAULT 0,
            created_at TEXT, updated_at TEXT
        )");

        self::$pdo->exec("CREATE TABLE tutorial_steps (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            tutorial_id INTEGER NOT NULL,
            position INTEGER NOT NULL DEFAULT 0,
            body TEXT, image_path TEXT,
            created_at TEXT, updated_at TEXT,
            FOREIGN KEY (tutorial_id) REFERENCES tutorials(id) ON DELETE CASCADE
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

// ---------------------------------------------------- pengganti helper API
/**
 * jsonResponse() di produksi mengakhiri request lewat exit(). Di sini ia
 * dilempar supaya tes bisa memeriksa status dan isinya.
 *
 * Turunan Error, BUKAN Exception — dan ini bukan detail sepele. Controller
 * membungkus jalur suksesnya dengan `catch (Exception $e)`; kalau respons
 * sukses ikut turunan Exception, ia tertangkap catch itu sendiri dan tiap
 * panggilan berubah jadi 500. Persis itu yang terjadi pada versi pertama tes
 * ini, dan kegagalannya bukan bug produksi melainkan cacat tesnya. Error
 * lolos dari catch(Exception), jadi alurnya sama dengan exit() yang asli.
 */
class ApiResponse extends Error
{
    public function __construct(public array $payload, public int $status) { parent::__construct('response'); }
}

function jsonResponse(array $data, int $status = 200): void { throw new ApiResponse($data, $status); }
function getJsonInput(): array { return []; }
function requireAuth(): void {}
function requireAdmin(): void {}
function currentUser() { return null; }
class Transaction {}
class CommunityGroup { public static function detectPlatform(?string $l): string { return 'other'; } }

define('PORTAL_ROOT', sys_get_temp_dir() . '/apeiron_test_' . getmypid());

$root = dirname(__DIR__);
require_once $root . '/src/Models/Tutorial.php';
require_once $root . '/src/Models/TutorialStep.php';
require_once $root . '/api/controllers/CommunityController.php';

// ---------------------------------------------------------------- kerangka
$pass = 0; $fail = 0;
function check(string $label, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL  %-48s got=%s want=%s\n", $label, var_export($got, true), var_export($want, true));
}

/** Menjalankan satu method controller dan mengembalikan [payload, status]. */
function call(callable $fn): array {
    try { $fn(); }
    catch (ApiResponse $r) { return [$r->payload, $r->status]; }
    // Exception apa pun yang lolos dari controller ditampilkan apa adanya —
    // "500" saja tidak memberi tahu apa yang sebenarnya rusak.
    catch (Throwable $e) { echo "  ! " . get_class($e) . ": " . $e->getMessage() . "
"; return [[], -1]; }
    return [[], 0];
}

Database::boot();
$c = new CommunityController();

// ------------------------------------------------------- tambah tanpa langkah
echo "--- tambah panduan ---\n";
$_POST = ['title' => 'Cara Install Template', 'excerpt' => 'Ringkas', 'content' => 'Isi panduan.',
          'category' => 'Template', 'icon' => 'LayoutTemplate', 'external_url' => '',
          'is_published' => '1', 'sort_order' => '0', 'steps' => '[]'];
$_FILES = [];
[$body, $status] = call([$c, 'createTutorial']);
check('status 201', $status, 201);
check('judul tersimpan', $body['tutorial']['title'] ?? null, 'Cara Install Template');
check('kategori tersimpan', $body['tutorial']['category'] ?? null, 'Template');
check('terbit', (bool) ($body['tutorial']['is_published'] ?? false), true);
check('tanpa langkah', count($body['tutorial']['steps'] ?? [null]), 0);
$id = $body['tutorial']['id'] ?? 0;

// ------------------------------------------------------- tambah dengan langkah
echo "--- tambah dengan langkah ---\n";
$_POST = ['title' => 'Panduan Berlangkah', 'excerpt' => '', 'content' => '', 'category' => '',
          'icon' => 'BookOpen', 'external_url' => '', 'is_published' => '1', 'sort_order' => '0',
          'steps' => json_encode([
              ['body' => 'Langkah satu', 'image_path' => null, 'image_action' => 'keep'],
              ['body' => 'Langkah dua',  'image_path' => null, 'image_action' => 'keep'],
              // Kosong sepenuhnya — harus dibuang, bukan disimpan sebagai baris hampa
              ['body' => '',             'image_path' => null, 'image_action' => 'keep'],
          ])];
[$body, $status] = call([$c, 'createTutorial']);
check('status 201', $status, 201);
check('langkah tersimpan', count($body['tutorial']['steps'] ?? []), 2);
check('urutan langkah 1', $body['tutorial']['steps'][0]['body'] ?? null, 'Langkah satu');
check('position 0', $body['tutorial']['steps'][0]['position'] ?? null, 0);
check('position 1', $body['tutorial']['steps'][1]['position'] ?? null, 1);
$stepId = $body['tutorial']['id'] ?? 0;

// ------------------------------------------------------- draf
echo "--- draf ---\n";
$_POST = ['title' => 'Draf', 'steps' => '[]', 'is_published' => '0', 'icon' => 'BookOpen',
          'excerpt' => '', 'content' => '', 'category' => '', 'external_url' => '', 'sort_order' => '0'];
[$body, $status] = call([$c, 'createTutorial']);
check('is_published 0 tersimpan sebagai draf', (bool) ($body['tutorial']['is_published'] ?? true), false);
check('draf tidak tampil ke user', count(Tutorial::allPublished()), 2);

// ------------------------------------------------------- validasi
echo "--- validasi ---\n";
$_POST = ['title' => '  ', 'steps' => '[]'];
[$body, $status] = call([$c, 'createTutorial']);
check('judul kosong ditolak', $status, 400);
check('pesan judul', $body['error'] ?? null, 'Judul tutorial wajib diisi');

$_POST = ['title' => 'X', 'external_url' => 'javascript:alert(1)', 'steps' => '[]'];
[$body, $status] = call([$c, 'createTutorial']);
check('skema link ditolak', $status, 400);

// ------------------------------------------------------- ubah
echo "--- ubah ---\n";
$_POST = ['title' => 'Judul Baru', 'excerpt' => 'Ringkas baru', 'content' => 'Isi baru',
          'category' => 'Plugin', 'icon' => 'Package', 'external_url' => 'https://youtu.be/abc123',
          'is_published' => '1', 'sort_order' => '2',
          'steps' => json_encode([
              ['body' => 'Langkah dua', 'image_path' => null, 'image_action' => 'keep'],
              ['body' => 'Langkah satu', 'image_path' => null, 'image_action' => 'keep'],
              ['body' => 'Langkah tiga', 'image_path' => null, 'image_action' => 'keep'],
          ])];
[$body, $status] = call(fn() => $c->updateTutorial(["id" => $stepId]));
check('status 200', $status, 200);
check('judul berubah', $body['tutorial']['title'] ?? null, 'Judul Baru');
check('youtube dikenali', $body['tutorial']['youtube_id'] ?? null, 'abc123');
check('langkah jadi 3', count($body['tutorial']['steps'] ?? []), 3);
check('urutan baru dipakai', $body['tutorial']['steps'][0]['body'] ?? null, 'Langkah dua');

// ------------------------------------------------------- hapus
echo "--- hapus ---\n";
$before = count(Tutorial::all());
[$body, $status] = call(fn() => $c->deleteTutorial(['id' => $stepId]));
check('status 200', $status, 200);
check('baris berkurang', count(Tutorial::all()), $before - 1);
check('langkah ikut terhapus', count(TutorialStep::forTutorial($stepId)), 0);

printf("\n%d lulus, %d gagal\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
