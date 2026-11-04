<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_role('dosen');

$dosenId = (int) ($user['dosen_id'] ?? 0);
if ($dosenId <= 0) {
    logout_user();
    redirect('login.php');
}

$pageTitle = 'Dashboard';
$pageSubtitle = 'Ringkasan aktivitas mengajar Anda';
$activeMenu = 'dashboard';

$stmtTotalMk = db()->prepare('SELECT COUNT(*) FROM mata_kuliah WHERE dosen_id = :dosen_id');
$stmtTotalMk->execute(['dosen_id' => $dosenId]);
$totalMk = (int) $stmtTotalMk->fetchColumn();

$stmtTotalMahasiswa = db()->prepare(
    "SELECT COUNT(DISTINCT k.mahasiswa_id)
     FROM krs k
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     WHERE mk.dosen_id = :dosen_id AND k.status = 'disetujui'"
);
$stmtTotalMahasiswa->execute(['dosen_id' => $dosenId]);
$totalMahasiswa = (int) $stmtTotalMahasiswa->fetchColumn();

$stmtBelumDinilai = db()->prepare(
    "SELECT COUNT(*)
     FROM krs k
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     LEFT JOIN nilai n ON n.krs_id = k.id
     WHERE mk.dosen_id = :dosen_id AND k.status = 'disetujui' AND n.id IS NULL"
);
$stmtBelumDinilai->execute(['dosen_id' => $dosenId]);
$belumDinilai = (int) $stmtBelumDinilai->fetchColumn();

$stmtRataNilai = db()->prepare(
    "SELECT COUNT(n.id) AS total_nilai, ROUND(AVG(n.nilai_akhir), 2) AS rata_nilai
     FROM nilai n
     JOIN krs k ON k.id = n.krs_id
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     WHERE mk.dosen_id = :dosen_id"
);
$stmtRataNilai->execute(['dosen_id' => $dosenId]);
$nilaiSummary = $stmtRataNilai->fetch() ?: ['total_nilai' => 0, 'rata_nilai' => null];
$totalNilai = (int) ($nilaiSummary['total_nilai'] ?? 0);
$rataNilai = $nilaiSummary['rata_nilai'] !== null ? (float) $nilaiSummary['rata_nilai'] : null;

$stmtCourses = db()->prepare(
    "SELECT mk.id, mk.kode_mk, mk.nama_mk, mk.sks, mk.semester, p.nama_prodi,
            COUNT(DISTINCT CASE WHEN k.status = 'disetujui' THEN k.mahasiswa_id END) AS total_mahasiswa,
            COUNT(DISTINCT CASE WHEN k.status = 'disetujui' AND n.id IS NOT NULL THEN k.id END) AS total_dinilai
     FROM mata_kuliah mk
     JOIN prodi p ON p.id = mk.prodi_id
     LEFT JOIN krs k ON k.mata_kuliah_id = mk.id
     LEFT JOIN nilai n ON n.krs_id = k.id
     WHERE mk.dosen_id = :dosen_id
     GROUP BY mk.id, mk.kode_mk, mk.nama_mk, mk.sks, mk.semester, p.nama_prodi
     ORDER BY mk.semester ASC, mk.kode_mk ASC"
);
$stmtCourses->execute(['dosen_id' => $dosenId]);
$courses = $stmtCourses->fetchAll();

$stmtLatestKrs = db()->prepare(
    "SELECT k.id, k.status, k.created_at, k.semester, k.tahun_ajaran,
            m.nim, m.nama AS nama_mahasiswa, mk.kode_mk, mk.nama_mk
     FROM krs k
     JOIN mahasiswa m ON m.id = k.mahasiswa_id
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     WHERE mk.dosen_id = :dosen_id
     ORDER BY k.created_at DESC
     LIMIT 6"
);
$stmtLatestKrs->execute(['dosen_id' => $dosenId]);
$latestKrs = $stmtLatestKrs->fetchAll();

$stmtJadwal = db()->prepare(
    "SELECT j.hari, j.jam_mulai, j.jam_selesai, j.ruang, mk.kode_mk, mk.nama_mk
     FROM jadwal j
     JOIN mata_kuliah mk ON mk.id = j.mata_kuliah_id
     WHERE mk.dosen_id = :dosen_id
     ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), j.jam_mulai ASC"
);
$stmtJadwal->execute(['dosen_id' => $dosenId]);
$jadwalList = $stmtJadwal->fetchAll();

