<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_role('mahasiswa');

$mahasiswaId = (int) ($user['mahasiswa_id'] ?? 0);
if ($mahasiswaId <= 0) {
    logout_user();
    redirect('login.php');
}

$pageTitle = 'Ambil KRS';
$pageSubtitle = 'Rencana studi periode aktif';
$activeMenu = 'krs';

$semesterAktif = semester_mahasiswa((int) $user['angkatan']);
$tahunAjaran = TAHUN_AJARAN_AKTIF;

$stmtProdi = db()->prepare('SELECT nama_prodi FROM prodi WHERE id = :id LIMIT 1');
$stmtProdi->execute(['id' => (int) $user['mahasiswa_prodi_id']]);
$namaProdi = (string) ($stmtProdi->fetchColumn() ?: '-');

$stmtKrs = db()->prepare(
    "SELECT k.id, k.status, k.created_at, k.semester, k.tahun_ajaran,
            mk.kode_mk, mk.nama_mk, mk.sks, d.nama AS nama_dosen,
            (SELECT GROUP_CONCAT(CONCAT(j.hari, ' ', DATE_FORMAT(j.jam_mulai, '%H:%i')) ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), j.jam_mulai SEPARATOR ' | ')
             FROM jadwal j
             WHERE j.mata_kuliah_id = mk.id) AS jadwal
     FROM krs k
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     LEFT JOIN dosen d ON d.id = mk.dosen_id
     WHERE k.mahasiswa_id = :mahasiswa_id
       AND k.semester = :semester
       AND k.tahun_ajaran = :tahun_ajaran
     ORDER BY FIELD(k.status, 'menunggu', 'disetujui', 'ditolak'), k.created_at DESC"
);
$stmtKrs->execute([
    'mahasiswa_id' => $mahasiswaId,
    'semester' => $semesterAktif,
    'tahun_ajaran' => $tahunAjaran,
]);
$krsList = $stmtKrs->fetchAll();

$approvedSks = 0;
$pendingSks = 0;
foreach ($krsList as $row) {
    if ($row['status'] === 'disetujui') {
        $approvedSks += (int) $row['sks'];
    } elseif ($row['status'] === 'menunggu') {
        $pendingSks += (int) $row['sks'];
    }
}
$totalSks = $approvedSks + $pendingSks;
$sisaSks = max(0, MAX_SKS - $totalSks);

