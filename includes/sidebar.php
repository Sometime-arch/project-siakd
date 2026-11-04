<?php
declare(strict_types=1);

$sidebarMenus = [
    'admin' => [
        ['label' => 'Utama', 'items' => [
            ['menu' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'bi-grid-1x2', 'url' => 'admin/dashboard.php'],
        ]],
        ['label' => 'Data Akademik', 'items' => [
            ['menu' => 'mahasiswa', 'title' => 'Mahasiswa', 'icon' => 'bi-people', 'url' => 'admin/mahasiswa.php'],
            ['menu' => 'dosen', 'title' => 'Dosen', 'icon' => 'bi-person-badge', 'url' => 'admin/dosen.php'],
            ['menu' => 'prodi', 'title' => 'Program Studi', 'icon' => 'bi-building', 'url' => 'admin/prodi.php'],
            ['menu' => 'mata_kuliah', 'title' => 'Mata Kuliah', 'icon' => 'bi-journal-bookmark', 'url' => 'admin/mata_kuliah.php'],
            ['menu' => 'jadwal', 'title' => 'Jadwal Kuliah', 'icon' => 'bi-calendar3', 'url' => 'admin/jadwal.php'],
        ]],
        ['label' => 'Administrasi', 'items' => [
            ['menu' => 'krs', 'title' => 'Validasi KRS', 'icon' => 'bi-clipboard-check', 'url' => 'admin/krs.php'],
        ]],
    ],
    'dosen' => [
        ['label' => 'Utama', 'items' => [
            ['menu' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'bi-grid-1x2', 'url' => 'dosen/dashboard.php'],
        ]],
        ['label' => 'Akademik', 'items' => [
            ['menu' => 'mata_kuliah', 'title' => 'Mata Kuliah Saya', 'icon' => 'bi-journal-bookmark', 'url' => 'dosen/mata_kuliah.php'],
            ['menu' => 'nilai', 'title' => 'Input Nilai', 'icon' => 'bi-pencil-square', 'url' => 'dosen/nilai.php'],
            ['menu' => 'jadwal', 'title' => 'Jadwal Mengajar', 'icon' => 'bi-calendar3', 'url' => 'dosen/jadwal.php'],
        ]],
    ],
    'mahasiswa' => [
        ['label' => 'Utama', 'items' => [
            ['menu' => 'dashboard', 'title' => 'Dashboard', 'icon' => 'bi-grid-1x2', 'url' => 'mahasiswa/dashboard.php'],
        ]],
        ['label' => 'Akademik', 'items' => [
            ['menu' => 'krs', 'title' => 'Ambil KRS', 'icon' => 'bi-journal-plus', 'url' => 'mahasiswa/krs.php'],
            ['menu' => 'khs', 'title' => 'Kartu Hasil Studi', 'icon' => 'bi-award', 'url' => 'mahasiswa/khs.php'],
            ['menu' => 'jadwal', 'title' => 'Jadwal Kuliah', 'icon' => 'bi-calendar3', 'url' => 'mahasiswa/jadwal.php'],
        ]],
    ],
];

$menus = $sidebarMenus[$currentUser['role']] ?? [];
?>
<aside class="sidebar" id="sidebar" aria-label="Navigasi utama">
    <a class="sidebar-brand" href="<?= e(base_url(role_dashboard($currentUser['role']))) ?>">
        <span class="brand-mark"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i></span>
        <span>
            <span class="brand-title"><?= e(APP_NAME) ?></span>
            <span class="brand-subtitle"><?= e(APP_FULL_NAME) ?></span>
        </span>
    </a>
    <div class="sidebar-divider"></div>

    <div class="sidebar-scroll">
        <?php foreach ($menus as $section): ?>
            <p class="sidebar-label"><?= e($section['label']) ?></p>
            <ul class="sidebar-nav">
                <?php foreach ($section['items'] as $item): ?>
                    <li>
                        <a class="nav-link <?= $activeMenu === $item['menu'] ? 'active' : '' ?>"
                           href="<?= e(base_url($item['url'])) ?>"
                           <?= $activeMenu === $item['menu'] ? 'aria-current="page"' : '' ?>>
                            <i class="bi <?= e($item['icon']) ?>" aria-hidden="true"></i>
                            <span><?= e($item['title']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endforeach; ?>
    </div>

    <div class="sidebar-period">
        <span class="sidebar-period-label">Periode Aktif</span>
        <span class="sidebar-period-value">
            <span class="status-dot"></span>
            <?= e(TAHUN_AJARAN_AKTIF) ?> &middot; <?= e(SEMESTER_AKTIF) ?>
        </span>
    </div>
</aside>
