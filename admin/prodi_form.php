<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$id = max(0, (int) query('id', 0));
$isEdit = $id > 0;
$data = ['kode_prodi' => '', 'nama_prodi' => ''];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM prodi WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Program studi tidak ditemukan.');
        redirect('admin/prodi.php');
    }
    $data = $found;
}

$state = pull_form_state();
$old = $state['old'];
$errors = $state['errors'];
$value = static fn (string $key): string => (string) ($old[$key] ?? $data[$key] ?? '');
$error = static fn (string $key): ?string => $errors[$key] ?? null;

$pageTitle = $isEdit ? 'Edit Program Studi' : 'Tambah Program Studi';
$pageSubtitle = $isEdit ? 'Perbarui data program studi' : 'Tambahkan program studi baru';
$activeMenu = 'prodi';

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <p><?= e($pageSubtitle) ?>.</p>
    </div>
    <a href="<?= e(base_url('admin/prodi.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali</a>
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

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">
                <h2>Informasi Program Studi</h2>
                <p class="section-subtitle mt-1">Kode prodi harus unik dan mudah dikenali.</p>
            </div>
            <div class="card-body">
                <form action="<?= e(base_url('actions/prodi.php')) ?>" method="post" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
                    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

                    <div class="mb-3">
                        <label for="kode_prodi" class="form-label">Kode Program Studi <span class="required">*</span></label>
                        <input type="text" class="form-control <?= $error('kode_prodi') ? 'is-invalid' : '' ?>" id="kode_prodi" name="kode_prodi" value="<?= e($value('kode_prodi')) ?>" maxlength="10" placeholder="Contoh: TI" style="text-transform: uppercase;" required>
                        <?php if ($error('kode_prodi')): ?><div class="invalid-feedback"><?= e($error('kode_prodi')) ?></div><?php endif; ?>
                        <div class="form-text">2-10 karakter, hanya huruf dan angka.</div>
                    </div>
                    <div class="mb-4">
                        <label for="nama_prodi" class="form-label">Nama Program Studi <span class="required">*</span></label>
                        <input type="text" class="form-control <?= $error('nama_prodi') ? 'is-invalid' : '' ?>" id="nama_prodi" name="nama_prodi" value="<?= e($value('nama_prodi')) ?>" maxlength="100" placeholder="Contoh: Teknik Informatika" required>
                        <?php if ($error('nama_prodi')): ?><div class="invalid-feedback"><?= e($error('nama_prodi')) ?></div><?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1" aria-hidden="true"></i> Simpan</button>
                        <a href="<?= e(base_url('admin/prodi.php')) ?>" class="btn btn-light">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
