<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pageTitle = 'Mata Kuliah';
$pageSubtitle = 'Kelola kurikulum dan dosen pengampu';
$activeMenu = 'mata_kuliah';

$search = (string) query('q');
$prodiFilter = max(0, (int) query('prodi', 0));
$page = current_page();
$perPage = 10;

$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(mk.kode_mk LIKE :search OR mk.nama_mk LIKE :search OR d.nama LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if ($prodiFilter > 0) {
    $conditions[] = 'mk.prodi_id = :prodi_id';
    $params[':prodi_id'] = $prodiFilter;
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = db()->prepare(
    "SELECT COUNT(*) FROM mata_kuliah mk LEFT JOIN dosen d ON d.id = mk.dosen_id $where"
);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$totalData = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalData / $perPage));
$page = min($page, $totalPages);
$offset = page_offset($page, $perPage);

$stmt = db()->prepare(
    "SELECT mk.id, mk.kode_mk, mk.nama_mk, mk.sks, mk.semester,
            p.nama_prodi, p.kode_prodi, d.nama AS nama_dosen, d.nidn,
            (SELECT COUNT(*) FROM krs k WHERE k.mata_kuliah_id = mk.id) AS total_krs
     FROM mata_kuliah mk
     JOIN prodi p ON p.id = mk.prodi_id
     LEFT JOIN dosen d ON d.id = mk.dosen_id
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
$mkList = $stmt->fetchAll();

$prodiList = db()->query('SELECT id, kode_prodi, nama_prodi FROM prodi ORDER BY nama_prodi ASC')->fetchAll();
$paginationParams = array_filter(['q' => $search, 'prodi' => $prodiFilter ?: null], static fn ($value) => $value !== null && $value !== '');

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1>Mata Kuliah</h1>
        <p>Total <?= $totalData ?> mata kuliah<?= $search !== '' ? ' untuk pencarian "' . e($search) . '"' : '' ?>.</p>
    </div>
    <a href="<?= e(base_url('admin/mata_kuliah_form.php')) ?>" class="btn btn-primary">
        <i class="bi bi-journal-plus me-1" aria-hidden="true"></i> Tambah Mata Kuliah
    </a>
</div>

<div class="card">
    <form class="filter-bar" method="get" action="<?= e(base_url('admin/mata_kuliah.php')) ?>">
        <div class="search-box">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Cari kode, nama, atau dosen..." aria-label="Cari mata kuliah">
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <select class="form-select" name="prodi" aria-label="Filter program studi" style="min-width: 180px;">
                <option value="">Semua Program Studi</option>
                <?php foreach ($prodiList as $prodi): ?>
                    <option value="<?= (int) $prodi['id'] ?>" <?= $prodiFilter === (int) $prodi['id'] ? 'selected' : '' ?>><?= e($prodi['nama_prodi']) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-funnel me-1" aria-hidden="true"></i> Filter</button>
            <?php if ($search !== '' || $prodiFilter > 0): ?>
                <a href="<?= e(base_url('admin/mata_kuliah.php')) ?>" class="btn btn-light" data-bs-toggle="tooltip" title="Reset filter"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($mkList): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Mata Kuliah</th>
                        <th>SKS</th>
                        <th>Semester</th>
                        <th>Program Studi</th>
                        <th>Dosen Pengampu</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mkList as $row): ?>
                        <tr>
                            <td><span class="badge text-bg-primary-subtle rounded-pill"><?= e($row['kode_mk']) ?></span></td>
                            <td>
                                <span class="cell-title"><?= e($row['nama_mk']) ?></span>
                                <span class="cell-subtitle"><?= (int) $row['total_krs'] ?> pengambilan KRS</span>
                            </td>
                            <td><span class="fw-semibold"><?= (int) $row['sks'] ?></span> SKS</td>
                            <td>Semester <?= (int) $row['semester'] ?></td>
                            <td><?= e($row['nama_prodi']) ?><span class="cell-subtitle"><?= e($row['kode_prodi']) ?></span></td>
                            <td>
                                <?php if ($row['nama_dosen']): ?>
                                    <span class="cell-title"><?= e($row['nama_dosen']) ?></span>
                                    <span class="cell-subtitle"><?= e($row['nidn']) ?></span>
                                <?php else: ?>
                                    <span class="badge text-bg-warning-subtle rounded-pill">Belum ditentukan</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e(base_url('admin/jadwal.php?q=' . urlencode($row['nama_mk']))) ?>" class="btn-icon" data-bs-toggle="tooltip" title="Lihat jadwal" aria-label="Lihat jadwal <?= e($row['nama_mk']) ?>">
                                        <i class="bi bi-calendar3" aria-hidden="true"></i>
                                    </a>
                                    <a href="<?= e(base_url('admin/mata_kuliah_form.php?id=' . (int) $row['id'])) ?>" class="btn-icon" data-bs-toggle="tooltip" title="Edit" aria-label="Edit <?= e($row['nama_mk']) ?>">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </a>
                                    <button type="button" class="btn-icon text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-delete-url="<?= e(base_url('actions/mata_kuliah.php')) ?>"
                                            data-delete-action="delete"
                                            data-delete-id="<?= (int) $row['id'] ?>"
                                            data-delete-name="mata kuliah <?= e($row['nama_mk']) ?>"
                                            title="Hapus" aria-label="Hapus <?= e($row['nama_mk']) ?>">
                                        <i class="bi bi-trash3" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top">
            <span class="small-note">Menampilkan <?= count($mkList) ?> dari <?= $totalData ?> data</span>
            <?= render_pagination($page, $totalPages, $paginationParams) ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-journal-bookmark" aria-hidden="true"></i></div>
            <h3>Mata kuliah tidak ditemukan</h3>
            <p><?= ($search !== '' || $prodiFilter > 0) ? 'Coba ubah kata kunci atau filter program studi.' : 'Belum ada mata kuliah pada kurikulum.' ?></p>
            <a href="<?= e(base_url('admin/mata_kuliah_form.php')) ?>" class="btn btn-primary btn-sm mt-2"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Tambah Mata Kuliah</a>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
