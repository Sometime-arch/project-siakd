<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/prodi.php');
}

verify_csrf_or_redirect('admin/prodi.php');
$action = (string) post('action');
$id = max(0, (int) post('id', 0));

if ($action === 'delete') {
    try {
        $stmt = db()->prepare(
            'SELECT p.nama_prodi,
                    (SELECT COUNT(*) FROM mahasiswa m WHERE m.prodi_id = p.id) AS total_mahasiswa,
                    (SELECT COUNT(*) FROM dosen d WHERE d.prodi_id = p.id) AS total_dosen,
                    (SELECT COUNT(*) FROM mata_kuliah mk WHERE mk.prodi_id = p.id) AS total_mk
             FROM prodi p WHERE p.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch();

        if (!$record) {
            flash('danger', 'Program studi tidak ditemukan.');
            redirect('admin/prodi.php');
        }

        $dependencies = (int) $record['total_mahasiswa'] + (int) $record['total_dosen'] + (int) $record['total_mk'];
        if ($dependencies > 0) {
            flash('warning', 'Program studi ' . $record['nama_prodi'] . ' tidak dapat dihapus karena masih memiliki mahasiswa, dosen, atau mata kuliah terkait.');
            redirect('admin/prodi.php');
        }

        $stmt = db()->prepare('DELETE FROM prodi WHERE id = :id');
        $stmt->execute(['id' => $id]);
        flash('success', 'Program studi ' . $record['nama_prodi'] . ' berhasil dihapus.');
    } catch (Throwable $exception) {
        error_log('Delete prodi failed: ' . $exception->getMessage());
        flash('danger', 'Program studi gagal dihapus.');
    }
    redirect('admin/prodi.php');
}

if (!in_array($action, ['create', 'update'], true)) {
    flash('danger', 'Aksi tidak dikenali.');
    redirect('admin/prodi.php');
}

$isEdit = $action === 'update';
$formUrl = 'admin/prodi_form.php' . ($isEdit && $id > 0 ? '?id=' . $id : '');
$currentRecord = null;
if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM prodi WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $currentRecord = $stmt->fetch();
    if (!$currentRecord) {
        flash('danger', 'Program studi tidak ditemukan.');
        redirect('admin/prodi.php');
    }
}

$kode = strtoupper((string) post('kode_prodi'));
$nama = (string) post('nama_prodi');
$errors = [];

if (!preg_match('/^[A-Z0-9]{2,10}$/', $kode)) {
    $errors['kode_prodi'] = 'Kode prodi harus 2-10 karakter dan hanya boleh berisi huruf atau angka.';
}
if (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) {
    $errors['nama_prodi'] = 'Nama prodi wajib diisi dengan panjang 3-100 karakter.';
}

if (!$errors) {
    $sql = 'SELECT COUNT(*) FROM prodi WHERE (kode_prodi = :kode OR nama_prodi = :nama)';
    $params = ['kode' => $kode, 'nama' => $nama];
    if ($isEdit) {
        $sql .= ' AND id <> :id';
        $params['id'] = $id;
    }
    $check = db()->prepare($sql);
    $check->execute($params);
    if ((int) $check->fetchColumn() > 0) {
        $errors['umum'] = 'Kode atau nama program studi sudah digunakan.';
    }
}

if ($errors) {
    store_form_state(['kode_prodi' => $kode, 'nama_prodi' => $nama], $errors);
    redirect($formUrl);
}

try {
    if ($isEdit) {
        $stmt = db()->prepare('UPDATE prodi SET kode_prodi = :kode, nama_prodi = :nama WHERE id = :id');
        $stmt->execute(['kode' => $kode, 'nama' => $nama, 'id' => $id]);
        flash('success', 'Program studi ' . $nama . ' berhasil diperbarui.');
    } else {
        $stmt = db()->prepare('INSERT INTO prodi (kode_prodi, nama_prodi) VALUES (:kode, :nama)');
        $stmt->execute(['kode' => $kode, 'nama' => $nama]);
        flash('success', 'Program studi ' . $nama . ' berhasil ditambahkan.');
    }
} catch (PDOException $exception) {
    error_log('Save prodi failed: ' . $exception->getMessage());
    store_form_state(['kode_prodi' => $kode, 'nama_prodi' => $nama], ['umum' => 'Data gagal disimpan. Kode atau nama prodi mungkin sudah digunakan.']);
    redirect($formUrl);
} catch (Throwable $exception) {
    error_log('Save prodi failed: ' . $exception->getMessage());
    flash('danger', 'Terjadi kesalahan saat menyimpan program studi.');
    redirect($formUrl);
}

redirect('admin/prodi.php');
