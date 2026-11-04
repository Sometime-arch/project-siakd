<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pageTitle = 'Data Mahasiswa';
$pageSubtitle = 'Kelola akun dan profil mahasiswa';
$activeMenu = 'mahasiswa';

$search = (string) query('q');
$prodiFilter = max(0, (int) query('prodi', 0));
$page = current_page();
$perPage = 10;

$conditions = [];
$params = [];

if ($search !== '') {
    $conditions[] = '(m.nim LIKE :search OR m.nama LIKE :search OR m.email LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

if ($prodiFilter > 0) {
    $conditions[] = 'm.prodi_id = :prodi_id';
    $params[':prodi_id'] = $prodiFilter;
}

$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = db()->prepare("SELECT COUNT(*) FROM mahasiswa m $where");
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$totalData = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalData / $perPage));
$page = min($page, $totalPages);
$offset = page_offset($page, $perPage);

$stmt = db()->prepare(
    "SELECT m.id, m.nim, m.nama, m.email, m.jenis_kelamin, m.angkatan,
            p.nama_prodi, p.kode_prodi
     FROM mahasiswa m
     JOIN prodi p ON p.id = m.prodi_id
     $where
     ORDER BY m.angkatan DESC, m.nama ASC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$mahasiswaList = $stmt->fetchAll();

$prodiList = db()->query('SELECT id, kode_prodi, nama_prodi FROM prodi ORDER BY nama_prodi ASC')->fetchAll();
$paginationParams = array_filter(['q' => $search, 'prodi' => $prodiFilter ?: null], static fn ($value) => $value !== null && $value !== '');

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1>Data Mahasiswa</h1>
        <p>Total <?= number_format($totalData, 0, ',', '.') ?> mahasiswa terdaftar<?= $search !== '' ? ' untuk pencarian "#' . e($search) . '"' : '' ?>.</p>
    </div>
    <a href="<?= e(base_url('admin/mahasiswa_form.php')) ?>" class="btn btn-primary">
        <i class="bi bi-person-plus me-1" aria-hidden="true"></i> Tambah Mahasiswa
    </a>
</div>

<div class="card">
    <form class="filter-bar" method="get" action="<?= e(base_url('admin/mahasiswa.php')) ?>">
        <div class="search-box">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Cari NIM, nama, atau email..." aria-label="Cari mahasiswa">
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <select class="form-select" name="prodi" aria-label="Filter program studi" style="min-width: 180px;">
                <option value="">Semua Program Studi</option>
                <?php foreach ($prodiList as $prodi): ?>
                    <option value="<?= (int) $prodi['id'] ?>" <?= $prodiFilter === (int) $prodi['id'] ? 'selected' : '' ?>>
                        <?= e($prodi['nama_prodi']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline-secondary">
                <i class="bi bi-funnel me-1" aria-hidden="true"></i> Filter
            </button>
            <?php if ($search !== '' || $prodiFilter > 0): ?>
                <a href="<?= e(base_url('admin/mahasiswa.php')) ?>" class="btn btn-light" data-bs-toggle="tooltip" title="Reset filter">
                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($mahasiswaList): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mahasiswa</th>
                        <th>NIM</th>
                        <th>Program Studi</th>
                        <th>Angkatan</th>
                        <th>L/P</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mahasiswaList as $row): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <span class="avatar avatar-sm"><?= e(initials($row['nama'])) ?></span>
                                    <div class="min-w-0">
                                        <span class="cell-title"><?= e($row['nama']) ?></span>
                                        <span class="cell-subtitle"><?= e($row['email']) ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><span class="fw-semibold"><?= e($row['nim']) ?></span></td>
                            <td>
                                <span class="prodi-dot"></span><?= e($row['nama_prodi']) ?>
                                <span class="cell-subtitle"><?= e($row['kode_prodi']) ?></span>
                            </td>
                            <td><?= (int) $row['angkatan'] ?></td>
                            <td><?= $row['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e(base_url('admin/mahasiswa_detail.php?id=' . (int) $row['id'])) ?>" class="btn-icon" data-bs-toggle="tooltip" title="Lihat detail" aria-label="Lihat detail <?= e($row['nama']) ?>">
                                        <i class="bi bi-eye" aria-hidden="true"></i>
                                    </a>
                                    <a href="<?= e(base_url('admin/mahasiswa_form.php?id=' . (int) $row['id'])) ?>" class="btn-icon" data-bs-toggle="tooltip" title="Edit" aria-label="Edit <?= e($row['nama']) ?>">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </a>
                                    <button type="button" class="btn-icon text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-delete-url="<?= e(base_url('actions/mahasiswa.php')) ?>"
                                            data-delete-action="delete"
                                            data-delete-id="<?= (int) $row['id'] ?>"
                                            data-delete-name="mahasiswa <?= e($row['nama']) ?>"
                                            title="Hapus" aria-label="Hapus <?= e($row['nama']) ?>">
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
            <span class="small-note">Menampilkan <?= count($mahasiswaList) ?> dari <?= $totalData ?> data</span>
            <?= render_pagination($page, $totalPages, $paginationParams) ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-people" aria-hidden="true"></i></div>
            <h3>Data mahasiswa tidak ditemukan</h3>
            <p><?= ($search !== '' || $prodiFilter > 0) ? 'Coba ubah kata kunci atau filter program studi.' : 'Belum ada mahasiswa. Tambahkan data mahasiswa pertama.' ?></p>
            <a href="<?= e(base_url('admin/mahasiswa_form.php')) ?>" class="btn btn-primary btn-sm mt-2">
                <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Tambah Mahasiswa
            </a>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
