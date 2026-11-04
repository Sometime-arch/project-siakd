<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

/**
 * Membuat URL absolut berdasarkan BASE_URL.
 */
function base_url(string $path = ''): string
{
    $path = ltrim($path, '/');

    return BASE_URL . ($path !== '' ? '/' . $path : '/');
}

/**
 * Redirect ke halaman internal.
 */
function redirect(string $path): never
{
    $location = preg_match('#^https?://#i', $path) ? $path : base_url($path);
    header('Location: ' . $location);
    exit;
}

/**
 * Escape output untuk mencegah XSS.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Menyimpan pesan flash ke session.
 */
function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

/**
 * Mengambil dan menghapus seluruh pesan flash.
 */
function pull_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);

    return is_array($flashes) ? $flashes : [];
}

/**
 * Menyimpan input lama dan error validasi agar form bisa diisi ulang.
 */
function store_form_state(array $old, array $errors = []): void
{
    unset(
        $old['password'],
        $old['password_confirmation'],
        $old['csrf_token'],
        $old['action']
    );

    $_SESSION['_old_input'] = $old;
    $_SESSION['_form_errors'] = $errors;
}

/**
 * Mengambil state form sekali pakai.
 *
 * @return array{old: array, errors: array}
 */
function pull_form_state(): array
{
    $state = [
        'old' => is_array($_SESSION['_old_input'] ?? null) ? $_SESSION['_old_input'] : [],
        'errors' => is_array($_SESSION['_form_errors'] ?? null) ? $_SESSION['_form_errors'] : [],
    ];

    unset($_SESSION['_old_input'], $_SESSION['_form_errors']);

    return $state;
}

/**
 * Mengambil nilai POST dan melakukan trim untuk string.
 */
function post(string $key, mixed $default = ''): mixed
{
    if (!array_key_exists($key, $_POST)) {
        return $default;
    }

    $value = $_POST[$key];

    return is_string($value) ? trim($value) : $value;
}

/**
 * Mengambil parameter GET dan melakukan trim untuk string.
 */
function query(string $key, mixed $default = ''): mixed
{
    if (!array_key_exists($key, $_GET)) {
        return $default;
    }

    $value = $_GET[$key];

    return is_string($value) ? trim($value) : $value;
}

/**
 * Nomor halaman yang sedang dibuka.
 */
function current_page(): int
{
    return max(1, (int) query('page', 1));
}

/**
 * Menghitung offset untuk query pagination.
 */
function page_offset(int $page, int $perPage): int
{
    return max(0, ($page - 1) * $perPage);
}

/**
 * Render navigasi pagination Bootstrap.
 */
function render_pagination(int $page, int $totalPages, array $queryParams = []): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $page = max(1, min($page, $totalPages));
    $self = basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $start = max(1, $page - 2);
    $end = min($totalPages, $page + 2);

    $buildUrl = static function (int $target) use ($self, $queryParams): string {
        $queryParams['page'] = $target;

        return $self . '?' . http_build_query($queryParams);
    };

    $html = '<nav aria-label="Navigasi halaman"><ul class="pagination pagination-sm mb-0">';
    $html .= '<li class="page-item ' . ($page <= 1 ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . e($buildUrl(max(1, $page - 1))) . '" aria-label="Sebelumnya">&laquo;</a></li>';

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . e($buildUrl(1)) . '">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $html .= '<li class="page-item ' . ($i === $page ? 'active' : '') . '">';
        $html .= '<a class="page-link" href="' . e($buildUrl($i)) . '">' . $i . '</a></li>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . e($buildUrl($totalPages)) . '">' . $totalPages . '</a></li>';
    }

    $html .= '<li class="page-item ' . ($page >= $totalPages ? 'disabled' : '') . '">';
    $html .= '<a class="page-link" href="' . e($buildUrl(min($totalPages, $page + 1))) . '" aria-label="Berikutnya">&raquo;</a></li>';
    $html .= '</ul></nav>';

    return $html;
}

/**
 * Inisial nama untuk avatar.
 */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $initials = '';

    foreach (array_slice($parts, 0, 2) as $part) {
        $initials .= mb_strtoupper(mb_substr($part, 0, 1));
    }

    return $initials !== '' ? $initials : 'U';
}

/**
 * Label role yang ramah dibaca.
 */
function role_label(string $role): string
{
    return match ($role) {
        'admin' => 'Administrator',
        'dosen' => 'Dosen',
        'mahasiswa' => 'Mahasiswa',
        default => ucfirst($role),
    };
}

/**
 * Badge status KRS.
 */
function status_badge(string $status): string
{
    return match ($status) {
        'disetujui' => '<span class="badge text-bg-success-subtle text-success-emphasis rounded-pill">Disetujui</span>',
        'ditolak' => '<span class="badge text-bg-danger-subtle text-danger-emphasis rounded-pill">Ditolak</span>',
        default => '<span class="badge text-bg-warning-subtle text-warning-emphasis rounded-pill">Menunggu</span>',
    };
}

/**
 * Konversi nilai angka menjadi nilai huruf.
 */
function nilai_huruf(float $score): string
{
    return match (true) {
        $score >= 85 => 'A',
        $score >= 80 => 'A-',
        $score >= 75 => 'B+',
        $score >= 70 => 'B',
        $score >= 65 => 'B-',
        $score >= 60 => 'C+',
        $score >= 55 => 'C',
        $score >= 40 => 'D',
        default => 'E',
    };
}

/**
 * Kelas CSS untuk menampilkan badge nilai huruf.
 */
function nilai_class(string $letter): string
{
    if (str_starts_with($letter, 'A')) {
        return 'grade-a';
    }
    if (str_starts_with($letter, 'B')) {
        return 'grade-b';
    }
    if (str_starts_with($letter, 'C')) {
        return 'grade-c';
    }
    if ($letter === 'D') {
        return 'grade-d';
    }

    return 'grade-e';
}

/**
 * Bobot nilai huruf untuk perhitungan IPS/IPK.
 */
function nilai_point(string $letter): float
{
    return match ($letter) {
        'A' => 4.0,
        'A-' => 3.7,
        'B+' => 3.3,
        'B' => 3.0,
        'B-' => 2.7,
        'C+' => 2.3,
        'C' => 2.0,
        'D' => 1.0,
        default => 0.0,
    };
}

/**
 * Nilai akhir: 30% tugas, 30% UTS, 40% UAS.
 */
function hitung_nilai_akhir(float $tugas, float $uts, float $uas): float
{
    return round(($tugas * 0.30) + ($uts * 0.30) + ($uas * 0.40), 2);
}

/**
 * Semester mahasiswa saat ini berdasarkan angkatan dan tahun ajaran aktif.
 */
function semester_mahasiswa(int $angkatan): int
{
    $tahunMulai = (int) substr(TAHUN_AJARAN_AKTIF, 0, 4);
    $selisih = max(0, $tahunMulai - $angkatan) * 2;
    $semester = $selisih + (SEMESTER_AKTIF === 'Ganjil' ? 1 : 2);

    return max(1, min(14, $semester));
}

/**
 * Format tanggal Indonesia.
 */
function tanggal_id(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    $bulan = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
    ];

    return date('d', $timestamp) . ' ' . $bulan[(int) date('n', $timestamp)] . ' ' . date('Y', $timestamp);
}

/**
 * Format jam dari TIME MySQL.
 */
function jam_id(?string $time): string
{
    return $time ? substr($time, 0, 5) : '-';
}

/**
 * Label hari yang konsisten.
 */
function daftar_hari(): array
{
    return ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
}
