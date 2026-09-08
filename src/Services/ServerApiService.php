<?php

/**
 * Service to communicate with Central License Server
 *
 * This service isolates all external API calls to the authoritative server.
 * Portal acts as a client and never generates keys itself.
 *
 * IMPROVEMENTS v2.1:
 * - Enhanced logging with request/response tracking
 * - Better error messages with context
 * - Request timing measurement for monitoring
 * - Improved retry logic with detailed error tracking
 *
 * @version 2.1 - Enhanced logging & monitoring
 */
class ServerApiService
{
    // Maximum retry attempts
    private const MAX_RETRIES = 3;

    // Retry delay in milliseconds (exponential backoff)
    private const RETRY_DELAY_MS = 500;

    // Request timeout settings (milliseconds)
    private const REQUEST_TIMEOUT = 30000;  // 30 seconds
    private const CONNECT_TIMEOUT = 10000;  // 10 seconds

    // Timeout pembacaan status — jauh lebih pendek karena berjalan saat user
    // menunggu halaman terbuka, bukan saat transaksi diproses di latar.
    private const READ_TIMEOUT = 8;          // detik
    private const READ_CONNECT_TIMEOUT = 4;  // detik

    // Log levels
    private const LOG_INFO = 'INFO';
    private const LOG_WARN = 'WARN';
    private const LOG_ERROR = 'ERROR';
    private const LOG_DEBUG = 'DEBUG';

