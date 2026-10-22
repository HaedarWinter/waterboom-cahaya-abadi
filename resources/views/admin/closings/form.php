<?php
$pageTitle = 'Form Tutup Harian';
$activeMenu = 'closings';
require __DIR__ . '/../../layouts/admin.php';
?>

<div class="l-container">
    <div class="l-stack l-stack--lg">
        <h1 class="c-heading c-heading--1">Tutup Harian</h1>

        <div class="c-card">
            <form method="POST" class="l-stack l-stack--md">
                <div class="c-form-field">
                    <label class="c-label" for="closing_date">Tanggal <span class="u-text-danger">*</span></label>
                    <input type="date" id="closing_date" name="closing_date" class="c-input" value="<?= e(date('Y-m-d')) ?>" required>
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
                    <label class="c-label" for="physical_cash">Saldo Fisik (Rp) <span class="u-text-danger">*</span></label>
                    <input type="number" id="physical_cash" name="physical_cash" class="c-input" min="0" step="1" required>
                    <small class="c-help">Masukkan jumlah uang fisik yang dihitung</small>
                </div>

                <div class="c-form-field">
                    <label class="c-label" for="difference_note">Catatan Selisih</label>
                    <textarea id="difference_note" name="difference_note" class="c-textarea" rows="3"></textarea>
                </div>

                <div class="l-flex l-flex--end l-gap-sm">
                    <a href="closings.php" class="c-button c-button--secondary">Batal</a>
                    <button type="submit" class="c-button c-button--primary" onclick="return confirm('Tutup periode dan kunci ledger?')">Tutup Periode</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>