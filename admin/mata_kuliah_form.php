<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$id = max(0, (int) query('id', 0));
$isEdit = $id > 0;
$data = [
    'kode_mk' => '',
    'nama_mk' => '',
    'sks' => 3,
    'semester' => 1,
    'prodi_id' => '',
    'dosen_id' => '',
];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM mata_kuliah WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Mata kuliah tidak ditemukan.');
        redirect('admin/mata_kuliah.php');
    }
    $data = $found;
}

$state = pull_form_state();
$old = $state['old'];
$errors = $state['errors'];
$value = static fn (string $key): string => (string) ($old[$key] ?? $data[$key] ?? '');
$error = static fn (string $key): ?string => $errors[$key] ?? null;

$prodiList = db()->query('SELECT id, kode_prodi, nama_prodi FROM prodi ORDER BY nama_prodi ASC')->fetchAll();
$dosenList = db()->query(
    "SELECT d.id, d.nidn, d.nama, p.nama_prodi
     FROM dosen d JOIN prodi p ON p.id = d.prodi_id
     ORDER BY d.nama ASC"
)->fetchAll();

$pageTitle = $isEdit ? 'Edit Mata Kuliah' : 'Tambah Mata Kuliah';
$pageSubtitle = $isEdit ? 'Perbarui data mata kuliah' : 'Tambahkan mata kuliah ke kurikulum';
$activeMenu = 'mata_kuliah';

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <p><?= e($pageSubtitle) ?>.</p>
    </div>
    <a href="<?= e(base_url('admin/mata_kuliah.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali</a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
        <i class="bi bi-exclamation-octagon mt-1" aria-hidden="true"></i>
        <div>
            <strong>Periksa kembali data yang diisi.</strong>
            <ul class="mb-0 mt-1 ps-3">
                <?php foreach ($errors as $message): ?><li><?= e($message) ?></li><?php endforeach; ?>
            </ul>
        </div>
    </div>
<?php endif; ?>

<?php if (!$dosenList): ?>
    <div class="alert alert-warning d-flex align-items-center gap-2" role="alert">
        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
        <span>Belum ada data dosen. <a href="<?= e(base_url('admin/dosen_form.php')) ?>" class="fw-semibold">Tambahkan dosen</a> terlebih dahulu agar mata kuliah memiliki pengampu.</span>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h2>Informasi Mata Kuliah</h2>
                <p class="section-subtitle mt-1">Data ini digunakan pada KRS, jadwal, dan input nilai.</p>
            </div>
            <div class="card-body">
                <form action="<?= e(base_url('actions/mata_kuliah.php')) ?>" method="post" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
                    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="kode_mk" class="form-label">Kode Mata Kuliah <span class="required">*</span></label>
                            <input type="text" class="form-control <?= $error('kode_mk') ? 'is-invalid' : '' ?>" id="kode_mk" name="kode_mk" value="<?= e($value('kode_mk')) ?>" maxlength="15" placeholder="Contoh: IF301" style="text-transform: uppercase;" required>
                            <?php if ($error('kode_mk')): ?><div class="invalid-feedback"><?= e($error('kode_mk')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-8">
                            <label for="nama_mk" class="form-label">Nama Mata Kuliah <span class="required">*</span></label>
                            <input type="text" class="form-control <?= $error('nama_mk') ? 'is-invalid' : '' ?>" id="nama_mk" name="nama_mk" value="<?= e($value('nama_mk')) ?>" maxlength="120" placeholder="Nama mata kuliah" required>
                            <?php if ($error('nama_mk')): ?><div class="invalid-feedback"><?= e($error('nama_mk')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label for="sks" class="form-label">Jumlah SKS <span class="required">*</span></label>
                            <select class="form-select <?= $error('sks') ? 'is-invalid' : '' ?>" id="sks" name="sks" required>
                                <?php for ($i = 1; $i <= 6; $i++): ?>
                                    <option value="<?= $i ?>" <?= $value('sks') === (string) $i ? 'selected' : '' ?>><?= $i ?> SKS</option>
                                <?php endfor; ?>
                            </select>
                            <?php if ($error('sks')): ?><div class="invalid-feedback"><?= e($error('sks')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label for="semester" class="form-label">Semester <span class="required">*</span></label>
                            <select class="form-select <?= $error('semester') ? 'is-invalid' : '' ?>" id="semester" name="semester" required>
                                <?php for ($i = 1; $i <= 14; $i++): ?>
                                    <option value="<?= $i ?>" <?= $value('semester') === (string) $i ? 'selected' : '' ?>>Semester <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            <?php if ($error('semester')): ?><div class="invalid-feedback"><?= e($error('semester')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label for="prodi_id" class="form-label">Program Studi <span class="required">*</span></label>
                            <select class="form-select <?= $error('prodi_id') ? 'is-invalid' : '' ?>" id="prodi_id" name="prodi_id" required>
                                <option value="">Pilih prodi</option>
                                <?php foreach ($prodiList as $prodi): ?>
                                    <option value="<?= (int) $prodi['id'] ?>" <?= $value('prodi_id') === (string) $prodi['id'] ? 'selected' : '' ?>><?= e($prodi['kode_prodi']) ?> &mdash; <?= e($prodi['nama_prodi']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($error('prodi_id')): ?><div class="invalid-feedback"><?= e($error('prodi_id')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label for="dosen_id" class="form-label">Dosen Pengampu <span class="required">*</span></label>
                            <select class="form-select <?= $error('dosen_id') ? 'is-invalid' : '' ?>" id="dosen_id" name="dosen_id" required>
                                <option value="">Pilih dosen pengampu</option>
                                <?php foreach ($dosenList as $dosen): ?>
                                    <option value="<?= (int) $dosen['id'] ?>" <?= $value('dosen_id') === (string) $dosen['id'] ? 'selected' : '' ?>>
                                        <?= e($dosen['nama']) ?> &mdash; <?= e($dosen['nama_prodi']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($error('dosen_id')): ?><div class="invalid-feedback"><?= e($error('dosen_id')) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1" aria-hidden="true"></i> Simpan</button>
                        <a href="<?= e(base_url('admin/mata_kuliah.php')) ?>" class="btn btn-light">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
