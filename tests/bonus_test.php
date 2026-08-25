<?php
/**
 * Uji fungsi murni BonusController.
 *
 *   php tests/bonus_test.php
 *
 * Tidak menyentuh database maupun filesystem: yang diuji validasi dan
 * pembersihan nama berkas, dua tempat yang salahnya paling mahal.
 *
 * Yang dijaga: bonus bertipe Link tidak boleh menyimpan skema selain
 * http/https — URL-nya dipasang langsung pada href tombol Unduh, jadi
 * javascript: yang lolos berarti satu baris database menjalankan kode pada
 * setiap orang yang mengkliknya.
 */

class ApiResponse extends Error
{
    public function __construct(public array $payload, public int $status) { parent::__construct('r'); }
}
function jsonResponse(array $d, int $s = 200): void { throw new ApiResponse($d, $s); }
function requireAuth(): void {}
function requireAdmin(): void {}

define('PORTAL_ROOT', sys_get_temp_dir() . '/apeiron_bonus_test');

class Bonus
{
    public const SOURCE_FILE = 'file';
    public const SOURCE_LINK = 'link';
}

require_once dirname(__DIR__) . '/api/controllers/BonusController.php';

$pass = 0; $fail = 0;
function check(string $label, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL  %-46s got=%s want=%s\n", $label, var_export($got, true), var_export($want, true));
}

$m = new ReflectionMethod('BonusController', 'validate');
$m->setAccessible(true);
$v = fn($name, $type, $url, $hasFile) => $m->invoke(null, $name, $type, $url, $hasFile);

echo "--- nama ---\n";
check('nama kosong ditolak', $v('', 'link', 'https://a.test/x', false), 'Nama bonus wajib diisi');
check('nama 256 huruf ditolak', $v(str_repeat('a', 256), 'link', 'https://a.test/x', false), 'Nama bonus maksimal 255 karakter');

echo "--- tipe file ---\n";
check('file tanpa berkas ditolak', $v('A', 'file', null, false), 'Berkas bonus wajib diunggah untuk tipe Upload File');
check('file dengan berkas lolos', $v('A', 'file', null, true), null);
// URL tidak relevan bagi tipe file, jadi tidak boleh ikut menggagalkan.
check('file + url ngawur tetap lolos', $v('A', 'file', 'javascript:alert(1)', true), null);

echo "--- tipe link ---\n";
check('link kosong ditolak', $v('A', 'link', null, false), 'Link bonus wajib diisi untuk tipe Link');
check('https lolos', $v('A', 'link', 'https://drive.google.com/x', false), null);
check('http lolos', $v('A', 'link', 'http://example.test/x', false), null);
check('bukan url ditolak', $v('A', 'link', 'bukan-url', false), 'Link bonus tidak valid');

echo "--- skema berbahaya ---\n";
// Yang penting bahwa semuanya ditolak, bukan bunyi pesannya: sebagian
// tertangkap FILTER_VALIDATE_URL lebih dulu, sebagian baru oleh pemeriksaan
// skema.
$rejected = fn($url) => $v('A', 'link', $url, false) !== null;
check('javascript: ditolak', $rejected('javascript:alert(1)'), true);
// Ini lolos FILTER_VALIDATE_URL karena strukturnya sah; hanya pemeriksaan
// skema yang menahannya.
check('javascript:// ditolak', $rejected('javascript://comment%0Aalert(1)'), true);
check('data: ditolak', $rejected('data:text/html,<script>alert(1)</script>'), true);
check('ftp:// ditolak', $rejected('ftp://example.test/x'), true);
check('file:// ditolak', $rejected('file:///etc/passwd'), true);

echo "--- nama unduhan ---\n";
$m2 = new ReflectionMethod('BonusController', 'safeName');
$m2->setAccessible(true);
$safe = fn($n) => $m2->invoke(null, $n);
check('spasi jadi garis bawah', $safe('pack ikon.zip'), 'pack_ikon.zip');
// Kutip dan newline bisa memecah header Content-Disposition.
check('kutip dibuang', $safe('a"b.zip'), 'a_b.zip');
check('newline dibuang', $safe("a\nb.zip"), 'a_b.zip');
check('jalur dipangkas', $safe('../../etc/passwd'), 'passwd');

printf("\n%d lulus, %d gagal\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
