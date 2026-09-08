<?php
/**
 * Uji penafsiran balasan License Server.
 *
 *   php tests/license_read_test.php
 *
 * Bug yang melatarbelakanginya: setiap kegagalan, apa pun sebabnya, dilaporkan
 * ke user sebagai "Server lisensi tidak merespons. Menampilkan data terakhir
 * yang tersimpan." Di produksi server justru menjawab 404 dalam 125 ms karena
 * berkas endpointnya tidak ada — dan keterangan yang salah itu membuat
 * masalahnya dicari di Portal, bukan di server.
 *
 * Yang diuji classifyLicenseRead(), fungsi yang menafsirkan balasan mentah. Ia
 * sengaja dipisahkan dari cURL: yang pernah salah penafsirannya, bukan
 * pemanggilan HTTP-nya, dan sebagai fungsi tersendiri ia bisa diperiksa untuk
 * setiap bentuk balasan tanpa menyalakan server.
 */

$root = dirname(__DIR__);

define('APP_ENV', 'testing');
define('LICENSE_SERVER_URL', 'http://127.0.0.1:1');
define('LICENSE_SERVER_API_KEY', 'kunci-uji-bukan-rahasia');

// LicenseServerException dideklarasikan di dalam ServerApiService.php sendiri,
// jadi tidak perlu — dan tidak boleh — dibuat ulang di sini.
require_once $root . '/src/Services/ServerApiService.php';

$pass = 0;
$fail = 0;

function check(string $label, $got, $want): void
{
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL  %-54s got=%s want=%s\n", $label, var_export($got, true), var_export($want, true));
}

function tafsir(?string $body, int $http, string $curlError = '', int $errno = 0): array
{
    return ServerApiService::classifyLicenseRead($body, $http, $curlError, $errno, 120);
}

/** Balasan sukses seperti yang dikirim portal_license.php. */
function balasanSukses(array $data): string
{
    return json_encode(['success' => true, 'data' => $data]);
}

$halamanHosting = '<!DOCTYPE html><html><head><title>This Page Does Not Exist</title></head><body>404</body></html>';

// =====================================================================
echo "--- 200 lisensi aktif: data dipetakan apa adanya ---\n";
// =====================================================================
$h = tafsir(balasanSukses([
    'status' => 'active',
    'is_lifetime' => false,
    'duration_label' => '1 Tahun',
    'activated_at' => '2026-05-19 01:21:00',
    'expires_at' => '2027-05-19 01:21:00',
    'days_remaining' => 256,
    'activations_used' => 1,
    'activation_limit' => 1,
]), 200);

check('tanpa kegagalan', $h['failure'], null);
check('status', $h['data']['status'], 'active');
check('tanggal aktivasi', $h['data']['activated_at'], '2026-05-19 01:21:00');
check('tanggal berakhir', $h['data']['expires_at'], '2027-05-19 01:21:00');
check('sisa hari', $h['data']['days_remaining'], 256);

// Yang paling mudah tertukar saat memetakan "Pemakaian X / Y situs".
check('jumlah aktivasi (activations_used)', $h['data']['activations_used'], 1);
check('batas aktivasi', $h['data']['activation_limit'], 1);

echo "--- jumlah dan batas tidak boleh tertukar ---\n";
// Kalau keduanya bernilai sama (1/1), tertukar pun tidak kelihatan. Angka yang
// berbeda membuat tertukarnya langsung terlihat.
$h = tafsir(balasanSukses(['status' => 'active', 'activations_used' => 2, 'activation_limit' => 5]), 200);
check('aktivasi 2', $h['data']['activations_used'], 2);
check('batas 5', $h['data']['activation_limit'], 5);
check('bukan 5 / 2', $h['data']['activations_used'] . '/' . $h['data']['activation_limit'], '2/5');

echo "--- 200 lisensi belum aktif ---\n";
$h = tafsir(balasanSukses(['status' => 'inactive', 'activations_used' => 0, 'activation_limit' => 3]), 200);
check('status inactive', $h['data']['status'], 'inactive');
check('aktivasi 0 tetap terbaca', $h['data']['activations_used'], 0);
check('tanpa kegagalan', $h['failure'], null);

echo "--- lifetime ---\n";
$h = tafsir(balasanSukses(['status' => 'active', 'is_lifetime' => true, 'expires_at' => null]), 200);
check('lifetime terbaca', $h['data']['is_lifetime'], true);
check('tanpa tanggal berakhir', $h['data']['expires_at'], null);

