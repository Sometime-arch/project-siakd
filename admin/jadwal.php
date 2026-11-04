<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pageTitle = 'Jadwal Kuliah';
$pageSubtitle = 'Atur jadwal dan ruang perkuliahan';
$activeMenu = 'jadwal';

$search = (string) query('q');
$hariFilter = (string) query('hari');
if (!in_array($hariFilter, daftar_hari(), true)) {
    $hariFilter = '';
}
$page = current_page();
$perPage = 10;

$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = '(mk.kode_mk LIKE :search OR mk.nama_mk LIKE :search OR j.ruang LIKE :search OR d.nama LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}
if ($hariFilter !== '') {
    $conditions[] = 'j.hari = :hari';
    $params[':hari'] = $hariFilter;
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = db()->prepare(
    "SELECT COUNT(*) FROM jadwal j
     JOIN mata_kuliah mk ON mk.id = j.mata_kuliah_id
     LEFT JOIN dosen d ON d.id = mk.dosen_id
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
    "SELECT j.id, j.hari, j.jam_mulai, j.jam_selesai, j.ruang,
            mk.kode_mk, mk.nama_mk, mk.sks, p.nama_prodi, d.nama AS nama_dosen
     FROM jadwal j
     JOIN mata_kuliah mk ON mk.id = j.mata_kuliah_id
     JOIN prodi p ON p.id = mk.prodi_id
     LEFT JOIN dosen d ON d.id = mk.dosen_id
     $where
     ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), j.jam_mulai ASC
     LIMIT :limit OFFSET :offset"
);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$jadwalList = $stmt->fetchAll();

$paginationParams = array_filter(['q' => $search, 'hari' => $hariFilter], static fn ($value) => $value !== '');

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1>Jadwal Kuliah</h1>
        <p>Total <?= $totalData ?> jadwal perkuliahan terdaftar.</p>
    </div>
    <a href="<?= e(base_url('admin/jadwal_form.php')) ?>" class="btn btn-primary">
        <i class="bi bi-calendar-plus me-1" aria-hidden="true"></i> Tambah Jadwal
    </a>
</div>

<div class="card">
    <form class="filter-bar" method="get" action="<?= e(base_url('admin/jadwal.php')) ?>">
        <div class="search-box">
            <i class="bi bi-search" aria-hidden="true"></i>
            <input type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Cari mata kuliah, dosen, atau ruang..." aria-label="Cari jadwal">
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <select class="form-select" name="hari" aria-label="Filter hari" style="min-width: 150px;">
                <option value="">Semua Hari</option>
                <?php foreach (daftar_hari() as $hari): ?>
                    <option value="<?= e($hari) ?>" <?= $hariFilter === $hari ? 'selected' : '' ?>><?= e($hari) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-outline-secondary"><i class="bi bi-funnel me-1" aria-hidden="true"></i> Filter</button>
            <?php if ($search !== '' || $hariFilter !== ''): ?>
                <a href="<?= e(base_url('admin/jadwal.php')) ?>" class="btn btn-light" data-bs-toggle="tooltip" title="Reset filter"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></a>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($jadwalList): ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Mata Kuliah</th>
                        <th>Hari</th>
                        <th>Waktu</th>
                        <th>Ruang</th>
                        <th>Dosen</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jadwalList as $row): ?>
                        <tr>
                            <td>
                                <span class="cell-title"><?= e($row['nama_mk']) ?></span>
                                <span class="cell-subtitle"><?= e($row['kode_mk']) ?> &middot; <?= (int) $row['sks'] ?> SKS &middot; <?= e($row['nama_prodi']) ?></span>
                            </td>
                            <td><span class="badge text-bg-primary-subtle rounded-pill"><?= e($row['hari']) ?></span></td>
                            <td><?= e(jam_id($row['jam_mulai'])) ?> &ndash; <?= e(jam_id($row['jam_selesai'])) ?></td>
                            <td><?= e($row['ruang']) ?></td>
                            <td><?= e($row['nama_dosen'] ?: 'Belum ditentukan') ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="<?= e(base_url('admin/jadwal_form.php?id=' . (int) $row['id'])) ?>" class="btn-icon" data-bs-toggle="tooltip" title="Edit" aria-label="Edit jadwal <?= e($row['nama_mk']) ?>">
                                        <i class="bi bi-pencil" aria-hidden="true"></i>
                                    </a>
                                    <button type="button" class="btn-icon text-danger" data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-delete-url="<?= e(base_url('actions/jadwal.php')) ?>"
                                            data-delete-action="delete"
                                            data-delete-id="<?= (int) $row['id'] ?>"
                                            data-delete-name="jadwal <?= e($row['nama_mk']) ?> hari <?= e($row['hari']) ?>"
                                            title="Hapus" aria-label="Hapus jadwal <?= e($row['nama_mk']) ?>">
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
            <span class="small-note">Menampilkan <?= count($jadwalList) ?> dari <?= $totalData ?> data</span>
            <?= render_pagination($page, $totalPages, $paginationParams) ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-calendar3" aria-hidden="true"></i></div>
            <h3>Jadwal tidak ditemukan</h3>
            <p><?= ($search !== '' || $hariFilter !== '') ? 'Coba ubah kata kunci atau filter hari.' : 'Belum ada jadwal perkuliahan.' ?></p>
            <a href="<?= e(base_url('admin/jadwal_form.php')) ?>" class="btn btn-primary btn-sm mt-2"><i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Tambah Jadwal</a>
        </div>
    <?php endif; ?>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