$stmtGradeRows = db()->prepare(
    "SELECT n.nilai_huruf, COUNT(*) AS total
     FROM nilai n
     JOIN krs k ON k.id = n.krs_id
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     WHERE mk.dosen_id = :dosen_id
     GROUP BY n.nilai_huruf"
);
$stmtGradeRows->execute(['dosen_id' => $dosenId]);
$gradeCounts = [];
$totalGrades = 0;
foreach ($stmtGradeRows->fetchAll() as $row) {
    $gradeCounts[$row['nilai_huruf']] = (int) $row['total'];
    $totalGrades += (int) $row['total'];
}
$maxGrade = $gradeCounts ? max($gradeCounts) : 1;

$hariMap = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu',
];
$hariIni = $hariMap[date('l')] ?? '';
$jam = (int) date('G');
$sapaan = $jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam'));

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1>Dashboard Dosen</h1>
        <p>Pantau kelas, jadwal mengajar, dan progres penilaian mahasiswa.</p>
    </div>
    <a href="<?= e(base_url('dosen/nilai.php')) ?>" class="btn btn-primary">
        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Input Nilai
    </a>
</div>

<section class="welcome-banner mb-4">
    <div class="position-relative" style="z-index: 1;">
        <span class="auth-eyebrow mb-2">Periode <?= e(TAHUN_AJARAN_AKTIF) ?> &middot; <?= e(SEMESTER_AKTIF) ?></span>
        <h2><?= e($sapaan) ?>, <?= e($user['nama']) ?>.</h2>
        <p>
            Anda mengampu <strong><?= $totalMk ?></strong> mata kuliah dengan
            <strong><?= $totalMahasiswa ?></strong> mahasiswa aktif.
            <?php if ($belumDinilai > 0): ?>
                Masih ada <strong><?= $belumDinilai ?></strong> nilai yang perlu diisi.
            <?php else: ?>
                Semua nilai mahasiswa pada KRS yang disetujui sudah lengkap.
            <?php endif; ?>
        </p>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a href="<?= e(base_url('dosen/nilai.php')) ?>" class="btn btn-light btn-sm">
                <i class="bi bi-clipboard-check me-1" aria-hidden="true"></i> Kelola Nilai
            </a>
            <a href="<?= e(base_url('dosen/jadwal.php')) ?>" class="btn btn-outline-light btn-sm" style="border-color: rgba(255,255,255,.35); color:#fff;">
                <i class="bi bi-calendar3 me-1" aria-hidden="true"></i> Jadwal Mengajar
            </a>
        </div>
    </div>
    <i class="bi bi-easel2 banner-icon" aria-hidden="true"></i>
