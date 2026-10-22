<?php
$pageTitle = 'Tambah Pengeluaran';
$activeMenu = 'expenses';
require __DIR__ . '/../../layouts/admin.php';
?>

<div class="l-container">
    <div class="l-stack l-stack--lg">
        <h1 class="c-heading c-heading--1">Tambah Pengeluaran</h1>

        <div class="c-card">
            <form method="POST" class="l-stack l-stack--md">
                <div class="c-form-field">
                    <label class="c-label" for="expense_date">Tanggal <span class="u-text-danger">*</span></label>
                    <input type="date" id="expense_date" name="expense_date" class="c-input" value="<?= e(date('Y-m-d')) ?>" required>
                </div>

                <div class="c-form-field">
                    <label class="c-label" for="cash_account_id">Rekening Kas <span class="u-text-danger">*</span></label>
                    <select id="cash_account_id" name="cash_account_id" class="c-select" required>
                        <?php
                        $conn = \App\Core\Database::getInstance()->getConnection();
                        $accounts = $conn->query("SELECT id, name FROM cash_accounts WHERE is_active = 1")->fetch_all(MYSQLI_ASSOC);
                        foreach ($accounts as $acc): ?>
                            <option value="<?= $acc['id'] ?>"><?= e($acc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="c-form-field">
                    <label class="c-label" for="category">Kategori <span class="u-text-danger">*</span></label>
                    <select id="category" name="category" class="c-select" required>
                        <option value="OPERASIONAL">Operasional</option>
                        <option value="MAINTENANCE">Maintenance</option>
                        <option value="UTILITIES">Utilities</option>
                        <option value="PAYROLL">Payroll</option>
                        <option value="MARKETING">Marketing</option>
                        <option value="LAINNYA">Lainnya</option>
                    </select>
                </div>

                <div class="c-form-field">
                    <label class="c-label" for="description">Deskripsi <span class="u-text-danger">*</span></label>
                    <textarea id="description" name="description" class="c-textarea" rows="3" required></textarea>
                </div>

                <div class="c-form-field">
                    <label class="c-label" for="amount">Jumlah (Rp) <span class="u-text-danger">*</span></label>
                    <input type="number" id="amount" name="amount" class="c-input" min="1" step="1" required>
                </div>

                <div class="c-form-field">
                    <label class="c-label" for="receipt_path">Bukti (path file)</label>
                    <input type="text" id="receipt_path" name="receipt_path" class="c-input" placeholder="storage/receipts/...">
                </div>

                <div class="l-flex l-flex--end l-gap-sm">
                    <a href="expenses.php" class="c-button c-button--secondary">Batal</a>
                    <button type="submit" class="c-button c-button--primary">Simpan Draft</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>