<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(role_dashboard($user['role']));
}

$action = (string) post('action');
$id = max(0, (int) post('id', 0));

if ($user['role'] === 'admin') {
    verify_csrf_or_redirect('admin/krs.php');

    if (!in_array($action, ['approve', 'reject'], true)) {
        flash('danger', 'Aksi validasi tidak dikenali.');
        redirect('admin/krs.php');
    }

    try {
        $stmt = db()->prepare(
            "SELECT k.id, k.mahasiswa_id, k.semester, k.tahun_ajaran, k.status, k.mata_kuliah_id,
                    mk.nama_mk, mk.sks, m.nama AS nama_mahasiswa
             FROM krs k
             JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
             JOIN mahasiswa m ON m.id = k.mahasiswa_id
             WHERE k.id = :id LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $krs = $stmt->fetch();

        if (!$krs) {
            flash('danger', 'Data KRS tidak ditemukan.');
            redirect('admin/krs.php');
        }
        if ($krs['status'] !== 'menunggu') {
            flash('warning', 'KRS tersebut sudah divalidasi sebelumnya.');
            redirect('admin/krs.php');
        }

        if ($action === 'approve') {
            $sksStmt = db()->prepare(
                "SELECT COALESCE(SUM(mk.sks), 0)
                 FROM krs k
                 JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
                 WHERE k.mahasiswa_id = :mahasiswa_id
                   AND k.semester = :semester
                   AND k.tahun_ajaran = :tahun_ajaran
                   AND k.status = 'disetujui'
                   AND k.id <> :id"
            );
            $sksStmt->execute([
                'mahasiswa_id' => $krs['mahasiswa_id'],
                'semester' => $krs['semester'],
                'tahun_ajaran' => $krs['tahun_ajaran'],
                'id' => $krs['id'],
            ]);
            $approvedSks = (int) $sksStmt->fetchColumn();

            if ($approvedSks + (int) $krs['sks'] > MAX_SKS) {
                flash('warning', 'KRS tidak dapat disetujui karena total SKS akan melebihi batas ' . MAX_SKS . ' SKS per semester.');
                redirect('admin/krs.php');
            }

            $stmt = db()->prepare("UPDATE krs SET status = 'disetujui' WHERE id = :id AND status = 'menunggu'");
            $stmt->execute(['id' => $id]);
            flash('success', 'KRS ' . $krs['nama_mahasiswa'] . ' untuk mata kuliah ' . $krs['nama_mk'] . ' berhasil disetujui.');
        } else {
            $stmt = db()->prepare("UPDATE krs SET status = 'ditolak' WHERE id = :id AND status = 'menunggu'");
            $stmt->execute(['id' => $id]);
            flash('success', 'KRS ' . $krs['nama_mahasiswa'] . ' untuk mata kuliah ' . $krs['nama_mk'] . ' ditolak.');
        }
    } catch (Throwable $exception) {
        error_log('Validate KRS failed: ' . $exception->getMessage());
        flash('danger', 'Terjadi kesalahan saat memvalidasi KRS.');
    }

    redirect('admin/krs.php');
}

if ($user['role'] !== 'mahasiswa') {
    flash('danger', 'Anda tidak memiliki akses untuk mengubah KRS.');
    redirect(role_dashboard($user['role']));
}

verify_csrf_or_redirect('mahasiswa/krs.php');
$mahasiswaId = (int) ($user['mahasiswa_id'] ?? 0);
if ($mahasiswaId <= 0) {
    flash('danger', 'Profil mahasiswa tidak ditemukan.');
    redirect('mahasiswa/dashboard.php');
}

if ($action === 'delete') {
    try {
        $stmt = db()->prepare(
            'SELECT k.id, k.status, mk.nama_mk
             FROM krs k JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
             WHERE k.id = :id AND k.mahasiswa_id = :mahasiswa_id LIMIT 1'
        );
        $stmt->execute(['id' => $id, 'mahasiswa_id' => $mahasiswaId]);
        $krs = $stmt->fetch();

        if (!$krs) {
            flash('danger', 'Data KRS tidak ditemukan.');
            redirect('mahasiswa/krs.php');
        }
        if ($krs['status'] === 'disetujui') {
            flash('warning', 'KRS yang sudah disetujui tidak dapat dihapus.');
            redirect('mahasiswa/krs.php');
        }

        $stmt = db()->prepare('DELETE FROM krs WHERE id = :id AND mahasiswa_id = :mahasiswa_id');
        $stmt->execute(['id' => $id, 'mahasiswa_id' => $mahasiswaId]);
        flash('success', 'Mata kuliah ' . $krs['nama_mk'] . ' berhasil dihapus dari KRS.');
    } catch (Throwable $exception) {
        error_log('Delete KRS failed: ' . $exception->getMessage());
        flash('danger', 'KRS gagal dihapus.');
    }
    redirect('mahasiswa/krs.php');
}

if ($action !== 'create') {
    flash('danger', 'Aksi KRS tidak dikenali.');
    redirect('mahasiswa/krs.php');
}

$mataKuliahId = max(0, (int) post('mata_kuliah_id', 0));
if ($mataKuliahId <= 0) {
    flash('danger', 'Mata kuliah wajib dipilih.');
    redirect('mahasiswa/krs.php');
}

try {
    $stmt = db()->prepare(
        'SELECT mk.id, mk.kode_mk, mk.nama_mk, mk.sks, mk.prodi_id, d.nama AS nama_dosen
         FROM mata_kuliah mk
         LEFT JOIN dosen d ON d.id = mk.dosen_id
         WHERE mk.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $mataKuliahId]);
    $mk = $stmt->fetch();

    if (!$mk) {
        flash('danger', 'Mata kuliah tidak ditemukan.');
        redirect('mahasiswa/krs.php');
    }
    if ((int) $mk['prodi_id'] !== (int) $user['mahasiswa_prodi_id']) {
        flash('danger', 'Mata kuliah tersebut bukan bagian dari program studi Anda.');
        redirect('mahasiswa/krs.php');
    }
    if (empty($mk['nama_dosen'])) {
        flash('warning', 'Mata kuliah belum memiliki dosen pengampu sehingga belum dapat diambil.');
        redirect('mahasiswa/krs.php');
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM krs WHERE mahasiswa_id = :mahasiswa_id AND mata_kuliah_id = :mata_kuliah_id');
    $stmt->execute(['mahasiswa_id' => $mahasiswaId, 'mata_kuliah_id' => $mataKuliahId]);
    if ((int) $stmt->fetchColumn() > 0) {
        flash('warning', 'Mata kuliah ' . $mk['nama_mk'] . ' sudah pernah diajukan pada KRS Anda.');
        redirect('mahasiswa/krs.php');
    }

    $semester = semester_mahasiswa((int) $user['angkatan']);
    $stmt = db()->prepare(
        "SELECT COALESCE(SUM(mk.sks), 0)
         FROM krs k JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
         WHERE k.mahasiswa_id = :mahasiswa_id
           AND k.tahun_ajaran = :tahun_ajaran
           AND k.semester = :semester
           AND k.status IN ('menunggu', 'disetujui')"
    );
    $stmt->execute([
        'mahasiswa_id' => $mahasiswaId,
        'tahun_ajaran' => TAHUN_AJARAN_AKTIF,
        'semester' => $semester,
    ]);
    $currentSks = (int) $stmt->fetchColumn();

    if ($currentSks + (int) $mk['sks'] > MAX_SKS) {
        flash('danger', 'Penambahan ' . $mk['sks'] . ' SKS akan melebihi batas maksimal ' . MAX_SKS . ' SKS per semester. SKS terpakai saat ini: ' . $currentSks . '.');
        redirect('mahasiswa/krs.php');
    }

    $stmt = db()->prepare(
        "INSERT INTO krs (mahasiswa_id, mata_kuliah_id, semester, tahun_ajaran, status)
         VALUES (:mahasiswa_id, :mata_kuliah_id, :semester, :tahun_ajaran, 'menunggu')"
    );
    $stmt->execute([
        'mahasiswa_id' => $mahasiswaId,
        'mata_kuliah_id' => $mataKuliahId,
        'semester' => $semester,
        'tahun_ajaran' => TAHUN_AJARAN_AKTIF,
    ]);

    flash('success', 'Mata kuliah ' . $mk['nama_mk'] . ' berhasil diajukan. Menunggu validasi admin.');
} catch (PDOException $exception) {
    error_log('Create KRS failed: ' . $exception->getMessage());
    flash('danger', 'Mata kuliah gagal ditambahkan. Kemungkinan sudah ada pada KRS Anda.');
} catch (Throwable $exception) {
    error_log('Create KRS failed: ' . $exception->getMessage());
    flash('danger', 'Terjadi kesalahan saat menambahkan mata kuliah.');
}

redirect('mahasiswa/krs.php');
