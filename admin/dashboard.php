<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_role('admin');

$pageTitle = 'Dashboard';
$pageSubtitle = 'Ringkasan data akademik kampus';
$activeMenu = 'dashboard';

$totalMahasiswa = (int) db()->query('SELECT COUNT(*) FROM mahasiswa')->fetchColumn();
$totalDosen = (int) db()->query('SELECT COUNT(*) FROM dosen')->fetchColumn();
$totalMataKuliah = (int) db()->query('SELECT COUNT(*) FROM mata_kuliah')->fetchColumn();
$totalProdi = (int) db()->query('SELECT COUNT(*) FROM prodi')->fetchColumn();
$pendingKrs = (int) db()->query("SELECT COUNT(*) FROM krs WHERE status = 'menunggu'")->fetchColumn();

// Total SKS KRS yang disetujui pada periode aktif.
$stmtActiveSks = db()->prepare(
    "SELECT COALESCE(SUM(mk.sks), 0)
     FROM krs k
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     WHERE k.status = 'disetujui' AND k.tahun_ajaran = :tahun_ajaran"
);
$stmtActiveSks->execute(['tahun_ajaran' => TAHUN_AJARAN_AKTIF]);
$activeSks = (int) $stmtActiveSks->fetchColumn();

$latestKrs = db()->query(
    "SELECT k.id, k.status, k.created_at, k.semester, k.tahun_ajaran,
            m.nim, m.nama AS nama_mahasiswa, mk.kode_mk, mk.nama_mk, mk.sks,
            p.nama_prodi
     FROM krs k
     JOIN mahasiswa m ON m.id = k.mahasiswa_id
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     JOIN prodi p ON p.id = m.prodi_id
     ORDER BY k.created_at DESC
     LIMIT 6"
)->fetchAll();

$gradeRows = db()->query(
    "SELECT nilai_huruf, COUNT(*) AS total
     FROM nilai
     GROUP BY nilai_huruf"
)->fetchAll();
$gradeCounts = [];
$totalGrades = 0;
foreach ($gradeRows as $row) {
    $gradeCounts[$row['nilai_huruf']] = (int) $row['total'];
    $totalGrades += (int) $row['total'];
}
$maxGrade = $gradeCounts ? max($gradeCounts) : 1;

$prodiRows = db()->query(
    "SELECT p.nama_prodi, p.kode_prodi, COUNT(m.id) AS total
     FROM prodi p
     LEFT JOIN mahasiswa m ON m.prodi_id = p.id
     GROUP BY p.id, p.nama_prodi, p.kode_prodi
     ORDER BY total DESC, p.nama_prodi ASC"
)->fetchAll();
$maxProdi = 1;
foreach ($prodiRows as $row) {
    $maxProdi = max($maxProdi, (int) $row['total']);
}

$jam = (int) date('G');
$sapaan = $jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam'));

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1>Dashboard Admin</h1>
        <p>Pantau data akademik dan aktivitas terbaru kampus dari satu tempat.</p>
    </div>
    <a href="<?= e(base_url('admin/mahasiswa.php')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Kelola Mahasiswa
    </a>
</div>

<section class="welcome-banner mb-4">
    <div class="position-relative" style="z-index: 1;">
        <span class="auth-eyebrow mb-2">Periode <?= e(TAHUN_AJARAN_AKTIF) ?> &middot; <?= e(SEMESTER_AKTIF) ?></span>
        <h2><?= e($sapaan) ?>, <?= e($user['nama']) ?>.</h2>
        <p>Ada <strong><?= $pendingKrs ?></strong> pengajuan KRS yang menunggu validasi. Data akademik saat ini mencakup <?= $totalMahasiswa ?> mahasiswa, <?= $totalDosen ?> dosen, dan <?= $totalMataKuliah ?> mata kuliah aktif.</p>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a href="<?= e(base_url('admin/krs.php?status=menunggu')) ?>" class="btn btn-light btn-sm">
                <i class="bi bi-clipboard-check me-1" aria-hidden="true"></i> Validasi KRS
            </a>
            <a href="<?= e(base_url('admin/mata_kuliah.php')) ?>" class="btn btn-outline-light btn-sm" style="border-color: rgba(255,255,255,.35); color:#fff;">
                <i class="bi bi-journal-bookmark me-1" aria-hidden="true"></i> Data Mata Kuliah
            </a>
        </div>
    </div>
    <i class="bi bi-mortarboard banner-icon" aria-hidden="true"></i>
