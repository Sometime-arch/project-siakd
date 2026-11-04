<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_role('dosen');

$dosenId = (int) ($user['dosen_id'] ?? 0);
if ($dosenId <= 0) {
    logout_user();
    redirect('login.php');
}

$pageTitle = 'Input Nilai';
$pageSubtitle = 'Kelola nilai mahasiswa pada kelas Anda';
$activeMenu = 'nilai';

$stmtCourses = db()->prepare(
    'SELECT id, kode_mk, nama_mk, sks, semester
     FROM mata_kuliah
     WHERE dosen_id = :dosen_id
     ORDER BY semester ASC, kode_mk ASC'
);
$stmtCourses->execute(['dosen_id' => $dosenId]);
$courses = $stmtCourses->fetchAll();

$requestedId = max(0, (int) query('mata_kuliah_id', 0));
$selectedId = 0;
$selectedCourse = null;

foreach ($courses as $course) {
    if ((int) $course['id'] === $requestedId) {
        $selectedId = (int) $course['id'];
        $selectedCourse = $course;
        break;
    }
}

if ($selectedId === 0 && $courses) {
    $selectedId = (int) $courses[0]['id'];
    $selectedCourse = $courses[0];
}

$search = (string) query('q');
$page = current_page();
$perPage = 10;

$summary = ['total_mahasiswa' => 0, 'total_dinilai' => 0, 'belum_dinilai' => 0, 'rata_nilai' => null];
$nilaiList = [];
$totalData = 0;
$totalPages = 1;

if ($selectedId > 0) {
    $stmtSummary = db()->prepare(
        "SELECT COUNT(*) AS total_mahasiswa,
                COUNT(n.id) AS total_dinilai,
                ROUND(AVG(n.nilai_akhir), 2) AS rata_nilai
         FROM krs k
         LEFT JOIN nilai n ON n.krs_id = k.id
         WHERE k.mata_kuliah_id = :mata_kuliah_id AND k.status = 'disetujui'"
    );
    $stmtSummary->execute(['mata_kuliah_id' => $selectedId]);
    $summary = $stmtSummary->fetch() ?: $summary;
    $summary['total_mahasiswa'] = (int) $summary['total_mahasiswa'];
    $summary['total_dinilai'] = (int) $summary['total_dinilai'];
    $summary['belum_dinilai'] = max(0, $summary['total_mahasiswa'] - $summary['total_dinilai']);
    $summary['rata_nilai'] = $summary['rata_nilai'] !== null ? (float) $summary['rata_nilai'] : null;

    $conditions = ["k.mata_kuliah_id = :mata_kuliah_id", "k.status = 'disetujui'"];
    $params = [':mata_kuliah_id' => $selectedId];

    if ($search !== '') {
        $conditions[] = '(m.nim LIKE :search OR m.nama LIKE :search)';
        $params[':search'] = '%' . $search . '%';
    }

    $where = 'WHERE ' . implode(' AND ', $conditions);

    $countStmt = db()->prepare(
        "SELECT COUNT(*)
         FROM krs k
         JOIN mahasiswa m ON m.id = k.mahasiswa_id
         $where"
    );
    foreach ($params as $key => $value) {
        $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $countStmt->execute();
    $totalData = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalData / $perPage));
    $page = min($page, $totalPages);
    $offset = page_offset($page, $perPage);

    $stmtNilai = db()->prepare(
        "SELECT k.id AS krs_id, k.semester, k.tahun_ajaran,
                m.nim, m.nama, p.nama_prodi,
                n.id AS nilai_id, n.tugas, n.uts, n.uas, n.nilai_akhir, n.nilai_huruf
         FROM krs k
         JOIN mahasiswa m ON m.id = k.mahasiswa_id
         JOIN prodi p ON p.id = m.prodi_id
         LEFT JOIN nilai n ON n.krs_id = k.id
         $where
         ORDER BY m.nama ASC
         LIMIT :limit OFFSET :offset"
    );
    foreach ($params as $key => $value) {
        $stmtNilai->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmtNilai->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmtNilai->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmtNilai->execute();
    $nilaiList = $stmtNilai->fetchAll();
}

$paginationParams = ['mata_kuliah_id' => $selectedId];
if ($search !== '') {
    $paginationParams['q'] = $search;
}

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1>Input Nilai</h1>
        <p>Pilih mata kuliah, lalu input atau perbarui nilai mahasiswa yang KRS-nya telah disetujui.</p>
    </div>
    <a href="<?= e(base_url('dosen/mata_kuliah.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Mata Kuliah Saya
    </a>
</div>

<?php if (!$courses): ?>
    <div class="card">
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-journal-x" aria-hidden="true"></i></div>
            <h3>Belum ada mata kuliah</h3>
            <p>Anda belum ditugaskan mengampu mata kuliah. Hubungi administrator untuk informasi lebih lanjut.</p>
        </div>
    </div>
