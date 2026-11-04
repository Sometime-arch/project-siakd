<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/dosen.php');
}

verify_csrf_or_redirect('admin/dosen.php');
$action = (string) post('action');
$id = max(0, (int) post('id', 0));

if ($action === 'delete') {
    try {
        $stmt = db()->prepare('SELECT d.nama, d.user_id FROM dosen d WHERE d.id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch();
        if (!$record) {
            flash('danger', 'Data dosen tidak ditemukan.');
            redirect('admin/dosen.php');
        }

        $delete = db()->prepare("DELETE FROM users WHERE id = :id AND role = 'dosen'");
        $delete->execute(['id' => $record['user_id']]);
        flash('success', 'Data dosen ' . $record['nama'] . ' berhasil dihapus. Mata kuliah terkait kini tanpa dosen pengampu.');
    } catch (Throwable $exception) {
        error_log('Delete dosen failed: ' . $exception->getMessage());
        flash('danger', 'Data dosen gagal dihapus.');
    }
    redirect('admin/dosen.php');
}

if (!in_array($action, ['create', 'update'], true)) {
    flash('danger', 'Aksi tidak dikenali.');
    redirect('admin/dosen.php');
}

$isEdit = $action === 'update';
$formUrl = 'admin/dosen_form.php' . ($isEdit && $id > 0 ? '?id=' . $id : '');
$currentRecord = null;
if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM dosen WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $currentRecord = $stmt->fetch();
    if (!$currentRecord) {
        flash('danger', 'Data dosen tidak ditemukan.');
        redirect('admin/dosen.php');
    }
}

$nidn = (string) post('nidn');
$nama = (string) post('nama');
$email = strtolower((string) post('email'));
$prodiId = max(0, (int) post('prodi_id', 0));
$password = (string) ($_POST['password'] ?? '');
$passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

$errors = [];
if (!preg_match('/^[0-9]{10,20}$/', $nidn)) {
    $errors['nidn'] = 'NIDN wajib berupa angka dengan panjang 10-20 digit.';
}
if (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) {
    $errors['nama'] = 'Nama wajib diisi dengan panjang 3-100 karakter.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
    $errors['email'] = 'Format email tidak valid.';
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
if (!$isEdit || $password !== '' || $passwordConfirmation !== '') {
    if (strlen($password) < 8) {
        $errors['password'] = 'Password minimal 8 karakter.';
    }
    if ($password !== $passwordConfirmation) {
        $errors['password'] = 'Konfirmasi password tidak sama.';
    }
}

if (!$errors) {
    if ($isEdit) {
        $check = db()->prepare('SELECT COUNT(*) FROM dosen WHERE nidn = :nidn AND id <> :id');
        $check->execute(['nidn' => $nidn, 'id' => $id]);
        if ((int) $check->fetchColumn() > 0) {
            $errors['nidn'] = 'NIDN sudah digunakan oleh dosen lain.';
        }
        $check = db()->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id <> :user_id');
        $check->execute(['email' => $email, 'user_id' => $currentRecord['user_id']]);
        if ((int) $check->fetchColumn() > 0) {
            $errors['email'] = 'Email sudah digunakan oleh akun lain.';
        }
    } else {
        $check = db()->prepare('SELECT COUNT(*) FROM dosen WHERE nidn = :nidn');
        $check->execute(['nidn' => $nidn]);
        if ((int) $check->fetchColumn() > 0) {
            $errors['nidn'] = 'NIDN sudah digunakan oleh dosen lain.';
        }
        $check = db()->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $check->execute(['email' => $email]);
        if ((int) $check->fetchColumn() > 0) {
            $errors['email'] = 'Email sudah digunakan oleh akun lain.';
        }
    }
}

if ($errors) {
    store_form_state(['nidn' => $nidn, 'nama' => $nama, 'email' => $email, 'prodi_id' => $prodiId], $errors);
    redirect($formUrl);
}

try {
    $pdo = db();
    $pdo->beginTransaction();

    if ($isEdit) {
        $userSql = 'UPDATE users SET nama = :nama, email = :email';
        $userParams = ['nama' => $nama, 'email' => $email, 'id' => $currentRecord['user_id']];
        if ($password !== '') {
            $userSql .= ', password = :password';
            $userParams['password'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $userSql .= " WHERE id = :id AND role = 'dosen'";
        $stmt = $pdo->prepare($userSql);
        $stmt->execute($userParams);

        $stmt = $pdo->prepare(
            'UPDATE dosen SET nidn = :nidn, nama = :nama, email = :email, prodi_id = :prodi_id WHERE id = :id'
        );
        $stmt->execute([
            'nidn' => $nidn,
            'nama' => $nama,
            'email' => $email,
            'prodi_id' => $prodiId,
            'id' => $id,
        ]);

        $pdo->commit();
        flash('success', 'Data dosen ' . $nama . ' berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO users (nama, email, password, role, status)
             VALUES (:nama, :email, :password, 'dosen', 'aktif')"
        );
        $stmt->execute([
            'nama' => $nama,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO dosen (user_id, nidn, nama, email, prodi_id)
             VALUES (:user_id, :nidn, :nama, :email, :prodi_id)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'nidn' => $nidn,
            'nama' => $nama,
            'email' => $email,
            'prodi_id' => $prodiId,
        ]);

        $pdo->commit();
        flash('success', 'Dosen ' . $nama . ' berhasil ditambahkan.');
    }
} catch (PDOException $exception) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    error_log('Save dosen failed: ' . $exception->getMessage());
    store_form_state(['nidn' => $nidn, 'nama' => $nama, 'email' => $email, 'prodi_id' => $prodiId], ['umum' => 'Data gagal disimpan. Kemungkinan NIDN atau email sudah digunakan.']);
    redirect($formUrl);
} catch (Throwable $exception) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    error_log('Save dosen failed: ' . $exception->getMessage());
    flash('danger', 'Terjadi kesalahan saat menyimpan data dosen.');
    redirect($formUrl);
}

redirect('admin/dosen.php');
