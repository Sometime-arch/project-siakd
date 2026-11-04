<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

/**
 * Mengambil pengguna aktif dari session, lalu memvalidasi ulang ke database.
 */
function current_user(): ?array
{
    static $loaded = false;
    static $user = null;

    if ($loaded) {
        return $user;
    }

    $loaded = true;
    $userId = (int) ($_SESSION['user_id'] ?? 0);

    if ($userId <= 0) {
        return null;
    }

    try {
        $stmt = db()->prepare(
            "SELECT u.id, u.nama, u.email, u.role, u.status, u.created_at,
                    m.id AS mahasiswa_id, m.nim, m.prodi_id AS mahasiswa_prodi_id,
                    m.angkatan, m.jenis_kelamin, m.alamat,
                    d.id AS dosen_id, d.nidn, d.prodi_id AS dosen_prodi_id
             FROM users u
             LEFT JOIN mahasiswa m ON m.user_id = u.id
             LEFT JOIN dosen d ON d.user_id = u.id
             WHERE u.id = :id AND u.status = 'aktif'
             LIMIT 1"
        );
        $stmt->execute(['id' => $userId]);
        $result = $stmt->fetch();

        if ($result) {
            $user = $result;
            return $user;
        }
    } catch (Throwable $exception) {
        error_log('Auth check failed: ' . $exception->getMessage());
    }

    unset($_SESSION['user_id']);
    $user = null;

    return null;
}

/**
 * Wajib login untuk mengakses halaman.
 */
function require_login(): array
{
    $user = current_user();

    if (!$user) {
        flash('warning', 'Silakan login terlebih dahulu.');
        redirect('login.php');
    }

    return $user;
}

/**
 * Batasi halaman berdasarkan role.
 */
function require_role(string ...$roles): array
{
    $user = require_login();

    if (!in_array($user['role'], $roles, true)) {
        flash('danger', 'Anda tidak memiliki akses ke halaman tersebut.');
        redirect(role_dashboard($user['role']));
    }

    return $user;
}

/**
 * URL dashboard berdasarkan role.
 */
function role_dashboard(string $role): string
{
    return match ($role) {
        'admin' => 'admin/dashboard.php',
        'dosen' => 'dosen/dashboard.php',
        'mahasiswa' => 'mahasiswa/dashboard.php',
        default => 'login.php',
    };
}

/**
 * Proses login dengan password_verify.
 */
function login_user(string $email, string $password): bool
{
    $stmt = db()->prepare(
        "SELECT id, nama, email, password, role, status
         FROM users
         WHERE email = :email
         LIMIT 1"
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || $user['status'] !== 'aktif' || !password_verify($password, $user['password'])) {
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['role'] = $user['role'];
    clear_login_attempts();

    return true;
}

/**
 * Perlindungan sederhana dari percobaan login berulang.
 */
function login_locked_seconds(): int
{
    $lockedUntil = (int) ($_SESSION['_login_locked_until'] ?? 0);
    $remaining = $lockedUntil - time();

    return $remaining > 0 ? $remaining : 0;
}

function register_failed_login(): void
{
    $attempts = (int) ($_SESSION['_login_attempts'] ?? 0) + 1;
    $_SESSION['_login_attempts'] = $attempts;

    if ($attempts >= 5) {
        $_SESSION['_login_locked_until'] = time() + 300;
        $_SESSION['_login_attempts'] = 0;
    }
}

function clear_login_attempts(): void
{
    unset($_SESSION['_login_attempts'], $_SESSION['_login_locked_until']);
}

/**
 * CSRF token per session.
 */
function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(?string $token = null): bool
{
    $token ??= (string) ($_POST['csrf_token'] ?? '');
    $sessionToken = $_SESSION['_csrf_token'] ?? '';

    return is_string($sessionToken)
        && $sessionToken !== ''
        && $token !== ''
        && hash_equals($sessionToken, $token);
}

/**
 * Hentikan request bila token CSRF tidak valid.
 */
function verify_csrf_or_redirect(string $fallback): void
{
    if (!verify_csrf()) {
        flash('danger', 'Token keamanan tidak valid. Silakan coba lagi.');
        redirect($fallback);
    }
}

/**
 * Logout dan hancurkan session.
 */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }

    session_destroy();
}
