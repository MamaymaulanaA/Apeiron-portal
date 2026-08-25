<?php
/**
 * Uji pembuatan invoice.
 *
 *   php tests/invoice_test.php
 *
 * Yang dijaga di sini pertama-tama adalah cacat yang sempat lolos ke
 * produksi: versi lama menyusun HTML dengan `return` lalu menambahkan
 * sisanya dengan `$html .=` di bawahnya — kode yang tidak pernah berjalan.
 * Berkas yang terunduh terpotong tepat setelah tabel: tanpa license key,
 * tanpa footer, tanpa </html>. Tes ini gagal keras kalau bentuk itu kembali.
 *
 * Berikutnya: invoice tidak boleh mengarang. Periode lisensi datang dari
 * server, bukan tulisan "Lifetime" yang dipasang mati seperti sebelumnya.
 *
 * Dan yang ketiga: invoice TIDAK BOLEH memuat license key, API key, maupun
 * nominal. Kunci sengaja dikeluarkan karena dokumen ini diunduh, disimpan,
 * dan diteruskan lewat email — setiap salinannya akan jadi salinan
 * kredensial. Tes di bawah menjaga kunci itu tidak diam-diam kembali lewat
 * perubahan berikutnya.
 */

define('APP_NAME', 'Apeiron Portal');

define('APP_TIMEZONE', 'Asia/Jakarta');
// Sama seperti produksi: penyimpanan dan perhitungan UTC, tampilan Jakarta.
date_default_timezone_set('UTC');

