<?php
/**
 * Uji perapian struktur folder di dalam ZIP plugin.
 *
 *   php tests/plugin_zip_test.php
 *
 * Bug yang melatarbelakanginya: setiap rilis mendarat di direktori berbeda saat
 * dipasang. WordPress menentukan folder plugin dari ISI zip, bukan dari namanya
 * — bila di dalamnya ada tepat satu folder di tingkat atas, folder itu yang
 * dipakai; kalau tidak, WordPress memakai nama berkas zip. Nama berkas kita
 * mengandung nomor versi, jadi tanpa folder pembungkus 1.0.4 dan 1.0.5 menjadi
 * dua plugin terpisah dan tombol Perbarui tidak pernah muncul.
 *
 * Diuji dengan ZIP sungguhan yang dibuat di sini, bukan tiruan: yang dijaga
 * justru bentuk berkasnya, dan tiruan tidak punya bentuk.
 */

$root = dirname(__DIR__);

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "Ekstensi zip tidak tersedia; tes ini butuh ZipArchive.\n");
    exit(1);
}

// normalizeZipRoot() diambil dari berkas produksi, bukan disalin.
$src = file_get_contents($root . '/api/controllers/PluginController.php');
$awal = strpos($src, '    private static function normalizeZipRoot(');
if ($awal === false) {
    fwrite(STDERR, "Tidak menemukan normalizeZipRoot() di PluginController.\n");
    exit(1);
}
$akhir = strpos($src, "\n    }", $awal) + 6;

// `private` dilonggarkan agar bisa dipanggil dari tes. Badan fungsinya tetap
// kode produksi apa adanya — yang berubah hanya pintunya, bukan isinya.
$badan = str_replace('private static function normalizeZipRoot', 'public static function normalizeZipRoot', substr($src, $awal, $akhir - $awal));
eval('class ZipUji { ' . $badan . ' }');

$pass = 0;
$fail = 0;

function check(string $label, $got, $want): void
{
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL  %-52s got=%s want=%s\n", $label, var_export($got, true), var_export($want, true));
}

$dir = sys_get_temp_dir() . '/apeiron_zip_' . getmypid();
@mkdir($dir, 0777, true);
register_shutdown_function(static function () use ($dir) {
    foreach (glob($dir . '/*') ?: [] as $f) { @unlink($f); }
    @rmdir($dir);
});

/** Buat ZIP berisi entri yang diminta; kembalikan jalurnya. */
function buatZip(string $nama, array $entri): string
{
    global $dir;
    $path = $dir . '/' . $nama;
    @unlink($path);

    $z = new ZipArchive();
    $z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($entri as $jalur => $isi) {
        if (str_ends_with($jalur, '/')) {
            $z->addEmptyDir(rtrim($jalur, '/'));
        } else {
            $z->addFromString($jalur, $isi);
        }
    }
    $z->close();
    return $path;
}

/** Daftar berkas di dalam ZIP (tanpa entri direktori), terurut. */
function isiZip(string $path): array
{
    $z = new ZipArchive();
    $z->open($path);
    $hasil = [];
    for ($i = 0; $i < $z->numFiles; $i++) {
        $n = $z->getNameIndex($i);
        if (!str_ends_with($n, '/')) $hasil[] = $n;
    }
    $z->close();
    sort($hasil);
    return $hasil;
}

function rapikan(string $path, string $slug = 'apeiron-kit'): ?string
{
    return ZipUji::normalizeZipRoot($path, $slug);
}

// =====================================================================
echo "--- berkas di akar zip: dibungkus folder slug ---\n";
// =====================================================================
// INI PENYEBABNYA. Tanpa folder pembungkus, WordPress memakai nama zip —
// apeiron-kit-1.0.5 — sehingga tiap versi jadi direktori sendiri.
$z = buatZip('tanpa-folder.zip', [
    'apeiron-kit.php' => '<?php // plugin utama',
    'readme.txt' => 'Apeiron Kit',
    'src/Helper.php' => '<?php // helper',
]);
check('berhasil', rapikan($z), null);
check('semuanya masuk satu folder', isiZip($z), [
    'apeiron-kit/apeiron-kit.php',
    'apeiron-kit/readme.txt',
    'apeiron-kit/src/Helper.php',
]);

// =====================================================================
echo "--- folder bernama versi: diganti jadi slug ---\n";
// =====================================================================
// Admin yang mengompres foldernya sendiri sering membawa nama berversi.
$z = buatZip('folder-versi.zip', [
    'apeiron-kit-1.0.5/apeiron-kit.php' => '<?php',
    'apeiron-kit-1.0.5/readme.txt' => 'x',
]);
check('berhasil', rapikan($z), null);
check('nama folder jadi stabil', isiZip($z), [
    'apeiron-kit/apeiron-kit.php',
    'apeiron-kit/readme.txt',
]);

echo "--- folder dengan nama lain: tetap diseragamkan ---\n";
$z = buatZip('folder-lain.zip', [
    'Apeiron/apeiron-kit.php' => '<?php',
    'Apeiron/src/A.php' => '<?php',
]);
check('berhasil', rapikan($z), null);
check('diseragamkan', isiZip($z), ['apeiron-kit/apeiron-kit.php', 'apeiron-kit/src/A.php']);

