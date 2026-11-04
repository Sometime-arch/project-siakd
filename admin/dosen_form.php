<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$id = max(0, (int) query('id', 0));
$isEdit = $id > 0;
$data = [
    'nidn' => '',
    'nama' => '',
    'email' => '',
    'prodi_id' => '',
];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM dosen WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Data dosen tidak ditemukan.');
        redirect('admin/dosen.php');
    }
    $data = $found;
}

$state = pull_form_state();
$old = $state['old'];
$errors = $state['errors'];
$value = static function (string $key) use ($old, $data): string {
    return (string) ($old[$key] ?? $data[$key] ?? '');
};
$error = static fn (string $key): ?string => $errors[$key] ?? null;

$prodiList = db()->query('SELECT id, kode_prodi, nama_prodi FROM prodi ORDER BY nama_prodi ASC')->fetchAll();

$pageTitle = $isEdit ? 'Edit Dosen' : 'Tambah Dosen';
$pageSubtitle = $isEdit ? 'Perbarui profil dan akun dosen' : 'Buat data dan akun login dosen';
$activeMenu = 'dosen';

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <p><?= e($pageSubtitle) ?>.</p>
    </div>
    <a href="<?= e(base_url('admin/dosen.php')) ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali
    </a>
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

<form action="<?= e(base_url('actions/dosen.php')) ?>" method="post" novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h2>Profil Dosen</h2>
                    <p class="section-subtitle mt-1">Identitas dosen dan program studi induk.</p>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="nidn" class="form-label">NIDN <span class="required">*</span></label>
                            <input type="text" class="form-control <?= $error('nidn') ? 'is-invalid' : '' ?>" id="nidn" name="nidn" value="<?= e($value('nidn')) ?>" maxlength="20" inputmode="numeric" placeholder="Contoh: 0412018501" required>
                            <?php if ($error('nidn')): ?><div class="invalid-feedback"><?= e($error('nidn')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="nama" class="form-label">Nama Lengkap <span class="required">*</span></label>
                            <input type="text" class="form-control <?= $error('nama') ? 'is-invalid' : '' ?>" id="nama" name="nama" value="<?= e($value('nama')) ?>" maxlength="100" placeholder="Nama lengkap dan gelar" required>
                            <?php if ($error('nama')): ?><div class="invalid-feedback"><?= e($error('nama')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email <span class="required">*</span></label>
                            <input type="email" class="form-control <?= $error('email') ? 'is-invalid' : '' ?>" id="email" name="email" value="<?= e($value('email')) ?>" maxlength="120" placeholder="dosen@kampus.ac.id" required>
                            <?php if ($error('email')): ?><div class="invalid-feedback"><?= e($error('email')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label for="prodi_id" class="form-label">Program Studi <span class="required">*</span></label>
                            <select class="form-select <?= $error('prodi_id') ? 'is-invalid' : '' ?>" id="prodi_id" name="prodi_id" required>
                                <option value="">Pilih program studi</option>
                                <?php foreach ($prodiList as $prodi): ?>
                                    <option value="<?= (int) $prodi['id'] ?>" <?= $value('prodi_id') === (string) $prodi['id'] ? 'selected' : '' ?>>
                                        <?= e($prodi['kode_prodi']) ?> &mdash; <?= e($prodi['nama_prodi']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($error('prodi_id')): ?><div class="invalid-feedback"><?= e($error('prodi_id')) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4">
                <div class="card-header">
                    <h2>Akun Login</h2>
                    <p class="section-subtitle mt-1">Dosen dapat mengakses mata kuliah dan input nilai.</p>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="password" class="form-label">Password <?= $isEdit ? '' : '<span class="required">*</span>' ?></label>
                        <input type="password" class="form-control <?= $error('password') ? 'is-invalid' : '' ?>" id="password" name="password" minlength="8" placeholder="<?= $isEdit ? 'Kosongkan jika tidak diubah' : 'Minimal 8 karakter' ?>" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                        <?php if ($error('password')): ?><div class="invalid-feedback"><?= e($error('password')) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmation" class="form-label">Konfirmasi Password <?= $isEdit ? '' : '<span class="required">*</span>' ?></label>
                        <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" minlength="8" placeholder="Ulangi password" autocomplete="new-password" <?= $isEdit ? '' : 'required' ?>>
                    </div>
                    <div class="alert alert-info mb-0 d-flex align-items-start gap-2">
                        <i class="bi bi-info-circle mt-1" aria-hidden="true"></i>
                        <span>Dosen akan melihat mahasiswa hanya pada mata kuliah yang diampu.</span>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1" aria-hidden="true"></i> Simpan Data</button>
                        <a href="<?= e(base_url('admin/dosen.php')) ?>" class="btn btn-light">Batal</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
