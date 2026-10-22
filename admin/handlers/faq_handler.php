<?php
/**
 * Handler: FAQ (simpan, update, hapus, toggle aktif)
 */
session_start();
require '../../config/koneksi.php';
require '../../config/security.php';

if (!isset($_SESSION['login'])) {
    header("Location: /ujikom1/login.php");
    exit;
}
check_session_timeout();

if (!has_canonical_permission('content.faq.manage')) {
    header("Location: /ujikom1/admin/?error=Akses ditolak!");
    exit;
}

$action = $_POST['action'] ?? '';

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    header("Location: /ujikom1/admin/faq.php?error=Token tidak valid.");
    exit;
}
regenerate_csrf();

// ── SIMPAN FAQ BARU ──
if ($action === 'simpan') {
    $pertanyaan = trim($_POST['pertanyaan'] ?? '');
    $jawaban    = trim($_POST['jawaban'] ?? '');
    $urutan     = (int)($_POST['urutan'] ?? 0);

    if (empty($pertanyaan) || empty($jawaban)) {
        header("Location: /ujikom1/admin/faq.php?error=Pertanyaan dan jawaban wajib diisi!");
        exit;
    }

    $stmt = $koneksi->prepare("INSERT INTO faq (pertanyaan, jawaban, urutan) VALUES (?, ?, ?)");
    $stmt->bind_param("ssi", $pertanyaan, $jawaban, $urutan);

    if ($stmt->execute()) {
        header("Location: /ujikom1/admin/faq.php?pesan=FAQ berhasil ditambahkan!");
    } else {
        header("Location: /ujikom1/admin/faq.php?error=Gagal menyimpan FAQ.");
    }
    exit;
}

// ── UPDATE FAQ ──
if ($action === 'update') {
    $id         = (int)($_POST['id'] ?? 0);
    $pertanyaan = trim($_POST['pertanyaan'] ?? '');
    $jawaban    = trim($_POST['jawaban'] ?? '');
    $urutan     = (int)($_POST['urutan'] ?? 0);

    if (empty($pertanyaan) || empty($jawaban)) {
        header("Location: /ujikom1/admin/faq.php?error=Pertanyaan dan jawaban wajib diisi!");
        exit;
    }

    $stmt = $koneksi->prepare("UPDATE faq SET pertanyaan = ?, jawaban = ?, urutan = ? WHERE id = ?");
    $stmt->bind_param("ssii", $pertanyaan, $jawaban, $urutan, $id);

    if ($stmt->execute()) {
        header("Location: /ujikom1/admin/faq.php?pesan=FAQ berhasil diperbarui!");
    } else {
        header("Location: /ujikom1/admin/faq.php?error=Gagal memperbarui FAQ.");
    }
    exit;
}

// ── TOGGLE AKTIF ──
if ($action === 'toggle_aktif') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $koneksi->prepare("UPDATE faq SET aktif = IF(aktif='Ya','Tidak','Ya') WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: /ujikom1/admin/faq.php?pesan=Status FAQ berhasil diubah!");
    } else {
        header("Location: /ujikom1/admin/faq.php?error=Gagal mengubah status.");
    }
    exit;
}

// ── HAPUS FAQ ──
if ($action === 'hapus') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $koneksi->prepare("DELETE FROM faq WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: /ujikom1/admin/faq.php?pesan=FAQ berhasil dihapus!");
    } else {
        header("Location: /ujikom1/admin/faq.php?error=Gagal menghapus FAQ.");
    }
    exit;
}

header("Location: /ujikom1/admin/faq.php");
exit;
?>
