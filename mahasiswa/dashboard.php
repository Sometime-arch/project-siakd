<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_role('mahasiswa');

$mahasiswaId = (int) ($user['mahasiswa_id'] ?? 0);
if ($mahasiswaId <= 0) {
    logout_user();
    redirect('login.php');
}

$pageTitle = 'Dashboard';
$pageSubtitle = 'Ringkasan studi dan aktivitas akademik';
$activeMenu = 'dashboard';

$semesterAktif = semester_mahasiswa((int) $user['angkatan']);

$stmtProdi = db()->prepare('SELECT nama_prodi, kode_prodi FROM prodi WHERE id = :id LIMIT 1');
$stmtProdi->execute(['id' => (int) $user['mahasiswa_prodi_id']]);
$prodi = $stmtProdi->fetch() ?: ['nama_prodi' => '-', 'kode_prodi' => '-'];

$stmtKrs = db()->prepare(
    "SELECT k.id, k.status, k.created_at, k.semester, k.tahun_ajaran,
            mk.kode_mk, mk.nama_mk, mk.sks,
            n.nilai_akhir, n.nilai_huruf
     FROM krs k
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     LEFT JOIN nilai n ON n.krs_id = k.id
     WHERE k.mahasiswa_id = :mahasiswa_id
       AND k.semester = :semester
       AND k.tahun_ajaran = :tahun_ajaran
     ORDER BY FIELD(k.status, 'menunggu', 'disetujui', 'ditolak'), mk.kode_mk ASC"
);
$stmtKrs->execute([
    'mahasiswa_id' => $mahasiswaId,
    'semester' => $semesterAktif,
    'tahun_ajaran' => TAHUN_AJARAN_AKTIF,
]);
$krsList = $stmtKrs->fetchAll();

$approvedSks = 0;
$pendingSks = 0;
$approvedCourses = 0;
$pendingCourses = 0;
foreach ($krsList as $row) {
    if ($row['status'] === 'disetujui') {
        $approvedSks += (int) $row['sks'];
        $approvedCourses++;
    } elseif ($row['status'] === 'menunggu') {
        $pendingSks += (int) $row['sks'];
        $pendingCourses++;
    }
}

$stmtGrades = db()->prepare(
    "SELECT mk.sks, n.nilai_huruf
     FROM krs k
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     JOIN nilai n ON n.krs_id = k.id
     WHERE k.mahasiswa_id = :mahasiswa_id AND k.status = 'disetujui'"
);
$stmtGrades->execute(['mahasiswa_id' => $mahasiswaId]);
$gradeRows = $stmtGrades->fetchAll();

$gradedSks = 0;
$totalPoints = 0.0;
foreach ($gradeRows as $row) {
    $gradedSks += (int) $row['sks'];
    $totalPoints += nilai_point((string) $row['nilai_huruf']) * (int) $row['sks'];
}
$ipk = $gradedSks > 0 ? round($totalPoints / $gradedSks, 2) : null;

$stmtJadwal = db()->prepare(
    "SELECT j.hari, j.jam_mulai, j.jam_selesai, j.ruang,
            mk.kode_mk, mk.nama_mk, mk.sks, d.nama AS nama_dosen
     FROM krs k
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     JOIN jadwal j ON j.mata_kuliah_id = mk.id
     LEFT JOIN dosen d ON d.id = mk.dosen_id
     WHERE k.mahasiswa_id = :mahasiswa_id
       AND k.status = 'disetujui'
       AND k.semester = :semester
       AND k.tahun_ajaran = :tahun_ajaran
     ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), j.jam_mulai ASC"
);
$stmtJadwal->execute([
    'mahasiswa_id' => $mahasiswaId,
    'semester' => $semesterAktif,
    'tahun_ajaran' => TAHUN_AJARAN_AKTIF,
]);
$jadwalList = $stmtJadwal->fetchAll();

