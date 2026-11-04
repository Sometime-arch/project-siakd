<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/jadwal.php');
}

verify_csrf_or_redirect('admin/jadwal.php');
$action = (string) post('action');
$id = max(0, (int) post('id', 0));

if ($action === 'delete') {
    try {
        $stmt = db()->prepare('SELECT j.id FROM jadwal j WHERE j.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        if (!$stmt->fetch()) {
            flash('danger', 'Jadwal tidak ditemukan.');
            redirect('admin/jadwal.php');
        }

        $stmt = db()->prepare('DELETE FROM jadwal WHERE id = :id');
        $stmt->execute(['id' => $id]);
        flash('success', 'Jadwal berhasil dihapus.');
    } catch (Throwable $exception) {
        error_log('Delete jadwal failed: ' . $exception->getMessage());
        flash('danger', 'Jadwal gagal dihapus.');
    }
    redirect('admin/jadwal.php');
}

if (!in_array($action, ['create', 'update'], true)) {
    flash('danger', 'Aksi tidak dikenali.');
    redirect('admin/jadwal.php');
}

$isEdit = $action === 'update';
$formUrl = 'admin/jadwal_form.php' . ($isEdit && $id > 0 ? '?id=' . $id : '');
$currentRecord = null;
if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM jadwal WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $currentRecord = $stmt->fetch();
    if (!$currentRecord) {
        flash('danger', 'Jadwal tidak ditemukan.');
        redirect('admin/jadwal.php');
    }
}

$mkId = max(0, (int) post('mata_kuliah_id', 0));
$hari = (string) post('hari');
$jamMulai = substr((string) post('jam_mulai'), 0, 5);
$jamSelesai = substr((string) post('jam_selesai'), 0, 5);
$ruang = (string) post('ruang');

$errors = [];
if ($mkId <= 0) {
    $errors['mata_kuliah_id'] = 'Mata kuliah wajib dipilih.';
} else {
    $check = db()->prepare('SELECT COUNT(*) FROM mata_kuliah WHERE id = :id');
    $check->execute(['id' => $mkId]);
    if ((int) $check->fetchColumn() === 0) {
        $errors['mata_kuliah_id'] = 'Mata kuliah tidak ditemukan.';
    }
}
if (!in_array($hari, daftar_hari(), true)) {
    $errors['hari'] = 'Hari tidak valid.';
}
if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $jamMulai)) {
    $errors['jam_mulai'] = 'Format jam mulai tidak valid.';
}
if (!preg_match('/^([01][0-9]|2[0-3]):[0-5][0-9]$/', $jamSelesai)) {
    $errors['jam_selesai'] = 'Format jam selesai tidak valid.';
}
if (!isset($errors['jam_mulai'], $errors['jam_selesai']) && $jamSelesai <= $jamMulai) {
    $errors['jam_selesai'] = 'Jam selesai harus lebih besar dari jam mulai.';
}
if (mb_strlen($ruang) < 2 || mb_strlen($ruang) > 50) {
    $errors['ruang'] = 'Ruang wajib diisi dengan panjang 2-50 karakter.';
}

if ($errors) {
    store_form_state([
        'mata_kuliah_id' => $mkId,
        'hari' => $hari,
        'jam_mulai' => $jamMulai,
        'jam_selesai' => $jamSelesai,
        'ruang' => $ruang,
    ], $errors);
    redirect($formUrl);
}

try {
    if ($isEdit) {
        $stmt = db()->prepare(
            'UPDATE jadwal SET mata_kuliah_id = :mk_id, hari = :hari, jam_mulai = :mulai,
                    jam_selesai = :selesai, ruang = :ruang
             WHERE id = :id'
        );
        $stmt->execute([
            'mk_id' => $mkId,
            'hari' => $hari,
            'mulai' => $jamMulai . ':00',
            'selesai' => $jamSelesai . ':00',
            'ruang' => $ruang,
            'id' => $id,
        ]);
        flash('success', 'Jadwal berhasil diperbarui.');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO jadwal (mata_kuliah_id, hari, jam_mulai, jam_selesai, ruang)
             VALUES (:mk_id, :hari, :mulai, :selesai, :ruang)'
        );
        $stmt->execute([
            'mk_id' => $mkId,
            'hari' => $hari,
            'mulai' => $jamMulai . ':00',
            'selesai' => $jamSelesai . ':00',
            'ruang' => $ruang,
        ]);
        flash('success', 'Jadwal berhasil ditambahkan.');
    }
} catch (Throwable $exception) {
    error_log('Save jadwal failed: ' . $exception->getMessage());
    flash('danger', 'Jadwal gagal disimpan.');
    redirect($formUrl);
}

redirect('admin/jadwal.php');
