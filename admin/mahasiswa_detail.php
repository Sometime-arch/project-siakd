<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$id = max(0, (int) query('id', 0));
$stmt = db()->prepare(
    'SELECT m.*, p.nama_prodi, p.kode_prodi, u.status AS status_akun, u.created_at AS akun_dibuat
     FROM mahasiswa m
     JOIN prodi p ON p.id = m.prodi_id
     JOIN users u ON u.id = m.user_id
     WHERE m.id = :id LIMIT 1'
);
$stmt->execute(['id' => $id]);
$mahasiswa = $stmt->fetch();

if (!$mahasiswa) {
    flash('danger', 'Data mahasiswa tidak ditemukan.');
    redirect('admin/mahasiswa.php');
}

$krsStmt = db()->prepare(
    "SELECT k.id, k.semester, k.tahun_ajaran, k.status,
            mk.kode_mk, mk.nama_mk, mk.sks,
            n.nilai_akhir, n.nilai_huruf
     FROM krs k
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     LEFT JOIN nilai n ON n.krs_id = k.id
     WHERE k.mahasiswa_id = :mahasiswa_id
     ORDER BY k.tahun_ajaran DESC, k.semester DESC, mk.nama_mk ASC"
);
$krsStmt->execute(['mahasiswa_id' => $id]);
$krsList = $krsStmt->fetchAll();

$totalSks = 0;
$totalMutu = 0.0;
$sksNilai = 0;
foreach ($krsList as $item) {
    if ($item['status'] === 'disetujui') {
        $totalSks += (int) $item['sks'];
    }
    if ($item['nilai_huruf'] !== null) {
        $sksNilai += (int) $item['sks'];
        $totalMutu += nilai_point($item['nilai_huruf']) * (int) $item['sks'];
    }
}
$ipk = $sksNilai > 0 ? round($totalMutu / $sksNilai, 2) : 0.0;

$pageTitle = 'Detail Mahasiswa';
$pageSubtitle = 'Profil, KRS, dan capaian nilai';
$activeMenu = 'mahasiswa';

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1>Detail Mahasiswa</h1>
        <p>Informasi lengkap dan riwayat akademik mahasiswa.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(base_url('admin/mahasiswa.php')) ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
        </a>
        <a href="<?= e(base_url('admin/mahasiswa_form.php?id=' . $id)) ?>" class="btn btn-primary">
            <i class="bi bi-pencil me-1" aria-hidden="true"></i> Edit Data
        </a>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card mb-4">
            <div class="card-body text-center">
                <span class="avatar mx-auto mb-3" style="width: 68px; height: 68px; font-size: 1.3rem; border-radius: 18px;"><?= e(initials($mahasiswa['nama'])) ?></span>
                <h2 class="section-title"><?= e($mahasiswa['nama']) ?></h2>
                <p class="small-note mb-2"><?= e($mahasiswa['nim']) ?></p>
                <span class="badge text-bg-primary-subtle rounded-pill"><?= e($mahasiswa['nama_prodi']) ?></span>
                <div class="divider"></div>
                <div class="khs-summary">
                    <div class="khs-summary-item">
                        <span class="khs-summary-value"><?= $totalSks ?></span>
                        <span class="khs-summary-label">SKS Disetujui</span>
                    </div>
                    <div class="khs-summary-item">
                        <span class="khs-summary-value"><?= number_format($ipk, 2, ',', '.') ?></span>
                        <span class="khs-summary-label">IPK</span>
                    </div>
                    <div class="khs-summary-item">
                        <span class="khs-summary-value"><?= count($krsList) ?></span>
                        <span class="khs-summary-label">Mata Kuliah</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2>Informasi Pribadi</h2></div>
            <div class="card-body">
                <dl class="detail-list">
                    <div><dt>Email</dt><dd><?= e($mahasiswa['email']) ?></dd></div>
                    <div><dt>Jenis Kelamin</dt><dd><?= $mahasiswa['jenis_kelamin'] === 'L' ? 'Laki-laki' : 'Perempuan' ?></dd></div>
                    <div><dt>Angkatan</dt><dd><?= (int) $mahasiswa['angkatan'] ?></dd></div>
                    <div><dt>Semester Kini</dt><dd><?= semester_mahasiswa((int) $mahasiswa['angkatan']) ?></dd></div>
                    <div><dt>Program Studi</dt><dd><?= e($mahasiswa['kode_prodi']) ?> &mdash; <?= e($mahasiswa['nama_prodi']) ?></dd></div>
                    <div><dt>Alamat</dt><dd><?= nl2br(e($mahasiswa['alamat'])) ?></dd></div>
                    <div><dt>Status Akun</dt><dd><span class="badge text-bg-success-subtle rounded-pill"><?= e(ucfirst($mahasiswa['status_akun'])) ?></span></dd></div>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h2>Riwayat KRS</h2>
                    <p class="section-subtitle mt-1">Seluruh mata kuliah yang pernah diambil.</p>
                </div>
                <span class="badge text-bg-primary-subtle rounded-pill"><?= count($krsList) ?> data</span>
            </div>
            <?php if ($krsList): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Mata Kuliah</th>
                                <th>Periode</th>
                                <th>Status</th>
                                <th>Nilai</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($krsList as $item): ?>
                                <tr>
                                    <td>
                                        <span class="cell-title"><?= e($item['nama_mk']) ?></span>
                                        <span class="cell-subtitle"><?= e($item['kode_mk']) ?> &middot; <?= (int) $item['sks'] ?> SKS</span>
                                    </td>
                                    <td>
                                        <span class="cell-title"><?= e($item['tahun_ajaran']) ?></span>
                                        <span class="cell-subtitle">Semester <?= (int) $item['semester'] ?></span>
                                    </td>
                                    <td><?= status_badge($item['status']) ?></td>
                                    <td>
                                        <?php if ($item['nilai_huruf'] !== null): ?>
                                            <span class="cell-title"><?= e($item['nilai_huruf']) ?></span>
                                            <span class="cell-subtitle"><?= number_format((float) $item['nilai_akhir'], 2, ',', '.') ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">&mdash;</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="bi bi-journal-x" aria-hidden="true"></i></div>
                    <h3>Belum ada riwayat KRS</h3>
                    <p>Mahasiswa ini belum pernah mengambil mata kuliah.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
