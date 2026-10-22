<?php
$pageTitle = 'Manajemen Pengeluaran';
$activeMenu = 'expenses';
require __DIR__ . '/../../layouts/admin.php';
?>

<div class="l-container">
    <div class="l-stack l-stack--lg">
        <div class="l-flex l-flex--between l-flex--wrap">
            <h1 class="c-heading c-heading--1">Pengeluaran</h1>
            <a href="?view=create" class="c-button c-button--primary">
                <svg class="c-button__icon" aria-hidden="true"><use xlink:href="#icon-plus"></use></svg>
                Tambah Pengeluaran
            </a>
        </div>

        <div class="c-card">
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

            <table class="c-table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Kategori</th>
                        <th>Deskripsi</th>
                        <th class="c-table__numeric">Jumlah</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses as $exp): ?>
                    <tr>
                        <td><?= e(date('d/m/Y', strtotime($exp['expense_date']))) ?></td>
                        <td><span class="c-badge c-badge--info"><?= e($exp['category']) ?></span></td>
                        <td><?= e($exp['description']) ?></td>
                        <td class="c-table__numeric">Rp <?= number_format($exp['amount'], 0, ',', '.') ?></td>
                        <td>
                            <?php
                                $statusClass = match($exp['status']) {
                                    'POSTED' => 'success',
                                    'VOIDED' => 'danger',
                                    default => 'warning'
                                };
                            ?>
                            <span class="c-badge c-badge--<?= $statusClass ?>"><?= e($exp['status']) ?></span>
                        </td>
                        <td>
                            <a href="?view=show&id=<?= $exp['id'] ?>" class="c-button c-button--ghost c-button--sm">Detail</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($expenses)): ?>
                    <tr><td colspan="6" class="c-table__empty">Belum ada pengeluaran.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>