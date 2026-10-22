<?php
/**
 * Handler: Update pengaturan website
 * Menerima POST dari admin/pengaturan.php
 */
session_start();
if (!isset($_SESSION['login'])) {
    header("Location: /ujikom1/login.php?error=Silakan login terlebih dahulu!");
    exit;
}

require '../../config/koneksi.php';
require '../../config/security.php';
check_session_timeout();
enforce_active_session($koneksi);

// Cek hak akses: settings.manage
if (!has_canonical_permission('settings.manage')) {
    header("Location: /ujikom1/admin/pengaturan.php?error=Akses ditolak! Anda tidak memiliki izin untuk mengubah pengaturan.");
    exit;
}

// Validasi method POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: /ujikom1/admin/pengaturan.php");
    exit;
}

// Validasi CSRF
if (!validate_csrf($_POST['csrf_token'] ?? '')) {
    header("Location: /ujikom1/admin/pengaturan.php?error=Token keamanan tidak valid.");
    exit;
}
regenerate_csrf();

// Daftar key yang valid untuk mencegah manipulasi
$valid_keys = [
    'harga_weekday', 'harga_weekend',
    'jam_buka', 'jam_tutup', 'hari_operasional',
    'alamat', 'no_telepon', 'instagram',
    'deskripsi_website',
    'fasilitas_utama', 'fasilitas_pendukung', 'fasilitas_tambahan',
    // Missing keys added below:
    'admin_wa_number', 'fonnte_token', 'wa_notif_enabled',
    'kuota_default', 'rekening_bank', 'rekening_nama', 'rekening_nomor'
];

// Ambil nilai yang ada saat ini untuk perbandingan
$existing = [];
$res = $koneksi->query("SELECT setting_key, setting_value, label FROM settings");
while ($row = $res->fetch_assoc()) {
    $existing[$row['setting_key']] = $row;
}

$settings = $_POST['settings'] ?? [];
$updated_count = 0;
$updated_labels = [];

foreach ($settings as $key => $value) {
    // Hanya proses key yang valid
    if (!isset($existing[$key]) || !in_array($key, $valid_keys)) continue;

    $value = trim($value);
    
    // Hanya update jika nilainya benar-benar berubah
    if ($value !== $existing[$key]['setting_value']) {
        $stmt = $koneksi->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        $stmt->bind_param("ss", $value, $key);
        if ($stmt->execute()) {
            $updated_count++;
            $updated_labels[] = $existing[$key]['label'];
        }
    }
}

if ($updated_count > 0) {
    $item_text = $updated_count === 1 ? $updated_labels[0] : implode(', ', $updated_labels);
    $msg = "Berhasil memperbarui: " . $item_text;
    header("Location: /ujikom1/admin/pengaturan.php?pesan=" . urlencode($msg));
} else {
    header("Location: /ujikom1/admin/pengaturan.php?error=Tidak ada perubahan yang dilakukan.");
}
exit;
?>
