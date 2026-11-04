<?php
declare(strict_types=1);

/**
 * Konfigurasi utama aplikasi SIAKD.
 * Ubah tahun ajaran / semester di sini saat periode akademik berganti.
 */

date_default_timezone_set('Asia/Jakarta');

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

define('APP_NAME', 'SIAKD');
define('APP_FULL_NAME', 'Sistem Informasi Akademik');
define('APP_ROOT', dirname(__DIR__));
define('TAHUN_AJARAN_AKTIF', '2026/2027');
define('SEMESTER_AKTIF', 'Ganjil');
define('MAX_SKS', 24);

/**
 * Menentukan base URL agar aplikasi tetap berjalan baik di root domain
 * maupun di subfolder (contoh: http://localhost/test-project).
 */
$documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath((string) $_SERVER['DOCUMENT_ROOT']) : false;
$applicationRoot = realpath(APP_ROOT);
$baseUrl = '';

if ($documentRoot && $applicationRoot) {
    $normalize = static fn (string $path): string => rtrim(str_replace('\\', '/', $path), '/');
    $docRoot = $normalize($documentRoot);
    $appRoot = $normalize($applicationRoot);

    if (strcasecmp($appRoot, $docRoot) === 0) {
        $baseUrl = '';
    } elseif (str_starts_with(strtolower($appRoot), strtolower($docRoot) . '/')) {
        $baseUrl = substr($appRoot, strlen($docRoot));
    }
}

define('BASE_URL', $baseUrl);
