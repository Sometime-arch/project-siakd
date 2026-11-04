<?php
declare(strict_types=1);

$hariInggris = ['Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa', 'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat', 'Saturday' => 'Sabtu'];
$bulanInggris = [1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
$tanggalHariIni = ($hariInggris[date('l')] ?? date('l')) . ', ' . date('j') . ' ' . ($bulanInggris[(int) date('n')] ?? '') . ' ' . date('Y');
?>
<nav class="app-topbar d-flex align-items-center justify-content-between gap-2" aria-label="Navigasi atas">
    <div class="d-flex align-items-center gap-2 gap-md-3 min-w-0">
        <button class="sidebar-toggle" id="sidebarToggle" type="button" aria-label="Buka menu navigasi" aria-controls="sidebar" aria-expanded="false">
            <i class="bi bi-list" aria-hidden="true"></i>
        </button>
        <div class="min-w-0">
            <p class="topbar-title"><?= e($pageTitle) ?></p>
            <span class="topbar-subtitle"><?= e($pageSubtitle) ?></span>
        </div>
    </div>

    <div class="d-flex align-items-center gap-2 gap-md-3">
        <span class="topbar-date">
            <i class="bi bi-calendar4-week" aria-hidden="true"></i>
            <?= e($tanggalHariIni) ?>
        </span>

        <div class="dropdown">
            <button class="user-menu dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Menu profil pengguna">
                <span class="avatar"><?= e(initials($currentUser['nama'])) ?></span>
                <span class="user-meta">
                    <span class="user-name"><?= e($currentUser['nama']) ?></span>
                    <span class="user-role"><?= e(role_label($currentUser['role'])) ?></span>
                </span>
            </button>
            <div class="dropdown-menu dropdown-menu-end" style="min-width: 250px;">
                <div class="dropdown-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="avatar avatar-sm"><?= e(initials($currentUser['nama'])) ?></span>
                        <div class="min-w-0">
                            <div class="fw-semibold text-navy text-truncate" style="max-width: 170px;"><?= e($currentUser['nama']) ?></div>
                            <div class="text-muted text-truncate" style="max-width: 170px; font-size: 0.68rem;"><?= e($currentUser['email']) ?></div>
                        </div>
                    </div>
                    <span class="badge text-bg-primary-subtle rounded-pill mt-2"><?= e(role_label($currentUser['role'])) ?></span>
                </div>
                <div class="dropdown-divider"></div>
                <a class="dropdown-item" href="<?= e(base_url(role_dashboard($currentUser['role']))) ?>">
                    <i class="bi bi-grid-1x2" aria-hidden="true"></i> Dashboard
                </a>
                <?php if ($currentUser['role'] === 'mahasiswa'): ?>
                    <a class="dropdown-item" href="<?= e(base_url('mahasiswa/khs.php')) ?>">
                        <i class="bi bi-award" aria-hidden="true"></i> Kartu Hasil Studi
                    </a>
                <?php elseif ($currentUser['role'] === 'dosen'): ?>
                    <a class="dropdown-item" href="<?= e(base_url('dosen/nilai.php')) ?>">
                        <i class="bi bi-pencil-square" aria-hidden="true"></i> Input Nilai
                    </a>
                <?php else: ?>
                    <a class="dropdown-item" href="<?= e(base_url('admin/krs.php')) ?>">
                        <i class="bi bi-clipboard-check" aria-hidden="true"></i> Validasi KRS
                    </a>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <form action="<?= e(base_url('logout.php')) ?>" method="post">
                    <?= csrf_field() ?>
                    <button type="submit" class="dropdown-item text-danger">
                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Keluar
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
