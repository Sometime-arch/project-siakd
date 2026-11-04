<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/mahasiswa.php');
}

verify_csrf_or_redirect('admin/mahasiswa.php');

$action = (string) post('action');
$id = max(0, (int) post('id', 0));

if ($action === 'delete') {
    if ($id <= 0) {
        flash('danger', 'Data mahasiswa tidak valid.');
        redirect('admin/mahasiswa.php');
    }

    try {
        $stmt = db()->prepare("SELECT u.id, m.nama FROM mahasiswa m JOIN users u ON u.id = m.user_id WHERE m.id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $record = $stmt->fetch();

        if (!$record) {
            flash('danger', 'Data mahasiswa tidak ditemukan.');
            redirect('admin/mahasiswa.php');
        }

        $delete = db()->prepare("DELETE FROM users WHERE id = :id AND role = 'mahasiswa'");
        $delete->execute(['id' => $record['id']]);

        flash('success', 'Data mahasiswa ' . $record['nama'] . ' berhasil dihapus.');
    } catch (Throwable $exception) {
        error_log('Delete mahasiswa failed: ' . $exception->getMessage());
        flash('danger', 'Data mahasiswa gagal dihapus. Pastikan tidak ada data yang masih terhubung.');
    }

    redirect('admin/mahasiswa.php');
}

if (!in_array($action, ['create', 'update'], true)) {
    flash('danger', 'Aksi tidak dikenali.');
    redirect('admin/mahasiswa.php');
}

$isEdit = $action === 'update';
$formUrl = 'admin/mahasiswa_form.php' . ($isEdit && $id > 0 ? '?id=' . $id : '');
$currentRecord = null;

if ($isEdit) {
    $stmt = db()->prepare('SELECT * FROM mahasiswa WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $currentRecord = $stmt->fetch();

    if (!$currentRecord) {
        flash('danger', 'Data mahasiswa tidak ditemukan.');
        redirect('admin/mahasiswa.php');
    }
}

$nim = strtoupper((string) post('nim'));
$nama = (string) post('nama');
$email = strtolower((string) post('email'));
$jenisKelamin = (string) post('jenis_kelamin');
$alamat = (string) post('alamat');
$prodiId = max(0, (int) post('prodi_id', 0));
$angkatan = (int) post('angkatan', 0);
$password = (string) ($_POST['password'] ?? '');
$passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

$errors = [];

if ($nim === '' || !preg_match('/^[A-Z0-9.\-]{5,20}$/', $nim)) {
    $errors['nim'] = 'NIM wajib diisi, 5-20 karakter, hanya huruf, angka, titik, atau tanda hubung.';
}
if (mb_strlen($nama) < 3 || mb_strlen($nama) > 100) {
    $errors['nama'] = 'Nama wajib diisi dengan panjang 3-100 karakter.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 120) {
    $errors['email'] = 'Format email tidak valid.';
}
if (!in_array($jenisKelamin, ['L', 'P'], true)) {
    $errors['jenis_kelamin'] = 'Jenis kelamin tidak valid.';
}
if (mb_strlen($alamat) < 5 || mb_strlen($alamat) > 255) {
    $errors['alamat'] = 'Alamat wajib diisi dengan panjang 5-255 karakter.';
}
if ($prodiId <= 0) {
    $errors['prodi_id'] = 'Program studi wajib dipilih.';
} else {
    $prodiCheck = db()->prepare('SELECT COUNT(*) FROM prodi WHERE id = :id');
    $prodiCheck->execute(['id' => $prodiId]);
    if ((int) $prodiCheck->fetchColumn() === 0) {
        $errors['prodi_id'] = 'Program studi yang dipilih tidak ditemukan.';
    }
}
$tahunMaksimal = (int) date('Y') + 1;
if ($angkatan < 2000 || $angkatan > $tahunMaksimal) {
    $errors['angkatan'] = 'Angkatan harus berada antara tahun 2000 dan ' . $tahunMaksimal . '.';
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
        $check = db()->prepare('SELECT COUNT(*) FROM mahasiswa WHERE nim = :nim AND id <> :id');
        $check->execute(['nim' => $nim, 'id' => $id]);
        if ((int) $check->fetchColumn() > 0) {
            $errors['nim'] = 'NIM sudah digunakan oleh mahasiswa lain.';
        }

        $check = db()->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id <> :user_id');
        $check->execute(['email' => $email, 'user_id' => $currentRecord['user_id']]);
        if ((int) $check->fetchColumn() > 0) {
            $errors['email'] = 'Email sudah digunakan oleh akun lain.';
        }
    } else {
        $check = db()->prepare('SELECT COUNT(*) FROM mahasiswa WHERE nim = :nim');
        $check->execute(['nim' => $nim]);
        if ((int) $check->fetchColumn() > 0) {
            $errors['nim'] = 'NIM sudah digunakan oleh mahasiswa lain.';
        }

        $check = db()->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $check->execute(['email' => $email]);
        if ((int) $check->fetchColumn() > 0) {
            $errors['email'] = 'Email sudah digunakan oleh akun lain.';
        }
    }
}

if ($errors) {
    store_form_state([
        'nim' => $nim,
        'nama' => $nama,
        'email' => $email,
        'jenis_kelamin' => $jenisKelamin,
        'alamat' => $alamat,
        'prodi_id' => $prodiId,
        'angkatan' => $angkatan,
    ], $errors);
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

        $userSql .= ' WHERE id = :id AND role = \'mahasiswa\'';
        $stmt = $pdo->prepare($userSql);
        $stmt->execute($userParams);

        $stmt = $pdo->prepare(
            'UPDATE mahasiswa
             SET nim = :nim, nama = :nama, email = :email, jenis_kelamin = :jenis_kelamin,
                 alamat = :alamat, prodi_id = :prodi_id, angkatan = :angkatan
             WHERE id = :id'
        );
        $stmt->execute([
            'nim' => $nim,
            'nama' => $nama,
            'email' => $email,
            'jenis_kelamin' => $jenisKelamin,
            'alamat' => $alamat,
            'prodi_id' => $prodiId,
            'angkatan' => $angkatan,
            'id' => $id,
        ]);

        $pdo->commit();
        flash('success', 'Data mahasiswa ' . $nama . ' berhasil diperbarui.');
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO users (nama, email, password, role, status)
             VALUES (:nama, :email, :password, 'mahasiswa', 'aktif')"
        );
        $stmt->execute([
            'nama' => $nama,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
        ]);
        $userId = (int) $pdo->lastInsertId();

        $stmt = $pdo->prepare(
            'INSERT INTO mahasiswa (user_id, nim, nama, email, jenis_kelamin, alamat, prodi_id, angkatan)
             VALUES (:user_id, :nim, :nama, :email, :jenis_kelamin, :alamat, :prodi_id, :angkatan)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'nim' => $nim,
            'nama' => $nama,
            'email' => $email,
            'jenis_kelamin' => $jenisKelamin,
            'alamat' => $alamat,
            'prodi_id' => $prodiId,
            'angkatan' => $angkatan,
        ]);

        $pdo->commit();
        flash('success', 'Mahasiswa ' . $nama . ' berhasil ditambahkan. Akun login menggunakan email yang didaftarkan.');
    }
} catch (PDOException $exception) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    error_log('Save mahasiswa failed: ' . $exception->getMessage());
    store_form_state([
        'nim' => $nim,
        'nama' => $nama,
        'email' => $email,
        'jenis_kelamin' => $jenisKelamin,
        'alamat' => $alamat,
        'prodi_id' => $prodiId,
        'angkatan' => $angkatan,
    ], ['umum' => 'Data gagal disimpan. Kemungkinan NIM atau email sudah digunakan.']);
    redirect($formUrl);
} catch (Throwable $exception) {
    if (db()->inTransaction()) {
        db()->rollBack();
    }
    error_log('Save mahasiswa failed: ' . $exception->getMessage());
    flash('danger', 'Terjadi kesalahan saat menyimpan data mahasiswa.');
    redirect($formUrl);
}

redirect('admin/mahasiswa.php');