$stmtLatestGrades = db()->prepare(
    "SELECT mk.kode_mk, mk.nama_mk, mk.sks, n.nilai_akhir, n.nilai_huruf
     FROM nilai n
     JOIN krs k ON k.id = n.krs_id
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     WHERE k.mahasiswa_id = :mahasiswa_id
     ORDER BY k.tahun_ajaran DESC, k.semester DESC, k.id DESC
     LIMIT 5"
);
$stmtLatestGrades->execute(['mahasiswa_id' => $mahasiswaId]);
$latestGrades = $stmtLatestGrades->fetchAll();

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
        <h1>Dashboard Mahasiswa</h1>
        <p>Pantau rencana studi, nilai, dan jadwal kuliah Anda.</p>
    </div>
    <a href="<?= e(base_url('mahasiswa/krs.php')) ?>" class="btn btn-primary">
        <i class="bi bi-journal-plus me-1" aria-hidden="true"></i> Ambil KRS
    </a>
</div>

<section class="welcome-banner mb-4">
    <div class="position-relative" style="z-index: 1;">
        <span class="auth-eyebrow mb-2"><?= e($prodi['nama_prodi']) ?> &middot; Semester <?= $semesterAktif ?> &middot; <?= e(TAHUN_AJARAN_AKTIF) ?> <?= e(SEMESTER_AKTIF) ?></span>
        <h2><?= e($sapaan) ?>, <?= e($user['nama']) ?>.</h2>
        <p>
            Anda telah mengambil <strong><?= $approvedSks ?></strong> SKS yang disetujui
            <?php if ($pendingSks > 0): ?>
                dan <strong><?= $pendingSks ?></strong> SKS menunggu validasi
            <?php endif; ?>.
            <?php if ($ipk !== null): ?>
                IPK Anda saat ini <strong><?= e(number_format($ipk, 2, ',', '.')) ?></strong>.
            <?php else: ?>
                Nilai Anda akan tampil setelah dosen menginputnya.
            <?php endif; ?>
        </p>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a href="<?= e(base_url('mahasiswa/krs.php')) ?>" class="btn btn-light btn-sm">
                <i class="bi bi-journal-plus me-1" aria-hidden="true"></i> Kelola KRS
            </a>
            <a href="<?= e(base_url('mahasiswa/khs.php')) ?>" class="btn btn-outline-light btn-sm" style="border-color: rgba(255,255,255,.35); color:#fff;">
                <i class="bi bi-award me-1" aria-hidden="true"></i> Lihat KHS
            </a>
        </div>
    </div>
    <i class="bi bi-mortarboard banner-icon" aria-hidden="true"></i>
</section>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-journal-check" aria-hidden="true"></i></div>
            <p class="stat-label">SKS Disetujui</p>
            <p class="stat-value"><?= $approvedSks ?></p>
            <p class="stat-note"><i class="bi bi-check-circle me-1 text-success" aria-hidden="true"></i><?= $approvedCourses ?> mata kuliah aktif</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-amber"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
            <p class="stat-label">SKS Menunggu</p>
            <p class="stat-value"><?= $pendingSks ?></p>
            <p class="stat-note"><?= $pendingCourses ?> KRS menunggu validasi admin</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-green"><i class="bi bi-award" aria-hidden="true"></i></div>
            <p class="stat-label">Indeks Prestasi Kumulatif</p>
            <p class="stat-value"><?= $ipk !== null ? e(number_format($ipk, 2, ',', '.')) : '-' ?></p>
            <p class="stat-note">Dari <?= $gradedSks ?> SKS yang sudah dinilai</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-purple"><i class="bi bi-calendar3" aria-hidden="true"></i></div>
            <p class="stat-label">Jadwal Mingguan</p>
            <p class="stat-value"><?= count($jadwalList) ?></p>
            <p class="stat-note">Jadwal dari KRS yang disetujui</p>
        </div>
    </div>
</div>

