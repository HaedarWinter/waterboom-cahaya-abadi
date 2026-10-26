<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . " - " : "" ?>Waterboom Cahaya Abadi</title>
    <link rel="icon" href="<?= e(base_url('favicon.ico')) ?>" type="image/x-icon">
    <script>
        (function() {
            try {
                var forced = new URLSearchParams(location.search).get('theme');
                if (forced !== 'dark' && forced !== 'light') forced = localStorage.getItem('theme') || 'light';
                if (forced === 'dark') {
                    document.documentElement.style.colorScheme = 'dark';
                    window.addEventListener('DOMContentLoaded', function() {
                        document.body.classList.add('dark-theme');
                    });
                }
            } catch (e) {}
        })();
    </script>
<link rel="stylesheet" href="<?= e(asset_url('assets/css/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_url('assets/dist/css/app.min.css')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php if (isset($extra_head)) echo $extra_head; ?>
</head>
<?php $force_theme = ($_GET['theme'] ?? '') === 'dark' ? 'dark-theme' : ''; ?>
<body class="<?= $force_theme ?>">
<?php if ($force_theme): ?><script>document.documentElement.style.colorScheme='dark';</script><?php endif; ?>
<?php require dirname(__DIR__) . '/components/demo-banner.php'; ?>
