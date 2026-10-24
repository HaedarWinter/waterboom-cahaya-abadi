<?php
$pageTitle = 'Laporan Pengeluaran';
$activeMenu = 'reports';
require __DIR__ . '/../../layouts/admin.php';
?>

<div class="l-container">
    <div class="l-stack l-stack--lg">
        <h1 class="c-heading c-heading--1">Pengeluaran per Kategori</h1>
        <form method="GET" class="l-flex l-flex--wrap l-gap-sm finance-filter">
            <input type="hidden" name="report" value="expenses">
            <label class="c-form-field">Dari<input type="date" name="start_date" value="<?= e($startDate) ?>"></label>
            <label class="c-form-field">Sampai<input type="date" name="end_date" value="<?= e($endDate) ?>"></label>
            <button class="c-button c-button--secondary" type="submit">Terapkan Filter</button>
        </form>
        <div class="c-card finance-panel">
            <table class="c-table">
                <thead><tr><th>Kategori</th><th>Transaksi</th><th class="c-table__numeric">Total</th></tr></thead>
                <tbody>
                    <?php foreach ($data as $row): ?>
                    <tr>
                        <td><?= e($row['category']) ?></td>
                        <td><?= number_format((int)$row['count'], 0, ',', '.') ?></td>
                        <td class="c-table__numeric">Rp <?= number_format((int)$row['total'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!$data): ?><tr><td colspan="3" class="c-table__empty">Tidak ada data.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../layouts/admin_footer.php'; ?>
