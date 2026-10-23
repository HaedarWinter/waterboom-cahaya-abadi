<?php
/**
 * admin/includes/layout_wrapper.php
 * Layout wrapper untuk semua halaman admin
 * Load design system CSS terpusat
 */
?><!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . " — " : "" ?>Cahaya Abadi Admin</title>
    
    <base href="/ujikom1/">
    
    <!-- Design System (Atomic CSS) -->
    <link rel="stylesheet" href="../public/assets/dist/css/app.min.css">
    
    <!-- Legacy Admin Styles (fallback untuk komponen lama) -->
    <link rel="stylesheet" href="../public/assets/css/admin_style.css">
    <link rel="stylesheet" href="../public/assets/css/style.css">
            
    <!-- Libraries -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    
    <?php if (isset($extra_head)) echo $extra_head; ?>
</head>
<body class="admin-body">