// =====================================================================
echo "--- sudah benar: tidak diutak-atik ---\n";
// =====================================================================
$entri = [
    'apeiron-kit/apeiron-kit.php' => '<?php // utama',
    'apeiron-kit/src/B.php' => '<?php',
];
$z = buatZip('sudah-benar.zip', $entri);
$sebelum = md5_file($z);
check('berhasil', rapikan($z), null);
check('isinya sama', isiZip($z), ['apeiron-kit/apeiron-kit.php', 'apeiron-kit/src/B.php']);
// Zip yang sudah benar tidak perlu ditulis ulang; menulis ulang tanpa alasan
// hanya menambah risiko tanpa manfaat.
check('berkasnya tidak ditulis ulang', md5_file($z), $sebelum);

// =====================================================================
echo "--- sampah macOS dibuang ---\n";
// =====================================================================
// __MACOSX/ berada di tingkat atas bersama folder aslinya, dan keberadaannya
// saja sudah membuat WordPress menganggap zip ini tidak punya folder tunggal.
$z = buatZip('macos.zip', [
    'apeiron-kit/apeiron-kit.php' => '<?php',
    '__MACOSX/apeiron-kit/._apeiron-kit.php' => 'sampah',
    'apeiron-kit/.DS_Store' => 'sampah',
    'apeiron-kit/src/.DS_Store' => 'sampah',
]);
check('berhasil', rapikan($z), null);
check('hanya berkas plugin yang tersisa', isiZip($z), ['apeiron-kit/apeiron-kit.php']);

echo "--- __MACOSX saja yang menghalangi deteksi folder ---\n";
// Tanpa membuang __MACOSX, zip ini terlihat punya dua folder di tingkat atas.
$z = buatZip('macos-2.zip', [
    'apeiron-kit-1.0.5/apeiron-kit.php' => '<?php',
    '__MACOSX/._apeiron-kit-1.0.5' => 'sampah',
]);
check('berhasil', rapikan($z), null);
check('tetap satu folder benar', isiZip($z), ['apeiron-kit/apeiron-kit.php']);

// =====================================================================
echo "--- struktur bersarang tetap utuh ---\n";
// =====================================================================
// Perapian ini hanya menyentuh tingkat atas; isi di dalamnya tidak boleh
// ikut rata atau berpindah.
$z = buatZip('bersarang.zip', [
    'apeiron-kit-1.0.5/src/Elementor/Widgets/Cover/Cover.php' => '<?php',
    'apeiron-kit-1.0.5/assets/css/style.css' => 'body{}',
    'apeiron-kit-1.0.5/languages/id.po' => 'msgid ""',
]);
check('berhasil', rapikan($z), null);
check('kedalaman terjaga', isiZip($z), [
    'apeiron-kit/assets/css/style.css',
    'apeiron-kit/languages/id.po',
    'apeiron-kit/src/Elementor/Widgets/Cover/Cover.php',
]);

// =====================================================================
echo "--- isi berkas tidak berubah ---\n";
// =====================================================================
// Yang dipindahkan jalurnya, bukan isinya.
$kode = "<?php\n/* Plugin Name: Apeiron Kit */\necho 'halo';\n";
$z = buatZip('isi.zip', ['apeiron-kit-1.0.5/apeiron-kit.php' => $kode]);
rapikan($z);
$zip = new ZipArchive();
$zip->open($z);
check('isi berkas utuh', $zip->getFromName('apeiron-kit/apeiron-kit.php'), $kode);
$zip->close();

// =====================================================================
echo "--- zip yang tidak sah ditolak ---\n";
// =====================================================================
$z = buatZip('kosong.zip', ['cuma-folder/' => '']);
$galat = rapikan($z);
check('zip tanpa berkas ditolak', is_string($galat), true);
check('pesannya menjelaskan', str_contains((string) $galat, 'tidak berisi berkas'), true);

// Zip slip: jalur yang mencoba keluar dari folder tujuan.
$z = buatZip('slip.zip', ['../../wp-config.php' => 'jahat']);
$galat = rapikan($z);
check('jalur di luar folder ditolak', is_string($galat), true);
check('berkas tidak ikut dipindahkan', str_contains((string) $galat, 'tidak sah'), true);

echo "--- bukan zip sama sekali ---\n";
$path = $dir . '/palsu.zip';
file_put_contents($path, 'ini bukan zip');
check('ditolak', is_string(rapikan($path)), true);

// =====================================================================
echo "--- slug lain ikut dihormati ---\n";
// =====================================================================
// Portal bisa punya lebih dari satu produk; foldernya mengikuti slug produk
// yang bersangkutan, bukan nama yang dipatok.
$z = buatZip('produk-lain.zip', ['apa-saja/index.php' => '<?php']);
check('berhasil', rapikan($z, 'apeiron-forms'), null);
check('memakai slug produknya', isiZip($z), ['apeiron-forms/index.php']);

printf("\n%d lulus, %d gagal\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
