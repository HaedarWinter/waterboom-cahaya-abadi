<?php
/**
 * Handler: Review & Rating (simpan publik, approve/tolak/hapus admin)
 */
require_once __DIR__ . '/../../app/init.php';

$action = $_POST['action'] ?? '';

// ── SIMPAN REVIEW BARU (dari halaman publik) ──
if ($action === 'simpan') {
if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        header("Location: /ujikom1/index.php?error_review=Token keamanan tidak valid.#testimoni");
        exit;
    }
    regenerate_csrf();

    if (!check_honeypot('website_url')) {
        header("Location: /ujikom1/index.php?pesan_review=Terima kasih atas ulasan Anda!#testimoni");
        exit;
    }

    if (!check_rate_limit('review')) {
        header("Location: /ujikom1/index.php?error_review=Terlalu banyak pengiriman. Silakan tunggu beberapa menit.#testimoni");
        exit;
    }

    $nama   = trim($_POST['nama'] ?? '');
    $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
    $ulasan = trim($_POST['ulasan'] ?? '');

    if (empty($nama)) {
        header("Location: /ujikom1/index.php?error_review=Nama wajib diisi!#testimoni");
        exit;
    }

    if (empty($ulasan)) {
        header("Location: /ujikom1/index.php?error_review=Ulasan wajib diisi!#testimoni");
        exit;
    }

    $stmt = $koneksi->prepare("INSERT INTO reviews (nama, rating, ulasan) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $nama, $rating, $ulasan);

    if ($stmt->execute()) {
        header("Location: /ujikom1/index.php?pesan_review=Terima kasih! Ulasan Anda akan ditampilkan setelah disetujui admin.#testimoni");
    } else {
        header("Location: /ujikom1/index.php?error_review=Gagal mengirim ulasan. Coba lagi.#testimoni");
    }
    exit;
}

// ── ADMIN ACTIONS ──
if (!isset($_SESSION['login'])) {
    header("Location: /ujikom1/login.php");
    exit;
}
check_session_timeout();
if (!has_canonical_permission('review.moderate')) {
    header("Location: /ujikom1/admin/?error=Akses ditolak.");
    exit;
}

if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    header("Location: /ujikom1/admin/reviews.php?error=Token tidak valid.");
    exit;
}
regenerate_csrf();

// APPROVE REVIEW
if ($action === 'approve') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $koneksi->prepare("UPDATE reviews SET status = 'Approved' WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: /ujikom1/admin/reviews.php?pesan=Review berhasil disetujui!");
    } else {
        header("Location: /ujikom1/admin/reviews.php?error=Gagal menyetujui review.");
    }
    exit;
}

// TOLAK REVIEW
if ($action === 'tolak') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $koneksi->prepare("UPDATE reviews SET status = 'Ditolak' WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: /ujikom1/admin/reviews.php?pesan=Review ditolak.");
    } else {
        header("Location: /ujikom1/admin/reviews.php?error=Gagal menolak review.");
    }
    exit;
}

// HAPUS REVIEW
if ($action === 'hapus') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = $koneksi->prepare("DELETE FROM reviews WHERE id = ?");
    $stmt->bind_param("i", $id);
    if ($stmt->execute()) {
        header("Location: /ujikom1/admin/reviews.php?pesan=Review berhasil dihapus!");
    } else {
        header("Location: /ujikom1/admin/reviews.php?error=Gagal menghapus review.");
    }
    exit;
}

header("Location: /ujikom1/");
exit;
?>