/** Salinan format_date() dari config/app.php. */
function format_date(string $date, string $format = 'd M Y H:i'): string
{
    try {
        $dt = new DateTime($date, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone(APP_TIMEZONE));
        return $dt->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

// Hanya bagian invoice yang diambil, supaya tes tidak menyeret database,
// sesi, dan seluruh isi controller.
$src = file_get_contents(dirname(__DIR__) . '/api/controllers/UserController.php');
// Keempat bagian ini berurutan di berkas, jadi diambil sebagai satu irisan:
// dari LICENSE_LABELS sampai penutup generateInvoiceHtml. Mengambilnya
// satu-satu sempat menghasilkan potongan yang tumpang tindih.
$mulai = strpos($src, 'private const LICENSE_LABELS');
$akhirTanda = strpos($src, 'private static function generateInvoiceHtml');
if ($mulai === false || $akhirTanda === false) {
    fwrite(STDERR, "Bagian invoice tidak ditemukan di UserController.
");
    exit(1);
}
// Penutup method terakhir: '}' pertama pada indentasi empat spasi setelahnya.
$akhir = strpos($src, "
    }", $akhirTanda);
$blok = substr($src, $mulai, $akhir - $mulai + 6);

eval('class Inv { ' . $blok . '
    public static function html(...$a) { return self::generateInvoiceHtml(...$a); }
    public static function ringkas(?array $s) { return self::licenseSummary($s); }
}');

/**
 * Isi yang benar-benar dilihat pembaca — stylesheet dan komentar dibuang.
 *
 * Assertion soal isi harus memakai ini. Versi pertama tes ini menyisir
 * seluruh dokumen dan gagal karena kata "Lifetime" muncul di komentar CSS,
 * bukan di halamannya. Yang salah tesnya, bukan invoicenya.
 */
function tampil(string $html): string
{
    $h = preg_replace('#<style[^>]*>.*?</style>#is', '', $html);
    $h = preg_replace('#<!--.*?-->#s', '', $h);
    return $h;
}

$pass = 0; $fail = 0;
function check(string $label, $got, $want): void
{
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL  %-54s got=%s want=%s\n", $label, var_export($got, true), var_export($want, true));
}

$user = (object) ['id' => 7, 'name' => 'Sinta Dewi', 'email' => 'sinta@example.com'];
$tx = (object) [
    'id' => 42,
    'amount' => 499000.00,
    'product_id' => 'apeiron-kit',
    'created_at' => '2026-08-20 03:00:00',
    'approved_at' => '2026-08-21 12:30:00',
];
$lic = (object) [
    'license_key' => 'APEIRON-81BC283035-C7B737988D-776038F43B12-9A0C4E',
    'api_key' => '63c25c7c003fdf26a5324e73e95ba4c3',
    'product_id' => 'apeiron-kit',
];

$AKTIF = [
    'status' => 'active', 'is_lifetime' => false, 'duration_label' => '1 Tahun',
    'activated_at' => '2026-08-22 09:15:00', 'expires_at' => '2027-08-21 12:30:00',
    'days_remaining' => 327,
];
$LIFETIME = [
    'status' => 'active', 'is_lifetime' => true, 'duration_label' => 'Lifetime',
    'activated_at' => '2026-08-22 09:15:00', 'expires_at' => null, 'days_remaining' => null,
];
$EXPIRED = [
    'status' => 'expired', 'is_lifetime' => false, 'duration_label' => '1 Tahun',
    'activated_at' => '2025-08-22 09:15:00', 'expires_at' => '2026-08-21 12:30:00',
    'days_remaining' => 0,
];

// =====================================================================
echo "--- dokumen harus utuh (cacat lama) ---\n";
// =====================================================================
$h = Inv::html('INV-000042', $user, $tx, $lic, $AKTIF);

check('ada <!DOCTYPE', str_starts_with($h, '<!DOCTYPE html>'), true);
check('ada penutup </html>', str_ends_with(trim($h), '</html>'), true);
check('ada </body>', str_contains($h, '</body>'), true);
check('footer ada', str_contains($h, 'Terima kasih telah menggunakan'), true);
// Tag pembuka dan penutup <div> harus seimbang; kalau dokumennya terpotong
// di tengah, jumlahnya tidak akan sama.
check(
    'div seimbang',
    substr_count($h, '<div') === substr_count($h, '</div>'),
    true
);

echo "--- jam ditampilkan dalam WIB, bukan UTC ---\n";
// approved_at tersimpan 12:30 UTC. Dalam WIB itu 19:30. Sebelum ini
// invoice mencetak '12:30 WIB' — jam UTC yang diberi label Jakarta.
check('jam dikonversi ke WIB', str_contains($h, '19:30 WIB'), true);
check('bukan jam UTC mentah', str_contains($h, '12:30 WIB'), false);

echo "--- rahasia tidak boleh ikut tercetak ---\n";
// Ketiganya ADA di objek yang dioper ke generator; yang diuji di sini
// adalah bahwa generator MEMILIH untuk tidak mencetaknya. Kalau suatu
// saat kunci dikembalikan ke invoice, tes inilah yang gagal lebih dulu.
check('license key TIDAK tercetak', str_contains($h, $lic->license_key), false);
check('api key TIDAK tercetak', str_contains($h, $lic->api_key), false);
check('nominal TIDAK tercetak', str_contains($h, '499'), false);
check('tanpa "Total dibayar"', str_contains(tampil($h), 'Total dibayar'), false);
check('tanpa kolom Jumlah', str_contains(tampil($h), 'JUMLAH'), false);
// Pembaca diberi tahu ke mana harus mencari kuncinya.
check('ada penjelasan kunci', str_contains(tampil($h), 'tidak dicantumkan'), true);
check('mengarahkan ke halaman Plugin', str_contains(tampil($h), 'Plugin'), true);

echo "--- data nyata, bukan contoh ---\n";
check('nama pelanggan tampil', str_contains($h, 'Sinta Dewi'), true);
check('email tampil', str_contains($h, 'sinta@example.com'), true);
check('nomor invoice tampil', str_contains($h, 'INV-000042'), true);
check('tanggal disetujui tampil', str_contains($h, '21 Aug 2026'), true);
check('produk tampil', str_contains(tampil($h), 'apeiron-kit'), true);
// Versi lama selalu menulis "Lifetime access to premium plugin".
check('tanpa teks contoh lama', str_contains($h, 'Lifetime access to premium'), false);

echo "--- periode ikut server ---\n";
check('1 Tahun tampil', str_contains(tampil($h), '1 Tahun'), true);
check('tidak mengklaim Lifetime', str_contains(tampil($h), 'Lifetime'), false);
check('sisa waktu tampil', str_contains(tampil($h), '327 hari'), true);
check('tanggal berlaku hingga', str_contains(tampil($h), 'Berlaku hingga'), true);

echo "--- lifetime ---\n";
$hl = Inv::html('INV-000042', $user, $tx, $lic, $LIFETIME);
check('Lifetime tampil', str_contains(tampil($hl), 'Lifetime'), true);
check('berbunyi Selamanya', str_contains(tampil($hl), 'Selamanya'), true);
// Tidak ada yang bisa dihitung mundur.
check('tanpa sisa waktu', str_contains(tampil($hl), 'Sisa waktu'), false);
check('tanpa "hari lagi"', str_contains(tampil($hl), 'hari lagi'), false);

echo "--- expired ---\n";
$he = Inv::html('INV-000042', $user, $tx, $lic, $EXPIRED);
check('label Berakhir', str_contains(tampil($he), 'Berakhir'), true);
check('tanpa "Berlaku hingga"', str_contains(tampil($he), 'Berlaku hingga'), false);
check('tanpa sisa waktu', str_contains(tampil($he), 'Sisa waktu'), false);
check('status Expired', str_contains(tampil($he), 'Expired'), true);

echo "--- status belum terbaca ---\n";
$hn = Inv::html('INV-000042', $user, $tx, $lic, null);
// Invoice tetap terbit; yang tidak diketahui dinyatakan apa adanya.
check('kunci tetap tidak tercetak', str_contains($hn, 'APEIRON-81BC283035'), false);
check('mengaku belum diketahui', str_contains($hn, 'Belum diketahui'), true);
check('ada catatan penjelas', str_contains($hn, 'belum bisa dibaca dari server'), true);
check('dokumen tetap utuh', str_ends_with(trim($hn), '</html>'), true);

echo "--- tanpa lisensi sama sekali ---\n";
$h0 = Inv::html('INV-000042', $user, $tx, null, null);
check('dokumen tetap utuh', str_ends_with(trim($h0), '</html>'), true);
check('menyebut belum ada lisensi', str_contains(tampil($h0), 'Belum ada lisensi'), true);
check('nominal tetap tidak tercetak', str_contains($h0, '499'), false);

echo "--- font dan warna ---\n";
check('Ubuntu dimuat', str_contains($h, 'family=Ubuntu'), true);
check('Ubuntu jadi font utama', str_contains($h, 'font-family: "Ubuntu"'), true);
check('Ubuntu Mono tersedia', str_contains($h, '"Ubuntu Mono"'), true);
check('biru utama dipakai', str_contains($h, '#2563eb'), true);
// Hijau lama tidak boleh kembali lewat invoice.
check('tanpa hijau #2ecc71', str_contains($h, '#2ecc71'), false);
check('tanpa color: green', str_contains($h, 'color: green'), false);
check('tanpa Arial sebagai font utama', str_contains($h, 'font-family: Arial'), false);

echo "--- keamanan: nama tidak boleh jadi markup ---\n";
$jahat = (object) ['id' => 9, 'name' => '<script>alert(1)</script>', 'email' => 'a"b@x.id'];
$hx = Inv::html('INV-1', $jahat, $tx, $lic, $AKTIF);
check('script di-escape', str_contains($hx, '<script>alert(1)</script>'), false);
check('muncul sebagai teks', str_contains($hx, '&lt;script&gt;'), true);
check('kutip di email di-escape', str_contains($hx, 'a&quot;b@x.id'), true);

echo "--- ringkasan status ---\n";
check('aktif + sisa', Inv::ringkas($AKTIF), 'Aktif • 327 hari lagi');
check('lifetime tanpa hitung mundur', Inv::ringkas($LIFETIME), 'Aktif');
check('expired tanpa hitung mundur', Inv::ringkas($EXPIRED), 'Expired');
check('null', Inv::ringkas(null), 'Belum diketahui');
// 0 hari pada lisensi yang masih berlaku adalah angka sah, bukan "kosong".
check('sisa 0 hari tetap tampil', Inv::ringkas(['status'=>'active','is_lifetime'=>false,'days_remaining'=>0]), 'Aktif • 0 hari lagi');

printf("\n%d lulus, %d gagal\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