</section>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-people" aria-hidden="true"></i></div>
            <p class="stat-label">Total Mahasiswa</p>
            <p class="stat-value"><?= number_format($totalMahasiswa, 0, ',', '.') ?></p>
            <p class="stat-note"><i class="bi bi-check-circle me-1 text-success" aria-hidden="true"></i>Terdaftar di <?= $totalProdi ?> program studi</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-green"><i class="bi bi-person-badge" aria-hidden="true"></i></div>
            <p class="stat-label">Total Dosen</p>
            <p class="stat-value"><?= number_format($totalDosen, 0, ',', '.') ?></p>
            <p class="stat-note"><i class="bi bi-journal-text me-1 text-success" aria-hidden="true"></i>Tenaga pengajar aktif</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-amber"><i class="bi bi-journal-bookmark" aria-hidden="true"></i></div>
            <p class="stat-label">Total Mata Kuliah</p>
            <p class="stat-value"><?= number_format($totalMataKuliah, 0, ',', '.') ?></p>
            <p class="stat-note"><i class="bi bi-calendar3 me-1 text-warning" aria-hidden="true"></i><?= number_format($activeSks, 0, ',', '.') ?> SKS disetujui periode ini</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-purple"><i class="bi bi-building" aria-hidden="true"></i></div>
            <p class="stat-label">Total Program Studi</p>
            <p class="stat-value"><?= number_format($totalProdi, 0, ',', '.') ?></p>
            <p class="stat-note"><i class="bi bi-hourglass-split me-1" style="color:#b45309;" aria-hidden="true"></i><?= $pendingKrs ?> KRS menunggu validasi</p>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div>
                    <h2>Aktivitas KRS Terbaru</h2>
                    <p class="section-subtitle mt-1">Pengajuan terbaru dari mahasiswa</p>
                </div>
                <a href="<?= e(base_url('admin/krs.php')) ?>" class="btn btn-outline-secondary btn-sm">Lihat Semua</a>
            </div>
            <?php if ($latestKrs): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Mahasiswa</th>
                                <th>Mata Kuliah</th>
                                <th>Periode</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($latestKrs as $row): ?>
                                <tr>
                                    <td>
                                        <span class="cell-title"><?= e($row['nama_mahasiswa']) ?></span>
                                        <span class="cell-subtitle"><?= e($row['nim']) ?> &middot; <?= e($row['nama_prodi']) ?></span>
                                    </td>
                                    <td>
                                        <span class="cell-title"><?= e($row['nama_mk']) ?></span>
                                        <span class="cell-subtitle"><?= e($row['kode_mk']) ?> &middot; <?= (int) $row['sks'] ?> SKS</span>
                                    </td>
                                    <td>
                                        <span class="cell-title"><?= e($row['tahun_ajaran']) ?></span>
                                        <span class="cell-subtitle">Semester <?= (int) $row['semester'] ?></span>
                                    </td>
                                    <td><?= status_badge($row['status']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-clipboard-x" aria-hidden="true"></i></div>
                    <h3>Belum ada aktivitas KRS</h3>
                    <p>Pengajuan KRS mahasiswa akan muncul di sini.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <h2>Distribusi Nilai</h2>
                <p class="section-subtitle mt-1"><?= $totalGrades ?> nilai telah diinput</p>
            </div>
            <div class="card-body">
                <?php if ($totalGrades > 0): ?>
                    <?php foreach (['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'D', 'E'] as $letter): ?>
                        <?php $count = $gradeCounts[$letter] ?? 0; ?>
                        <div class="chart-row">
                            <span class="chart-label"><?= e($letter) ?></span>
                            <div class="chart-track">
                                <div class="chart-fill" style="width: <?= e((string) (($count / $maxGrade) * 100)) ?>%;"></div>
                            </div>
                            <span class="chart-value"><?= $count ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="divider"></div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="small-note">Rata-rata nilai akhir</span>
                        <strong class="text-navy">
                            <?php
                            $avg = db()->query('SELECT ROUND(AVG(nilai_akhir), 2) FROM nilai')->fetchColumn();
                            echo e((string) ($avg ?? '0'));
                            ?>
                        </strong>
                    </div>
                <?php else: ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-bar-chart" aria-hidden="true"></i></div>
                        <h3>Belum ada nilai</h3>
                        <p>Statistik akan tampil setelah dosen menginput nilai mahasiswa.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header">
                <h2>Sebaran Mahasiswa per Program Studi</h2>
                <p class="section-subtitle mt-1">Jumlah mahasiswa aktif per prodi</p>
            </div>
            <div class="card-body">
                <?php if ($prodiRows): ?>
                    <?php foreach ($prodiRows as $row): ?>
                        <?php $percentage = ($maxProdi > 0) ? ((int) $row['total'] / $maxProdi) * 100 : 0; ?>
                        <div class="chart-row">
                            <span class="chart-label" style="width: 38px;"><?= e($row['kode_prodi']) ?></span>
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between mb-1">
                                    <span style="font-size: 0.72rem; font-weight: 500;"><?= e($row['nama_prodi']) ?></span>
                                    <span style="font-size: 0.7rem; color: var(--muted);"><?= (int) $row['total'] ?> mhs</span>
                                </div>
                                <div class="chart-track">
                                    <div class="chart-fill" style="width: <?= e((string) $percentage) ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="small-note mb-0">Belum ada program studi.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header">
                <h2>Akses Cepat</h2>
                <p class="section-subtitle mt-1">Menu administrasi yang sering digunakan</p>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <a href="<?= e(base_url('admin/mahasiswa_form.php')) ?>" class="mini-stat d-block h-100">
                            <i class="bi bi-person-plus text-primary mb-2 d-block" aria-hidden="true"></i>
                            <span class="mini-stat-value" style="font-size: .85rem;">Tambah Mahasiswa</span>
                            <span class="mini-stat-label">Buat akun &amp; data</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?= e(base_url('admin/dosen_form.php')) ?>" class="mini-stat d-block h-100">
                            <i class="bi bi-person-badge text-success mb-2 d-block" aria-hidden="true"></i>
                            <span class="mini-stat-value" style="font-size: .85rem;">Tambah Dosen</span>
                            <span class="mini-stat-label">Data pengajar</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?= e(base_url('admin/mata_kuliah_form.php')) ?>" class="mini-stat d-block h-100">
                            <i class="bi bi-journal-plus text-warning mb-2 d-block" aria-hidden="true"></i>
                            <span class="mini-stat-value" style="font-size: .85rem;">Mata Kuliah</span>
                            <span class="mini-stat-label">Tambah kurikulum</span>
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?= e(base_url('admin/jadwal_form.php')) ?>" class="mini-stat d-block h-100">
                            <i class="bi bi-calendar-plus text-purple mb-2 d-block" style="color:#7e22ce;" aria-hidden="true"></i>
                            <span class="mini-stat-value" style="font-size: .85rem;">Jadwal</span>
                            <span class="mini-stat-label">Atur kelas</span>
                        </a>
                    </div>
                </div>
                <div class="alert alert-warning mt-3 mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-hourglass-split" aria-hidden="true"></i>
                    <span><?= $pendingKrs ?> KRS menunggu persetujuan.</span>
                </div>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
