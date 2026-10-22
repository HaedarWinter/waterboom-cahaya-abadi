<?php
// admin/includes/header.php
require_once __DIR__ . '/../../app/init.php';

// Ambil jumlah reservasi pending untuk notifikasi
$db = \App\Core\Database::getInstance();
$notif_count = $db->query("SELECT COUNT(*) as n FROM reservasi WHERE status = 'MENUNGGU_VERIFIKASI'")->fetch_assoc()['n'] ?? 0;

// Ambil 5 reservasi terbaru yang pending
$recent_notifs = $db->query("SELECT id, kode_booking, nama, created_at FROM reservasi WHERE status = 'MENUNGGU_VERIFIKASI' ORDER BY created_at DESC LIMIT 5")->fetch_all(MYSQLI_ASSOC);
?>

<header class="admin-header">
    <button class="sidebar-toggle" id="mobile-toggle"><i data-lucide="menu"></i></button>
    
    <div class="header-title">
        <h1 class="page-heading"><?= $page_title ?? 'Dashboard' ?></h1>
        <div class="breadcrumb">
            <a href="/ujikom1/admin/">Dashboard</a> 
            <?= isset($breadcrumb) ? ' / ' . $breadcrumb : '' ?>
        </div>
    </div>

    <div class="header-actions-right">
        <!-- Notification Bell -->
        <div class="notif-wrapper" id="notif-dropdown-trigger">
            <div class="notif-icon">
                <i data-lucide="bell"></i>
                <?php if ($notif_count > 0): ?>
                    <span class="notif-badge" id="notif-count"><?= $notif_count ?></span>
                <?php endif; ?>
            </div>
            
            <div class="notif-dropdown" id="notif-dropdown">
                <div class="notif-header">
                    <span>Notifikasi</span>
                    <?php if ($notif_count > 0): ?>
                        <span class="notif-new-label"><?= $notif_count ?> Baru</span>
                    <?php endif; ?>
                </div>
                <div class="notif-list">
                    <?php if (empty($recent_notifs)): ?>
                        <div class="notif-empty">
                            <i data-lucide="check-circle-2"></i>
                            <p>Semua beres! Tidak ada reservasi pending.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($recent_notifs as $n): ?>
                            <a href="/ujikom1/admin/reservasi.php?q=<?= $n['kode_booking'] ?>" class="notif-item">
                                <div class="notif-item-icon"><i data-lucide="calendar-plus"></i></div>
                                <div class="notif-item-body">
                                    <div class="notif-item-title">Booking Baru: <strong><?= e($n['kode_booking']) ?></strong></div>
                                    <div class="notif-item-user">Oleh <?= e($n['nama']) ?></div>
                                    <div class="notif-item-time"><?= date('H:i', strtotime($n['created_at'])) ?> &bull; <?= date('d M', strtotime($n['created_at'])) ?></div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <a href="/ujikom1/admin/reservasi.php?status=MENUNGGU_VERIFIKASI" class="notif-view-all">Lihat Semua Reservasi Menunggu Verifikasi</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="header-user-nav">
            <a href="logout.php?token=<?= e(csrf_token()) ?>" class="header-logout swal-logout">
                <i data-lucide="log-out"></i>
                <span>Keluar</span>
            </a>
        </div>
    </div>
</header>

<style>
/* Styling Tambahan untuk Header Actions */
.header-actions-right {
    display: flex;
    align-items: center;
    gap: 1.5rem;
}

.notif-wrapper {
    position: relative;
    cursor: pointer;
}

.notif-icon {
    position: relative;
    color: var(--fg-muted);
    transition: color 0.2s;
    display: flex;
    align-items: center;
}

.notif-wrapper:hover .notif-icon {
    color: var(--accent);
}

.notif-badge {
    position: absolute;
    top: -6px;
    right: -8px;
    background: #ef4444;
    color: #fff;
    font-size: 0.65rem;
    font-weight: 800;
    min-width: 16px;
    height: 16px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    border: 2px solid var(--header-bg);
}

