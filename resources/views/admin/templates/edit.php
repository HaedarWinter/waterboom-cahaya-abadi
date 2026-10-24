<?php
$pageTitle = 'Edit Template: ' . e($template['name']);
$activeMenu = 'templates';
require __DIR__ . '/../../layouts/admin.php';
?>

<div class="l-container">
    <div class="l-stack l-stack--lg">
        <h1 class="c-heading c-heading--1">Edit Template: <?= e($template['name']) ?></h1>
        <p class="finance-template-hint">Kunci: <code class="finance-template-key"><?= e($template['key']) ?></code></p>

        <div class="c-card finance-form-card">
            <form method="POST" class="l-stack l-stack--md finance-form">
                <div class="c-form-field">
                    <label class="c-label" for="name">Nama Template <span class="u-text-danger">*</span></label>
                    <input type="text" id="name" name="name" class="c-input" value="<?= e($template['name']) ?>" required>
                </div>

                <div class="c-form-field">
                    <label class="c-label" for="subject">Subjek/Judul</label>
                    <input type="text" id="subject" name="subject" class="c-input" value="<?= e($template['subject'] ?? '') ?>">
                    <small class="c-help">Opsional. Bisa gunakan variabel seperti {nama}, {kode_booking}</small>
                </div>

                <div class="c-form-field">
                    <label class="c-label" for="body">Isi Pesan <span class="u-text-danger">*</span></label>
                    <textarea id="body" name="body" class="c-textarea" rows="10" required><?= e($template['body']) ?></textarea>
                    <small class="c-help">
                        Variabel tersedia: <br>
                        <?php if ($template['variables']): 
                            $vars = json_decode($template['variables'], true);
                            echo implode(', ', array_map(fn($v) => '{' . $v . '}', $vars));
                        else: ?>
                            (tidak ada variabel didefinisikan)
                        <?php endif; ?>
                    </small>
                </div>

                <div class="c-form-field c-form-field--checkbox">
                    <input type="checkbox" id="is_active" name="is_active" class="c-checkbox" <?= $template['is_active'] ? 'checked' : '' ?>>
                    <label class="c-label" for="is_active">Aktif</label>
                </div>

                <div class="l-flex l-flex--end l-gap-sm">
                    <a href="templates.php" class="c-button c-button--secondary">Batal</a>
                    <button type="submit" class="c-button c-button--primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>

        <div class="c-card finance-preview-card">
            <h2 class="finance-preview-title">Preview (dengan data dummy)</h2>
            <div class="finance-preview-body">
<?php
$demoVars = [
    'nama' => 'Budi Santoso',
    'kode_booking' => 'WTR-2026-ABC12',
    'tanggal_kunjungan' => '25 Desember 2026',
    'total_harga' => '150.000',
    'deadline' => '24 Desember 2026 23:59',
    'reason' => 'Nominal tidak sesuai',
    'base_url' => 'https://demo.cahayaabadi.id',
    'ticket_url' => 'https://demo.cahayaabadi.id/ticket/WTR-2026-ABC123?sig=...',
    'amount' => '150.000',
];
$preview = preg_replace_callback('/\{(\w+)\}/', function($m) use ($demoVars) {
    return $demoVars[$m[1]] ?? $m[0];
}, $template['body']);
echo e($preview);
?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/admin_footer.php'; ?>
