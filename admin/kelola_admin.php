<?php
/**
 * Halaman Kelola Admin — CRUD akun administrator
 * Hanya bisa diakses oleh admin yang sudah login
 */
require_once '../app/init.php';
enforce_active_session($koneksi);

// Pastikan hanya Super Admin yang bisa mengelola ini
if (!has_canonical_permission('user.manage') || !has_canonical_permission('role.manage')) {
    header("Location: /ujikom1/admin/?error=Akses ditolak! Halaman ini hanya untuk Super Admin.");
    exit;
}
$is_super = true;

// Search
$search = $_GET['q'] ?? '';
$where = "1=1";
if ($search) {
    $where .= " AND (username LIKE ? OR email LIKE ?)";
}

// Pagination
$per_page = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $per_page;

if ($search) {
    $count_stmt = $koneksi->prepare("SELECT COUNT(*) as total FROM admin WHERE $where");
    $s = "%$search%";
    $count_stmt->bind_param("ss", $s, $s);
    $count_stmt->execute();
    $total = $count_stmt->get_result()->fetch_assoc()['total'];
} else {
    $total = $koneksi->query("SELECT COUNT(*) as total FROM admin")->fetch_assoc()['total'];
}
$total_pages = max(1, ceil($total / $per_page));

if ($search) {
    $stmt = $koneksi->prepare("SELECT id, username, email, role, status, permissions, created_at FROM admin WHERE $where ORDER BY id ASC LIMIT ? OFFSET ?");
    $s = "%$search%";
    $stmt->bind_param("ssii", $s, $s, $per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $koneksi->query("SELECT id, username, email, role, status, permissions, created_at FROM admin ORDER BY id ASC LIMIT $per_page OFFSET $offset");
}

// ─── PROSES AKSI (POST) ───
$form_error = '';
$form_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // Validasi CSRF
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $form_error = 'Token keamanan tidak valid.';
    } else {
        regenerate_csrf();
        $action = $_POST['action'];

        // ─── TAMBAH ADMIN (Hanya Super Admin) ───
        if ($action === 'add') {
            if (!$is_super) {
                $form_error = 'Anda tidak memiliki hak akses untuk menambah admin.';
            } else {
                $new_user  = trim($_POST['username'] ?? '');
                $new_email = trim($_POST['email'] ?? '');
                $new_pass  = $_POST['password'] ?? '';
                $confirm   = $_POST['confirm_password'] ?? '';

                if (empty($new_user) || empty($new_email) || empty($new_pass)) {
                    $form_error = 'Username, email, dan password wajib diisi!';
                } elseif (strlen($new_pass) < 6) {
                    $form_error = 'Password minimal 6 karakter!';
                } elseif ($new_pass !== $confirm) {
                    $form_error = 'Konfirmasi password tidak cocok!';
                } else {
                    // Cek duplikat username atau email
                    $cek = $koneksi->prepare("SELECT id FROM admin WHERE username = ? OR email = ?");
                    $cek->bind_param("ss", $new_user, $new_email);
                    $cek->execute();
                    if ($cek->get_result()->num_rows > 0) {
                        $form_error = 'Username atau Email sudah digunakan!';
                    } else {
                        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                        // Default permissions untuk admin baru
                        $def_perms = json_encode([
                            "content"   => ["view" => true, "add" => false, "edit" => false, "delete" => false],
                            "gallery"   => ["view" => true, "manage" => false],
                            "reservasi" => ["view" => true, "manage" => false],
                            "news"      => ["view" => true, "manage" => false],
                            "reviews"   => ["view" => true, "manage" => false],
                            "faq"       => ["view" => true, "manage" => false],
                            "finance"   => ["view" => false, "manage" => false],
                            "settings"  => ["manage" => false],
                            "user"      => ["manage" => false],
                            "system"    => ["export" => false]
                        ]);
                        $stmt = $koneksi->prepare("INSERT INTO admin (username, email, password, permissions) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param("ssss", $new_user, $new_email, $hash, $def_perms);
                        if ($stmt->execute()) {
                            header("Location: kelola_admin.php?pesan=Admin baru berhasil ditambahkan!");
                            exit;
                        } else {
                            $form_error = 'Gagal menambahkan admin.';
                        }
                    }
                }
            }
        }

        // ─── UPDATE PERMISSIONS & PROFILE (Hanya Super Admin) ───
        if ($action === 'update_permissions') {
            if (!$is_super) {
                $form_error = 'Hanya Super Admin yang bisa mengelola hak akses.';
            } else {
                $admin_id    = (int)($_POST['admin_id'] ?? 0);
                $new_role    = $_POST['role'] ?? 'Admin';
                $new_status  = $_POST['status'] ?? 'Aktif';
                $perms_input = $_POST['perms'] ?? []; // Array dari checkbox

                // Build JSON structure
                $perms_json = json_encode([
                    "content" => [
                        "view"   => isset($perms_input['content_view']),
                        "add"    => isset($perms_input['content_add']),
                        "edit"   => isset($perms_input['content_edit']),
                        "delete" => isset($perms_input['content_delete'])
                    ],
                    "gallery" => [
                        "view"   => isset($perms_input['gallery_view']),
                        "manage" => isset($perms_input['gallery_manage'])
                    ],
                    "reservasi" => [
                        "view"   => isset($perms_input['reservasi_view']),
                        "manage" => isset($perms_input['reservasi_manage'])
                    ],
                    "news" => [
                        "view"   => isset($perms_input['news_view']),
                        "manage" => isset($perms_input['news_manage'])
                    ],
                    "reviews" => [
                        "view"   => isset($perms_input['reviews_view']),
                        "manage" => isset($perms_input['reviews_manage'])
                    ],
                    "faq" => [
                        "view"   => isset($perms_input['faq_view']),
                        "manage" => isset($perms_input['faq_manage'])
                    ],
                    "finance" => [
                        "view"   => isset($perms_input['finance_view']),
                        "manage" => isset($perms_input['finance_manage'])
                    ],
                    "settings" => [
                        "manage" => isset($perms_input['settings_manage'])
                    ],
                    "user" => [
                        "manage" => isset($perms_input['user_manage'])
                    ],
                    "system" => [
                        "export" => isset($perms_input['system_export'])
                    ]
                ]);

                $stmt = $koneksi->prepare("UPDATE admin SET role = ?, status = ?, permissions = ? WHERE id = ?");
                $stmt->bind_param("sssi", $new_role, $new_status, $perms_json, $admin_id);

                if ($stmt->execute()) {
                    header("Location: kelola_admin.php?pesan=Hak akses Admin berhasil diperbarui!");
                    exit;
                } else {
                    $form_error = 'Gagal memperbarui hak akses.';
                }
            }
        }

        // ─── UBAH PASSWORD ───
        if ($action === 'change_password') {
            $admin_id = (int)($_POST['admin_id'] ?? 0);
            $new_pass = $_POST['new_password'] ?? '';
            $confirm  = $_POST['confirm_new_password'] ?? '';

            // Security check: Cabang hanya bisa ganti password sendiri
            if (!$is_super && $admin_id !== $_SESSION['admin_id']) {
                $form_error = 'Anda tidak memiliki hak akses untuk mengubah password orang lain.';
            } elseif (empty($new_pass)) {
                $form_error = 'Password baru wajib diisi!';
            } elseif (strlen($new_pass) < 6) {
                $form_error = 'Password minimal 6 karakter!';
            } elseif ($new_pass !== $confirm) {
                $form_error = 'Konfirmasi password tidak cocok!';
            } else {
                $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $stmt = $koneksi->prepare("UPDATE admin SET password = ? WHERE id = ?");
                $stmt->bind_param("si", $hash, $admin_id);
                if ($stmt->execute()) {
                    header("Location: kelola_admin.php?pesan=Password berhasil diubah!");
                    exit;
                } else {
                    $form_error = 'Gagal mengubah password.';
                }
            }
        }

        // ─── HAPUS ADMIN (Hanya Super Admin) ───
        if ($action === 'delete') {
            if (!$is_super) {
                $form_error = 'Hanya Super Admin yang bisa menghapus akun.';
            } else {
                $admin_id = (int)($_POST['admin_id'] ?? 0);
                $count = $koneksi->query("SELECT COUNT(*) AS n FROM admin WHERE role='Super Admin'")->fetch_assoc()['n'];
                
                // Cek apakah target adalah diri sendiri
                if ($admin_id === $_SESSION['admin_id']) {
                    $form_error = 'Anda tidak bisa menghapus akun sendiri!';
                } else {
                    // Cek apakah menghapus super admin terakhir
                    $target = $koneksi->query("SELECT role FROM admin WHERE id = $admin_id")->fetch_assoc();
                    if ($target['role'] === 'Super Admin' && $count <= 1) {
                        $form_error = 'Tidak bisa menghapus Super Admin terakhir!';
                    } else {
                        $stmt = $koneksi->prepare("DELETE FROM admin WHERE id = ?");
                        $stmt->bind_param("i", $admin_id);
                        if ($stmt->execute()) {
                            header("Location: kelola_admin.php?pesan=Admin berhasil dihapus!");
                            exit;
                        } else {
                            $form_error = 'Gagal menghapus admin.';
                        }
                    }
                }
            }
        }
    }

    // Refresh data setelah action
    $result = $koneksi->query("SELECT id, username, email, role, status, permissions, created_at FROM admin ORDER BY id ASC");
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= e(asset_url('assets/dist/css/app.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_url('assets/css/admin_style.css')) ?>">
    <title>Kelola Admin — Cahaya Abadi</title>
                <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Lucide Icons CDN -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="admin-body">

<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="admin-main">
    <header class="admin-header">
        <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">&#9776;</button>
        <div class="header-title">
            <h1 class="page-heading">Kelola Admin</h1>
            <span class="breadcrumb"><a href="/ujikom1/admin/">Dashboard</a> / Kelola Admin</span>
        </div>
        <a href="logout.php?token=<?= e(csrf_token()) ?>" class="header-logout swal-logout">
            <i data-lucide="log-out"></i>
            <span>Keluar</span>
        </a>
    </header>

    <div class="admin-content">

        <!-- FORM TAMBAH ADMIN BARU (Hanya untuk Super Admin) -->
        <?php if ($is_super): ?>
        <div class="section-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Tambah Admin Baru</h2>
                    <p class="section-sub">Buat akun administrator baru untuk mengelola sistem</p>
                </div>
            </div>
            <form method="post" style="padding:1.5rem;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                    <div class="form-group" style="margin-bottom:0">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Masukkan username" required class="input-field">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" placeholder="admin@example.com" required class="input-field">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Min. 6 karakter" required minlength="6" class="input-field">
                    </div>
                    <div class="form-group" style="margin-bottom:0">
                        <label for="confirm_password">Konfirmasi Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi password" required class="input-field">
                    </div>
                </div>
                <div style="margin-top:1.5rem;">
                    <button type="submit" class="btn">
                        <i data-lucide="user-plus" style="width:16px;height:16px;margin-right:6px"></i>
                        Tambah Admin
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- DAFTAR ADMIN -->
        <div class="section-card">
            <div class="section-header">
                <div>
                    <h2 class="section-title">Daftar Administrator</h2>
                    <p class="section-sub">Kelola akun admin dan hak akses secara granular</p>
                </div>
                <div class="header-actions">
                    <form method="get" class="search-form">
                        <input type="text" name="q" class="search-input" placeholder="Cari admin..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit" class="btn-search"><i data-lucide="search"></i></button>
                        <?php if ($search): ?><a href="kelola_admin.php" class="btn-clear"><i data-lucide="x"></i></a><?php endif; ?>
                    </form>
                </div>
            </div>
            <div class="table-scroll">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width:46px">No</th>
                            <th>Admin Info</th>
                            <th>Role & Status</th>
                            <th>Tgl Dibuat</th>
                            <th style="width:200px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result->num_rows > 0): ?>
                        <?php $no = $offset + 1; while ($row = $result->fetch_assoc()): 
                            $is_self = ($row['username'] === $_SESSION['username']);
                            $role_class = ($row['role'] === 'Super Admin' ? 'badge-super' : ($row['role'] === 'Akuntan' ? 'badge-info' : 'badge-admin'));
                            $status_class = ($row['status'] === 'Aktif' ? 'badge-active' : 'badge-inactive');
                        ?>
                        <tr>
                            <td class="td-num"><?= $no++ ?></td>
                            <td class="td-name">
                                <div style="display:flex; align-items:center; gap:.75rem;">
                                    <div class="name-avatar"><?= strtoupper(substr($row['username'], 0, 1)) ?></div>
                                    <div>
                                        <div style="font-weight:700; color:var(--fg);">
                                            <?= htmlspecialchars($row['username']) ?>
                                            <?php if ($is_self): ?>
                                                <span class="badge badge-success" style="padding:2px 8px; font-size:.6rem; margin-left:4px;">Anda</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size:.75rem; color:var(--fg-muted); font-weight:400;"><?= htmlspecialchars($row['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div style="display:flex; flex-direction:column; gap:4px; align-items:flex-start;">
                                    <span class="badge <?= $role_class ?>">
                                        <i data-lucide="<?= $row['role'] === 'Super Admin' ? 'shield-check' : 'user' ?>" style="width:12px;height:12px"></i>
                                        <?= $row['role'] ?>
                                    </span>
                                    <span class="badge <?= $status_class ?>">
                                        <i data-lucide="<?= $row['status'] === 'Aktif' ? 'check-circle' : 'x-circle' ?>" style="width:12px;height:12px"></i>
                                        <?= $row['status'] ?>
                                    </span>
                                </div>
                            </td>
                            <td><span class="date-badge"><?= date('d M Y', strtotime($row['created_at'])) ?></span></td>
                            <td class="td-actions">
                                <div class="actions-wrapper">
                                    <?php if ($is_super): ?>
                                    <button class="action-btn detail btn-manage-access"
                                        data-id="<?= $row['id'] ?>"
                                        data-username="<?= htmlspecialchars($row['username']) ?>"
                                        data-email="<?= htmlspecialchars($row['email']) ?>"
                                        data-role="<?= $row['role'] ?>"
                                        data-status="<?= $row['status'] ?>"
                                        data-perms='<?= $row['permissions'] ?>'>
                                        <i data-lucide="settings-2"></i>
                                        <span>Hak Akses</span>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <button class="action-btn edit swal-change-pass"
                                        data-id="<?= $row['id'] ?>"
                                        data-username="<?= htmlspecialchars($row['username']) ?>">
                                        <i data-lucide="key-round"></i>
                                        <span class="<?= $is_super ? 'sr-only' : '' ?>">Ubah Password</span>
                                    </button>
                                    
                                    <?php if ($is_super && !$is_self): ?>
                                        <button class="action-btn del swal-delete-admin"
                                            data-id="<?= $row['id'] ?>"
                                            data-username="<?= htmlspecialchars($row['username']) ?>">
                                            <i data-lucide="trash-2"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php else: ?>
                        <tr><td colspan="5" class="td-empty">Belum ada data admin.</td></tr>
                        <?php endif; ?>
                        </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <span class="pagination-info">
                    <?= $offset + 1 ?>–<?= min($offset + $per_page, $total) ?> dari <?= $total ?> data
                </span>
                <div class="pagination-btns">
                    <a href="<?= $page > 1 ? '?page='.($page-1).($search ? '&q='.urlencode($search) : '') : '#' ?>" 
                       class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                       <?= $page <= 1 ? 'onclick="return false;"' : '' ?>>&larr; Sebelumnya</a>
                    <span class="page-current"><?= $page ?> / <?= $total_pages ?></span>
                    <a href="<?= $page < $total_pages ? '?page='.($page+1).($search ? '&q='.urlencode($search) : '') : '#' ?>" 
                       class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"
                       <?= $page >= $total_pages ? 'onclick="return false;"' : '' ?>>Berikutnya &rarr;</a>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</main>

<!-- ── DRAWER PANEL: MANAJEMEN HAK AKSES ── -->
<div class="drawer-overlay" id="drawerOverlay"></div>
<div class="drawer" id="accessDrawer">
    <div class="drawer-header">
        <h3 class="drawer-title">Manajemen Hak Akses</h3>
        <button class="drawer-close" id="closeDrawer"><i data-lucide="x"></i></button>
    </div>
    <form method="post" id="form-manage-access">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_permissions">
        <input type="hidden" name="admin_id" id="access-admin-id">
        
        <div class="drawer-content">
            <!-- Profil Singkat -->
            <div style="display:flex; align-items:center; gap:1rem; margin-bottom:1.5rem; padding:1rem; background:var(--surface-hover); border-radius:12px;">
                <div class="name-avatar" id="access-avatar" style="width:48px;height:48px;font-size:1.2rem;">A</div>
                <div>
                    <div id="access-username" style="font-weight:700; color:var(--fg); font-size:1rem;">Username</div>
                    <div id="access-email" style="font-size:.8rem; color:var(--fg-muted);">email@example.com</div>
                </div>
            </div>

            <!-- Role & Status -->
            <div class="form-grid">
                <div class="form-group">
                    <label class="input-label">Role Jabatan</label>
                    <select name="role" id="access-role" class="input-field">
                        <option value="Admin">Admin Biasa</option>
                        <option value="Super Admin">Super Admin</option>
                        <option value="Akuntan">Akuntan</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="input-label">Status Akun</label>
                    <select name="status" id="access-status" class="input-field">
                        <option value="Aktif">Aktif</option>
                        <option value="Nonaktif">Nonaktif</option>
                    </select>
                </div>
            </div>

            <div class="nav-section-label" style="margin-bottom:1rem; padding-left:0;">Hak Akses Granular</div>

            <!-- Manajemen Konten -->
            <div class="permission-group">
                <div class="permission-group-title">
                    <i data-lucide="book-open" style="width:16px;height:16px;color:#0e7c6b"></i>
                    Buku Tamu (Konten)
                </div>
                <div class="permission-items">
                    <label class="permission-item">
                        <input type="checkbox" name="perms[content_view]" value="1" class="perm-check">
                        <div>
                            <div class="perm-label">Lihat Data</div>
                            <div class="perm-desc">Melihat daftar buku tamu & statistik.</div>
                        </div>
                    </label>
                    <label class="permission-item">
                        <input type="checkbox" name="perms[content_add]" value="1" class="perm-check">
                        <div>
                            <div class="perm-label">Tambah Data</div>
                            <div class="perm-desc">Input data pengunjung baru.</div>
                        </div>
                    </label>
                    <label class="permission-item">
                        <input type="checkbox" name="perms[content_edit]" value="1" class="perm-check">
                        <div>
                            <div class="perm-label">Edit Data</div>
                            <div class="perm-desc">Mengubah informasi pengunjung.</div>
                        </div>
                    </label>
                    <label class="permission-item">
                        <input type="checkbox" name="perms[content_delete]" value="1" class="perm-check">
                        <div>
                            <div class="perm-label">Hapus Data</div>
                            <div class="perm-desc text-danger">Menghapus data pengunjung.</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Manajemen Galeri -->
            <div class="permission-group">
                <div class="permission-group-title">
                    <i data-lucide="images" style="width:16px;height:16px;color:#0e7c6b"></i>
                    Manajemen Galeri
                </div>
                <div class="permission-items">
                    <label class="permission-item">
                        <input type="checkbox" name="perms[gallery_view]" value="1" class="perm-check">
                        <div>
                            <div class="perm-label">Lihat Galeri</div>
                            <div class="perm-desc">Melihat daftar foto.</div>
                        </div>
                    </label>
                    <label class="permission-item">
                        <input type="checkbox" name="perms[gallery_manage]" value="1" class="perm-check">
                        <div>
                            <div class="perm-label">Kelola Foto</div>
                            <div class="perm-desc">Tambah, Edit, & Hapus foto.</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Sistem Reservasi -->
            <div class="permission-group">
                <div class="permission-group-title">
                    <i data-lucide="calendar-check" style="width:16px;height:16px;color:#0e7c6b"></i>
                    Sistem Reservasi
                </div>
                <div class="permission-items">
                    <label class="permission-item">
                        <input type="checkbox" name="perms[reservasi_view]" value="1" class="perm-check">
                        <div>
                            <div class="perm-label">Lihat Data</div>
                            <div class="perm-desc">Melihat daftar pesanan.</div>
                        </div>
                    </label>
                    <label class="permission-item">
                        <input type="checkbox" name="perms[reservasi_manage]" value="1" class="perm-check">
                        <div>
                            <div class="perm-label">Update Status</div>
                            <div class="perm-desc">Konfirmasi & Hapus pesanan.</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Manajemen Berita & Media -->
            <div class="permission-group">
                <div class="permission-group-title">
                    <i data-lucide="newspaper" style="width:16px;height:16px;color:#0e7c6b"></i>
                    Manajemen Berita
                </div>
                <div class="permission-items">
                    <label class="permission-item">
                        <input type="checkbox" name="perms[news_view]" value="1" class="perm-check">
                        <div>
                            <div class="perm-label">Lihat Berita</div>
                            <div class="perm-desc">Melihat daftar artikel.</div>
                        </div>
                    </label>
                    <label class="permission-item">
                        <input type="checkbox" name="perms[news_manage]" value="1" class="perm-check">
                        <div>
                            <div class="perm-label">Kelola Artikel</div>
                            <div class="perm-desc">Tambah, Edit, & Hapus berita.</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Audit Keuangan (Akuntan) -->
            <div class="permission-group">
                <div class="permission-group-title">
                    <i data-lucide="wallet" style="width:16px;height:16px;color:#0e7c6b"></i>
                    Audit Keuangan
                </div>
                <div class="permission-items">
                    <label class="permission-item">
                        <input type="checkbox" name="perms[finance_view]" value="1" class="perm-check">
                        <div>
                            <div class="perm-label">Lihat Laporan</div>
                            <div class="perm-desc">Melihat dashboard laba rugi.</div>
                        </div>
                    </label>
                    <label class="permission-item">
                        <input type="checkbox" name="perms[finance_manage]" value="1" class="perm-check">
                        <div>
                            <div class="perm-label">Kelola Keuangan</div>
                            <div class="perm-desc">Input biaya operasional & pengeluaran.</div>
                        </div>
                    </label>
                </div>
            </div>

            <!-- Review & FAQ -->
            <div class="permission-group">
                <div class="permission-group-title">
                    <i data-lucide="message-square" style="width:16px;height:16px;color:#0e7c6b"></i>
                    Review & FAQ
                </div>
                <div class="permission-items" style="grid-template-columns: 1fr 1fr;">
                    <div style="display:flex; flex-direction:column; gap:0.5rem;">
                        <label class="permission-item">
                            <input type="checkbox" name="perms[reviews_view]" value="1" class="perm-check">
                            <div class="perm-label" style="font-size:0.8rem">Lihat Review</div>
                        </label>
                        <label class="permission-item">
                            <input type="checkbox" name="perms[reviews_manage]" value="1" class="perm-check">
                            <div class="perm-label" style="font-size:0.8rem">Kelola Review</div>
                        </label>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:0.5rem;">
                        <label class="permission-item">
                            <input type="checkbox" name="perms[faq_view]" value="1" class="perm-check">
                            <div class="perm-label" style="font-size:0.8rem">Lihat FAQ</div>
                        </label>
                        <label class="permission-item">
                            <input type="checkbox" name="perms[faq_manage]" value="1" class="perm-check">
                            <div class="perm-label" style="font-size:0.8rem">Kelola FAQ</div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Manajemen User & Sistem -->
            <div class="permission-group">
                <div class="permission-group-title">
                    <i data-lucide="shield-check" style="width:16px;height:16px;color:#0e7c6b"></i>
                    Otoritas & Sistem
                </div>
                <div class="permission-items" style="grid-template-columns: 1fr;">
                    <label class="permission-item">
                        <input type="checkbox" name="perms[settings_manage]" value="1" class="perm-check">
                        <div>
                            <div class="perm-label">Pengaturan Web</div>
                            <div class="perm-desc">Mengubah harga tiket, kontak, dll.</div>
                        </div>
                    </label>
                    <label class="permission-item">
                        <input type="checkbox" name="perms[user_manage]" value="1" class="perm-check" id="check-user-manage">
                        <div>
                            <div class="perm-label">Kelola Admin</div>
                            <div class="perm-desc">Atur hak akses admin lain.</div>
                        </div>
                    </label>
                    <label class="permission-item">
                        <input type="checkbox" name="perms[system_export]" value="1" class="perm-check">
                        <div>
                            <div class="perm-label">Ekspor Laporan</div>
                            <div class="perm-desc">Download PDF/CSV Buku Tamu.</div>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <div class="drawer-footer">
            <button type="button" class="btn btn-clear btn-block" id="btn-cancel-drawer">Batal</button>
            <button type="submit" class="btn btn-block">Simpan Perubahan</button>
        </div>
    </form>
</div>

<!-- Hidden forms for JS-triggered actions -->
<form id="form-change-pass" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="change_password">
    <input type="hidden" name="admin_id" id="cp-admin-id">
    <input type="hidden" name="new_password" id="cp-new-pass">
    <input type="hidden" name="confirm_new_password" id="cp-confirm-pass">
</form>
<form id="form-delete-admin" method="post" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="admin_id" id="del-admin-id">
</form>

<script>
// ─── DRAWER LOGIC ───
const drawer = document.getElementById('accessDrawer');
const drawerOverlay = document.getElementById('drawerOverlay');

function openDrawer() {
    drawer.classList.add('open');
    drawerOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
}

function closeDrawer() {
    drawer.classList.remove('open');
    drawerOverlay.classList.remove('open');
    document.body.style.overflow = '';
}

document.getElementById('closeDrawer').addEventListener('click', closeDrawer);
document.getElementById('btn-cancel-drawer').addEventListener('click', closeDrawer);
drawerOverlay.addEventListener('click', closeDrawer);

// Data Population for Access Drawer
document.querySelectorAll('.btn-manage-access').forEach(el => {
    el.addEventListener('click', function() {
        const data = this.dataset;
        let perms = {};
        
        try {
            perms = JSON.parse(data.perms || '{}');
        } catch (e) {
            console.error("Gagal parse permissions JSON:", e);
        }

        // Basic Info - pastikan elemen ada sebelum diisi
        const idInput = document.getElementById('access-admin-id');
        const userText = document.getElementById('access-username');
        const emailText = document.getElementById('access-email');
        const avatarBox = document.getElementById('access-avatar');
        
        if (idInput) idInput.value = data.id;
        if (userText) userText.innerText = data.username;
        if (emailText) emailText.innerText = data.email;
        if (avatarBox) avatarBox.innerText = data.username.charAt(0).toUpperCase();

        // Role & Status
        const roleSelect = document.getElementById('access-role');
        const statusSelect = document.getElementById('access-status');
        if (roleSelect) roleSelect.value = data.role;
        if (statusSelect) statusSelect.value = data.status;

        // Reset all checkboxes first
        document.querySelectorAll('.perm-check').forEach(cb => cb.checked = false);

        // Populate Checkboxes - Content
        if (perms.content) {
            const cv = document.querySelector('[name="perms[content_view]"]');
            const ca = document.querySelector('[name="perms[content_add]"]');
            const ce = document.querySelector('[name="perms[content_edit]"]');
            const cd = document.querySelector('[name="perms[content_delete]"]');
            if (cv) cv.checked = !!perms.content.view;
            if (ca) ca.checked = !!perms.content.add;
            if (ce) ce.checked = !!perms.content.edit;
            if (cd) cd.checked = !!perms.content.delete;
        }

        // Gallery
        if (perms.gallery) {
            const gv = document.querySelector('[name="perms[gallery_view]"]');
            const gm = document.querySelector('[name="perms[gallery_manage]"]');
            if (gv) gv.checked = !!perms.gallery.view;
            if (gm) gm.checked = !!perms.gallery.manage;
        }

        // Reservasi
        if (perms.reservasi) {
            const rv = document.querySelector('[name="perms[reservasi_view]"]');
            const rm = document.querySelector('[name="perms[reservasi_manage]"]');
            if (rv) rv.checked = !!perms.reservasi.view;
            if (rm) rm.checked = !!perms.reservasi.manage;
        }

        // News
        if (perms.news) {
            const nv = document.querySelector('[name="perms[news_view]"]');
            const nm = document.querySelector('[name="perms[news_manage]"]');
            if (nv) nv.checked = !!perms.news.view;
            if (nm) nm.checked = !!perms.news.manage;
        }

        // Reviews
        if (perms.reviews) {
            const rv_v = document.querySelector('[name="perms[reviews_view]"]');
            const rv_m = document.querySelector('[name="perms[reviews_manage]"]');
            if (rv_v) rv_v.checked = !!perms.reviews.view;
            if (rv_m) rv_m.checked = !!perms.reviews.manage;
        }

        // FAQ
        if (perms.faq) {
            const fv = document.querySelector('[name="perms[faq_view]"]');
            const fm = document.querySelector('[name="perms[faq_manage]"]');
            if (fv) fv.checked = !!perms.faq.view;
            if (fm) fm.checked = !!perms.faq.manage;
        }

        // Finance
        if (perms.finance) {
            const fiv = document.querySelector('[name="perms[finance_view]"]');
            const fim = document.querySelector('[name="perms[finance_manage]"]');
            if (fiv) fiv.checked = !!perms.finance.view;
            if (fim) fim.checked = !!perms.finance.manage;
        }

        // Settings
        const sm = document.querySelector('[name="perms[settings_manage]"]');
        if (sm) sm.checked = perms.settings && !!perms.settings.manage;

        // User Management Warning
        const um = document.getElementById('check-user-manage');
        const uw = document.getElementById('warning-user-manage');
        if (um) {
            um.checked = perms.user && !!perms.user.manage;
            if (uw) uw.style.display = um.checked ? 'flex' : 'none';
        }

        // System Export
        const se = document.querySelector('[name="perms[system_export]"]');
        if (se) se.checked = perms.system && !!perms.system.export;

        openDrawer();
    });
});

// Warning toggle for user management
document.getElementById('check-user-manage').addEventListener('change', function() {
    document.getElementById('warning-user-manage').style.display = this.checked ? 'flex' : 'none';
});

// ─── EXISTING SWEETALERT LOGIC ───
const params = new URLSearchParams(window.location.search);

// Sukses notification
if (params.has('pesan')) {
    Swal.fire({
        icon: 'success', title: 'Berhasil!', text: params.get('pesan'),
        confirmButtonColor: '#0e7c6b', timer: 3500, timerProgressBar: true
    }).then(() => {
        const url = new URL(window.location.href);
        url.searchParams.delete('pesan');
        window.history.replaceState({}, '', url);
    });
}

// Error dari POST
<?php if ($form_error): ?>
Swal.fire({
    icon: 'error', title: 'Gagal!', text: '<?= addslashes($form_error) ?>',
    confirmButtonColor: '#0e7c6b'
});
<?php endif; ?>

// Ubah Password via SweetAlert
document.querySelectorAll('.swal-change-pass').forEach(function(el) {
    el.addEventListener('click', function() {
        var id = this.getAttribute('data-id');
        var username = this.getAttribute('data-username');

        Swal.fire({
            title: 'Ubah Password',
            html: '<p style="font-size:.85rem;color:#8a8578;margin-bottom:1rem;">Akun: <strong>' + username + '</strong></p>' +
                  '<input type="password" id="swal-pass1" class="swal2-input" placeholder="Password baru (min 6 karakter)">' +
                  '<input type="password" id="swal-pass2" class="swal2-input" placeholder="Konfirmasi password baru">',
            showCancelButton: true,
            confirmButtonColor: '#0e7c6b',
            confirmButtonText: 'Simpan',
            cancelButtonText: 'Batal',
            cancelButtonColor: '#e4e4e7',
            preConfirm: function() {
                var p1 = document.getElementById('swal-pass1').value;
                var p2 = document.getElementById('swal-pass2').value;
                if (!p1 || p1.length < 6) {
                    Swal.showValidationMessage('Password minimal 6 karakter!');
                    return false;
                }
                if (p1 !== p2) {
                    Swal.showValidationMessage('Konfirmasi password tidak cocok!');
                    return false;
                }
                return { pass: p1, confirm: p2 };
            }
        }).then(function(result) {
            if (result.isConfirmed) {
                document.getElementById('cp-admin-id').value = id;
                document.getElementById('cp-new-pass').value = result.value.pass;
                document.getElementById('cp-confirm-pass').value = result.value.confirm;
                document.getElementById('form-change-pass').submit();
            }
        });
    });
});

// Hapus Admin via SweetAlert
document.querySelectorAll('.swal-delete-admin').forEach(function(el) {
    el.addEventListener('click', function() {
        var id = this.getAttribute('data-id');
        var username = this.getAttribute('data-username');

        Swal.fire({
            icon: 'warning',
            title: 'Hapus Admin?',
            html: 'Akun <strong>' + username + '</strong> akan dihapus permanen.',
            showCancelButton: true,
            confirmButtonColor: '#c0392b',
            cancelButtonColor: '#e4e4e7',
            confirmButtonText: 'Ya, Hapus',
            cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) {
                document.getElementById('del-admin-id').value = id;
                document.getElementById('form-delete-admin').submit();
            }
        });
    });
});

// Logout confirmation
document.querySelectorAll('.swal-logout').forEach(function(el) {
    el.addEventListener('click', function(e) {
        e.preventDefault();
        var tujuan = this.getAttribute('href');
        Swal.fire({
            icon: 'question', title: 'Yakin ingin logout?',
            text: 'Sesi admin kamu akan diakhiri.',
            showCancelButton: true, confirmButtonColor: '#0e7c6b',
            cancelButtonColor: '#e4e4e7', confirmButtonText: 'Ya, Logout', cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) window.location.href = tujuan;
        });
    });
});

// ── NOTIFIKASI SWEETALERT ──
if (params.has('pesan')) {
    Swal.fire({
        icon: 'success', title: 'Berhasil!', text: params.get('pesan'),
        confirmButtonColor: '#0e7c6b', timer: 3500, timerProgressBar: true
    }).then(() => {
        const url = new URL(window.location.href);
        url.searchParams.delete('pesan');
        window.history.replaceState({}, '', url);
    });
}
if (params.has('error')) {
    Swal.fire({
        icon: 'error', title: 'Gagal!', text: params.get('error'),
        confirmButtonColor: '#0e7c6b'
    }).then(() => {
        const url = new URL(window.location.href);
        url.searchParams.delete('error');
        window.history.replaceState({}, '', url);
    });
}

// Inisialisasi Lucide Icons
lucide.createIcons();
</script>

</body>
</html>