$stmtTawaran = db()->prepare(
    "SELECT mk.id, mk.kode_mk, mk.nama_mk, mk.sks, mk.semester, p.nama_prodi,
            d.nama AS nama_dosen,
            (SELECT GROUP_CONCAT(CONCAT(j.hari, ' ', DATE_FORMAT(j.jam_mulai, '%H:%i')) ORDER BY FIELD(j.hari, 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'), j.jam_mulai SEPARATOR ' | ')
             FROM jadwal j
             WHERE j.mata_kuliah_id = mk.id) AS jadwal
     FROM mata_kuliah mk
     JOIN prodi p ON p.id = mk.prodi_id
     LEFT JOIN dosen d ON d.id = mk.dosen_id
     WHERE mk.prodi_id = :prodi_id
       AND mk.dosen_id IS NOT NULL
       AND mk.id NOT IN (
           SELECT k.mata_kuliah_id FROM krs k WHERE k.mahasiswa_id = :mahasiswa_id
       )
     ORDER BY mk.semester ASC, mk.kode_mk ASC"
);
$stmtTawaran->execute([
    'prodi_id' => (int) $user['mahasiswa_prodi_id'],
    'mahasiswa_id' => $mahasiswaId,
]);
$tawaranList = $stmtTawaran->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1>Ambil KRS</h1>
        <p>Semester <?= $semesterAktif ?> &middot; <?= e($tahunAjaran) ?> <?= e(SEMESTER_AKTIF) ?> &middot; Maksimal <?= MAX_SKS ?> SKS per semester.</p>
    </div>
    <a href="<?= e(base_url('mahasiswa/khs.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-award me-1" aria-hidden="true"></i> Lihat KHS
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon"><i class="bi bi-journal-check" aria-hidden="true"></i></div>
            <p class="stat-label">SKS Diambil</p>
            <p class="stat-value"><?= $totalSks ?></p>
            <p class="stat-note">Disetujui + menunggu validasi</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-green"><i class="bi bi-check2-circle" aria-hidden="true"></i></div>
            <p class="stat-label">SKS Disetujui</p>
            <p class="stat-value"><?= $approvedSks ?></p>
            <p class="stat-note">Sudah divalidasi administrator</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-amber"><i class="bi bi-hourglass-split" aria-hidden="true"></i></div>
            <p class="stat-label">SKS Menunggu</p>
            <p class="stat-value"><?= $pendingSks ?></p>
            <p class="stat-note">Menunggu persetujuan admin</p>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon is-purple"><i class="bi bi-speedometer2" aria-hidden="true"></i></div>
            <p class="stat-label">Sisa SKS</p>
            <p class="stat-value"><?= $sisaSks ?></p>
            <p class="stat-note">Dari batas <?= MAX_SKS ?> SKS</p>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
        <div>
            <h2>KRS Periode Aktif</h2>
            <p class="section-subtitle mt-1">Mata kuliah yang Anda ambil pada semester ini</p>
        </div>
        <span class="badge text-bg-light border text-secondary-emphasis rounded-pill px-3 py-2">
            <?= $totalSks ?>/<?= MAX_SKS ?> SKS
        </span>
    </div>

    <?php if ($krsList): ?>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Mata Kuliah</th>
                        <th>SKS</th>
                        <th>Dosen</th>
                        <th>Jadwal</th>
                        <th>Status</th>
                        <th>Diajukan</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($krsList as $row): ?>
                        <tr>
                            <td>
                                <span class="cell-title"><?= e($row['nama_mk']) ?></span>
                                <span class="cell-subtitle"><?= e($row['kode_mk']) ?> &middot; Semester <?= (int) $row['semester'] ?></span>
                            </td>
                            <td><?= (int) $row['sks'] ?> SKS</td>
                            <td><?= e($row['nama_dosen'] ?: '-') ?></td>
                            <td><span class="cell-subtitle"><?= e($row['jadwal'] ?: 'Belum dijadwalkan') ?></span></td>
                            <td><?= status_badge($row['status']) ?></td>
                            <td><span class="cell-subtitle"><?= e(tanggal_id($row['created_at'])) ?></span></td>
                            <td class="text-end">
                                <?php if ($row['status'] !== 'disetujui'): ?>
                                    <button type="button" class="btn btn-sm btn-outline-danger"
                                            data-bs-toggle="modal" data-bs-target="#deleteModal"
                                            data-delete-url="<?= e(base_url('actions/krs.php')) ?>"
                                            data-delete-action="delete"
                                            data-delete-id="<?= (int) $row['id'] ?>"
                                            data-delete-name="<?= e($row['nama_mk']) ?>">
                                        <i class="bi bi-trash me-1" aria-hidden="true"></i> Hapus
                                    </button>
                                <?php else: ?>
                                    <span class="small-note"><i class="bi bi-lock me-1" aria-hidden="true"></i>Terkunci</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="px-3 py-3 border-top">
            <p class="small-note mb-0">
                <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                KRS berstatus menunggu atau ditolak dapat dihapus. KRS yang sudah disetujui tidak dapat dihapus.
            </p>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-journal-x" aria-hidden="true"></i></div>
            <h3>KRS masih kosong</h3>
            <p>Pilih mata kuliah dari daftar tawaran di bawah untuk mulai menyusun rencana studi.</p>
            <a href="#mata-kuliah-tawaran" class="btn btn-primary btn-sm">Lihat Mata Kuliah</a>
        </div>
    <?php endif; ?>
</div>

<div class="card" id="mata-kuliah-tawaran">
    <div class="card-header">
        <h2>Mata Kuliah Ditawarkan</h2>
        <p class="section-subtitle mt-1">Mata kuliah program studi <?= e($namaProdi) ?> yang belum pernah Anda ambil</p>
    </div>
    <div class="card-body">
        <?php if ($tawaranList): ?>
            <div class="row g-3">
                <?php foreach ($tawaranList as $mk): ?>
                    <?php $bisaDiambil = ($totalSks + (int) $mk['sks']) <= MAX_SKS; ?>
                    <div class="col-lg-6">
                        <div class="course-option flex-column">
                            <div class="course-option-body w-100">
                                <div class="d-flex align-items-start justify-content-between gap-2">
                                    <div>
                                        <span class="course-code"><?= e($mk['kode_mk']) ?></span>
                                        <span class="course-title"><?= e($mk['nama_mk']) ?></span>
                                    </div>
                                    <span class="badge text-bg-primary-subtle text-primary-emphasis rounded-pill"><?= (int) $mk['sks'] ?> SKS</span>
                                </div>
                                <div class="course-meta">
                                    <span><i class="bi bi-person me-1" aria-hidden="true"></i><?= e($mk['nama_dosen']) ?></span>
                                    <span><i class="bi bi-mortarboard me-1" aria-hidden="true"></i>Semester <?= (int) $mk['semester'] ?></span>
                                    <span><i class="bi bi-calendar3 me-1" aria-hidden="true"></i><?= e($mk['jadwal'] ?: 'Jadwal belum tersedia') ?></span>
                                </div>
                            </div>
                            <div class="d-flex align-items-center justify-content-between gap-2 w-100 pt-2 border-top">
                                <span class="small-note">
                                    <?= $bisaDiambil ? 'Sisa SKS setelah mengambil: ' . ($sisaSks - (int) $mk['sks']) : 'Melebihi batas ' . MAX_SKS . ' SKS' ?>
                                </span>
                                <form method="post" action="<?= e(base_url('actions/krs.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="create">
                                    <input type="hidden" name="mata_kuliah_id" value="<?= (int) $mk['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary" <?= $bisaDiambil ? '' : 'disabled' ?>>
                                        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i> Ambil
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state py-4">
                <div class="empty-state-icon"><i class="bi bi-check2-all" aria-hidden="true"></i></div>
                <h3>Tidak ada mata kuliah baru</h3>
                <p>Semua mata kuliah program studi Anda sudah pernah diajukan pada KRS, atau belum ada mata kuliah yang dapat diambil.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