.notif-dropdown {
    position: absolute;
    top: calc(100% + 15px);
    right: -10px;
    width: 320px;
    background: var(--surface);
    border-radius: 16px;
    box-shadow: 0 15px 50px rgba(0,0,0,0.2);
    border: 1px solid var(--border);
    z-index: 9999;
    visibility: hidden;
    opacity: 0;
    transform: translateY(10px);
    transition: all 0.3s var(--ease);
}

.notif-dropdown.show {
    visibility: visible;
    opacity: 1;
    transform: translateY(0);
}

.notif-header {
    padding: 1rem 1.25rem;
    border-bottom: 1px solid var(--border-subtle);
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-weight: 800;
    font-size: 0.9rem;
}

.notif-new-label {
    background: rgba(14, 124, 107, 0.1);
    color: var(--accent);
    padding: 2px 8px;
    border-radius: 20px;
    font-size: 0.65rem;
    text-transform: uppercase;
}

.notif-list {
    max-height: 380px;
    overflow-y: auto;
}

.notif-item {
    display: flex;
    gap: 1rem;
    padding: 1rem 1.25rem;
    text-decoration: none;
    transition: background 0.2s;
    border-bottom: 1px solid var(--border-subtle);
}

.notif-item:hover {
    background: var(--surface-hover);
}

.notif-item-icon {
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: rgba(14, 124, 107, 0.1);
    color: var(--accent);
    display: flex;
    align-items: center;
    justify-content: center;
}

.notif-item-body {
    flex: 1;
}

.notif-item-title {
    font-size: 0.82rem;
    color: var(--fg);
    line-height: 1.4;
}

.notif-item-user {
    font-size: 0.75rem;
    color: var(--fg-muted);
}

.notif-item-time {
    font-size: 0.65rem;
    color: var(--fg-subtle);
    margin-top: 4px;
}

.notif-view-all {
    display: block;
    padding: 0.85rem;
    text-align: center;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--accent);
    text-decoration: none;
    background: var(--surface-hover);
}

.notif-empty {
    padding: 3rem 2rem;
    text-align: center;
}

.notif-empty i {
    width: 48px;
    height: 48px;
    color: var(--accent);
    opacity: 0.2;
    margin-bottom: 1rem;
}

.notif-empty p {
    font-size: 0.85rem;
    color: var(--fg-muted);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const trigger = document.getElementById('notif-dropdown-trigger');
    const dropdown = document.getElementById('notif-dropdown');

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('show');
    });

    document.addEventListener('click', function() {
        dropdown.classList.remove('show');
    });

    // POLLING NOTIFIKASI
    function checkNotifications() {
        const apiPath = '<?= base_url("admin/api/notifications.php") ?>'; 
        fetch(apiPath)
            .then(res => res.json())
            .then(data => {
                const badge = document.getElementById('notif-count');
                const list = document.querySelector('.notif-list');
                const headerLabel = document.querySelector('.notif-header');
                
                // Update Badge
                if (data.count > 0) {
                    if (badge) {
                        badge.textContent = data.count;
                    } else {
                        const newBadge = document.createElement('span');
                        newBadge.className = 'notif-badge';
                        newBadge.id = 'notif-count';
                        newBadge.textContent = data.count;
                        document.querySelector('.notif-icon').appendChild(newBadge);
                    }
                } else if (badge) {
                    badge.remove();
                }

                // Update List Content
                if (data.recent && data.recent.length > 0) {
                    let html = '';
                    data.recent.forEach(n => {
                        html += `
                            <a href="/ujikom1/admin/reservasi.php?q=${n.kode_booking}" class="notif-item">
                                <div class="notif-item-icon"><i data-lucide="calendar-plus"></i></div>
                                <div class="notif-item-body">
                                    <div class="notif-item-title">Booking Baru: <strong>${n.kode_booking}</strong></div>
                                    <div class="notif-item-user">Oleh ${n.nama}</div>
                                    <div class="notif-item-time">${new Date(n.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</div>
                                </div>
                            </a>`;
                    });
                    html += '<a href="/ujikom1/admin/reservasi.php?status=MENUNGGU_VERIFIKASI" class="notif-view-all">Lihat Semua</a>';
                    list.innerHTML = html;
                    lucide.createIcons();
                }
            })
            .catch(err => console.error('Notif error:', err));
    }

    // Cek setiap 30 detik
    setInterval(checkNotifications, 30000);
});
</script>