// =====================================================================
echo "--- setiap kegagalan punya kodenya sendiri ---\n";
// =====================================================================
// Inti perbaikannya. Sebelumnya SEMUA baris di bawah ini menghasilkan pesan
// yang sama persis kepada user.
$kasus = [
    'endpoint hilang (404 HTML)' => [$halamanHosting, 404, 'ENDPOINT_NOT_FOUND'],
    'lisensi tak terdaftar (404 JSON)' => ['{"success":false,"message":"License not found"}', 404, 'LICENSE_NOT_FOUND'],
    'kunci API salah (401)' => ['{"success":false}', 401, 'UNAUTHORIZED'],
    'ditolak (403)' => ['{"success":false}', 403, 'FORBIDDEN'],
    'permintaan invalid (400)' => ['{"success":false}', 400, 'VALIDATION_ERROR'],
    'permintaan invalid (422)' => ['{"success":false}', 422, 'VALIDATION_ERROR'],
    'terlalu sering (429)' => ['{"success":false}', 429, 'RATE_LIMIT'],
    'server bermasalah (500)' => ['{"success":false}', 500, 'SERVER_ERROR'],
    'server bermasalah (503)' => ['{"success":false}', 503, 'SERVER_ERROR'],
    'HTTP lain (418)' => ['{"success":false}', 418, 'HTTP_ERROR'],
    'JSON rusak' => ['{"success": true, "data": {rusak', 200, 'INVALID_RESPONSE'],
    'bukan JSON sama sekali' => ['<html>halo</html>', 200, 'INVALID_RESPONSE'],
    'sukses tanpa data' => ['{"success":true}', 200, 'INVALID_RESPONSE'],
    'data kosong' => ['{"success":true,"data":{}}', 200, 'INVALID_RESPONSE'],
    'data bukan objek' => ['{"success":true,"data":"teks"}', 200, 'INVALID_RESPONSE'],
    'server menolak' => ['{"success":false,"message":"Product mismatch"}', 200, 'REJECTED'],
];

foreach ($kasus as $nama => [$body, $http, $kodeDiharapkan]) {
    $h = tafsir($body, $http);
    check("{$nama}: tanpa data", $h['data'], null);
    check("{$nama}: kode {$kodeDiharapkan}", $h['failure']['code'] ?? null, $kodeDiharapkan);
    check("{$nama}: ada pesan", !empty($h['failure']['message']), true);
}

// =====================================================================
echo "--- kegagalan jaringan dan timeout dibedakan ---\n";
// =====================================================================
// Server lambat dan server tidak terjangkau bukan hal yang sama, dan yang
// dikerjakan untuk memperbaikinya juga berbeda.
$h = tafsir(null, 0, 'Operation timed out after 8001 milliseconds', CURLE_OPERATION_TIMEDOUT);
check('timeout: kode', $h['failure']['code'], 'TIMEOUT');
check('timeout: menyebut lamanya', str_contains($h['failure']['message'], '120 ms'), true);

$h = tafsir(null, 0, 'Could not resolve host: server-apeiron.web.id', CURLE_COULDNT_RESOLVE_HOST);
check('jaringan: kode', $h['failure']['code'], 'NETWORK_ERROR');
check('jaringan: bukan timeout', $h['failure']['code'] !== 'TIMEOUT', true);

// =====================================================================
echo "--- 404 berkas hilang vs lisensi tidak terdaftar ---\n";
// =====================================================================
// Inilah yang terjadi di produksi, dan inilah yang paling penting dibedakan:
// satu diperbaiki dengan mengunggah berkas ke server, satunya tidak.
$html = tafsir($halamanHosting, 404)['failure'];
$json = tafsir('{"success":false,"message":"License not found"}', 404)['failure'];

check('sama-sama HTTP 404', [$html['http'], $json['http']], [404, 404]);
check('kodenya berbeda', $html['code'] !== $json['code'], true);
check('halaman hosting menunjuk berkasnya', str_contains($html['message'], 'portal_license.php'), true);
check('JSON 404 tidak menuduh endpoint', str_contains($json['message'], 'portal_license.php'), false);

// Keduanya juga tidak boleh berbunyi "tidak merespons": servernya menjawab.
check('HTML 404 tidak bilang tidak merespons', str_contains(strtolower($html['message']), 'tidak merespons'), false);
check('JSON 404 tidak bilang tidak merespons', str_contains(strtolower($json['message']), 'tidak merespons'), false);

// =====================================================================
echo "--- pesan tidak membocorkan rahasia ---\n";
// =====================================================================
foreach ([[$halamanHosting, 404], ['{"success":false}', 401], ['{"success":false}', 500]] as [$b, $k]) {
    $sebab = tafsir($b, $k)['failure'];
    $teks = json_encode($sebab);
    check("HTTP {$k}: tanpa kunci API", str_contains($teks, 'kunci-uji-bukan-rahasia'), false);
    check("HTTP {$k}: tanpa isi balasan mentah", str_contains($teks, 'DOCTYPE'), false);
}

// =====================================================================
echo "--- lisensi tanpa key tidak menghubungi server ---\n";
// =====================================================================
$data = ServerApiService::fetchLicenseStatus('', 'apeiron-kit');
check('tanpa data', $data, null);
check('kode NO_LICENSE_KEY', ServerApiService::lastReadFailure()['code'] ?? null, 'NO_LICENSE_KEY');

printf("\n%d lulus, %d gagal\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
