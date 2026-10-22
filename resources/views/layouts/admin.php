<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle ?? 'Admin') ?> — Waterboom Cahaya Abadi</title>
    <link rel="stylesheet" href="<?= base_url('assets/dist/css/app.min.css') ?>">
</head>
<body>
    <?php if (getenv('APP_ENV') === 'demo'): ?>
    <div style="background: #fff3cd; padding: 0.75rem 1rem; text-align: center; font-weight: 600; border-bottom: 2px solid #ffc107;">
        ⚠️ MODE DEMO — Data akan direset berkala
    </div>
    <?php endif; ?>

    <div class="l-admin-layout">
        <header class="c-header">
            <div class="l-container">
                <div class="l-flex l-flex--between l-flex--center">
                    <h1 class="c-heading c-heading--2">Waterboom Cahaya Abadi</h1>
                    <div class="l-flex l-flex--center l-gap-md">
                        <span><?= e($_SESSION['admin_username'] ?? 'Admin') ?></span>
                        <a href="<?= base_url('logout.php') ?>" class="c-button c-button--secondary c-button--sm">Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="l-main">
            <aside class="c-sidebar">
                <nav class="c-sidebar__nav">
                    <a href="<?= base_url('admin/dashboard.php') ?>" class="c-sidebar__link <?= ($activeMenu === 'dashboard' ? 'is-active' : '') ?>">Dashboard</a>
                    <a href="<?= base_url('admin/closings/') ?>" class="c-sidebar__link <?= ($activeMenu === 'closings' ? 'is-active' : '') ?>">Tutup Harian</a>
                    <a href="<?= base_url('admin/expenses/') ?>" class="c-sidebar__link <?= ($activeMenu === 'expenses' ? 'is-active' : '') ?>">Pengeluaran</a>
                    <a href="<?= base_url('admin/reports/') ?>" class="c-sidebar__link <?= ($activeMenu === 'reports' ? 'is-active' : '') ?>">Laporan</a>
                    <a href="<?= base_url('admin/notifications/') ?>" class="c-sidebar__link <?= ($activeMenu === 'notifications' ? 'is-active' : '') ?>">Notifikasi</a>
                    <a href="<?= base_url('admin/templates/') ?>" class="c-sidebar__link <?= ($activeMenu === 'templates' ? 'is-active' : '') ?>">Template</a>
                </nav>
            </aside>

            <section class="l-content">
                <?php if (!empty($message)): ?>
                <div class="c-alert c-alert--<?= e($messageType ?? 'info') ?>" role="alert">
                    <?= e($message) ?>
                </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script src="<?= base_url('assets/dist/js/app.min.js') ?>"></script>
</body>
</html>
