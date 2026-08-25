<?php
/**
 * Uji penyajian bukti transaksi.
 *
 *   php tests/document_access_test.php
 *
 * Yang dijaga di sini: berkas hanya keluar untuk transaksi yang memang
 * memilikinya, dan tidak ada nilai — dari klien maupun dari database — yang
 * bisa membuat pembacaan keluar dari folder storage/documents.
 *
 * Tidak menyentuh MySQL: Transaction dipalsukan seperlunya, dan berkasnya
 * dibuat di folder sementara.
 */

// Nama tetap, bukan berbasis PID: kasus sukses dijalankan di proses anak,
// dan anak dengan PID berbeda harus menemukan folder yang sama.
$tmp = sys_get_temp_dir() . '/apeiron_doc_test';
define('PORTAL_ROOT', $tmp);
@mkdir($tmp . '/storage/documents', 0777, true);

// Berkas yang sah, dan satu berkas di LUAR folder dokumen sebagai sasaran
// percobaan keluar-jalur.
// Sengaja tanpa CR/LF: keluaran proses anak ditangkap lewat pipa, dan pipa
// di Windows menerjemahkan akhir baris — byte yang dibandingkan jadi tidak
// sama padahal berkasnya benar. Jenis MIME diambil dari ekstensi, bukan dari
// isi, jadi tanda tangan PNG asli tidak diperlukan di sini.
file_put_contents($tmp . '/storage/documents/doc_7_1.png', 'APEIRON-BUKTI-' . str_repeat('x', 40));
file_put_contents($tmp . '/rahasia.txt', 'JANGAN PERNAH TERBACA');

// ---------------------------------------------------------------- pengganti
class ApiResponse extends Error
{
    public function __construct(public array $payload, public int $status) { parent::__construct('r'); }
}
function jsonResponse(array $d, int $s = 200): void { throw new ApiResponse($d, $s); }
function requireAdmin(): void {}

class Transaction
{
    public static array $rows = [];
    public int $id;
    public ?string $document_path;
    public ?string $document_original_name;

    public static function find(int $id): ?self
    {
        if (!isset(self::$rows[$id])) return null;
        $t = new self();
        $t->id = $id;
        $t->document_path = self::$rows[$id]['path'];
        $t->document_original_name = self::$rows[$id]['name'];
        return $t;
    }
}

require_once dirname(__DIR__) . '/api/controllers/DocumentController.php';

// ---------------------------------------------------------------- kerangka
$pass = 0; $fail = 0;
function check(string $label, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL  %-46s got=%s want=%s\n", $label, var_export($got, true), var_export($want, true));
}

/**
 * Menjalankan endpoint dan mengembalikan ['status'=>..] untuk penolakan, atau
 * ['sent'=>true, 'body'=>..] bila berkas benar-benar terkirim.
 */
function fetchDoc(int $id): array {
    $c = new DocumentController();
    ob_start();
    try {
        $c->adminTransactionDocument(['id' => $id]);
        $out = ob_get_clean();
        return ['sent' => true, 'body' => $out];
    } catch (ApiResponse $r) {
        ob_end_clean();
        return ['status' => $r->status, 'error' => $r->payload['error'] ?? null];
    } catch (Throwable $e) {
        ob_end_clean();
        // readfile lalu exit; exit tidak bisa ditangkap, jadi di lingkungan tes
        // kita hanya sampai sini bila ada kesalahan lain.
        return ['throw' => get_class($e) . ': ' . $e->getMessage()];
    }
}

echo "--- berkas sah ---\n";
Transaction::$rows[41] = ['path' => 'doc_7_1.png', 'name' => 'bukti transfer.png'];

/**
 * send() diakhiri exit(), yang di CLI menghentikan SELURUH skrip tes. Jadi
 * kasus sukses dijalankan di proses anak: skrip ini memanggil dirinya sendiri
 * dengan DOC_TEST_SEND=1, dan yang diperiksa adalah byte yang keluar.
 */
// Argumen baris perintah, bukan variabel lingkungan: cara mengeset env
// berbeda antara cmd dan sh, dan shell_exec memakai shell bawaan sistem.
// Argumen selalu sampai apa pun shell-nya.
if (($argv[1] ?? '') === '--send') {
    // Anak: hanya kirim berkasnya. Peringatan header dari CLI diredam supaya
    // yang keluar ke stdout benar-benar hanya isi berkasnya.
    ini_set('display_errors', '0');
    (new DocumentController())->adminTransactionDocument(['id' => 41]);
    exit;
}

$sent = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --send');
$expected = file_get_contents($tmp . '/storage/documents/doc_7_1.png');
check('berkas sah terkirim utuh', is_string($sent) && str_contains((string) $sent, $expected), true);
check('tidak ada JSON penolakan yang ikut', str_contains((string) $sent, '"error"'), false);

echo "--- penolakan ---\n";
$r = fetchDoc(999);
check('transaksi tidak ada -> 404', $r['status'] ?? null, 404);

Transaction::$rows[42] = ['path' => null, 'name' => null];
$r = fetchDoc(42);
check('transaksi tanpa dokumen -> 404', $r['status'] ?? null, 404);

Transaction::$rows[43] = ['path' => 'tidak-ada.png', 'name' => 'x.png'];
$r = fetchDoc(43);
check('berkas hilang -> 404', $r['status'] ?? null, 404);

echo "--- percobaan keluar jalur ---\n";
// Kolom database yang berisi jalur relatif TIDAK boleh membaca berkas di luar
// folder dokumen. basename() memangkasnya jadi nama berkas biasa yang lalu
// tidak ditemukan, sehingga jawabannya 404 — bukan isi rahasia.txt.
foreach ([
    '../rahasia.txt',
    '../../rahasia.txt',
    'subdir/../../rahasia.txt',
    '/etc/passwd',
    'C:\\Windows\\win.ini',
] as $i => $evil) {
    Transaction::$rows[100 + $i] = ['path' => $evil, 'name' => 'x'];
    $r = fetchDoc(100 + $i);
    check('ditolak: ' . $evil, $r['status'] ?? null, 404);
}

echo "--- nama unduhan ---\n";
$m = new ReflectionMethod('DocumentController', 'safeName');
$m->setAccessible(true);
$safe = fn($n) => $m->invoke(null, $n);
check('spasi jadi garis bawah', $safe('bukti transfer.png'), 'bukti_transfer.png');
// Tanda kutip dan newline bisa memecah header Content-Disposition.
check('kutip dibuang', $safe('a"b.png'), 'a_b.png');
check('newline dibuang', $safe("a\nb.png"), 'a_b.png');
check('jalur dipangkas', $safe('../../etc/passwd'), 'passwd');

// ------------------------------------------------------------- bersih-bersih
@unlink($tmp . '/storage/documents/doc_7_1.png');
@unlink($tmp . '/rahasia.txt');
@rmdir($tmp . '/storage/documents');
@rmdir($tmp . '/storage');
@rmdir($tmp);

printf("\n%d lulus, %d gagal\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
