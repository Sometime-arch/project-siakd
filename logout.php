<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

$user = current_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('info', 'Gunakan tombol keluar yang tersedia pada menu profil.');
    redirect($user ? role_dashboard($user['role']) : 'login.php');
}

if (!verify_csrf()) {
    flash('danger', 'Token keamanan tidak valid. Silakan coba lagi.');
    redirect($user ? role_dashboard($user['role']) : 'login.php');
}

logout_user();
redirect('login.php?logged_out=1');