    /**
     * Generate a new license for a user via Server API
     *
     * IMPROVEMENTS:
     * - Added request timing measurement
     * - Enhanced logging with request/response tracking
     * - Better error context for debugging
     * - Log server response headers
     *
     * @param array $user User data (name, email)
     * @param string $transactionRef Transaction reference ID
     * @param string $productId Product identifier
     * @return array Response with license_key and api_key
     * @throws LicenseServerException If all retries fail
     */
    public static function generateLicense(array $user, string $transactionRef, string $productId = 'apeiron-kit'): array
    {
        $url = rtrim(LICENSE_SERVER_URL, '/') . '/create_license.php';

        $payload = [
            'client_name' => $user['name'],
            'client_email' => $user['email'],
            'product_id' => $productId,
            'reference' => $transactionRef
        ];

        $lastException = null;
        $lastAttempt = 0;

        // Log request start for monitoring
        $startTime = microtime(true);
        self::log(self::LOG_INFO, "License generation REQUEST: TX={$transactionRef}, User={$user['email']}, Product={$productId}");
        self::log(self::LOG_DEBUG, "Request payload: " . json_encode($payload));

        // Retry loop with exponential backoff
        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $attemptStartTime = microtime(true);
                $response = self::sendRequest($url, $payload, $startTime);
                $attemptDuration = round((microtime(true) - $attemptStartTime) * 1000);

                if (!empty($response['success']) && !empty($response['data']['license_key'])) {
                    // Log successful generation with timing
                    $totalDuration = round((microtime(true) - $startTime) * 1000);
                    self::log(self::LOG_INFO, "License generation SUCCESS: TX={$transactionRef}, Attempt={$attempt}, Duration={$attemptDuration}ms, Total={$totalDuration}ms");
                    self::log(self::LOG_DEBUG, "Response data: " . json_encode($response['data']));

                    return $response['data'];
                }

                // Server responded but no license key
                $msg = $response['message'] ?? 'Unknown error from license server';
                $lastException = new LicenseServerException("Server License Error: {$msg}", $attempt);
                self::log(self::LOG_WARN, "Server returned no license key: {$msg}");

                // Don't retry validation errors
                if (stripos($msg, 'invalid') !== false || stripos($msg, 'validation') !== false) {
                    self::log(self::LOG_ERROR, "Validation error - not retrying: {$msg}");
                    break;
                }

            } catch (Exception $e) {
                $lastException = $e;
                $attemptDuration = round((microtime(true) - $attemptStartTime) * 1000);

                self::log(self::LOG_ERROR, "License generation ATTEMPT {$attempt} FAILED: TX={$transactionRef}, Error={$e->getMessage()}, Duration={$attemptDuration}ms");

                // Don't retry on the last attempt
                if ($attempt < self::MAX_RETRIES) {
                    // Exponential backoff: 500ms, 1000ms, 2000ms
                    $delayMs = self::RETRY_DELAY_MS * pow(2, $attempt - 1);
                    self::log(self::LOG_DEBUG, "Waiting {$delayMs}ms before retry...");
                    usleep($delayMs * 1000); // Convert to microseconds
                }
            }
        }

        // All retries failed - throw exception for calling code to handle
        $totalDuration = round((microtime(true) - $startTime) * 1000);
        self::log(self::LOG_ERROR, "License generation FAILED: TX={$transactionRef}, All attempts exhausted, Total time={$totalDuration}ms");

        throw $lastException ?? new LicenseServerException('Failed to generate license after ' . self::MAX_RETRIES . ' attempts');
    }

    /**
     * Sebab kegagalan pembacaan terakhir.
     *
     * fetchLicenseStatus() tetap mengembalikan null saat gagal — pemanggil lama
     * tidak berubah. Yang ditambahkan hanya keterangan MENGAPA, supaya portal
     * berhenti menyebut semua kegagalan sebagai "server tidak merespons".
     * Server yang menjawab 404 dalam 125 ms jelas merespons.
     */
    private static ?array $sebabGagalBaca = null;

    /** @return array{code:string, http:?int, message:string}|null */
    public static function lastReadFailure(): ?array
    {
        return self::$sebabGagalBaca;
    }

    /** Catat sebab kegagalan lalu kembalikan null, seperti sebelumnya. */
    private static function gagalBaca(string $kode, string $pesan, ?int $http = null): ?array
    {
        self::$sebabGagalBaca = ['code' => $kode, 'http' => $http, 'message' => $pesan];
        self::log(self::LOG_WARN, "License read GAGAL [{$kode}]" . ($http ? " HTTP {$http}" : '') . ": {$pesan}");
        return null;
    }

    /**
     * Terjemahkan hasil mentah satu permintaan menjadi data, atau sebab kegagalan.
     *
     * Dipisah dari cURL dengan sengaja. Yang pernah salah bukan pemanggilan
     * HTTP-nya melainkan penafsirannya: setiap kegagalan diperlakukan sama,
     * sehingga 404 karena berkas endpoint tidak ada dilaporkan kepada user
     * sebagai "server tidak merespons" — padahal servernya menjawab dalam 125 ms.
     * Sebagai fungsi tersendiri, penafsiran itu bisa diuji langsung untuk setiap
     * bentuk balasan tanpa perlu menyalakan server.
     *
     * @return array{data: ?array, failure: ?array{code:string, http:?int, message:string}}
     */
    public static function classifyLicenseRead(
        ?string $body,
        int $httpCode,
        string $curlError = '',
        int $curlErrno = 0,
        int $durationMs = 0
    ): array {
        $gagal = static fn(string $kode, string $pesan, ?int $http = null): array => [
            'data' => null,
            'failure' => ['code' => $kode, 'http' => $http, 'message' => $pesan],
        ];

        if ($curlError !== '') {
            // Timeout dibedakan dari gagal menyambung: yang satu server lambat,
            // yang lain server tidak terjangkau sama sekali.
            return $curlErrno === CURLE_OPERATION_TIMEDOUT
                ? $gagal('TIMEOUT', "Server lisensi tidak menjawab dalam batas waktu ({$durationMs} ms).")
                : $gagal('NETWORK_ERROR', "Tidak bisa menghubungi server lisensi: {$curlError}");
        }

        $badanJson = str_starts_with(ltrim((string) $body), '{');

        if ($httpCode === 404) {
            // Dua hal yang sangat berbeda, dan dulu keduanya dilaporkan sama.
            // Halaman 404 milik hosting berarti BERKASNYA tidak ada di server;
            // JSON 404 berarti berkasnya ada dan lisensinyalah yang tidak
            // terdaftar. Yang pertama diperbaiki dengan mengunggah berkas, yang
            // kedua tidak — jadi menyatukannya membuat orang mencari di tempat
            // yang salah.
            return $badanJson
                ? $gagal('LICENSE_NOT_FOUND', 'Lisensi ini tidak terdaftar di server lisensi.', 404)
                : $gagal(
                    'ENDPOINT_NOT_FOUND',
                    'Endpoint portal_license.php tidak ada di License Server — server membalas halaman 404 hosting, bukan JSON.',
                    404
                );
        }

        if ($httpCode === 401 || $httpCode === 403) {
            return $gagal(
                $httpCode === 401 ? 'UNAUTHORIZED' : 'FORBIDDEN',
                'Server lisensi menolak kunci API portal.',
                $httpCode
            );
        }

        if ($httpCode === 400 || $httpCode === 422) {
            return $gagal('VALIDATION_ERROR', 'Server lisensi menolak isi permintaan.', $httpCode);
        }

        if ($httpCode === 429) {
            return $gagal('RATE_LIMIT', 'Terlalu sering menghubungi server lisensi. Coba lagi sebentar.', 429);
        }

        if ($httpCode >= 500) {
            return $gagal('SERVER_ERROR', "Server lisensi sedang bermasalah (HTTP {$httpCode}).", $httpCode);
        }

        if ($httpCode >= 400) {
            return $gagal('HTTP_ERROR', "Server lisensi membalas HTTP {$httpCode}.", $httpCode);
        }

        $json = json_decode((string) $body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $gagal('INVALID_RESPONSE', 'Balasan server lisensi bukan JSON yang sah.', $httpCode);
        }

        if (empty($json['success'])) {
            $msg = $json['message'] ?? 'tanpa pesan';
            return $gagal('REJECTED', "Server lisensi menolak permintaan: {$msg}", $httpCode);
        }

        if (empty($json['data']) || !is_array($json['data'])) {
            // Berhasil tetapi tanpa isi: bentuk balasannya berubah, atau
            // servernya menjawab kosong. Keduanya bukan "tidak merespons".
            return $gagal(
                'INVALID_RESPONSE',
                'Balasan server lisensi tidak memuat data yang diharapkan.',
                $httpCode
            );
        }

        return ['data' => $json['data'], 'failure' => null];
    }

    /**
     * Baca keadaan satu lisensi dari License Server.
     *
     * HANYA MEMBACA. Tidak membuat, mengaktifkan, memperpanjang, atau mengubah
     * apa pun — seluruh proses itu tetap milik server. Portal hanya bertanya
     * "apa keadaan lisensi ini" lalu menampilkan jawabannya.
     *
     * Sengaja TIDAK memakai retry dan timeout milik generateLicense(). Metode
     * itu dipanggil sekali saat transaksi disetujui, jadi menunggu tiga kali
     * percobaan selama 30 detik masih masuk akal di sana. Metode ini dipanggil
     * setiap kali user membuka halaman Plugin.
     *
     * @return array|null Data lisensi dari server, atau null bila tidak terbaca.
     *                    null berarti "tidak tahu", BUKAN "tidak aktif" —
     *                    pemanggil tidak boleh menyimpulkan status dari
     *                    kegagalan membaca. Sebabnya ada di lastReadFailure().
     */
    public static function fetchLicenseStatus(string $licenseKey, string $productId = 'apeiron-kit'): ?array
    {
        self::$sebabGagalBaca = null;

        if ($licenseKey === '') {
            return self::gagalBaca('NO_LICENSE_KEY', 'Lisensi ini belum punya license key.');
        }

        $url = rtrim(LICENSE_SERVER_URL, '/') . '/portal_license.php';
        $payload = ['license_key' => $licenseKey, 'product_id' => $productId];

        $startTime = microtime(true);

        try {
            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'X-API-Key: ' . LICENSE_SERVER_API_KEY,
                'User-Agent: ApeironPortal/2.1',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, self::READ_TIMEOUT);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::READ_CONNECT_TIMEOUT);

            if (defined('APP_ENV') && APP_ENV === 'production') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }

            $response = curl_exec($ch);
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $duration = (int) round((microtime(true) - $startTime) * 1000);

            // Penafsirannya ada di classifyLicenseRead() — satu tempat,
            // bisa diuji langsung untuk setiap bentuk balasan.
            $hasil = self::classifyLicenseRead(
                $response === false ? null : (string) $response,
                (int) $httpCode,
                $error,
                $errno,
                $duration
            );

            if ($hasil['failure'] !== null) {
                return self::gagalBaca(
                    $hasil['failure']['code'],
                    $hasil['failure']['message'],
                    $hasil['failure']['http']
                );
            }

            self::log(self::LOG_INFO, "License read SUCCESS: Duration={$duration}ms");

            return $hasil['data'];
        } catch (Exception $e) {
            return self::gagalBaca('EXCEPTION', 'Gagal membaca status lisensi: ' . $e->getMessage());
        }
    }

    /**
     * Check if license server is available
     *
     * IMPROVEMENTS:
     * - Added detailed logging
     * - Added response time measurement
     *
     * @return bool True if server is reachable
     */
    public static function isServerAvailable(): bool
    {
        try {
            $url = rtrim(LICENSE_SERVER_URL, '/') . '/health.php';
            $startTime = microtime(true);

            self::log(self::LOG_DEBUG, "Checking server availability: {$url}");

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'X-API-Key: ' . LICENSE_SERVER_API_KEY
            ]);

            // SSL handling
            if (defined('APP_ENV') && APP_ENV === 'production') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $duration = round((microtime(true) - $startTime) * 1000);

            if ($httpCode === 200) {
                self::log(self::LOG_INFO, "Server health check SUCCESS: Code={$httpCode}, Duration={$duration}ms");
                return true;
            } else {
                self::log(self::LOG_WARN, "Server health check FAILED: Code={$httpCode}, Duration={$duration}ms");
                return false;
            }

        } catch (Exception $e) {
            self::log(self::LOG_ERROR, "Server health check EXCEPTION: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send HTTP POST request to Server
     *
     * IMPROVEMENTS:
     * - Added detailed logging
     * - Added request ID for tracking
     * - Better timeout handling
     *
     * @param string $url Target URL
     * @param array $data Request payload
     * @param float $startTime Request start time for logging
     * @return array Decoded JSON response
     * @throws Exception On connection or HTTP error
     */
    private static function sendRequest(string $url, array $data, float $startTime = null): array
    {
        $requestId = uniqid('req_', true);
        if ($startTime === null) $startTime = microtime(true);

        self::log(self::LOG_DEBUG, "Request {$requestId} to: {$url}");

        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . LICENSE_SERVER_API_KEY,
            'User-Agent: ApeironPortal/2.1',
            'X-Request-ID: ' . $requestId
        ]);

        // Timeout configuration
        curl_setopt($ch, CURLOPT_TIMEOUT, self::REQUEST_TIMEOUT / 1000);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::CONNECT_TIMEOUT / 1000);

        // Security: SSL verification based on environment
        if (defined('APP_ENV') && APP_ENV === 'production') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlInfo = curl_getinfo($ch);
        curl_close($ch);

        $duration = round((microtime(true) - $startTime) * 1000);

        if ($error) {
            self::log(self::LOG_ERROR, "Request {$requestId} CONNECTION ERROR: {$error}, Duration={$duration}ms");
            throw new Exception("Connection to license server failed: {$error}");
        }

        if ($httpCode >= 400) {
            self::log(self::LOG_ERROR, "Request {$requestId} HTTP ERROR: Code={$httpCode}, Duration={$duration}ms, Body={$response}");

            // More detailed error message based on HTTP code
            $errorMessages = [
                400 => 'Bad Request - Invalid parameters',
                401 => 'Unauthorized - Invalid API Key',
                403 => 'Forbidden - Access denied',
                404 => 'Not Found - Endpoint not found',
                429 => 'Too Many Requests - Rate limit exceeded',
                500 => 'Internal Server Error',
                502 => 'Bad Gateway',
                503 => 'Service Unavailable',
                504 => 'Gateway Timeout'
            ];

            $errorMsg = $errorMessages[$httpCode] ?? "HTTP {$httpCode}";
            throw new Exception("License server returned error ({$httpCode}): {$errorMsg}");
        }

        $json = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            self::log(self::LOG_ERROR, "Request {$requestId} INVALID JSON: " . json_last_error_msg() . ", Response: {$response}");
            throw new Exception("Invalid response from license server: " . json_last_error_msg());
        }

        self::log(self::LOG_DEBUG, "Request {$requestId} SUCCESS: Code={$httpCode}, Duration={$duration}ms");

        return $json;
    }

    /**
     * Helper method for consistent logging format
     *
     * @param string $level Log level (INFO, WARN, ERROR, DEBUG)
     * @param string $message Log message
     */
    private static function log(string $level, string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[PORTAL][{$level}][{$timestamp}] {$message}";
        error_log($logMessage);
    }
}

/**
 * Custom exception for License Server errors
 *
 * IMPROVEMENTS:
 * - Added request ID tracking
 * - Better retry logic
 */
class LicenseServerException extends Exception
{
    private $attemptNumber;
    private ?string $requestId = null;

    public function __construct(string $message, int $attempt = 0, ?string $requestId = null)
    {
        parent::__construct($message);
        $this->attemptNumber = $attempt;
        $this->requestId = $requestId;
    }

    public function getAttemptNumber(): int
    {
        return $this->attemptNumber;
    }

    public function isRetryable(): bool
    {
        // Not retryable if it's a validation error
        $nonRetryableKeywords = ['invalid', 'validation', 'not found', 'unauthorized', 'duplicate', 'exists'];
        $message = strtolower($this->getMessage());

        foreach ($nonRetryableKeywords as $keyword) {
            if (strpos($message, $keyword) !== false) {
                return false;
            }
        }

        return true;
    }

    public function setRequestId(string $requestId): void
    {
        $this->requestId = $requestId;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
