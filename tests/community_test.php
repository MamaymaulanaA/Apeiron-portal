<?php
/**
 * Uji fungsi murni fitur Group dan Forum.
 *
 *   php tests/community_test.php
 *
 * Tidak menyentuh database sama sekali — yang diuji hanya method statis tanpa
 * query, jadi aman dijalankan di mana pun tanpa konfigurasi.
 *
 * Yang dijaga di sini terutama dua hal yang diam-diam berbahaya kalau salah:
 * host yang mirip merek asli tidak boleh dikenali sebagai merek itu, dan skema
 * URL selain http/https tidak boleh tersimpan — satu baris database dengan
 * javascript: akan jadi XSS bagi setiap orang yang mengklik kartunya.
 */
$root = dirname(__DIR__);
require_once $root . '/src/Models/CommunityGroup.php';
require_once $root . '/src/Models/Tutorial.php';

// Stub minimal supaya CommunityController bisa di-require tanpa bootstrap penuh.
if (!function_exists('jsonResponse')) { function jsonResponse(array $d, int $s = 200): void {} }
if (!function_exists('getJsonInput')) { function getJsonInput(): array { return []; } }
if (!function_exists('requireAuth')) { function requireAuth(): void {} }
if (!function_exists('requireAdmin')) { function requireAdmin(): void {} }
if (!function_exists('currentUser')) { function currentUser() { return null; } }
class Transaction {}
require_once $root . '/api/controllers/CommunityController.php';

$pass = 0; $fail = 0;
function check(string $label, $got, $want) {
    global $pass, $fail;
    if ($got === $want) { $pass++; return; }
    $fail++;
    printf("FAIL  %-52s got=%s want=%s\n", $label, var_export($got, true), var_export($want, true));
}

echo "--- detectPlatform ---\n";
check('whatsapp chat',   CommunityGroup::detectPlatform('https://chat.whatsapp.com/ABC'), 'whatsapp');
check('whatsapp wa.me',  CommunityGroup::detectPlatform('https://wa.me/628123'), 'whatsapp');
check('telegram t.me',   CommunityGroup::detectPlatform('https://t.me/kanal'), 'telegram');
check('discord.gg',      CommunityGroup::detectPlatform('https://discord.gg/xyz'), 'discord');
check('www prefix',      CommunityGroup::detectPlatform('https://www.facebook.com/groups/x'), 'facebook');
check('subdomain sah',   CommunityGroup::detectPlatform('https://api.whatsapp.com/send'), 'whatsapp');
// Yang paling penting: host mirip TIDAK boleh lolos sebagai merek aslinya.
check('lookalike ditolak', CommunityGroup::detectPlatform('https://discord.gg.jahat.example/x'), 'other');
check('substring ditolak', CommunityGroup::detectPlatform('https://not-t.me.evil.example/x'), 'other');
check('kosong',          CommunityGroup::detectPlatform(''), 'other');
check('null',            CommunityGroup::detectPlatform(null), 'other');

echo "--- youtubeId ---\n";
check('watch?v=',        Tutorial::youtubeId('https://www.youtube.com/watch?v=dQw4w9WgXcQ'), 'dQw4w9WgXcQ');
check('youtu.be',        Tutorial::youtubeId('https://youtu.be/dQw4w9WgXcQ'), 'dQw4w9WgXcQ');
check('embed',           Tutorial::youtubeId('https://www.youtube.com/embed/dQw4w9WgXcQ'), 'dQw4w9WgXcQ');
check('shorts',          Tutorial::youtubeId('https://youtube.com/shorts/abc123XYZ'), 'abc123XYZ');
check('bukan youtube',   Tutorial::youtubeId('https://docs.example.com/a'), null);
check('null',            Tutorial::youtubeId(null), null);

echo "--- normalizeIcon ---\n";
check('ikon sah',        Tutorial::normalizeIcon('KeyRound'), 'KeyRound');
check('ikon ngawur',     Tutorial::normalizeIcon('NotARealIcon'), 'BookOpen');
check('null',            Tutorial::normalizeIcon(null), 'BookOpen');

echo "--- validateUrl (lewat validateGroup) ---\n";
$m = new ReflectionMethod('CommunityController', 'validateGroup');
$m->setAccessible(true);
$vg = fn($name, $link) => $m->invoke(null, $name, $link);

check('link sah',        $vg('A', 'https://t.me/x'), null);
check('http sah',        $vg('A', 'http://example.com/x'), null);
check('nama kosong',     $vg('', 'https://t.me/x'), 'Nama group wajib diisi');
check('link kosong',     $vg('A', ''), 'Link group wajib diisi');
// Yang penting BUKAN bunyi pesannya, melainkan bahwa skema berbahaya ditolak.
// Sebagian tertangkap FILTER_VALIDATE_URL lebih dulu, sebagian baru tertangkap
// pemeriksaan skema — keduanya sama-sama tidak boleh lolos.
$rejected = fn($link) => $vg('A', $link) !== null;
check('javascript: ditolak', $rejected('javascript:alert(1)'), true);
check('data: ditolak',       $rejected('data:text/html,<script>alert(1)</script>'), true);
// Ini lolos FILTER_VALIDATE_URL karena strukturnya sah; hanya pemeriksaan
// skema yang menahannya.
check('javascript:// ditolak', $rejected('javascript://comment%0Aalert(1)'), true);
check('ftp:// ditolak',      $rejected('ftp://example.com/x'), true);
check('file:// ditolak',     $rejected('file:///etc/passwd'), true);
check('bukan url',           $rejected('bukan-url'), true);
check('http TIDAK ditolak',  $rejected('http://example.com/x'), false);

echo "--- validateTutorial ---\n";
$m2 = new ReflectionMethod('CommunityController', 'validateTutorial');
$m2->setAccessible(true);
$vt = fn($title, $url) => $m2->invoke(null, $title, $url);

check('judul + url sah', $vt('Judul', 'https://youtu.be/x'), null);
check('url boleh kosong', $vt('Judul', null), null);
check('url string kosong', $vt('Judul', '  '), null);
check('judul kosong',    $vt('', null), 'Judul tutorial wajib diisi');
$rejectedT = fn($url) => $vt('Judul', $url) !== null;
check('javascript: ditolak', $rejectedT('javascript:alert(1)'), true);
check('javascript:// ditolak', $rejectedT('javascript://comment%0Aalert(1)'), true);

printf("\n%d lulus, %d gagal\n", $pass, $fail);
exit($fail > 0 ? 1 : 0);
