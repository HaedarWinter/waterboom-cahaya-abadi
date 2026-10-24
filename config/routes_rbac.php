<?php
declare(strict_types=1);

namespace App\Http;

return [
    'admin/index.php' => 'dashboard.view',
    'admin/api/notifications.php' => 'dashboard.view',
    'admin/scanner.php' => 'checkin.scan',
    'admin/buku_kas.php' => 'ledger.view',
    'admin/reconciliation.php' => 'reconciliation.manage',
    'admin/logout.php' => '@authenticated',
    'admin/berita.php' => 'content.news.manage',
    'admin/galeri.php' => 'content.gallery.manage',
    'admin/faq.php' => 'content.faq.manage',
    'admin/reviews.php' => 'review.moderate',
    'admin/reservasi.php' => 'reservation.view',
    'admin/pengaturan.php' => 'settings.manage',
    'admin/kelola_admin.php' => 'user.manage',
    'admin/edit.php' => 'guestbook.moderate',
    'admin/audit_keuangan.php' => 'report.finance.view',
    'admin/expenses.php' => 'ledger.entry_manual',
    'admin/closings.php' => 'closing.perform',
    'admin/reports.php' => 'report.finance.view',
    'admin/notifications.php' => '@authenticated',
    'admin/templates.php' => 'settings.manage',
    'admin/logs.php' => 'activity.view',
];
