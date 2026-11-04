<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pageTitle = 'Program Studi';
$pageSubtitle = 'Kelola program studi kampus';
$activeMenu = 'prodi';

$search = (string) query('q');
$page = current_page();
$perPage = 10;

$params = [];
$where = '';
if ($search !== '') {
    $where = 'WHERE p.kode_prodi LIKE :search OR p.nama_prodi LIKE :search';
    $params[':search'] = '%' . $search . '%';
}

$countStmt = db()->prepare("SELECT COUNT(*) FROM prodi p $where");
$countStmt->execute($params);
$totalData = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalData / $perPage));
$page = min($page, $totalPages);
$offset = page_offset($page, $perPage);

$stmt = db()->prepare(
    "SELECT p.id, p.kode_prodi, p.nama_prodi, p.created_at,
            (SELECT COUNT(*) FROM mahasiswa m WHERE m.prodi_id = p.id) AS total_mahasiswa,
            (SELECT COUNT(*) FROM dosen d WHERE d.prodi_id = p.id) AS total_dosen,
            (SELECT COUNT(*) FROM mata_kuliah mk WHERE mk.prodi_id = p.id) AS total_mk
     FROM prodi p
     $where
     ORDER BY p.nama_prodi ASC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$prodiList = $stmt->fetchAll();

$paginationParams = array_filter(['q' => $search], static fn ($value) => $value !== '');

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1>Program Studi</h1>
        <p>Total <?= $totalData ?> program studi terdaftar di kampus.</p>
    </div>
    <a href="<?= e(base_url('admin/prodi_form.php')) ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Tambah Program Studi
    </a>
</div>

<div class="card">
    <form class="filter-bar" method="get" action="<?= e(base_url('admin/prodi.php')) ?>">
        <div class="search-box">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Cari kode atau nama prodi..." aria-label="Cari program studi">
        </div>
        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-search me-1" aria-hidden="true"></i> Cari</button>
            <?php if ($search !== ''): ?>
                <a href="<?= e(base_url('admin/prodi.php')) ?>" class="btn btn-light">Reset</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($prodiList): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kode</th>
                        <th>Nama Program Studi</th>
                        <th>Mahasiswa</th>
                        <th>Dosen</th>
                        <th>Mata Kuliah</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($prodiList as $row): ?>
                        <tr>
                            <td><span class="badge text-bg-primary-subtle rounded-pill"><?= e($row['kode_prodi']) ?></span></td>
                            <td>
                                <span class="cell-title"><?= e($row['nama_prodi']) ?></span>
                                <span class="cell-subtitle">Dibuat <?= e(tanggal_id($row['created_at'])) ?></span>
                            </td>
                            <td><?= (int) $row['total_mahasiswa'] ?> mahasiswa</td>
                            <td><?= (int) $row['total_dosen'] ?> dosen</td>
                            <td><?= (int) $row['total_mk'] ?> mata kuliah</td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e(base_url('admin/mahasiswa.php?prodi=' . (int) $row['id'])) ?>" class="btn-icon" data-bs-toggle="tooltip" title="Lihat mahasiswa" aria-label="Lihat mahasiswa prodi <?= e($row['nama_prodi']) ?>">
                                        <i class="bi bi-people" aria-hidden="true"></i>
                                    </a>
                                    <a href="<?= e(base_url('admin/prodi_form.php?id=' . (int) $row['id'])) ?>" class="btn-icon" data-bs-toggle="tooltip" title="Edit" aria-label="Edit <?= e($row['nama_prodi']) ?>">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </a>
                                    <button type="button" class="btn-icon text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-delete-url="<?= e(base_url('actions/prodi.php')) ?>"
                                            data-delete-action="delete"
                                            data-delete-id="<?= (int) $row['id'] ?>"
                                            data-delete-name="program studi <?= e($row['nama_prodi']) ?>"
                                            title="Hapus" aria-label="Hapus <?= e($row['nama_prodi']) ?>">
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
            <span class="small-note">Menampilkan <?= count($prodiList) ?> dari <?= $totalData ?> data</span>
            <?= render_pagination($page, $totalPages, $paginationParams) ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-building" aria-hidden="true"></i></div>
            <h3>Program studi tidak ditemukan</h3>
            <p>Tambahkan program studi terlebih dahulu sebelum menambah mahasiswa atau dosen.</p>
            <a href="<?= e(base_url('admin/prodi_form.php')) ?>" class="btn btn-primary btn-sm mt-2"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Tambah Program Studi</a>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
