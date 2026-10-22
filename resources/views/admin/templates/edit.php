<?php
$pageTitle = 'Edit Template: ' . e($template['name']);
$activeMenu = 'templates';
require __DIR__ . '/../../layouts/admin.php';
?>

<div class="l-container">
    <div class="l-stack l-stack--lg">
        <h1 class="c-heading c-heading--1">Edit Template: <?= e($template['name']) ?></h1>
        <p style="color: #666; margin-bottom: 1.5rem;">Kunci: <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;"><?= e($template['key']) ?></code></p>

        <div class="c-card">
            <form method="POST" class="l-stack l-stack--md">
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

        <div class="c-card" style="margin-top: 1rem;">
            <h3 style="margin-bottom: 0.5rem;">Preview (dengan data dummy)</h3>
            <div style="padding: 1rem; background: #fafafa; border-radius: 5px; white-space: pre-wrap; font-family: monospace; font-size: 0.9rem;">
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

<?php require __DIR__ . '/../../partials/footer.php'; ?>