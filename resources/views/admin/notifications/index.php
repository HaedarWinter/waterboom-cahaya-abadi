<?php
$pageTitle = 'Notifikasi';
$activeMenu = 'notifications';
require __DIR__ . '/../../layouts/admin.php';
?>

<div class="l-container notification-page">
    <div class="l-stack l-stack--lg">
        <?php if (!$schemaReady): ?>
        <section class="c-card notification-setup" role="status">
            <span class="notification-setup__icon" aria-hidden="true">
                <i data-lucide="database"></i>
            </span>
            <div class="notification-setup__content">
                <p class="notification-setup__eyebrow">Perlu penyiapan database</p>
                <h2 class="notification-setup__title">Antrean notifikasi belum aktif</h2>
                <p class="notification-setup__description">
                    Tabel notifikasi belum tersedia. Terapkan migrasi
                    <code>V4_006_notifications.sql</code>, lalu muat ulang halaman ini.
                </p>
            </div>
            <a href="?action=index" class="c-button c-button--secondary">Muat Ulang</a>
        </section>
        <?php else: ?>
        <div class="finance-stats notification-stats">
            <div class="c-card finance-stat-card">
                <div class="finance-stat-value finance-stat-value--pending"><?= number_format($stats['PENDING'] ?? 0) ?></div>
                <div class="finance-stat-label">Menunggu</div>
            </div>
            <div class="c-card finance-stat-card">
                <div class="finance-stat-value finance-stat-value--sent"><?= number_format($stats['SENT'] ?? 0) ?></div>
                <div class="finance-stat-label">Terkirim</div>
            </div>
            <div class="c-card finance-stat-card">
                <div class="finance-stat-value finance-stat-value--failed"><?= number_format($stats['FAILED'] ?? 0) ?></div>
                <div class="finance-stat-label">Gagal</div>
            </div>
            <div class="c-card finance-stat-card">
                <div class="finance-stat-value finance-stat-value--dead"><?= number_format($stats['DEAD'] ?? 0) ?></div>
                <div class="finance-stat-label">Gagal permanen</div>
            </div>
        </div>

        <section class="c-card notification-console">
            <div class="notification-console__intro">
                <div>
                    <h2 class="notification-console__title">Pengiriman pesan</h2>
                    <p class="notification-console__description">Kelola antrean pesan WhatsApp secara manual.</p>
                </div>
                <span class="notification-driver">
                    <?= getenv('WA_DRIVER') === 'fonnte' ? 'Fonnte API' : 'Mode demo · Log' ?>
                </span>
            </div>
            <div class="finance-actions">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="process">
                    <button type="submit" class="c-button c-button--primary" onclick="return confirm('Proses antrian sekarang?')">
                        <svg class="c-button__icon" aria-hidden="true"><use xlink:href="#icon-send"></use></svg>
                        Proses Antrian
                    </button>
                </form>
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="retry">
                    <button type="submit" class="c-button c-button--secondary" onclick="return confirm('Reset pesan gagal ke PENDING?')">
                        <svg class="c-button__icon" aria-hidden="true"><use xlink:href="#icon-refresh"></use></svg>
                        Retry Gagal
                    </button>
                </form>
                <a href="?action=index" class="c-button c-button--ghost">Muat Ulang</a>
            </div>
            <p class="finance-driver-notice">
                Mode log hanya mencatat pesan di log aplikasi dan tidak mengirim WhatsApp sungguhan.
            </p>
        </section>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/admin_footer.php'; ?>
