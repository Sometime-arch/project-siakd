<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$id = max(0, (int) query('id', 0));
$isEdit = $id > 0;
$data = [
    'mata_kuliah_id' => '',
    'hari' => 'Senin',
    'jam_mulai' => '08:00',
    'jam_selesai' => '10:30',
    'ruang' => '',
];

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM jadwal WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $found = $stmt->fetch();
    if (!$found) {
        flash('danger', 'Jadwal tidak ditemukan.');
        redirect('admin/jadwal.php');
    }
    $data = $found;
}

$state = pull_form_state();
$old = $state['old'];
$errors = $state['errors'];
$value = static function (string $key) use ($old, $data): string {
    $val = (string) ($old[$key] ?? $data[$key] ?? '');
    return str_contains($key, 'jam_') ? substr($val, 0, 5) : $val;
};
$error = static fn (string $key): ?string => $errors[$key] ?? null;

$mkList = db()->query(
    "SELECT mk.id, mk.kode_mk, mk.nama_mk, p.nama_prodi
     FROM mata_kuliah mk JOIN prodi p ON p.id = mk.prodi_id
     ORDER BY mk.semester ASC, mk.kode_mk ASC"
)->fetchAll();

$pageTitle = $isEdit ? 'Edit Jadwal' : 'Tambah Jadwal';
$pageSubtitle = $isEdit ? 'Perbarui jadwal perkuliahan' : 'Tambahkan jadwal perkuliahan baru';
$activeMenu = 'jadwal';

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-heading">
    <div>
        <h1><?= e($pageTitle) ?></h1>
        <p><?= e($pageSubtitle) ?>.</p>
    </div>
    <a href="<?= e(base_url('admin/jadwal.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1" aria-hidden="true"></i> Kembali</a>
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
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h2>Informasi Jadwal</h2>
                <p class="section-subtitle mt-1">Jadwal akan tampil pada dashboard dosen dan mahasiswa.</p>
            </div>
            <div class="card-body">
                <form action="<?= e(base_url('actions/jadwal.php')) ?>" method="post" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
                    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="mata_kuliah_id" class="form-label">Mata Kuliah <span class="required">*</span></label>
                            <select class="form-select <?= $error('mata_kuliah_id') ? 'is-invalid' : '' ?>" id="mata_kuliah_id" name="mata_kuliah_id" required>
                                <option value="">Pilih mata kuliah</option>
                                <?php foreach ($mkList as $mk): ?>
                                    <option value="<?= (int) $mk['id'] ?>" <?= $value('mata_kuliah_id') === (string) $mk['id'] ? 'selected' : '' ?>>
                                        <?= e($mk['kode_mk']) ?> &mdash; <?= e($mk['nama_mk']) ?> (<?= e($mk['nama_prodi']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($error('mata_kuliah_id')): ?><div class="invalid-feedback"><?= e($error('mata_kuliah_id')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label for="hari" class="form-label">Hari <span class="required">*</span></label>
                            <select class="form-select <?= $error('hari') ? 'is-invalid' : '' ?>" id="hari" name="hari" required>
                                <?php foreach (daftar_hari() as $hari): ?>
                                    <option value="<?= e($hari) ?>" <?= $value('hari') === $hari ? 'selected' : '' ?>><?= e($hari) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($error('hari')): ?><div class="invalid-feedback"><?= e($error('hari')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label for="jam_mulai" class="form-label">Jam Mulai <span class="required">*</span></label>
                            <input type="time" class="form-control <?= $error('jam_mulai') ? 'is-invalid' : '' ?>" id="jam_mulai" name="jam_mulai" value="<?= e($value('jam_mulai')) ?>" required>
                            <?php if ($error('jam_mulai')): ?><div class="invalid-feedback"><?= e($error('jam_mulai')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label for="jam_selesai" class="form-label">Jam Selesai <span class="required">*</span></label>
                            <input type="time" class="form-control <?= $error('jam_selesai') ? 'is-invalid' : '' ?>" id="jam_selesai" name="jam_selesai" value="<?= e($value('jam_selesai')) ?>" required>
                            <?php if ($error('jam_selesai')): ?><div class="invalid-feedback"><?= e($error('jam_selesai')) ?></div><?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label for="ruang" class="form-label">Ruang <span class="required">*</span></label>
                            <input type="text" class="form-control <?= $error('ruang') ? 'is-invalid' : '' ?>" id="ruang" name="ruang" value="<?= e($value('ruang')) ?>" maxlength="50" placeholder="Contoh: Lab. Basis Data" required>
                            <?php if ($error('ruang')): ?><div class="invalid-feedback"><?= e($error('ruang')) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1" aria-hidden="true"></i> Simpan</button>
                        <a href="<?= e(base_url('admin/jadwal.php')) ?>" class="btn btn-light">Batal</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
