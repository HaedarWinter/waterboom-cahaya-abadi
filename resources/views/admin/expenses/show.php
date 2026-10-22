<?php
$pageTitle = 'Detail Pengeluaran #' . $expense['id'];
$activeMenu = 'expenses';
require __DIR__ . '/../../layouts/admin.php';
?>

<div class="l-container">
    <div class="l-stack l-stack--lg">
        <div class="l-flex l-flex--between l-flex--wrap l-gap-sm">
            <h1 class="c-heading c-heading--1">Detail Pengeluaran #<?= $expense['id'] ?></h1>
            <div class="l-flex l-gap-sm">
                <?php if ($expense['status'] === 'DRAFT'): ?>
                    <form method="POST" action="?view=show&id=<?= $expense['id'] ?>">
                        <input type="hidden" name="id" value="<?= $expense['id'] ?>">
                        <button type="submit" name="action" value="post" class="c-button c-button--primary" onclick="return confirm('Posting ke ledger?')">Posting</button>
                    </form>
                <?php elseif ($expense['status'] === 'POSTED'): ?>
                    <form method="POST" action="?view=show&id=<?= $expense['id'] ?>">
                        <input type="hidden" name="id" value="<?= $expense['id'] ?>">
                        <input type="text" name="reason" placeholder="Alasan pembatalan" required class="c-input" style="width: 200px;">
                        <button type="submit" name="action" value="void" class="c-button c-button--danger" onclick="return confirm('Batalkan pengeluaran?')">Batalkan</button>
                    </form>
                <?php endif; ?>
                <a href="expenses.php" class="c-button c-button--secondary">Kembali</a>
            </div>
        </div>

        <div class="c-card">
            <dl class="l-grid l-grid--2 l-gap-md">
                <dt>Tanggal</dt><dd><?= e(date('d/m/Y', strtotime($expense['expense_date']))) ?></dd>
                <dt>Kategori</dt><dd><span class="c-badge c-badge--info"><?= e($expense['category']) ?></span></dd>
                <dt>Deskripsi</dt><dd><?= e($expense['description']) ?></dd>
                <dt>Jumlah</dt><dd class="u-text-bold">Rp <?= number_format($expense['amount'], 0, ',', '.') ?></dd>
                <dt>Status</dt><dd>
                    <?php
                        $statusClass = match($expense['status']) {
                            'POSTED' => 'success',
                            'VOIDED' => 'danger',
                            default => 'warning'
                        };
                    ?>
                    <span class="c-badge c-badge--<?= $statusClass ?>"><?= e($expense['status']) ?></span>
                </dd>
                <dt>Rekening Kas</dt><dd>
                    <?php
                    $conn = \App\Core\Database::getInstance()->getConnection();
                    $acc = $conn->query("SELECT name FROM cash_accounts WHERE id = " . (int)$expense['cash_account_id'])->fetch_assoc();
                    echo e($acc['name'] ?? '-');
                    ?>
                </dd>
                <dt>Dibuat oleh</dt><dd>User #<?= $expense['created_by'] ?></dd>
                <dt>Dibuat pada</dt><dd><?= e(date('d/m/Y H:i', strtotime($expense['created_at']))) ?></dd>
                <?php if ($expense['status'] === 'POSTED' && $expense['approved_by']): ?>
                    <dt>Diposting oleh</dt><dd>User #<?= $expense['approved_by'] ?></dd>
                    <dt>Diposting pada</dt><dd><?= e(date('d/m/Y H:i', strtotime($expense['approved_at']))) ?></dd>
                <?php endif; ?>
                <?php if ($expense['status'] === 'VOIDED' && $expense['voided_by']): ?>
                    <dt>Dibatalkan oleh</dt><dd>User #<?= $expense['voided_by'] ?></dd>
                    <dt>Dibatalkan pada</dt><dd><?= e(date('d/m/Y H:i', strtotime($expense['voided_at']))) ?></dd>
                    <dt>Alasan</dt><dd><?= e($expense['void_reason']) ?></dd>
                <?php endif; ?>
            </dl>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>