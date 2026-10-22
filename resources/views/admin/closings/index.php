<?php
$pageTitle = 'Tutup Harian';
$activeMenu = 'closings';
require __DIR__ . '/../../layouts/admin.php';
?>

<div class="l-container">
    <div class="l-stack l-stack--lg">
        <div class="l-flex l-flex--between l-flex--wrap">
            <h1 class="c-heading c-heading--1">Tutup Harian</h1>
            <a href="?view=form" class="c-button c-button--primary">
                <svg class="c-button__icon" aria-hidden="true"><use xlink:href="#icon-plus"></use></svg>
                Tutup Periode Baru
            </a>
        </div>

        <form method="GET" class="l-flex l-flex--wrap l-gap-sm" style="margin-bottom: 1rem;">
            <input type="hidden" name="view" value="index">
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
                        <th>Tanggal</th>
                        <th>Rekening</th>
                        <th class="c-table__numeric">Saldo Awal</th>
                        <th class="c-table__numeric">Total Masuk</th>
                        <th class="c-table__numeric">Total Keluar</th>
                        <th class="c-table__numeric">Saldo Sistem</th>
                        <th class="c-table__numeric">Fisik</th>
                        <th class="c-table__numeric">Selisih</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($closings as $cl): ?>
                    <tr>
                        <td><?= e(date('d/m/Y', strtotime($cl['closing_date']))) ?></td>
                        <td>
                            <?php
                            $conn = \App\Core\Database::getInstance()->getConnection();
                            $acc = $conn->query("SELECT name FROM cash_accounts WHERE id = " . (int)$cl['cash_account_id'])->fetch_assoc();
                            echo e($acc['name'] ?? '-');
                            ?>
                        </td>
                        <td class="c-table__numeric">Rp <?= number_format($cl['opening_balance'], 0, ',', '.') ?></td>
                        <td class="c-table__numeric">Rp <?= number_format($cl['total_in'], 0, ',', '.') ?></td>
                        <td class="c-table__numeric">Rp <?= number_format($cl['total_out'], 0, ',', '.') ?></td>
                        <td class="c-table__numeric">Rp <?= number_format($cl['closing_balance'], 0, ',', '.') ?></td>
                        <td class="c-table__numeric">Rp <?= number_format($cl['physical_cash'], 0, ',', '.') ?></td>
                        <td class="c-table__numeric">
                            <span class="<?= $cl['difference'] < 0 ? 'u-text-danger' : 'u-text-success' ?>">
                                Rp <?= number_format($cl['difference'], 0, ',', '.') ?>
                            </span>
                        </td>
                        <td>
                            <?php
                                $statusClass = match($cl['status']) {
                                    'CLOSED' => 'success',
                                    'REOPENED' => 'warning',
                                    default => 'neutral'
                                };
                            ?>
                            <span class="c-badge c-badge--<?= $statusClass ?>"><?= e($cl['status']) ?></span>
                        </td>
                        <td>
                            <?php if ($cl['status'] === 'CLOSED'): ?>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="id" value="<?= $cl['id'] ?>">
                                    <input type="text" name="reason" placeholder="Alasan buka" required class="c-input c-input--sm" style="width: 150px;">
                                    <button type="submit" name="action" value="reopen" class="c-button c-button--ghost c-button--sm" onclick="return confirm('Buka periode?')">Buka</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($closings)): ?>
                    <tr><td colspan="10" class="c-table__empty">Belum ada periode tertutup.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>