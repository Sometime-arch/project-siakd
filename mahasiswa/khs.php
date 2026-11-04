<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_role('mahasiswa');

$mahasiswaId = (int) ($user['mahasiswa_id'] ?? 0);
if ($mahasiswaId <= 0) {
    logout_user();
    redirect('login.php');
}

$pageTitle = 'Kartu Hasil Studi';
$pageSubtitle = 'Rekap nilai dan indeks prestasi';
$activeMenu = 'khs';

$stmt = db()->prepare(
    "SELECT k.id, k.semester, k.tahun_ajaran,
            mk.kode_mk, mk.nama_mk, mk.sks, d.nama AS nama_dosen,
            n.nilai_akhir, n.nilai_huruf
     FROM krs k
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     LEFT JOIN dosen d ON d.id = mk.dosen_id
     LEFT JOIN nilai n ON n.krs_id = k.id
     WHERE k.mahasiswa_id = :mahasiswa_id AND k.status = 'disetujui'
     ORDER BY k.tahun_ajaran DESC, k.semester DESC, mk.kode_mk ASC"
);
$stmt->execute(['mahasiswa_id' => $mahasiswaId]);
$rows = $stmt->fetchAll();

$groups = [];
$totalSks = 0;
$totalCourses = 0;
$gradedSks = 0;
$totalPoints = 0.0;

foreach ($rows as $row) {
    $key = $row['tahun_ajaran'] . '|' . $row['semester'];

    if (!isset($groups[$key])) {
        $groups[$key] = [
            'semester' => (int) $row['semester'],
            'tahun_ajaran' => $row['tahun_ajaran'],
            'rows' => [],
            'total_sks' => 0,
            'graded_sks' => 0,
            'points' => 0.0,
            'ips' => null,
        ];
    }

    $groups[$key]['rows'][] = $row;
    $groups[$key]['total_sks'] += (int) $row['sks'];
    $totalSks += (int) $row['sks'];
    $totalCourses++;

    if (!empty($row['nilai_huruf'])) {
        $point = nilai_point((string) $row['nilai_huruf']);
        $groups[$key]['graded_sks'] += (int) $row['sks'];
        $groups[$key]['points'] += $point * (int) $row['sks'];
        $gradedSks += (int) $row['sks'];
        $totalPoints += $point * (int) $row['sks'];
    }
}

foreach ($groups as $key => $group) {
    $groups[$key]['ips'] = $group['graded_sks'] > 0
        ? round($group['points'] / $group['graded_sks'], 2)
        : null;
}

