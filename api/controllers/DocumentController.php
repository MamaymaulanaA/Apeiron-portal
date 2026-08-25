<?php
/**
 * Document API Controller
 *
 * Menyajikan bukti transaksi lewat pemeriksaan izin, bukan sebagai berkas
 * statis.
 *
 * SEBELUMNYA bukti pembayaran dilayani langsung dari /storage/documents/<nama>.
 * Aturan kedua di .htaccess menyajikan berkas apa pun yang ada tanpa bertanya
 * siapa yang meminta, sehingga siapa pun yang tahu — atau menebak — nama
 * berkasnya bisa mengunduh bukti bayar orang lain tanpa pernah login. Nama
 * berkasnya sendiri berpola (doc_<user_id>_<n>.<ext>), jadi menebaknya tidak
 * sulit.
 *
 * Sekarang berkasnya hanya keluar lewat sini, setelah requireAdmin().
 */

class DocumentController
{
    /** Ekstensi yang memang diunggah user sebagai bukti — lihat config/storage.php. */
    private const MIME = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'pdf' => 'application/pdf',
    ];

    /**
     * GET /admin/transactions/{id}/document
     *
     * Nama berkas TIDAK diambil dari URL. Yang datang dari klien hanyalah id
     * transaksi; nama berkasnya dibaca dari baris database milik id itu. Dengan
     * begitu tidak ada masukan klien yang pernah menyentuh jalur berkas, dan
     * meminta dokumen berarti meminta dokumen milik transaksi tertentu —
     * bukan berkas sembarang di dalam folder.
     */
    public function adminTransactionDocument(array $params): void
    {
        requireAdmin();

        $transaction = Transaction::find((int) ($params['id'] ?? 0));
        if (!$transaction || !$transaction->document_path) {
            jsonResponse(['error' => 'Dokumen tidak ditemukan'], 404);
        }

        self::send(
            $transaction->document_path,
            $transaction->document_original_name ?? $transaction->document_path
        );
    }

    /**
     * Mengirim satu berkas dari storage/documents.
     *
     * basename() dipakai walaupun nilainya berasal dari database: kolom yang
     * berisi ../../config/app.php akan membaca berkas di luar folder storage.
     * Nilai dari database tidak lebih tepercaya daripada nilai dari klien bila
     * yang menulisnya bisa keliru.
     */
    private static function send(string $storedName, string $downloadName): void
    {
        $dir = PORTAL_ROOT . '/storage/documents/';
        $file = $dir . basename($storedName);

        // realpath() menutup sisa kemungkinan: symlink atau path aneh yang
        // lolos basename tetap harus berujung di dalam folder ini.
        $real = realpath($file);
        $base = realpath($dir);
        if ($real === false || $base === false || !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
            jsonResponse(['error' => 'Dokumen tidak ditemukan'], 404);
        }

        $ext = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        $mime = self::MIME[$ext] ?? 'application/octet-stream';

        // index.php sudah menetapkan Content-Type: application/json untuk
        // seluruh API; header() menimpanya, bukan menambah.
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($real));
        // inline supaya <img src> di panel admin bisa menampilkannya langsung;
        // nama unduhan tetap nama asli yang diunggah user.
        header('Content-Disposition: inline; filename="' . self::safeName($downloadName) . '"');
        // Bukti pembayaran tidak boleh tersimpan di cache bersama.
        header('Cache-Control: private, no-store');

        readfile($real);
        exit;
    }

    /** Membuang karakter yang bisa memecah header Content-Disposition. */
    private static function safeName(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '_', basename($name)) ?: 'dokumen';
    }
}
