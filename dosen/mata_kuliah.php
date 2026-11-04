<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_role('dosen');

$dosenId = (int) ($user['dosen_id'] ?? 0);
if ($dosenId <= 0) {
    logout_user();
    redirect('login.php');
}

$pageTitle = 'Mata Kuliah Saya';
$pageSubtitle = 'Daftar kelas yang Anda ampu';
$activeMenu = 'mata_kuliah';

$search = (string) query('q');
$page = current_page();
$perPage = 10;

$conditions = ['mk.dosen_id = :dosen_id'];
$params = [':dosen_id' => $dosenId];

if ($search !== '') {
    $conditions[] = '(mk.kode_mk LIKE :search OR mk.nama_mk LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

$where = 'WHERE ' . implode(' AND ', $conditions);

$countStmt = db()->prepare("SELECT COUNT(*) FROM mata_kuliah mk $where");
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$totalData = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalData / $perPage));
$page = min($page, $totalPages);
$offset = page_offset($page, $perPage);

$stmt = db()->prepare(
    "SELECT mk.id, mk.kode_mk, mk.nama_mk, mk.sks, mk.semester, p.nama_prodi,
            (SELECT COUNT(DISTINCT k.mahasiswa_id)
             FROM krs k
             WHERE k.mata_kuliah_id = mk.id AND k.status = 'disetujui') AS total_mahasiswa,
            (SELECT COUNT(*)
             FROM krs k
             JOIN nilai n ON n.krs_id = k.id
             WHERE k.mata_kuliah_id = mk.id AND k.status = 'disetujui') AS total_dinilai,
            (SELECT GROUP_CONCAT(CONCAT(j.hari, ' ', DATE_FORMAT(j.jam_mulai, '%H:%i')) ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), j.jam_mulai SEPARATOR ' | ')
             FROM jadwal j
             WHERE j.mata_kuliah_id = mk.id) AS jadwal
     FROM mata_kuliah mk
     JOIN prodi p ON p.id = mk.prodi_id
     $where
     ORDER BY mk.semester ASC, mk.kode_mk ASC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$mataKuliahList = $stmt->fetchAll();

$stmtSummary = db()->prepare(
    'SELECT COUNT(*) AS total_mk, COALESCE(SUM(sks), 0) AS total_sks
     FROM mata_kuliah
     WHERE dosen_id = :dosen_id'
);
$stmtSummary->execute(['dosen_id' => $dosenId]);
$summary = $stmtSummary->fetch() ?: ['total_mk' => 0, 'total_sks' => 0];

$paginationParams = array_filter(['q' => $search], static fn ($value) => $value !== null && $value !== '');

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1>Mata Kuliah Saya</h1>
        <p>Anda mengampu <?= (int) $summary['total_mk'] ?> mata kuliah dengan total <?= (int) $summary['total_sks'] ?> SKS.</p>
    </div>
    <a href="<?= e(base_url('dosen/nilai.php')) ?>" class="btn btn-primary">
        <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Input Nilai
    </a>
</div>

<div class="card">
    <form class="filter-bar" method="get" action="<?= e(base_url('dosen/mata_kuliah.php')) ?>">
        <div class="search-box">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Cari kode atau nama mata kuliah..." aria-label="Cari mata kuliah">
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <button type="submit" class="btn btn-outline-secondary">
                <i class="bi bi-search me-1" aria-hidden="true"></i> Cari
            </button>
            <?php if ($search !== ''): ?>
                <a href="<?= e(base_url('dosen/mata_kuliah.php')) ?>" class="btn btn-light">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($mataKuliahList): ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Mata Kuliah</th>
                        <th>Program Studi</th>
                        <th>SKS / Semester</th>
                        <th>Mahasiswa</th>
                        <th>Progres Nilai</th>
                        <th>Jadwal</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mataKuliahList as $row): ?>
                        <?php
                        $jumlahMahasiswa = (int) $row['total_mahasiswa'];
                        $jumlahDinilai = (int) $row['total_dinilai'];
                        $progress = $jumlahMahasiswa > 0 ? round(($jumlahDinilai / $jumlahMahasiswa) * 100) : 0;
                        ?>
                        <tr>
                            <td>
                                <span class="cell-title"><?= e($row['nama_mk']) ?></span>
                                <span class="cell-subtitle"><?= e($row['kode_mk']) ?></span>
                            </td>
                            <td><?= e($row['nama_prodi']) ?></td>
                            <td>
                                <span class="cell-title"><?= (int) $row['sks'] ?> SKS</span>
                                <span class="cell-subtitle">Semester <?= (int) $row['semester'] ?></span>
                            </td>
                            <td>
                                <span class="cell-title"><?= $jumlahMahasiswa ?></span>
                                <span class="cell-subtitle">KRS disetujui</span>
                            </td>
                            <td style="min-width: 130px;">
                                <div class="d-flex justify-content-between mb-1">
                                    <span class="small-note"><?= $jumlahDinilai ?>/<?= $jumlahMahasiswa ?></span>
                                    <span class="small-note"><?= $progress ?>%</span>
                                </div>
                                <div class="progress" role="progressbar" aria-label="Progres nilai <?= e($row['nama_mk']) ?>" aria-valuenow="<?= $progress ?>" aria-valuemin="0" aria-valuemax="100" style="height: 6px;">
                                    <div class="progress-bar" style="width: <?= $progress ?>%;"></div>
                                </div>
                            </td>
                            <td>
                                <?php if ($row['jadwal']): ?>
                                    <span class="cell-subtitle"><?= e($row['jadwal']) ?></span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary-subtle text-secondary-emphasis rounded-pill">Belum dijadwalkan</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= e(base_url('dosen/nilai.php?mata_kuliah_id=' . (int) $row['id'])) ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil-square me-1" aria-hidden="true"></i> Nilai
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top">
            <span class="small-note">
                Menampilkan <?= count($mataKuliahList) ?> dari <?= number_format($totalData, 0, ',', '.') ?> mata kuliah
            </span>
            <?= render_pagination($page, $totalPages, $paginationParams) ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-journal-x" aria-hidden="true"></i></div>
            <h3><?= $search !== '' ? 'Mata kuliah tidak ditemukan' : 'Belum ada mata kuliah' ?></h3>
            <p><?= $search !== '' ? 'Coba gunakan kata kunci lain atau reset pencarian.' : 'Mata kuliah yang ditugaskan administrator akan tampil di sini.' ?></p>
            <?php if ($search !== ''): ?>
                <a href="<?= e(base_url('dosen/mata_kuliah.php')) ?>" class="btn btn-outline-primary btn-sm">Reset Pencarian</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
