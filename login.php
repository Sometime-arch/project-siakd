<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$loggedInUser = current_user();
if ($loggedInUser) {
    redirect(role_dashboard($loggedInUser['role']));
}

$pageError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        flash('danger', 'Sesi formulir telah kedaluwarsa. Silakan coba lagi.');
        redirect('login.php');
    }

    $lockedSeconds = login_locked_seconds();
    if ($lockedSeconds > 0) {
        flash('warning', 'Terlalu banyak percobaan login. Coba lagi dalam ' . ceil($lockedSeconds / 60) . ' menit.');
        redirect('login.php');
    }

    $email = strtolower((string) post('email'));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        flash('danger', 'Email dan password wajib diisi.');
        redirect('login.php');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Format email tidak valid.');
        redirect('login.php');
    }

    try {
        if (login_user($email, $password)) {
            $user = current_user();
            redirect($user ? role_dashboard($user['role']) : 'index.php');
        }

        register_failed_login();
        flash('danger', 'Email atau password salah. Periksa kembali kredensial Anda.');
        redirect('login.php');
    } catch (Throwable $exception) {
        $pageError = $exception instanceof RuntimeException
            ? $exception->getMessage()
            : 'Terjadi kesalahan saat memproses login. Silakan coba lagi.';
    }
}

$flashes = pull_flashes();
$flashTypeMap = [
    'success' => 'success',
    'danger' => 'danger',
    'warning' => 'warning',
    'info' => 'info',
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Masuk &middot; <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(base_url('assets/img/logo.svg')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/style.css')) ?>?v=1.0" rel="stylesheet">
</head>
<body class="auth-page">
<div class="auth-shell">
    <section class="auth-hero">
        <a class="auth-brand" href="<?= e(base_url('login.php')) ?>">
            <span class="brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
            <span>
                <span class="brand-title"><?= e(APP_NAME) ?></span>
                <span class="brand-subtitle"><?= e(APP_FULL_NAME) ?></span>
            </span>
        </a>

        <div class="auth-hero-content">
            <span class="auth-eyebrow"><i class="bi bi-shield-check" aria-hidden="true"></i> Portal Akademik Terpadu</span>
            <h1>Satu pintu untuk <span>kegiatan akademik</span> kampus.</h1>
            <p>Kelola data mahasiswa, dosen, mata kuliah, KRS, jadwal, hingga nilai dalam satu sistem yang rapi, aman, dan mudah digunakan.</p>
            <div class="auth-features">
                <div class="auth-feature"><i class="bi bi-people" aria-hidden="true"></i> Manajemen data akademik</div>
                <div class="auth-feature"><i class="bi bi-journal-check" aria-hidden="true"></i> KRS &amp; validasi SKS</div>
                <div class="auth-feature"><i class="bi bi-pencil-square" aria-hidden="true"></i> Input nilai terintegrasi</div>
                <div class="auth-feature"><i class="bi bi-award" aria-hidden="true"></i> KHS &amp; perhitungan IPS</div>
            </div>
        </div>

        <div class="auth-footer">&copy; <?= date('Y') ?> <?= e(APP_NAME) ?>. Sistem Informasi Akademik Kampus.</div>
    </section>

    <section class="auth-panel">
        <div class="auth-card">
            <div class="d-lg-none mb-4">
                <a class="d-inline-flex align-items-center gap-2 text-dark" href="<?= e(base_url('login.php')) ?>">
                    <span class="brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
                    <span class="fw-bold"><?= e(APP_NAME) ?></span>
                </a>
            </div>

            <h2>Selamat datang kembali</h2>
            <p class="auth-lead">Masuk menggunakan akun kampus Anda untuk melanjutkan.</p>

            <?php if (isset($_GET['logged_out'])): ?>
                <div class="alert alert-success d-flex align-items-center gap-2" role="alert">
                    <i class="bi bi-check-circle" aria-hidden="true"></i>
                    Anda telah berhasil keluar dari sistem.
                </div>
            <?php endif; ?>

            <?php if ($pageError !== null): ?>
                <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
                    <i class="bi bi-exclamation-octagon mt-1" aria-hidden="true"></i>
                    <span><?= e($pageError) ?></span>
                </div>
            <?php endif; ?>

            <?php foreach ($flashes as $flashMessage): ?>
                <?php $type = $flashTypeMap[$flashMessage['type']] ?? 'info'; ?>
                <div class="alert alert-<?= e($type) ?> alert-dismissible fade show" role="alert">
                    <?= e($flashMessage['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            <?php endforeach; ?>

            <form action="<?= e(base_url('login.php')) ?>" method="post" novalidate>
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Email <span class="required">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-envelope" aria-hidden="true"></i>
                        <input type="email" class="form-control" id="email" name="email" placeholder="nama@siakd.test" autocomplete="username" required autofocus>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="password">Password <span class="required">*</span></label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-lock" aria-hidden="true"></i>
                        <input type="password" class="form-control" id="password" name="password" placeholder="Masukkan password" autocomplete="current-password" required>
                        <button type="button" class="password-toggle" data-password-toggle="#password" aria-label="Tampilkan password">
                            <i class="bi bi-eye" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 mt-2">
                    <i class="bi bi-box-arrow-in-right me-1" aria-hidden="true"></i> Masuk ke Dashboard
                </button>
            </form>

            <div class="demo-accounts">
                <span class="demo-accounts-label">Akun demo &mdash; klik untuk mengisi</span>
                <div class="demo-buttons">
                    <button type="button" class="demo-btn" data-demo-email="admin@siakd.test" data-demo-password="password123">
                        <i class="bi bi-shield-lock" aria-hidden="true"></i> Admin
                    </button>
                    <button type="button" class="demo-btn" data-demo-email="dosen@siakd.test" data-demo-password="password123">
                        <i class="bi bi-person-video3" aria-hidden="true"></i> Dosen
                    </button>
                    <button type="button" class="demo-btn" data-demo-email="mahasiswa@siakd.test" data-demo-password="password123">
                        <i class="bi bi-mortarboard" aria-hidden="true"></i> Mahasiswa
                    </button>
                </div>
                <p class="small-note mt-2 mb-0">Password seluruh akun demo: <strong>password123</strong></p>
            </div>
        </div>
    </section>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(base_url('assets/js/app.js')) ?>?v=1.0"></script>
</body>
</html>
