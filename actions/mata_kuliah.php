<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/mata_kuliah.php');
}

verify_csrf_or_redirect('admin/mata_kuliah.php');
$action = (string) post('action');
$id = max(0, (int) post('id', 0));

if ($action === 'delete') {
    try {
        $stmt = db()->prepare(
            'SELECT mk.nama_mk, (SELECT COUNT(*) FROM krs k WHERE k.mata_kuliah_id = mk.id) AS total_krs
             FROM mata_kuliah mk WHERE mk.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch();

        if (!$record) {
            flash('danger', 'Mata kuliah tidak ditemukan.');
            redirect('admin/mata_kuliah.php');
        }
        if ((int) $record['total_krs'] > 0) {
            flash('warning', 'Mata kuliah ' . $record['nama_mk'] . ' tidak dapat dihapus karena sudah memiliki data KRS. Hapus KRS terkait terlebih dahulu.');
            redirect('admin/mata_kuliah.php');
        }

        $stmt = db()->prepare('DELETE FROM mata_kuliah WHERE id = :id');
        $stmt->execute(['id' => $id]);
        flash('success', 'Mata kuliah ' . $record['nama_mk'] . ' berhasil dihapus.');
    } catch (Throwable $exception) {
        error_log('Delete mata kuliah failed: ' . $exception->getMessage());
        flash('danger', 'Mata kuliah gagal dihapus.');
    }
    redirect('admin/mata_kuliah.php');
}

if (!in_array($action, ['create', 'update'], true)) {
    flash('danger', 'Aksi tidak dikenali.');
    redirect('admin/mata_kuliah.php');
}

$isEdit = $action === 'update';
$formUrl = 'admin/mata_kuliah_form.php' . ($isEdit && $id > 0 ? '?id=' . $id : '');
$currentRecord = null;
if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM mata_kuliah WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $currentRecord = $stmt->fetch();
    if (!$currentRecord) {
        flash('danger', 'Mata kuliah tidak ditemukan.');
        redirect('admin/mata_kuliah.php');
    }
}

$kode = strtoupper((string) post('kode_mk'));
$nama = (string) post('nama_mk');
$sks = (int) post('sks', 0);
$semester = (int) post('semester', 0);
$prodiId = max(0, (int) post('prodi_id', 0));
$dosenId = max(0, (int) post('dosen_id', 0));

$errors = [];
if (!preg_match('/^[A-Z0-9\-]{2,15}$/', $kode)) {
    $errors['kode_mk'] = 'Kode mata kuliah harus 2-15 karakter, hanya huruf, angka, atau tanda hubung.';
}
if (mb_strlen($nama) < 3 || mb_strlen($nama) > 120) {
    $errors['nama_mk'] = 'Nama mata kuliah wajib diisi dengan panjang 3-120 karakter.';
}
if ($sks < 1 || $sks > 6) {
    $errors['sks'] = 'SKS harus berada antara 1 dan 6.';
}
if ($semester < 1 || $semester > 14) {
    $errors['semester'] = 'Semester harus berada antara 1 dan 14.';
}
if ($prodiId <= 0) {
    $errors['prodi_id'] = 'Program studi wajib dipilih.';
} else {
    $check = db()->prepare('SELECT COUNT(*) FROM prodi WHERE id = :id');
    $check->execute(['id' => $prodiId]);
    if ((int) $check->fetchColumn() === 0) {
        $errors['prodi_id'] = 'Program studi tidak ditemukan.';
    }
}
if ($dosenId <= 0) {
    $errors['dosen_id'] = 'Dosen pengampu wajib dipilih.';
} else {
    $check = db()->prepare('SELECT COUNT(*) FROM dosen WHERE id = :id');
    $check->execute(['id' => $dosenId]);
    if ((int) $check->fetchColumn() === 0) {
        $errors['dosen_id'] = 'Dosen pengampu tidak ditemukan.';
    }
}

if (!$errors) {
    $sql = 'SELECT COUNT(*) FROM mata_kuliah WHERE kode_mk = :kode';
    $params = ['kode' => $kode];
    if ($isEdit) {
        $sql .= ' AND id <> :id';
        $params['id'] = $id;
    }
    $check = db()->prepare($sql);
    $check->execute($params);
    if ((int) $check->fetchColumn() > 0) {
        $errors['kode_mk'] = 'Kode mata kuliah sudah digunakan.';
    }
}

if ($errors) {
    store_form_state([
        'kode_mk' => $kode,
        'nama_mk' => $nama,
        'sks' => $sks,
        'semester' => $semester,
        'prodi_id' => $prodiId,
        'dosen_id' => $dosenId,
    ], $errors);
    redirect($formUrl);
}

try {
    if ($isEdit) {
        $stmt = db()->prepare(
            'UPDATE mata_kuliah
             SET kode_mk = :kode, nama_mk = :nama, sks = :sks, semester = :semester,
                 prodi_id = :prodi_id, dosen_id = :dosen_id
             WHERE id = :id'
        );
        $stmt->execute([
            'kode' => $kode,
            'nama' => $nama,
            'sks' => $sks,
            'semester' => $semester,
            'prodi_id' => $prodiId,
            'dosen_id' => $dosenId,
            'id' => $id,
        ]);
        flash('success', 'Mata kuliah ' . $nama . ' berhasil diperbarui.');
    } else {
        $stmt = db()->prepare(
            'INSERT INTO mata_kuliah (kode_mk, nama_mk, sks, semester, prodi_id, dosen_id)
             VALUES (:kode, :nama, :sks, :semester, :prodi_id, :dosen_id)'
        );
        $stmt->execute([
            'kode' => $kode,
            'nama' => $nama,
            'sks' => $sks,
            'semester' => $semester,
            'prodi_id' => $prodiId,
            'dosen_id' => $dosenId,
        ]);
        flash('success', 'Mata kuliah ' . $nama . ' berhasil ditambahkan.');
    }
} catch (PDOException $exception) {
    error_log('Save mata kuliah failed: ' . $exception->getMessage());
    store_form_state([
        'kode_mk' => $kode,
        'nama_mk' => $nama,
        'sks' => $sks,
        'semester' => $semester,
        'prodi_id' => $prodiId,
        'dosen_id' => $dosenId,
    ], ['umum' => 'Data gagal disimpan. Kode mata kuliah mungkin sudah digunakan.']);
    redirect($formUrl);
} catch (Throwable $exception) {
    error_log('Save mata kuliah failed: ' . $exception->getMessage());
    flash('danger', 'Terjadi kesalahan saat menyimpan mata kuliah.');
    redirect($formUrl);
}

redirect('admin/mata_kuliah.php');
