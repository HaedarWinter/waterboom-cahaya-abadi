<?php
$pageTitle = 'Laporan Pendapatan';
$activeMenu = 'reports';
require __DIR__ . '/../../layouts/admin.php';
?>

<div class="l-container">
    <div class="l-stack l-stack--lg">
        <h1 class="c-heading c-heading--1">Pendapatan per Jenis Tiket</h1>

        <form method="GET" class="l-flex l-flex--wrap l-gap-sm" style="margin-bottom: 1rem;">
            <input type="hidden" name="report" value="revenue">
            <div class="c-form-field">
                <label class="c-label" for="start_date">Dari</label>
                <input type="date" id="start_date" name="start_date" class="c-input" value="<?= e($startDate) ?>">
            </div>
            <div class="c-form-field">
                <label class="c-label" for="end_date">Sampai</label>
                <input type="date" id="end_date" name="end_date" class="c-input" value="<?= e($endDate) ?>">
            </div>
            <button type="submit" class="c-button c-button--secondary" style="align-self: flex-end;">Filter</button>
        </form>

        <div class="c-card">
            <table class="c-table">
                <thead>
                    <tr>
                        <th>Jenis Tiket</th>
                        <th class="c-table__numeric">Qty Terjual</th>
                        <th class="c-table__numeric">Pendapatan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $totalQty = 0; $totalRev = 0; foreach ($data as $row): 
                        $totalQty += $row['qty_sold'];
                        $totalRev += $row['revenue'];
                    ?>
                    <tr>
                        <td><?= e($row['ticket_type']) ?></td>
                        <td class="c-table__numeric"><?= number_format($row['qty_sold'], 0, ',', '.') ?></td>
                        <td class="c-table__numeric">Rp <?= number_format($row['revenue'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($data)): ?>
                    <tr><td colspan="3" class="c-table__empty">Tidak ada data.</td></tr>
                    <?php else: ?>
                    <tr class="c-table__footer">
                        <td class="u-text-bold">Total</td>
                        <td class="c-table__numeric u-text-bold"><?= number_format($totalQty, 0, ',', '.') ?></td>
                        <td class="c-table__numeric u-text-bold">Rp <?= number_format($totalRev, 0, ',', '.') ?></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>