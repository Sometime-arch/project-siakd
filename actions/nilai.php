<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
$user = require_role('dosen');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('dosen/nilai.php');
}

verify_csrf_or_redirect('dosen/nilai.php');

$dosenId = (int) ($user['dosen_id'] ?? 0);
$action = (string) post('action');
$krsId = (int) post('krs_id', 0);

if ($dosenId <= 0 || $action !== 'save' || $krsId <= 0) {
    flash('danger', 'Permintaan tidak valid.');
    redirect('dosen/nilai.php');
}

$stmtKrs = db()->prepare(
    "SELECT k.id, k.status, k.mata_kuliah_id, mk.nama_mk, mk.dosen_id
     FROM krs k
     JOIN mata_kuliah mk ON mk.id = k.mata_kuliah_id
     WHERE k.id = :id
     LIMIT 1"
);
$stmtKrs->execute(['id' => $krsId]);
$krs = $stmtKrs->fetch();

if (!$krs || (int) $krs['dosen_id'] !== $dosenId) {
    flash('danger', 'Data KRS tidak ditemukan atau bukan bagian dari kelas Anda.');
    redirect('dosen/nilai.php');
}

if ($krs['status'] !== 'disetujui') {
    flash('warning', 'Nilai hanya dapat diinput untuk KRS yang telah disetujui.');
    redirect('dosen/nilai.php?mata_kuliah_id=' . (int) $krs['mata_kuliah_id']);
}

$nilai = [];
$errors = [];

foreach (['tugas' => 'Tugas', 'uts' => 'UTS', 'uas' => 'UAS'] as $field => $label) {
    $raw = post($field);

    if ($raw === '' || !is_numeric($raw)) {
        $errors[] = "Nilai {$label} wajib berupa angka.";
        continue;
    }

    $value = (float) $raw;

    if ($value < 0 || $value > 100) {
        $errors[] = "Nilai {$label} harus berada di antara 0 dan 100.";
        continue;
    }

    $nilai[$field] = round($value, 2);
}

if ($errors) {
    flash('danger', implode(' ', $errors));
    redirect('dosen/nilai.php?mata_kuliah_id=' . (int) $krs['mata_kuliah_id']);
}

$nilaiAkhir = hitung_nilai_akhir($nilai['tugas'], $nilai['uts'], $nilai['uas']);
$huruf = nilai_huruf($nilaiAkhir);

try {
    $stmtSave = db()->prepare(
        "INSERT INTO nilai (krs_id, tugas, uts, uas, nilai_akhir, nilai_huruf)
         VALUES (:krs_id, :tugas, :uts, :uas, :nilai_akhir, :nilai_huruf)
         ON DUPLICATE KEY UPDATE
            tugas = VALUES(tugas),
            uts = VALUES(uts),
            uas = VALUES(uas),
            nilai_akhir = VALUES(nilai_akhir),
            nilai_huruf = VALUES(nilai_huruf)"
    );
    $stmtSave->execute([
        'krs_id' => $krsId,
        'tugas' => $nilai['tugas'],
        'uts' => $nilai['uts'],
        'uas' => $nilai['uas'],
        'nilai_akhir' => $nilaiAkhir,
        'nilai_huruf' => $huruf,
    ]);

    flash('success', 'Nilai mahasiswa berhasil disimpan (' . $huruf . ' / ' . number_format($nilaiAkhir, 2, ',', '.') . ').');
} catch (Throwable $exception) {
    error_log('Simpan nilai failed: ' . $exception->getMessage());
    flash('danger', 'Nilai gagal disimpan. Silakan coba lagi.');
}

redirect('dosen/nilai.php?mata_kuliah_id=' . (int) $krs['mata_kuliah_id']);
