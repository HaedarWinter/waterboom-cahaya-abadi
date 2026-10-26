<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Admin') ?> — Waterboom Cahaya Abadi</title>
    <link rel="icon" href="<?= e(base_url('favicon.ico')) ?>" type="image/x-icon">
    <link rel="stylesheet" href="<?= e(asset_url('assets/dist/css/app.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/admin_style.css')) ?>">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="admin-body">
    <?php require dirname(__DIR__) . '/components/demo-banner.php'; ?>
    <?php require dirname(__DIR__, 3) . '/admin/includes/sidebar.php'; ?>

    <main class="admin-main">
        <header class="admin-header">
            <button class="sidebar-toggle" type="button" aria-label="Buka navigasi" aria-controls="sidebar">
                <i data-lucide="menu" aria-hidden="true"></i>
            </button>
            <div class="header-title">
                <h1 class="page-heading"><?= e($pageTitle ?? 'Admin') ?></h1>
                <nav class="breadcrumb" aria-label="Breadcrumb">
                    <a href="<?= e(base_url('admin/index.php')) ?>">Dashboard</a>
                    <span aria-hidden="true">/</span>
                    <span aria-current="page"><?= e($pageTitle ?? 'Admin') ?></span>
                </nav>
            </div>
            <span class="admin-header__user"><?= e($_SESSION['username'] ?? 'Admin') ?></span>
        </header>

        <div class="admin-content">
            <?php if (!empty($message)): ?>
            <div class="c-alert c-alert--<?= e($messageType ?? 'info') ?>" role="alert">
                <?= e($message) ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['flash'])): ?>
            <div class="c-alert c-alert--success" role="status">
                <?= e($_SESSION['flash']) ?>
                <?php unset($_SESSION['flash']); ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($_SESSION['error'])): ?>
            <div class="c-alert c-alert--danger" role="alert">
                <?= e($_SESSION['error']) ?>
                <?php unset($_SESSION['error']); ?>
            </div>
            <?php endif; ?>
