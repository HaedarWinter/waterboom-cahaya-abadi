<?php
declare(strict_types=1);

namespace App\Http;

return [
    'admin/index.php' => 'dashboard.view',
    'admin/api/notifications.php' => 'dashboard.view',
    'admin/scanner.php' => 'checkin.scan',
    'admin/handlers/scanner_handler.php' => 'checkin.scan',
    'admin/buku_kas.php' => 'ledger.view',
    'admin/reconciliation.php' => 'reconciliation.manage',
    'admin/handlers/reconciliation_handler.php' => 'reconciliation.manage',
    'admin/handlers/export.php' => 'guestbook.moderate',
    'admin/berita.php' => 'content.news.manage',
    'admin/handlers/berita_handler.php' => 'content.news.manage',
    'admin/galeri.php' => 'content.gallery.manage',
    'admin/handlers/galeri_handler.php' => 'content.gallery.manage',
    'admin/faq.php' => 'content.faq.manage',
    'admin/handlers/faq_handler.php' => 'content.faq.manage',
    'admin/reviews.php' => 'review.moderate',
    'admin/handlers/review_handler.php' => 'review.moderate',
    'admin/handlers/hapus.php' => 'guestbook.moderate',
    'admin/reservasi.php' => 'reservation.view',
    'admin/pengaturan.php' => 'settings.manage',
    'admin/handlers/settings_handler.php' => 'settings.manage',
    'admin/kelola_admin.php' => 'user.manage',
    'admin/edit.php' => 'guestbook.moderate',
    'admin/handlers/buku_kas_handler.php' => 'ledger.view',
    'admin/audit_keuangan.php' => 'report.finance.view',
    'admin/expenses.php' => 'ledger.entry_manual',
    'admin/closings.php' => 'closing.perform',
    'admin/reports.php' => 'report.finance.view',
    'admin/logs.php' => 'activity.view',
];
