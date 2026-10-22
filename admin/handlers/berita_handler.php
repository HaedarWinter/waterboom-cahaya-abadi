<?php
/**
 * Handler: Berita & Event (simpan, update, hapus, toggle aktif)
 */
session_start();
require '../../config/koneksi.php';
require '../../config/security.php';

use App\Models\Audit;

if (!isset($_SESSION['login'])) {
    header("Location: /ujikom1/login.php");
    exit;
}
check_session_timeout();

if (!has_canonical_permission('content.news.manage')) {
    header("Location: /ujikom1/admin/?error=Akses ditolak!");
    exit;
}

$action = $_POST['action'] ?? '';

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    header("Location: /ujikom1/admin/berita.php?error=Token tidak valid.");
    exit;
}
regenerate_csrf();

// ── SIMPAN BERITA BARU ──
if ($action === 'simpan') {
    $judul = trim($_POST['judul'] ?? '');
    $isi   = trim($_POST['isi'] ?? '');

    if (empty($judul) || empty($isi)) {
        header("Location: /ujikom1/admin/berita.php?error=Judul dan isi berita wajib diisi!");
        exit;
    }

    $gambar_name = null;
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['gambar']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed)) {
            header("Location: /ujikom1/admin/berita.php?error=Format gambar harus JPG, PNG, atau WebP!");
            exit;
        }
        if ($_FILES['gambar']['size'] > 3 * 1024 * 1024) {
            header("Location: /ujikom1/admin/berita.php?error=Ukuran gambar maksimal 3MB!");
            exit;
        }

        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $gambar_name = 'berita_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = '../../assets/berita/' . $gambar_name;
        move_uploaded_file($_FILES['gambar']['tmp_name'], $target);
    }

    $stmt = $koneksi->prepare("INSERT INTO berita (judul, isi, gambar) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $judul, $isi, $gambar_name);

    if ($stmt->execute()) {
        $new_id = $koneksi->insert_id;
        Audit::log('ADD', 'berita', $new_id, "Menambahkan berita baru: $judul");
        header("Location: /ujikom1/admin/berita.php?pesan=Berita berhasil ditambahkan!");
    } else {
        header("Location: /ujikom1/admin/berita.php?error=Gagal menyimpan berita.");
    }
    exit;
}

// ── UPDATE BERITA ──
if ($action === 'update') {
    $id    = (int)($_POST['id'] ?? 0);
    $judul = trim($_POST['judul'] ?? '');
    $isi   = trim($_POST['isi'] ?? '');

    if (empty($judul) || empty($isi)) {
        header("Location: /ujikom1/admin/berita.php?error=Judul dan isi berita wajib diisi!");
        exit;
    }

    // Cek apakah ada gambar baru
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['gambar']['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowed)) {
            header("Location: /ujikom1/admin/berita.php?error=Format gambar harus JPG, PNG, atau WebP!");
            exit;
        }
        if ($_FILES['gambar']['size'] > 3 * 1024 * 1024) {
            header("Location: /ujikom1/admin/berita.php?error=Ukuran gambar maksimal 3MB!");
            exit;
        }

        // Hapus gambar lama
        $old = $koneksi->query("SELECT gambar FROM berita WHERE id = $id")->fetch_assoc();
        if ($old && $old['gambar'] && file_exists('../../assets/berita/' . $old['gambar'])) {
            unlink('../../assets/berita/' . $old['gambar']);
        }

        $ext = pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION);
        $gambar_name = 'berita_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $target = '../../assets/berita/' . $gambar_name;
        move_uploaded_file($_FILES['gambar']['tmp_name'], $target);

        $stmt = $koneksi->prepare("UPDATE berita SET judul = ?, isi = ?, gambar = ? WHERE id = ?");
        $stmt->bind_param("sssi", $judul, $isi, $gambar_name, $id);
    } else {
        $stmt = $koneksi->prepare("UPDATE berita SET judul = ?, isi = ? WHERE id = ?");
        $stmt->bind_param("ssi", $judul, $isi, $id);
    }

    if ($stmt->execute()) {
        Audit::log('EDIT', 'berita', $id, "Memperbarui berita: $judul");
        header("Location: /ujikom1/admin/berita.php?pesan=Berita berhasil diperbarui!");
    } else {
        header("Location: /ujikom1/admin/berita.php?error=Gagal memperbarui berita.");
    }
    exit;
}

// ── TOGGLE AKTIF ──
if ($action === 'toggle_aktif') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $koneksi->prepare("UPDATE berita SET aktif = IF(aktif='Ya','Tidak','Ya') WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        Audit::log('TOGGLE', 'berita', $id, "Mengubah status aktif berita.");
        header("Location: /ujikom1/admin/berita.php?pesan=Status berita berhasil diubah!");
    } else {
        header("Location: /ujikom1/admin/berita.php?error=Gagal mengubah status.");
    }
    exit;
}

// ── HAPUS BERITA ──
if ($action === 'hapus') {
    $id = (int)($_POST['id'] ?? 0);
    // Hapus gambar
    $old = $koneksi->query("SELECT gambar FROM berita WHERE id = $id")->fetch_assoc();
    if ($old && $old['gambar'] && file_exists('../../assets/berita/' . $old['gambar'])) {
        unlink('../../assets/berita/' . $old['gambar']);
    }

    $stmt = $koneksi->prepare("DELETE FROM berita WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        Audit::log('DELETE', 'berita', $id, "Menghapus berita permanen.");
        header("Location: /ujikom1/admin/berita.php?pesan=Berita berhasil dihapus!");
    } else {
        header("Location: /ujikom1/admin/berita.php?error=Gagal menghapus berita.");
    }
    exit;
}

header("Location: /ujikom1/admin/berita.php");
exit;
?>