<?php else: ?>
    <div class="card mb-4">
        <form class="filter-bar" method="get" action="<?= e(base_url('dosen/nilai.php')) ?>">
            <div class="d-flex flex-wrap align-items-center gap-2">
                <label for="mataKuliahSelect" class="form-label mb-0 small-note text-nowrap">Mata Kuliah</label>
                <select class="form-select" id="mataKuliahSelect" name="mata_kuliah_id" aria-label="Pilih mata kuliah" style="min-width: 280px;">
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= (int) $course['id'] ?>" <?= (int) $course['id'] === $selectedId ? 'selected' : '' ?>>
                            <?= e($course['kode_mk'] . ' - ' . $course['nama_mk'] . ' (' . $course['sks'] . ' SKS)') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <div class="search-box">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" class="form-control" name="q" value="<?= e($search) ?>" placeholder="Cari NIM atau nama mahasiswa..." aria-label="Cari mahasiswa">
                </div>
                <button type="submit" class="btn btn-outline-secondary">Tampilkan</button>
                <?php if ($search !== ''): ?>
                    <a href="<?= e(base_url('dosen/nilai.php?mata_kuliah_id=' . $selectedId)) ?>" class="btn btn-light">Reset</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <?php if ($selectedCourse): ?>
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4">
            <div>
                <h2 class="section-title mb-1"><?= e($selectedCourse['nama_mk']) ?></h2>
                <p class="small-note mb-0">
                    <?= e($selectedCourse['kode_mk']) ?> &middot; <?= (int) $selectedCourse['sks'] ?> SKS &middot; Semester <?= (int) $selectedCourse['semester'] ?>
                    &middot; Periode <?= e(TAHUN_AJARAN_AKTIF) ?> <?= e(SEMESTER_AKTIF) ?>
                </p>
            </div>
            <span class="badge text-bg-light border text-secondary-emphasis rounded-pill px-3 py-2">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i> Hanya KRS disetujui yang dapat dinilai
            </span>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon"><i class="bi bi-people" aria-hidden="true"></i></div>
                    <p class="stat-label">Mahasiswa</p>
                    <p class="stat-value"><?= number_format($summary['total_mahasiswa'], 0, ',', '.') ?></p>
                    <p class="stat-note">KRS disetujui pada kelas ini</p>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon is-green"><i class="bi bi-check2-circle" aria-hidden="true"></i></div>
                    <p class="stat-label">Sudah Dinilai</p>
                    <p class="stat-value"><?= number_format($summary['total_dinilai'], 0, ',', '.') ?></p>
                    <p class="stat-note">Nilai tersimpan di database</p>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon is-amber"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
                    <p class="stat-label">Belum Dinilai</p>
                    <p class="stat-value"><?= number_format($summary['belum_dinilai'], 0, ',', '.') ?></p>
                    <p class="stat-note">Mahasiswa menunggu penilaian</p>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="stat-card">
                    <div class="stat-icon is-purple"><i class="bi bi-award" aria-hidden="true"></i></div>
                    <p class="stat-label">Rata-rata Nilai</p>
                    <p class="stat-value"><?= $summary['rata_nilai'] !== null ? e(number_format((float) $summary['rata_nilai'], 2, ',', '.')) : '-' ?></p>
                    <p class="stat-note">Dari nilai akhir yang telah diinput</p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="card">
        <?php if ($nilaiList): ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Mahasiswa</th>
                            <th>Program Studi</th>
                            <th>Tugas</th>
                            <th>UTS</th>
                            <th>UAS</th>
                            <th>Nilai Akhir</th>
                            <th>Huruf</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nilaiList as $row): ?>
                            <?php $sudahDinilai = $row['nilai_id'] !== null; ?>
                            <tr>
                                <td>
                                    <span class="cell-title"><?= e($row['nama']) ?></span>
                                    <span class="cell-subtitle"><?= e($row['nim']) ?></span>
                                </td>
                                <td><?= e($row['nama_prodi']) ?></td>
                                <td><?= $sudahDinilai ? e(number_format((float) $row['tugas'], 2, ',', '.')) : '<span class="text-muted">&mdash;</span>' ?></td>
                                <td><?= $sudahDinilai ? e(number_format((float) $row['uts'], 2, ',', '.')) : '<span class="text-muted">&mdash;</span>' ?></td>
                                <td><?= $sudahDinilai ? e(number_format((float) $row['uas'], 2, ',', '.')) : '<span class="text-muted">&mdash;</span>' ?></td>
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
                                        <span class="badge text-bg-warning-subtle text-warning-emphasis rounded-pill">Belum</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm <?= $sudahDinilai ? 'btn-outline-secondary' : 'btn-primary' ?>" data-bs-toggle="modal" data-bs-target="#nilaiModal<?= (int) $row['krs_id'] ?>">
                                        <i class="bi <?= $sudahDinilai ? 'bi-pencil' : 'bi-plus-lg' ?> me-1" aria-hidden="true"></i>
                                        <?= $sudahDinilai ? 'Edit' : 'Input' ?>
                                    </button>
                                </td>
                            </tr>

                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($nilaiList as $row): ?>
                <?php require __DIR__ . '/../includes/nilai_modal.php'; ?>
            <?php endforeach; ?>

            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 px-3 py-3 border-top">
                <span class="small-note">
                    Menampilkan <?= count($nilaiList) ?> dari <?= number_format($totalData, 0, ',', '.') ?> mahasiswa
                </span>
                <?= render_pagination($page, $totalPages, $paginationParams) ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="bi bi-person-x" aria-hidden="true"></i></div>
                <h3><?= $search !== '' ? 'Mahasiswa tidak ditemukan' : 'Belum ada mahasiswa yang dapat dinilai' ?></h3>
                <p><?= $search !== '' ? 'Coba gunakan NIM atau nama lain, atau reset pencarian.' : 'Mahasiswa akan tampil setelah KRS mereka disetujui administrator.' ?></p>
                <?php if ($search !== ''): ?>
                    <a href="<?= e(base_url('dosen/nilai.php?mata_kuliah_id=' . $selectedId)) ?>" class="btn btn-outline-primary btn-sm">Reset Pencarian</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
