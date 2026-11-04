<?php
declare(strict_types=1);

/**
 * Konfigurasi koneksi database.
 * Nilai default cocok untuk XAMPP/Laragon (user root tanpa password).
 * Bisa juga diatur melalui environment variable DB_HOST, DB_PORT, DB_NAME,
 * DB_USER, dan DB_PASS.
 */

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'siakd');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : '');

/**
 * Mengembalikan koneksi PDO tunggal (singleton) untuk seluruh request.
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        DB_HOST,
        DB_PORT,
        DB_NAME
    );

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $exception) {
        error_log('Database connection failed: ' . $exception->getMessage());
        throw new RuntimeException(
            'Koneksi database gagal. Pastikan MySQL berjalan dan database "siakd" sudah diimpor dari database.sql.'
        );
    }

    return $pdo;
}
