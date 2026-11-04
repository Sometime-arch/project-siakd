-- ============================================================
-- SIAKD - Sistem Informasi Akademik Kampus
-- Database schema + data dummy
-- Kompatibel dengan MySQL 8.x / MariaDB 10.4+
--
-- Import melalui phpMyAdmin:
--   Pilih tab Import -> pilih file database.sql -> Go
-- Atau melalui terminal:
--   mysql -u root -p < database.sql
--
-- Password semua akun demo: password123
-- ============================================================

CREATE DATABASE IF NOT EXISTS `siakd`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `siakd`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `nilai`;
DROP TABLE IF EXISTS `krs`;
DROP TABLE IF EXISTS `jadwal`;
DROP TABLE IF EXISTS `mata_kuliah`;
DROP TABLE IF EXISTS `mahasiswa`;
DROP TABLE IF EXISTS `dosen`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `prodi`;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Tabel program studi
-- ------------------------------------------------------------
CREATE TABLE `prodi` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kode_prodi` VARCHAR(10) NOT NULL,
    `nama_prodi` VARCHAR(100) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_prodi_kode` (`kode_prodi`),
    UNIQUE KEY `uq_prodi_nama` (`nama_prodi`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel users (akun login seluruh role)
-- ------------------------------------------------------------
CREATE TABLE `users` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nama` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'dosen', 'mahasiswa') NOT NULL,
    `status` ENUM('aktif', 'nonaktif') NOT NULL DEFAULT 'aktif',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel program studi (relasi users untuk dosen)
-- ------------------------------------------------------------
CREATE TABLE `dosen` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `nidn` VARCHAR(20) NOT NULL,
    `nama` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NOT NULL,
    `prodi_id` INT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_dosen_user` (`user_id`),
    UNIQUE KEY `uq_dosen_nidn` (`nidn`),
    UNIQUE KEY `uq_dosen_email` (`email`),
    KEY `idx_dosen_prodi` (`prodi_id`),
    CONSTRAINT `fk_dosen_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_dosen_prodi` FOREIGN KEY (`prodi_id`) REFERENCES `prodi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel mahasiswa
-- ------------------------------------------------------------
CREATE TABLE `mahasiswa` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `nim` VARCHAR(20) NOT NULL,
    `nama` VARCHAR(100) NOT NULL,
    `email` VARCHAR(120) NOT NULL,
    `jenis_kelamin` ENUM('L', 'P') NOT NULL,
    `alamat` VARCHAR(255) NOT NULL,
    `prodi_id` INT UNSIGNED NOT NULL,
    `angkatan` YEAR NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_mahasiswa_user` (`user_id`),
    UNIQUE KEY `uq_mahasiswa_nim` (`nim`),
    UNIQUE KEY `uq_mahasiswa_email` (`email`),
    KEY `idx_mahasiswa_prodi` (`prodi_id`),
    KEY `idx_mahasiswa_angkatan` (`angkatan`),
    CONSTRAINT `fk_mahasiswa_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_mahasiswa_prodi` FOREIGN KEY (`prodi_id`) REFERENCES `prodi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel mata kuliah
-- ------------------------------------------------------------
CREATE TABLE `mata_kuliah` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kode_mk` VARCHAR(15) NOT NULL,
    `nama_mk` VARCHAR(120) NOT NULL,
    `sks` TINYINT UNSIGNED NOT NULL,
    `semester` TINYINT UNSIGNED NOT NULL,
    `prodi_id` INT UNSIGNED NOT NULL,
    `dosen_id` INT UNSIGNED NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_mata_kuliah_kode` (`kode_mk`),
    KEY `idx_mata_kuliah_prodi` (`prodi_id`),
    KEY `idx_mata_kuliah_dosen` (`dosen_id`),
    CONSTRAINT `fk_mata_kuliah_prodi` FOREIGN KEY (`prodi_id`) REFERENCES `prodi` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_mata_kuliah_dosen` FOREIGN KEY (`dosen_id`) REFERENCES `dosen` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel KRS
-- Satu mahasiswa hanya boleh mengambil mata kuliah yang sama satu kali.
-- ------------------------------------------------------------
CREATE TABLE `krs` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `mahasiswa_id` INT UNSIGNED NOT NULL,
    `mata_kuliah_id` INT UNSIGNED NOT NULL,
    `semester` TINYINT UNSIGNED NOT NULL,
    `tahun_ajaran` VARCHAR(9) NOT NULL,
    `status` ENUM('menunggu', 'disetujui', 'ditolak') NOT NULL DEFAULT 'menunggu',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_krs_mahasiswa_mk` (`mahasiswa_id`, `mata_kuliah_id`),
    KEY `idx_krs_mk` (`mata_kuliah_id`),
    KEY `idx_krs_status` (`status`),
    KEY `idx_krs_periode` (`tahun_ajaran`, `semester`),
    CONSTRAINT `fk_krs_mahasiswa` FOREIGN KEY (`mahasiswa_id`) REFERENCES `mahasiswa` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_krs_mata_kuliah` FOREIGN KEY (`mata_kuliah_id`) REFERENCES `mata_kuliah` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel nilai (satu nilai per baris KRS)
-- ------------------------------------------------------------
CREATE TABLE `nilai` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `krs_id` INT UNSIGNED NOT NULL,
    `tugas` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `uts` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `uas` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `nilai_akhir` DECIMAL(5,2) NOT NULL DEFAULT 0,
    `nilai_huruf` VARCHAR(2) NOT NULL DEFAULT 'E',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_nilai_krs` (`krs_id`),
    CONSTRAINT `fk_nilai_krs` FOREIGN KEY (`krs_id`) REFERENCES `krs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- Tabel jadwal
-- ------------------------------------------------------------
CREATE TABLE `jadwal` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `mata_kuliah_id` INT UNSIGNED NOT NULL,
    `hari` ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu') NOT NULL,
    `jam_mulai` TIME NOT NULL,
    `jam_selesai` TIME NOT NULL,
    `ruang` VARCHAR(50) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_jadwal_mk` (`mata_kuliah_id`),
    KEY `idx_jadwal_hari` (`hari`, `jam_mulai`),
    CONSTRAINT `fk_jadwal_mata_kuliah` FOREIGN KEY (`mata_kuliah_id`) REFERENCES `mata_kuliah` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- DATA DUMMY
-- Password hash di bawah adalah hash password123
-- ============================================================

INSERT INTO `prodi` (`id`, `kode_prodi`, `nama_prodi`) VALUES
(1, 'TI', 'Teknik Informatika'),
(2, 'SI', 'Sistem Informasi'),
(3, 'MI', 'Manajemen Informatika');

INSERT INTO `users` (`id`, `nama`, `email`, `password`, `role`, `status`) VALUES
(1, 'Admin SIAKD', 'admin@siakd.test', '$2y$10$kQJOQgdmrM2fS3s7AHE5vei9SaE0qQSyGa4MrgzlY4zFAZgQ8FeXS', 'admin', 'aktif'),
(2, 'Dr. Budi Santoso, S.Kom., M.Kom.', 'dosen@siakd.test', '$2y$10$kQJOQgdmrM2fS3s7AHE5vei9SaE0qQSyGa4MrgzlY4zFAZgQ8FeXS', 'dosen', 'aktif'),
(3, 'Rina Amelia', 'mahasiswa@siakd.test', '$2y$10$kQJOQgdmrM2fS3s7AHE5vei9SaE0qQSyGa4MrgzlY4zFAZgQ8FeXS', 'mahasiswa', 'aktif'),
(4, 'Siti Rahmawati, S.Kom., M.T.', 'siti@siakd.test', '$2y$10$kQJOQgdmrM2fS3s7AHE5vei9SaE0qQSyGa4MrgzlY4zFAZgQ8FeXS', 'dosen', 'aktif'),
(5, 'Andi Pratama', 'andi@siakd.test', '$2y$10$kQJOQgdmrM2fS3s7AHE5vei9SaE0qQSyGa4MrgzlY4zFAZgQ8FeXS', 'mahasiswa', 'aktif'),
(6, 'Fajar Nugroho', 'fajar@siakd.test', '$2y$10$kQJOQgdmrM2fS3s7AHE5vei9SaE0qQSyGa4MrgzlY4zFAZgQ8FeXS', 'mahasiswa', 'aktif'),
(7, 'Dewi Lestari', 'dewi@siakd.test', '$2y$10$kQJOQgdmrM2fS3s7AHE5vei9SaE0qQSyGa4MrgzlY4zFAZgQ8FeXS', 'mahasiswa', 'aktif'),
(8, 'Rizky Hidayat', 'rizky@siakd.test', '$2y$10$kQJOQgdmrM2fS3s7AHE5vei9SaE0qQSyGa4MrgzlY4zFAZgQ8FeXS', 'mahasiswa', 'aktif'),
(9, 'Putri Maharani', 'putri@siakd.test', '$2y$10$kQJOQgdmrM2fS3s7AHE5vei9SaE0qQSyGa4MrgzlY4zFAZgQ8FeXS', 'mahasiswa', 'aktif'),
(10, 'Agus Wijaya, S.T., M.T.', 'agus@siakd.test', '$2y$10$kQJOQgdmrM2fS3s7AHE5vei9SaE0qQSyGa4MrgzlY4zFAZgQ8FeXS', 'dosen', 'aktif');

INSERT INTO `dosen` (`id`, `user_id`, `nidn`, `nama`, `email`, `prodi_id`) VALUES
(1, 2, '0412018501', 'Dr. Budi Santoso, S.Kom., M.Kom.', 'dosen@siakd.test', 1),
(2, 4, '0505068802', 'Siti Rahmawati, S.Kom., M.T.', 'siti@siakd.test', 2),
(3, 10, '0712089003', 'Agus Wijaya, S.T., M.T.', 'agus@siakd.test', 3);

INSERT INTO `mahasiswa` (`id`, `user_id`, `nim`, `nama`, `email`, `jenis_kelamin`, `alamat`, `prodi_id`, `angkatan`) VALUES
(1, 3, '2024010001', 'Rina Amelia', 'mahasiswa@siakd.test', 'P', 'Jl. Merdeka No. 12, Bandung', 1, 2024),
(2, 5, '2025010002', 'Andi Pratama', 'andi@siakd.test', 'L', 'Jl. Cendrawasih No. 8, Bandung', 2, 2025),
(3, 6, '2025010003', 'Fajar Nugroho', 'fajar@siakd.test', 'L', 'Jl. Kenanga No. 21, Cimahi', 1, 2025),
(4, 7, '2024010004', 'Dewi Lestari', 'dewi@siakd.test', 'P', 'Jl. Melati No. 5, Bandung', 2, 2024),
(5, 8, '2023010005', 'Rizky Hidayat', 'rizky@siakd.test', 'L', 'Jl. Diponegoro No. 17, Bandung', 3, 2023),
(6, 9, '2023010006', 'Putri Maharani', 'putri@siakd.test', 'P', 'Jl. Ahmad Yani No. 33, Bandung', 1, 2023);

INSERT INTO `mata_kuliah` (`id`, `kode_mk`, `nama_mk`, `sks`, `semester`, `prodi_id`, `dosen_id`) VALUES
(1, 'IF101', 'Algoritma dan Pemrograman', 3, 1, 1, 1),
(2, 'IF201', 'Struktur Data', 3, 3, 1, 1),
(3, 'IF202', 'Basis Data', 3, 3, 1, 1),
(4, 'IF301', 'Pemrograman Web', 3, 5, 1, 2),
(5, 'IF302', 'Jaringan Komputer', 3, 5, 1, 3),
(6, 'IF303', 'Kecerdasan Buatan', 3, 5, 1, 2),
(7, 'SI201', 'Sistem Informasi Manajemen', 3, 3, 2, 2),
(8, 'SI301', 'Analisis dan Perancangan Sistem', 3, 5, 2, 2),
(9, 'MKU101', 'Pendidikan Pancasila', 2, 1, 1, 1),
(10, 'IF304', 'Sistem Operasi', 3, 5, 1, 1),
(11, 'IF305', 'Pemrograman Mobile', 3, 6, 1, 3),
(12, 'SI302', 'Data Warehouse', 3, 6, 2, 2),
(13, 'MKU102', 'Bahasa Indonesia', 2, 1, 2, 2);

INSERT INTO `krs` (`id`, `mahasiswa_id`, `mata_kuliah_id`, `semester`, `tahun_ajaran`, `status`, `created_at`) VALUES
(1, 1, 1, 1, '2024/2025', 'disetujui', '2024-09-02 09:10:00'),
(2, 1, 2, 3, '2025/2026', 'disetujui', '2025-09-01 10:15:00'),
(3, 1, 3, 3, '2025/2026', 'disetujui', '2025-09-01 10:16:00'),
(4, 1, 9, 1, '2024/2025', 'disetujui', '2024-09-02 09:12:00'),
(5, 1, 4, 5, '2026/2027', 'disetujui', '2026-09-01 08:30:00'),
(6, 1, 5, 5, '2026/2027', 'disetujui', '2026-09-01 08:31:00'),
(7, 1, 10, 5, '2026/2027', 'disetujui', '2026-09-01 08:32:00'),
(8, 1, 6, 5, '2026/2027', 'menunggu', '2026-10-28 14:05:00'),
(9, 2, 7, 3, '2026/2027', 'disetujui', '2026-09-02 09:20:00'),
(10, 2, 2, 3, '2026/2027', 'disetujui', '2026-09-02 09:21:00'),
(11, 2, 9, 1, '2025/2026', 'disetujui', '2025-09-03 11:00:00'),
(12, 2, 8, 3, '2026/2027', 'menunggu', '2026-10-30 10:45:00'),
(13, 3, 2, 3, '2026/2027', 'menunggu', '2026-10-29 16:20:00'),
(14, 3, 9, 1, '2025/2026', 'disetujui', '2025-09-04 08:50:00'),
(15, 4, 3, 5, '2026/2027', 'disetujui', '2026-09-03 13:10:00'),
(16, 4, 7, 3, '2025/2026', 'disetujui', '2025-09-05 09:30:00'),
(17, 5, 1, 7, '2026/2027', 'disetujui', '2026-09-04 10:00:00'),
(18, 5, 5, 7, '2026/2027', 'disetujui', '2026-09-04 10:01:00'),
(19, 6, 4, 7, '2026/2027', 'disetujui', '2026-09-05 14:15:00'),
(20, 6, 10, 7, '2026/2027', 'disetujui', '2026-09-05 14:16:00');

INSERT INTO `nilai` (`krs_id`, `tugas`, `uts`, `uas`, `nilai_akhir`, `nilai_huruf`) VALUES
(1, 90.00, 88.00, 92.00, 90.20, 'A'),
(2, 85.00, 80.00, 84.00, 83.10, 'A-'),
(3, 80.00, 75.00, 79.00, 78.10, 'B+'),
(4, 88.00, 86.00, 90.00, 88.00, 'A'),
(14, 82.00, 84.00, 86.00, 84.20, 'A-'),
(16, 75.00, 78.00, 80.00, 78.20, 'B+'),
(17, 70.00, 68.00, 72.00, 70.20, 'B'),
(18, 60.00, 62.00, 65.00, 62.60, 'C+'),
(19, 85.00, 90.00, 88.00, 87.70, 'A'),
(20, 55.00, 58.00, 60.00, 57.90, 'C');

INSERT INTO `jadwal` (`id`, `mata_kuliah_id`, `hari`, `jam_mulai`, `jam_selesai`, `ruang`) VALUES
(1, 1, 'Senin', '08:00:00', '10:30:00', 'Ruang 101'),
(2, 2, 'Selasa', '08:00:00', '10:30:00', 'Lab. Data'),
(3, 3, 'Rabu', '13:00:00', '15:30:00', 'Lab. Basis Data'),
(4, 4, 'Kamis', '08:00:00', '10:30:00', 'Lab. Web'),
(5, 5, 'Jumat', '08:00:00', '10:30:00', 'Ruang 202'),
(6, 6, 'Senin', '13:00:00', '15:30:00', 'Ruang 303'),
(7, 7, 'Selasa', '13:00:00', '15:30:00', 'Ruang 204'),
(8, 8, 'Rabu', '08:00:00', '10:30:00', 'Ruang 205'),
(9, 9, 'Kamis', '10:40:00', '12:20:00', 'Ruang 101'),
(10, 10, 'Jumat', '13:00:00', '15:30:00', 'Ruang 202'),
(11, 11, 'Senin', '15:40:00', '18:10:00', 'Lab. Mobile'),
(12, 12, 'Selasa', '15:40:00', '18:10:00', 'Lab. Data'),
(13, 13, 'Jumat', '10:40:00', '12:20:00', 'Ruang 204');

SET FOREIGN_KEY_CHECKS = 1;