$ipk = $gradedSks > 0 ? round($totalPoints / $gradedSks, 2) : null;
$predikat = 'Belum ada nilai';
if ($ipk !== null) {
    $predikat = match (true) {
        $ipk >= 3.5 => 'Sangat Memuaskan',
        $ipk >= 3.0 => 'Memuaskan',
        $ipk >= 2.5 => 'Cukup',
        default => 'Perlu Perbaikan',
    };
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1>Kartu Hasil Studi</h1>
        <p>Rekap mata kuliah dan nilai dari KRS yang telah disetujui.</p>
    </div>
    <div class="d-flex flex-wrap gap-2 no-print">
        <a href="<?= e(base_url('mahasiswa/krs.php')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-journal-plus me-1" aria-hidden="true"></i> Ambil KRS
        </a>
        <button type="button" class="btn btn-primary" onclick="window.print()">
            <i class="bi bi-printer me-1" aria-hidden="true"></i> Cetak
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-award" aria-hidden="true"></i></div>
            <p class="stat-label">Indeks Prestasi Kumulatif</p>
            <p class="stat-value"><?= $ipk !== null ? e(number_format($ipk, 2, ',', '.')) : '-' ?></p>
            <p class="stat-note"><?= e($predikat) ?></p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-green"><i class="bi bi-journal-check" aria-hidden="true"></i></div>
            <p class="stat-label">Total SKS</p>
            <p class="stat-value"><?= $totalSks ?></p>
            <p class="stat-note"><?= $gradedSks ?> SKS sudah memiliki nilai</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-amber"><i class="bi bi-journals" aria-hidden="true"></i></div>
            <p class="stat-label">Total Mata Kuliah</p>
            <p class="stat-value"><?= $totalCourses ?></p>
            <p class="stat-note">Dari seluruh KRS disetujui</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-purple"><i class="bi bi-graph-up-arrow" aria-hidden="true"></i></div>
            <p class="stat-label">Predikat</p>
            <p class="stat-value" style="font-size: 1.2rem;"><?= e($predikat) ?></p>
            <p class="stat-note">Berdasarkan IPK saat ini</p>
        </div>
    </div>
</div>

<div class="alert alert-info d-flex align-items-start gap-2 mb-4">
    <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
    <div>
        <strong>Perhitungan IPS dan IPK.</strong>
        IPS dihitung dari bobot nilai huruf dikalikan SKS, dibagi total SKS yang sudah dinilai pada semester tersebut.
        Mata kuliah yang belum memiliki nilai tidak diikutkan dalam perhitungan.
    </div>
</div>

<?php if ($groups): ?>
    <?php foreach ($groups as $group): ?>
        <div class="card mb-4">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h2>Semester <?= (int) $group['semester'] ?></h2>
                    <p class="section-subtitle mt-1">Tahun Ajaran <?= e($group['tahun_ajaran']) ?> &middot; <?= count($group['rows']) ?> mata kuliah &middot; <?= (int) $group['total_sks'] ?> SKS</p>
                </div>
                <div class="text-end">
                    <span class="badge text-bg-primary rounded-pill px-3 py-2">
                        IPS <?= $group['ips'] !== null ? e(number_format((float) $group['ips'], 2, ',', '.')) : '-' ?>
                    </span>
                    <?php if ((int) $group['graded_sks'] < (int) $group['total_sks']): ?>
                        <p class="small-note mb-0 mt-1"><?= (int) $group['graded_sks'] ?>/<?= (int) $group['total_sks'] ?> SKS sudah dinilai</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Mata Kuliah</th>
                            <th>SKS</th>
                            <th>Nilai Angka</th>
                            <th>Huruf</th>
                            <th>Mutu</th>
                            <th>Dosen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($group['rows'] as $row): ?>
                            <?php $sudahDinilai = !empty($row['nilai_huruf']); ?>
                            <tr>
                                <td><span class="cell-subtitle"><?= e($row['kode_mk']) ?></span></td>
                                <td><span class="cell-title"><?= e($row['nama_mk']) ?></span></td>
                                <td><?= (int) $row['sks'] ?> SKS</td>
                                <td>
                                    <?php if ($sudahDinilai): ?>
                                        <strong class="text-navy"><?= e(number_format((float) $row['nilai_akhir'], 2, ',', '.')) ?></strong>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sudahDinilai): ?>
                                        <span class="grade-pill <?= e(nilai_class((string) $row['nilai_huruf'])) ?>"><?= e($row['nilai_huruf']) ?></span>
                                    <?php else: ?>
                                        <span class="badge text-bg-warning-subtle text-warning-emphasis rounded-pill">Belum dinilai</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $sudahDinilai ? e(number_format(nilai_point((string) $row['nilai_huruf']), 1, ',', '.')) : '<span class="text-muted">&mdash;</span>' ?>
                                </td>
                                <td><span class="cell-subtitle"><?= e($row['nama_dosen'] ?: '-') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-award" aria-hidden="true"></i></div>
            <h3>KHS belum tersedia</h3>
            <p>KHS akan tampil setelah Anda memiliki KRS yang disetujui oleh administrator.</p>
            <a href="<?= e(base_url('mahasiswa/krs.php')) ?>" class="btn btn-primary btn-sm">Ambil KRS</a>
        </div>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
