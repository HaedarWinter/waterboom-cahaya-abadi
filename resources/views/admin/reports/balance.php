<?php
$pageTitle = 'Ringkasan Saldo';
$activeMenu = 'reports';
require __DIR__ . '/../../layouts/admin.php';
?>

<div class="l-container">
    <div class="l-stack l-stack--lg">
        <h1 class="c-heading c-heading--1">Ringkasan Saldo Kas</h1>
        <form method="GET" class="l-flex l-flex--wrap l-gap-sm finance-filter">
            <input type="hidden" name="report" value="balance">
            <label class="c-form-field">Rekening ID<input type="number" name="account_id" min="1" value="<?= e((string)$accountId) ?>"></label>
            <label class="c-form-field">Sampai tanggal<input type="date" name="end_date" value="<?= e($endDate) ?>"></label>
            <button class="c-button c-button--secondary" type="submit">Tampilkan</button>
        </form>
        <div class="c-card l-stack l-stack--md finance-panel finance-balance">
            <p>Total masuk: <strong>Rp <?= number_format((int)$data['total_in'], 0, ',', '.') ?></strong></p>
            <p>Total keluar: <strong>Rp <?= number_format((int)$data['total_out'], 0, ',', '.') ?></strong></p>
            <p>Saldo per <?= e($endDate) ?>: <strong>Rp <?= number_format((int)$data['balance'], 0, ',', '.') ?></strong></p>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../layouts/admin_footer.php'; ?>
