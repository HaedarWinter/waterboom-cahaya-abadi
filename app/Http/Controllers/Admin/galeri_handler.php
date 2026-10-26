<?php
/**
 * Handler: CRUD Galeri (Upload, Edit, Hapus gambar)
 */
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: " . base_url("login.php?error=Silakan login terlebih dahulu!"));
    exit;
}

require '../../config/koneksi.php';
require '../../config/security.php';
check_session_timeout();
enforce_active_session(\App\Core\Database::getInstance()->getConnection());

// Cek hak akses: gallery.manage
if (!has_canonical_permission('content.gallery.manage')) {
    header("Location: " . base_url("admin/galeri.php?error=Anda tidak memiliki hak akses untuk mengelola galeri."));
    exit;
}

// Validasi method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . base_url("admin/galeri.php"));
    exit;
}

// Validasi CSRF
if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    header("Location: " . base_url("admin/galeri.php?error=Token keamanan tidak valid."));
    exit;
}
regenerate_csrf();

$action = $_POST['action'] ?? '';
$upload_dir = dirname(__DIR__, 4) . '/public/assets/galeri/';

// ── TAMBAH GAMBAR ──
if ($action === 'add') {
    $judul     = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $urutan    = (int)($_POST['urutan'] ?? 0);

    if (empty($judul)) {
        header("Location: " . base_url("admin/galeri.php?error=Judul gambar wajib diisi!"));
        exit;
    }

    // Validasi file upload
    if (!isset($_FILES['gambar']) || $_FILES['gambar']['error'] !== UPLOAD_ERR_OK) {
        header("Location: " . base_url("admin/galeri.php?error=Gagal mengupload gambar. Pastikan file dipilih."));
        exit;
    }

    $file = $_FILES['gambar'];
    $allowed = ['image/jpeg', 'image/png', 'image/webp'];
    $max_size = 2 * 1024 * 1024; // 2MB

    if (!in_array($file['type'], $allowed)) {
        header("Location: " . base_url("admin/galeri.php?error=Format file tidak didukung. Gunakan JPG, PNG, atau WEBP."));
        exit;
    }

    if ($file['size'] > $max_size) {
        header("Location: " . base_url("admin/galeri.php?error=Ukuran file terlalu besar. Maksimal 2MB."));
        exit;
    }

    // Generate nama file unik
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'galeri_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

    if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
        header("Location: " . base_url("admin/galeri.php?error=Gagal menyiapkan folder gambar."));
        exit;
    }

    if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
        header("Location: " . base_url("admin/galeri.php?error=Gagal menyimpan file gambar."));
        exit;
    }

    $stmt = \App\Core\Database::getInstance()->getConnection()->prepare("INSERT INTO galeri (judul, deskripsi, file_gambar, urutan) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $judul, $deskripsi, $filename, $urutan);

    if ($stmt->execute()) {
        header("Location: " . base_url("admin/galeri.php?pesan=Gambar berhasil ditambahkan!"));
    } else {
        unlink($upload_dir . $filename); // hapus file jika gagal insert
        header("Location: " . base_url("admin/galeri.php?error=Gagal menyimpan data galeri."));
    }
    exit;
}

// ── EDIT GAMBAR ──
if ($action === 'edit') {
    $id        = (int)($_POST['id'] ?? 0);
    $judul     = trim($_POST['judul'] ?? '');
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $urutan    = (int)($_POST['urutan'] ?? 0);
    $aktif     = ($_POST['aktif'] ?? 'Ya') === 'Ya' ? 'Ya' : 'Tidak';

    if (empty($judul)) {
        header("Location: " . base_url("admin/galeri.php?error=Judul gambar wajib diisi!"));
        exit;
    }

    // Cek apakah ada file baru diupload
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['gambar'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $max_size = 2 * 1024 * 1024;

        if (!in_array($file['type'], $allowed)) {
            header("Location: " . base_url("admin/galeri.php?error=Format file tidak didukung."));
            exit;
        }
        if ($file['size'] > $max_size) {
            header("Location: " . base_url("admin/galeri.php?error=Ukuran file terlalu besar. Maksimal 2MB."));
            exit;
        }

        // Hapus file lama
        $old = \App\Core\Database::getInstance()->getConnection()->query("SELECT file_gambar FROM galeri WHERE id = $id")->fetch_assoc();
        if ($old && file_exists($upload_dir . $old['file_gambar'])) {
            unlink($upload_dir . $old['file_gambar']);
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'galeri_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (!is_dir($upload_dir) && !mkdir($upload_dir, 0755, true) && !is_dir($upload_dir)) {
            header("Location: " . base_url("admin/galeri.php?error=Gagal menyiapkan folder gambar."));
            exit;
        }
        if (!move_uploaded_file($file['tmp_name'], $upload_dir . $filename)) {
            header("Location: " . base_url("admin/galeri.php?error=Gagal menyimpan file gambar."));
            exit;
        }

        $stmt = \App\Core\Database::getInstance()->getConnection()->prepare("UPDATE galeri SET judul = ?, deskripsi = ?, file_gambar = ?, urutan = ?, aktif = ? WHERE id = ?");
        $stmt->bind_param("sssisi", $judul, $deskripsi, $filename, $urutan, $aktif, $id);
    } else {
        // Tanpa ganti gambar
        $stmt = \App\Core\Database::getInstance()->getConnection()->prepare("UPDATE galeri SET judul = ?, deskripsi = ?, urutan = ?, aktif = ? WHERE id = ?");
        $stmt->bind_param("ssisi", $judul, $deskripsi, $urutan, $aktif, $id);
    }

    if ($stmt->execute()) {
        header("Location: " . base_url("admin/galeri.php?pesan=Data galeri berhasil diperbarui!"));
    } else {
        header("Location: " . base_url("admin/galeri.php?error=Gagal memperbarui data galeri."));
    }
    exit;
}

// ── HAPUS GAMBAR ──
if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);

    // Ambil nama file dulu sebelum hapus
    $old = \App\Core\Database::getInstance()->getConnection()->query("SELECT file_gambar FROM galeri WHERE id = $id")->fetch_assoc();

    $stmt = \App\Core\Database::getInstance()->getConnection()->prepare("DELETE FROM galeri WHERE id = ?");
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        // Hapus file fisik
        if ($old && file_exists($upload_dir . $old['file_gambar'])) {
            unlink($upload_dir . $old['file_gambar']);
        }
        header("Location: " . base_url("admin/galeri.php?pesan=Gambar berhasil dihapus!"));
    } else {
        header("Location: " . base_url("admin/galeri.php?error=Gagal menghapus gambar."));
    }
    exit;
}

// Jika action tidak dikenali
header("Location: " . base_url("admin/galeri.php"));
exit;
?>