<?php if ($pendingCourses > 0): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-4">
        <i class="bi bi-hourglass-split mt-1" aria-hidden="true"></i>
        <div>
            Ada <strong><?= $pendingCourses ?> KRS</strong> yang masih menunggu validasi administrator.
            Anda tetap dapat melihat statusnya di halaman <a href="<?= e(base_url('mahasiswa/krs.php')) ?>" class="alert-link">Ambil KRS</a>.
        </div>
    </div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div>
                    <h2>KRS Periode Aktif</h2>
                    <p class="section-subtitle mt-1">Semester <?= $semesterAktif ?> &middot; <?= e(TAHUN_AJARAN_AKTIF) ?> <?= e(SEMESTER_AKTIF) ?></p>
                </div>
                <a href="<?= e(base_url('mahasiswa/krs.php')) ?>" class="btn btn-outline-secondary btn-sm">Kelola</a>
            </div>
            <?php if ($krsList): ?>
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Mata Kuliah</th>
                                <th>SKS</th>
                                <th>Status</th>
                                <th>Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($krsList as $row): ?>
                                <tr>
                                    <td>
                                        <span class="cell-title"><?= e($row['nama_mk']) ?></span>
                                        <span class="cell-subtitle"><?= e($row['kode_mk']) ?></span>
                                    </td>
                                    <td><?= (int) $row['sks'] ?> SKS</td>
                                    <td><?= status_badge($row['status']) ?></td>
                                    <td>
                                        <?php if (!empty($row['nilai_huruf'])): ?>
                                            <span class="grade-pill <?= e(nilai_class((string) $row['nilai_huruf'])) ?>"><?= e($row['nilai_huruf']) ?></span>
                                        <?php else: ?>
                                            <span class="small-note">Belum dinilai</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-journal-x" aria-hidden="true"></i></div>
                    <h3>Belum ada KRS aktif</h3>
                    <p>Ambil mata kuliah untuk menyusun rencana studi semester ini.</p>
                    <a href="<?= e(base_url('mahasiswa/krs.php')) ?>" class="btn btn-primary btn-sm">Ambil KRS</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <h2>Informasi Akademik</h2>
                <p class="section-subtitle mt-1">Data profil dan periode studi</p>
            </div>
            <div class="card-body">
                <dl class="detail-list mb-0">
                    <div>
                        <dt>NIM</dt>
                        <dd><?= e($user['nim']) ?></dd>
                    </div>
                    <div>
                        <dt>Program Studi</dt>
                        <dd><?= e($prodi['nama_prodi']) ?></dd>
                    </div>
                    <div>
                        <dt>Angkatan</dt>
                        <dd><?= (int) $user['angkatan'] ?></dd>
                    </div>
                    <div>
                        <dt>Semester Aktif</dt>
                        <dd>Semester <?= $semesterAktif ?></dd>
                    </div>
                    <div>
                        <dt>Batas SKS</dt>
                        <dd><?= MAX_SKS ?> SKS per semester</dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd><span class="badge text-bg-success-subtle text-success-emphasis rounded-pill">Aktif</span></dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div>
                    <h2>Jadwal Mingguan</h2>
                    <p class="section-subtitle mt-1"><?= $hariIni !== '' ? 'Hari ini ' . e($hariIni) : 'Jadwal kuliah Anda' ?></p>
                </div>
                <a href="<?= e(base_url('mahasiswa/jadwal.php')) ?>" class="btn btn-outline-secondary btn-sm">Detail</a>
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
                                <div class="schedule-info flex-grow-1">
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
                        <p>Jadwal akan muncul setelah KRS Anda disetujui administrator.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card h-100">
            <div class="card-header d-flex align-items-center justify-content-between gap-2">
                <div>
                    <h2>Nilai Terbaru</h2>
                    <p class="section-subtitle mt-1">Nilai yang telah diinput dosen</p>
                </div>
                <a href="<?= e(base_url('mahasiswa/khs.php')) ?>" class="btn btn-outline-secondary btn-sm">KHS</a>
            </div>
            <div class="card-body">
                <?php if ($latestGrades): ?>
                    <?php foreach ($latestGrades as $index => $nilai): ?>
                        <div class="d-flex align-items-center justify-content-between gap-2 <?= $index < count($latestGrades) - 1 ? 'pb-3 mb-3 border-bottom' : '' ?>">
                            <div class="min-w-0">
                                <span class="cell-title"><?= e($nilai['nama_mk']) ?></span>
                                <span class="cell-subtitle"><?= e($nilai['kode_mk']) ?> &middot; <?= (int) $nilai['sks'] ?> SKS</span>
                            </div>
                            <div class="text-end">
                                <span class="grade-pill <?= e(nilai_class((string) $nilai['nilai_huruf'])) ?>"><?= e($nilai['nilai_huruf']) ?></span>
                                <span class="d-block small-note mt-1"><?= e(number_format((float) $nilai['nilai_akhir'], 2, ',', '.')) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state py-4">
                        <div class="empty-state-icon"><i class="bi bi-award" aria-hidden="true"></i></div>
                        <h3>Belum ada nilai</h3>
                        <p>Nilai akan tampil setelah dosen menginput dan menyimpannya.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
