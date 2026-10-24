<?php
$pageTitle = 'Kelola Template Pesan WA';
$activeMenu = 'templates';
require __DIR__ . '/../../layouts/admin.php';
?>

<div class="l-container">
    <div class="l-stack l-stack--lg">
        <h1 class="c-heading c-heading--1">Template Pesan WhatsApp</h1>

        <?php if (!$schemaReady): ?>
        <div class="c-alert c-alert--warning" role="alert">
            Tabel template belum tersedia. Jalankan migrasi database V4_006_notifications.sql setelah membuat backup.
        </div>
        <?php endif; ?>

        <div class="c-card">
            <table class="c-table">
                <thead>
                    <tr>
                        <th>Kunci Template</th>
                        <th>Nama</th>
                        <th>Status</th>
                        <th>Variabel</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($templates as $tpl): ?>
                    <tr>
                        <td><code class="finance-template-key"><?= e($tpl['key']) ?></code></td>
                        <td><?= e($tpl['name']) ?></td>
                        <td>
                            <span class="c-badge <?= $tpl['is_active'] ? 'c-badge--success' : 'c-badge--muted' ?>">
                                <?= $tpl['is_active'] ? '✓ Aktif' : '✗ Nonaktif' ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($tpl['variables']): ?>
                                <small class="finance-template-vars">
                                    <?php 
                                    $vars = json_decode($tpl['variables'], true);
                                    echo implode(', ', array_map(fn($v) => '{' . $v . '}', $vars));
                                    ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="?action=edit&key=<?= urlencode($tpl['key']) ?>" class="c-button c-button--ghost c-button--sm">Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../layouts/admin_footer.php'; ?>
