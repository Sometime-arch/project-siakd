<?php
declare(strict_types=1);

$currentUser = current_user() ?? require_login();
$pageTitle = $pageTitle ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? APP_FULL_NAME;
$activeMenu = $activeMenu ?? '';
$flashes = pull_flashes();
$flashTypeMap = [
    'success' => 'success',
    'danger' => 'danger',
    'warning' => 'warning',
    'info' => 'info',
];
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="<?= e(APP_FULL_NAME) ?> kampus">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <title><?= e($pageTitle) ?> &middot; <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/svg+xml" href="<?= e(base_url('assets/img/logo.svg')) ?>">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="<?= e(base_url('assets/css/style.css')) ?>?v=1.0" rel="stylesheet">
</head>
<body>
<?php require __DIR__ . '/sidebar.php'; ?>
<div class="sidebar-backdrop" id="sidebarBackdrop"></div>

<div class="app-shell">
    <?php require __DIR__ . '/navbar.php'; ?>

    <main class="app-content">
        <div class="container-fluid px-3 px-lg-4 py-4">
            <?php foreach ($flashes as $flashMessage): ?>
                <?php
                $type = $flashTypeMap[$flashMessage['type']] ?? 'info';
                $icons = [
                    'success' => 'bi-check-circle',
                    'danger' => 'bi-exclamation-octagon',
                    'warning' => 'bi-exclamation-triangle',
                    'info' => 'bi-info-circle',
                ];
                ?>
                <div class="alert alert-<?= e($type) ?> alert-dismissible fade show d-flex align-items-start gap-2" role="alert" data-auto-dismiss>
                    <i class="bi <?= e($icons[$type]) ?> mt-1"></i>
                    <div class="flex-grow-1"><?= e($flashMessage['message']) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                </div>
            <?php endforeach; ?>
