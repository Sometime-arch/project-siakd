<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_role('dosen');

$dosenId = (int) ($user['dosen_id'] ?? 0);
if ($dosenId <= 0) {
    logout_user();
    redirect('login.php');
}

$pageTitle = 'Jadwal Mengajar';
$pageSubtitle = 'Jadwal kuliah mingguan Anda';
$activeMenu = 'jadwal';

$search = (string) query('q');
$hariFilter = (string) query('hari');
$hariList = daftar_hari();

if ($hariFilter !== '' && !in_array($hariFilter, $hariList, true)) {
    $hariFilter = '';
}

$conditions = ['mk.dosen_id = :dosen_id'];
$params = [':dosen_id' => $dosenId];

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
            mk.kode_mk, mk.nama_mk, mk.sks, mk.semester, p.nama_prodi
     FROM jadwal j
     JOIN mata_kuliah mk ON mk.id = j.mata_kuliah_id
     JOIN prodi p ON p.id = mk.prodi_id
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
$totalMataKuliah = count(array_unique(array_column($jadwalRows, 'kode_mk')));

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
        <h1>Jadwal Mengajar</h1>
        <p><?= $totalJadwal ?> jadwal untuk <?= $totalMataKuliah ?> mata kuliah pada periode <?= e(TAHUN_AJARAN_AKTIF) ?> <?= e(SEMESTER_AKTIF) ?>.</p>
    </div>
    <a href="<?= e(base_url('dosen/mata_kuliah.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-journal-bookmark me-1" aria-hidden="true"></i> Mata Kuliah Saya
    </a>
</div>

<div class="card mb-4">
    <form class="filter-bar" method="get" action="<?= e(base_url('dosen/jadwal.php')) ?>">
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
                <a href="<?= e(base_url('dosen/jadwal.php')) ?>" class="btn btn-light">Reset</a>
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
                                        <span><i class="bi bi-geo-alt me-1" aria-hidden="true"></i><?= e($jadwal['ruang']) ?> &middot; <?= e($jadwal['nama_prodi']) ?></span>
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
            <h3><?= $hasFilter ? 'Jadwal tidak ditemukan' : 'Belum ada jadwal mengajar' ?></h3>
            <p><?= $hasFilter ? 'Coba ubah kata kunci atau filter hari, lalu tampilkan kembali.' : 'Jadwal mengajar akan muncul setelah administrator menambahkan jadwal untuk mata kuliah Anda.' ?></p>
            <?php if ($hasFilter): ?>
                <a href="<?= e(base_url('dosen/jadwal.php')) ?>" class="btn btn-outline-primary btn-sm">Reset Filter</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
