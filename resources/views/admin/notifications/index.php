<?php
$pageTitle = 'Notifikasi & Antrian Kirim';
$activeMenu = 'notifications';
require __DIR__ . '/../../layouts/admin.php';
?>

<div class="l-container">
    <div class="l-stack l-stack--lg">
        <h1 class="c-heading c-heading--1">Notifikasi & Antrian Pesan</h1>

        <div class="l-grid l-grid--4 l-gap-md" style="margin-bottom: 2rem;">
            <div class="c-card">
                <div class="l-stack l-stack--sm" style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; color: #f97316;">
                        <?= number_format($stats['PENDING'] ?? 0) ?>
                    </div>
                    <div style="font-size: 0.9rem; color: #666;">Pending</div>
                </div>
            </div>
            <div class="c-card">
                <div class="l-stack l-stack--sm" style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; color: #1f9d55;">
                        <?= number_format($stats['SENT'] ?? 0) ?>
                    </div>
                    <div style="font-size: 0.9rem; color: #666;">Terkirim</div>
                </div>
            </div>
            <div class="c-card">
                <div class="l-stack l-stack--sm" style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; color: #ef4444;">
                        <?= number_format($stats['FAILED'] ?? 0) ?>
                    </div>
                    <div style="font-size: 0.9rem; color: #666;">Gagal</div>
                </div>
            </div>
            <div class="c-card">
                <div class="l-stack l-stack--sm" style="text-align: center;">
                    <div style="font-size: 2rem; font-weight: bold; color: #666;">
                        <?= number_format($stats['DEAD'] ?? 0) ?>
                    </div>
                    <div style="font-size: 0.9rem; color: #666;">Dead Letter</div>
                </div>
            </div>
        </div>

        <div class="c-card">
            <div style="margin-bottom: 1.5rem; display: flex; gap: 1rem;">
                <form method="GET" style="display: inline;">
                    <input type="hidden" name="action" value="process">
                    <button type="submit" class="c-button c-button--primary" onclick="return confirm('Proses antrian sekarang?')">
                        <svg class="c-button__icon" aria-hidden="true"><use xlink:href="#icon-send"></use></svg>
                        Proses Antrian
                    </button>
                </form>
                <form method="GET" style="display: inline;">
                    <input type="hidden" name="action" value="retry">
                    <button type="submit" class="c-button c-button--secondary" onclick="return confirm('Reset pesan gagal ke PENDING?')">
                        <svg class="c-button__icon" aria-hidden="true"><use xlink:href="#icon-refresh"></use></svg>
                        Retry Gagal
                    </button>
                </form>
                <a href="?action=index" class="c-button c-button--ghost">Refresh</a>
            </div>

            <div style="padding: 1rem; background: #f5f5f5; border-radius: 5px; font-size: 0.9rem; color: #666;">
                <strong>Driver Mode:</strong> <?= getenv('WA_DRIVER') === 'fonnte' ? '🔌 Fonnte API' : '📝 Log (Demo)' ?><br>
                Notifikasi diproses melalui antrian. Driver log (demo) menampilkan pesan di error log, tidak mengirim WA asli.
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>