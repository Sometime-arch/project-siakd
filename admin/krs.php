<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pageTitle = 'Validasi KRS';
$pageSubtitle = 'Tinjau pengajuan rencana studi mahasiswa';
$activeMenu = 'krs';

$statusFilter = (string) query('status');
if (!in_array($statusFilter, ['menunggu', 'disetujui', 'ditolak'], true)) {
    $statusFilter = '';
}
$search = (string) query('q');
$page = current_page();
$perPage = 10;

$statusCounts = ['menunggu' => 0, 'disetujui' => 0, 'ditolak' => 0];
foreach (db()->query('SELECT status, COUNT(*) AS total FROM krs GROUP BY status')->fetchAll() as $row) {
    $statusCounts[$row['status']] = (int) $row['total'];
}
$totalKrs = array_sum($statusCounts);

$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(m.nim LIKE :search OR m.nama LIKE :search OR mk.nama_mk LIKE :search OR mk.kode_mk LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if ($statusFilter !== '') {
    $conditions[] = 'k.status = :status';
    $params[':status'] = $statusFilter;
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = db()->prepare(
    "SELECT COUNT(*) FROM krs k
     JOIN mahasiswa m ON m.id = k.mahasiswa_id
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     $where"
);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value, PDO::PARAM_STR);
}
$countStmt->execute();
$totalData = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalData / $perPage));
$page = min($page, $totalPages);
$offset = page_offset($page, $perPage);

$stmt = db()->prepare(
    "SELECT k.id, k.semester, k.tahun_ajaran, k.status, k.created_at,
            m.nim, m.nama AS nama_mahasiswa,
            mk.kode_mk, mk.nama_mk, mk.sks,
            p.nama_prodi
     FROM krs k
     JOIN mahasiswa m ON m.id = k.mahasiswa_id
     JOIN prodi p ON p.id = m.prodi_id
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     $where
     ORDER BY FIELD(k.status, 'menunggu', 'disetujui', 'ditolak'), k.created_at DESC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$krsList = $stmt->fetchAll();

$paginationParams = array_filter(['q' => $search, 'status' => $statusFilter], static fn ($value) => $value !== '');

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1>Validasi KRS</h1>
        <p>Setujui atau tolak pengajuan KRS. Mahasiswa hanya dapat menghapus KRS yang belum disetujui.</p>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-journal-check" aria-hidden="true"></i></div>
            <div><span class="stat-label">Total KRS</span><span class="stat-value"><?= $totalKrs ?></span></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-amber"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
            <div><span class="stat-label">Menunggu</span><span class="stat-value"><?= $statusCounts['menunggu'] ?></span></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-green"><i class="bi bi-check2-circle" aria-hidden="true"></i></div>
            <div><span class="stat-label">Disetujui</span><span class="stat-value"><?= $statusCounts['disetujui'] ?></span></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-red"><i class="bi bi-x-circle" aria-hidden="true"></i></div>
            <div><span class="stat-label">Ditolak</span><span class="stat-value"><?= $statusCounts['ditolak'] ?></span></div>
        </div>
    </div>
</div>

<div class="card">
    <form class="filter-bar" method="get" action="<?= e(base_url('admin/krs.php')) ?>">
        <div class="search-box">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Cari NIM, mahasiswa, atau mata kuliah..." aria-label="Cari KRS">
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <select class="form-select" name="status" aria-label="Filter status" style="min-width: 160px;">
                <option value="">Semua Status</option>
                <option value="menunggu" <?= $statusFilter === 'menunggu' ? 'selected' : '' ?>>Menunggu</option>
                <option value="disetujui" <?= $statusFilter === 'disetujui' ? 'selected' : '' ?>>Disetujui</option>
                <option value="ditolak" <?= $statusFilter === 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
            </select>
            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-funnel me-1" aria-hidden="true"></i> Filter</button>
            <?php if ($search !== '' || $statusFilter !== ''): ?>
                <a href="<?= e(base_url('admin/krs.php')) ?>" class="btn btn-light" data-bs-toggle="tooltip" title="Reset filter"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($krsList): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mahasiswa</th>
                        <th>Mata Kuliah</th>
                        <th>Periode</th>
                        <th>Status</th>
                        <th>Diajukan</th>
                        <th class="text-end">Validasi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($krsList as $row): ?>
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
                            <td><?= e(tanggal_id($row['created_at'])) ?></td>
                            <td>
                                <?php if ($row['status'] === 'menunggu'): ?>
                                    <div class="d-flex justify-content-end gap-1">
                                        <form method="post" action="<?= e(base_url('actions/krs.php')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-success" data-bs-toggle="tooltip" title="Setujui KRS">
                                                <i class="bi bi-check-lg" aria-hidden="true"></i><span class="d-none d-xl-inline ms-1">Setujui</span>
                                            </button>
                                        </form>
                                        <form method="post" action="<?= e(base_url('actions/krs.php')) ?>">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" data-bs-toggle="tooltip" title="Tolak KRS">
                                                <i class="bi bi-x-lg" aria-hidden="true"></i><span class="d-none d-xl-inline ms-1">Tolak</span>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="text-end"><span class="small-note">Sudah divalidasi</span></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top">
            <span class="small-note">Menampilkan <?= count($krsList) ?> dari <?= $totalData ?> data</span>
            <?= render_pagination($page, $totalPages, $paginationParams) ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-journal-check" aria-hidden="true"></i></div>
            <h3>Tidak ada data KRS</h3>
            <p><?= ($search !== '' || $statusFilter !== '') ? 'Coba ubah kata kunci atau filter status.' : 'Belum ada pengajuan KRS dari mahasiswa.' ?></p>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
