<?php
$pageTitle = 'Laporan Arus Kas';
$activeMenu = 'reports';
require __DIR__ . '/../../layouts/admin.php';
?>

<div class="l-container">
    <div class="l-stack l-stack--lg">
        <h1 class="c-heading c-heading--1">Arus Kas Harian</h1>

        <form method="GET" class="l-flex l-flex--wrap l-gap-sm finance-filter">
            <input type="hidden" name="report" value="cash_flow">
            <div class="c-form-field">
                <label class="c-label" for="start_date">Dari</label>
                <input type="date" id="start_date" name="start_date" class="c-input" value="<?= e($startDate) ?>">
            </div>
            <div class="c-form-field">
                <label class="c-label" for="end_date">Sampai</label>
                <input type="date" id="end_date" name="end_date" class="c-input" value="<?= e($endDate) ?>">
            </div>
            <div class="c-form-field">
                <label class="c-label" for="account_id">Rekening</label>
                <select id="account_id" name="account_id" class="c-select">
                    <option value="0">Semua</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?= $acc['id'] ?>"<?= $accountId == $acc['id'] ? ' selected' : '' ?>><?= e($acc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="c-button c-button--secondary">Terapkan Filter</button>
        </form>

        <div class="c-card finance-panel">
            <table class="c-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th class="c-table__numeric">Masuk</th>
                        <th class="c-table__numeric">Keluar</th>
                        <th class="c-table__numeric">Saldo Harian</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $running = 0; foreach ($data as $row): 
                        $running += $row['total_in'] - $row['total_out'];
                    ?>
                    <tr>
                        <td><?= e(date('d/m/Y', strtotime($row['entry_date']))) ?></td>
                        <td class="c-table__numeric">Rp <?= number_format($row['total_in'], 0, ',', '.') ?></td>
                        <td class="c-table__numeric">Rp <?= number_format($row['total_out'], 0, ',', '.') ?></td>
                        <td class="c-table__numeric u-text-bold">Rp <?= number_format($running, 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($data)): ?>
                    <tr><td colspan="4" class="c-table__empty">Tidak ada data.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/admin_footer.php'; ?>