</section>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-journal-bookmark" aria-hidden="true"></i></div>
            <p class="stat-label">Mata Kuliah Diampu</p>
            <p class="stat-value"><?= number_format($totalMk, 0, ',', '.') ?></p>
            <p class="stat-note"><i class="bi bi-calendar3 me-1 text-primary" aria-hidden="true"></i>Semester <?= e(SEMESTER_AKTIF) ?> <?= e(TAHUN_AJARAN_AKTIF) ?></p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-green"><i class="bi bi-people" aria-hidden="true"></i></div>
            <p class="stat-label">Mahasiswa Aktif</p>
            <p class="stat-value"><?= number_format($totalMahasiswa, 0, ',', '.') ?></p>
            <p class="stat-note"><i class="bi bi-check-circle me-1 text-success" aria-hidden="true"></i>KRS disetujui pada kelas Anda</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-amber"><i class="bi bi-pencil-square" aria-hidden="true"></i></div>
            <p class="stat-label">Nilai Belum Diisi</p>
            <p class="stat-value"><?= number_format($belumDinilai, 0, ',', '.') ?></p>
            <p class="stat-note"><i class="bi bi-hourglass-split me-1" style="color:#b45309;" aria-hidden="true"></i>Mahasiswa menunggu penilaian</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-purple"><i class="bi bi-award" aria-hidden="true"></i></div>
            <p class="stat-label">Rata-rata Nilai</p>
            <p class="stat-value"><?= $rataNilai !== null ? e(number_format($rataNilai, 2, ',', '.')) : '-' ?></p>
            <p class="stat-note"><i class="bi bi-journal-check me-1" style="color:#7e22ce;" aria-hidden="true"></i><?= number_format($totalNilai, 0, ',', '.') ?> nilai telah diinput</p>
        </div>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div>
                    <h2>Kelas yang Diampu</h2>
                    <p class="section-subtitle mt-1">Progres penilaian per mata kuliah</p>
                </div>
                <a href="<?= e(base_url('dosen/mata_kuliah.php')) ?>" class="btn btn-outline-secondary btn-sm">Lihat Semua</a>
            </div>
            <div class="card-body">
                <?php if ($courses): ?>
                    <?php foreach ($courses as $index => $course): ?>
                        <?php
                        $jumlahMahasiswa = (int) $course['total_mahasiswa'];
                        $jumlahDinilai = (int) $course['total_dinilai'];
                        $progress = $jumlahMahasiswa > 0 ? round(($jumlahDinilai / $jumlahMahasiswa) * 100) : 0;
                        ?>
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 <?= $index < count($courses) - 1 ? 'pb-3 mb-3 border-bottom' : '' ?>">
                            <div class="min-w-0">
                                <span class="cell-title"><?= e($course['nama_mk']) ?></span>
                                <span class="cell-subtitle"><?= e($course['kode_mk']) ?> &middot; <?= (int) $course['sks'] ?> SKS &middot; Semester <?= (int) $course['semester'] ?> &middot; <?= e($course['nama_prodi']) ?></span>
                            </div>
                            <div style="min-width: 190px;">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small-note"><?= $jumlahDinilai ?>/<?= $jumlahMahasiswa ?> dinilai</span>
                                    <span class="small-note"><?= $progress ?>%</span>
                                </div>
                                <div class="progress" role="progressbar" aria-label="Progres penilaian <?= e($course['nama_mk']) ?>" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100" style="height: 6px;">
                                    <div class="progress-bar" style="width: <?= $progress ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-journal-x" aria-hidden="true"></i></div>
                        <h3>Belum ada mata kuliah</h3>
                        <p>Mata kuliah yang ditugaskan administrator akan tampil di sini.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div>
                    <h2>Jadwal Mingguan</h2>
                    <p class="section-subtitle mt-1"><?= $hariIni !== '' ? 'Hari ini ' . e($hariIni) : 'Jadwal mengajar Anda' ?></p>
                </div>
                <a href="<?= e(base_url('dosen/jadwal.php')) ?>" class="btn btn-outline-secondary btn-sm">Detail</a>
            </div>
            <div class="card-body">
                <?php if ($jadwalList): ?>
                    <div class="schedule-list">
                        <?php foreach (array_slice($jadwalList, 0, 5) as $jadwal): ?>
                            <div class="schedule-item">
                                <div class="schedule-time">
                                    <strong><?= e(jam_id($jadwal['jam_mulai'])) ?></strong>
                                    <span><?= e($jadwal['hari']) ?></span>
                                </div>
                                <div class="schedule-info">
                                    <strong><?= e($jadwal['nama_mk']) ?></strong>
                                    <span><?= e($jadwal['kode_mk']) ?> &middot; <?= e($jadwal['ruang']) ?></span>
                                </div>
                                <?php if ($jadwal['hari'] === $hariIni): ?>
                                    <span class="badge text-bg-primary align-self-center ms-auto">Hari ini</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-calendar-x" aria-hidden="true"></i></div>
                        <h3>Belum ada jadwal</h3>
                        <p>Jadwal mengajar akan muncul setelah administrator menambahkannya.</p>
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
                <h2>Pengajuan KRS Terbaru</h2>
                <p class="section-subtitle mt-1">Aktivitas KRS pada mata kuliah yang Anda ampu</p>
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
                                        <span class="cell-subtitle"><?= e($row['nim']) ?></span>
                                    </td>
                                    <td>
                                        <span class="cell-title"><?= e($row['nama_mk']) ?></span>
                                        <span class="cell-subtitle"><?= e($row['kode_mk']) ?></span>
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
                    <p>Pengajuan KRS mahasiswa pada kelas Anda akan muncul di sini.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header">
                <h2>Distribusi Nilai Anda</h2>
                <p class="section-subtitle mt-1"><?= number_format($totalGrades, 0, ',', '.') ?> nilai telah diinput</p>
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
                        <strong class="text-navy"><?= $rataNilai !== null ? e(number_format($rataNilai, 2, ',', '.')) : '-' ?></strong>
                    </div>
                <?php else: ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-bar-chart" aria-hidden="true"></i></div>
                        <h3>Belum ada nilai</h3>
                        <p>Statistik akan tampil setelah Anda menginput nilai mahasiswa.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
