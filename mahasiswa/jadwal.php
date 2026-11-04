<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_role('mahasiswa');

$mahasiswaId = (int) ($user['mahasiswa_id'] ?? 0);
if ($mahasiswaId <= 0) {
    logout_user();
    redirect('login.php');
}

$pageTitle = 'Jadwal Kuliah';
$pageSubtitle = 'Jadwal kelas periode aktif';
$activeMenu = 'jadwal';

$semesterAktif = semester_mahasiswa((int) $user['angkatan']);
$search = (string) query('q');
$hariFilter = (string) query('hari');
$hariList = daftar_hari();

if ($hariFilter !== '' && !in_array($hariFilter, $hariList, true)) {
    $hariFilter = '';
}

$conditions = [
    'k.mahasiswa_id = :mahasiswa_id',
    "k.status = 'disetujui'",
    'k.tahun_ajaran = :tahun_ajaran',
    'k.semester = :semester',
];
$params = [
    ':mahasiswa_id' => $mahasiswaId,
    ':tahun_ajaran' => TAHUN_AJARAN_AKTIF,
    ':semester' => $semesterAktif,
];

if ($search !== '') {
    $conditions[] = '(mk.kode_mk LIKE :search OR mk.nama_mk LIKE :search OR j.ruang LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($hariFilter !== '') {
    $conditions[] = 'j.hari = :hari';
    $params[':hari'] = $hariFilter;
}

$where = 'WHERE ' . implode(' AND ', $conditions);

$stmt = db()->prepare(
    "SELECT j.id, j.hari, j.jam_mulai, j.jam_selesai, j.ruang,
            mk.kode_mk, mk.nama_mk, mk.sks, mk.semester,
            d.nama AS nama_dosen
     FROM krs k
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     JOIN jadwal j ON j.mata_kuliah_id = mk.id
     LEFT JOIN dosen d ON d.id = mk.dosen_id
     $where
     ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), j.jam_mulai ASC"
);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->execute();
$jadwalRows = $stmt->fetchAll();

$jadwalByDay = [];
foreach ($jadwalRows as $row) {
    $jadwalByDay[$row['hari']][] = $row;
}

$totalJadwal = count($jadwalRows);
$totalSks = 0;
$courseCodes = [];
foreach ($jadwalRows as $row) {
    $totalSks += (int) $row['sks'];
    $courseCodes[$row['kode_mk']] = true;
}

$stmtPending = db()->prepare(
    "SELECT COUNT(*) FROM krs
     WHERE mahasiswa_id = :mahasiswa_id
       AND tahun_ajaran = :tahun_ajaran
       AND semester = :semester
       AND status = 'menunggu'"
);
$stmtPending->execute([
    'mahasiswa_id' => $mahasiswaId,
    'tahun_ajaran' => TAHUN_AJARAN_AKTIF,
    'semester' => $semesterAktif,
]);
$pendingCount = (int) $stmtPending->fetchColumn();

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
$hasFilter = $search !== '' || $hariFilter !== '';

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1>Jadwal Kuliah</h1>
        <p>Semester <?= $semesterAktif ?> &middot; <?= e(TAHUN_AJARAN_AKTIF) ?> <?= e(SEMESTER_AKTIF) ?> &middot; <?= count($courseCodes) ?> mata kuliah &middot; <?= $totalSks ?> SKS.</p>
    </div>
    <a href="<?= e(base_url('mahasiswa/krs.php')) ?>" class="btn btn-primary">
        <i class="bi bi-journal-plus me-1" aria-hidden="true"></i> Ambil KRS
    </a>
</div>

<?php if ($pendingCount > 0): ?>
    <div class="alert alert-warning d-flex align-items-start gap-2 mb-4">
        <i class="bi bi-hourglass-split mt-1" aria-hidden="true"></i>
        <div>
            Ada <strong><?= $pendingCount ?></strong> mata kuliah pada KRS Anda yang masih menunggu validasi administrator.
            Jadwalnya akan tampil setelah KRS disetujui.
        </div>
    </div>
<?php endif; ?>

<div class="card mb-4">
    <form class="filter-bar" method="get" action="<?= e(base_url('mahasiswa/jadwal.php')) ?>">
        <div class="search-box">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Cari mata kuliah atau ruang..." aria-label="Cari jadwal">
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <select class="form-select" name="hari" aria-label="Filter hari" style="min-width: 150px;">
                <option value="">Semua Hari</option>
                <?php foreach ($hariList as $hari): ?>
                    <option value="<?= e($hari) ?>" <?= $hariFilter === $hari ? 'selected' : '' ?>><?= e($hari) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline-secondary">
                <i class="bi bi-funnel me-1" aria-hidden="true"></i> Filter
            </button>
            <?php if ($hasFilter): ?>
                <a href="<?= e(base_url('mahasiswa/jadwal.php')) ?>" class="btn btn-light">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<?php if ($jadwalRows): ?>
    <div class="row g-4">
        <?php foreach ($hariList as $hari): ?>
            <?php if (!isset($jadwalByDay[$hari])): ?>
                <?php continue; ?>
            <?php endif; ?>
            <div class="col-xl-6">
                <div class="card h-100">
                    <div class="card-header d-flex align-items-center justify-content-between gap-2">
                        <div>
                            <h2><?= e($hari) ?></h2>
                            <p class="section-subtitle mt-1"><?= count($jadwalByDay[$hari]) ?> jadwal</p>
                        </div>
                        <?php if ($hari === $hariIni): ?>
                            <span class="badge text-bg-primary rounded-pill">Hari ini</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <div class="schedule-list">
                            <?php foreach ($jadwalByDay[$hari] as $jadwal): ?>
                                <div class="schedule-item">
                                    <div class="schedule-time">
                                        <strong><?= e(jam_id($jadwal['jam_mulai'])) ?></strong>
                                        <span><?= e(jam_id($jadwal['jam_selesai'])) ?></span>
                                    </div>
                                    <div class="schedule-info flex-grow-1">
                                        <strong><?= e($jadwal['nama_mk']) ?></strong>
                                        <span><?= e($jadwal['kode_mk']) ?> &middot; <?= (int) $jadwal['sks'] ?> SKS &middot; Semester <?= (int) $jadwal['semester'] ?></span>
                                        <span><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><?= e($jadwal['ruang']) ?> &middot; <?= e($jadwal['nama_dosen'] ?: 'Dosen belum ditentukan') ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-calendar-x" aria-hidden="true"></i></div>
            <h3><?= $hasFilter ? 'Jadwal tidak ditemukan' : 'Belum ada jadwal kuliah' ?></h3>
            <p><?= $hasFilter ? 'Coba ubah kata kunci atau filter hari, lalu tampilkan kembali.' : 'Jadwal akan muncul setelah Anda memiliki KRS yang disetujui pada periode aktif.' ?></p>
            <?php if ($hasFilter): ?>
                <a href="<?= e(base_url('mahasiswa/jadwal.php')) ?>" class="btn btn-outline-primary btn-sm">Reset Filter</a>
            <?php else: ?>
                <a href="<?= e(base_url('mahasiswa/krs.php')) ?>" class="btn btn-primary btn-sm">Ambil KRS</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
