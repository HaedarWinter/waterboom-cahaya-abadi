<?php
/**
 * Export Handler — CSV & PDF
 * Mengexport data buku tamu ke format CSV atau PDF (HTML-based)
 */
session_start();

// Proteksi: hanya admin yang bisa export
if (!isset($_SESSION['login'])) {
    header("Location: /ujikom1/login.php?error=Silakan login terlebih dahulu!");
    exit;
}

require '../../config/koneksi.php';
require '../../config/security.php';
check_session_timeout();

// ─── PROTEKSI PERMISSION ───
if (!has_permission('system', 'export')) {
    header("Location: /ujikom1/admin/?error=Anda tidak memiliki izin untuk mengekspor data.");
    exit;
}

$type   = isset($_GET['type']) ? $_GET['type'] : 'csv';
$search = isset($_GET['q']) ? trim($_GET['q']) : '';

// Query data — sama seperti di dashboard tapi tanpa pagination (ambil semua)
if ($search) {
    $search_param = "%$search%";
    $stmt = $koneksi->prepare("SELECT * FROM buku_tamu WHERE nama LIKE ? OR email LIKE ? OR no_hp LIKE ? ORDER BY tanggal DESC, id DESC");
    $stmt->bind_param("sss", $search_param, $search_param, $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $koneksi->query("SELECT * FROM buku_tamu ORDER BY tanggal DESC, id DESC");
}

// ─── EXPORT CSV ───────────────────────────────────────────────────────
if ($type === 'csv') {
    $filename = 'buku_tamu_' . date('Y-m-d_His') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // BOM untuk Excel biar UTF-8 kebaca
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header kolom
    fputcsv($output, ['No', 'Nama', 'No HP', 'Email', 'Tanggal Reservasi', 'Tanggal Daftar']);
    
    $no = 1;
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $no++,
            $row['nama'],
            $row['no_hp'],
            $row['email'],
            date('d/m/Y H:i', strtotime($row['tanggal'])),
            date('d/m/Y H:i', strtotime($row['created_at']))
        ]);
    }
    
    fclose($output);
    exit;
}

// ─── EXPORT PDF (HTML-based printable page) ───────────────────────────
if ($type === 'pdf') {
    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Laporan Buku Tamu — Villa Buaya</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; padding: 2rem; color: #1a1a1a; font-size: 13px; }
        .header { text-align: center; margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 2px solid #0e7c6b; }
        .header h1 { font-size: 1.3rem; font-weight: 700; }
        .header p { color: #8a8578; font-size: .85rem; margin-top: .25rem; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 1rem; font-size: .8rem; color: #8a8578; }
        table { width: 100%; border-collapse: collapse; margin-top: .5rem; }
        th, td { padding: .5rem .75rem; border: 1px solid #ddd9d0; text-align: left; font-size: .8rem; }
        th { background: #f5f3ef; font-weight: 600; text-transform: uppercase; font-size: .72rem; letter-spacing: .3px; color: #666; }
        tbody tr:nth-child(even) { background: #faf9f6; }
        .footer { margin-top: 1.5rem; text-align: center; font-size: .75rem; color: #aaa; border-top: 1px solid #ddd9d0; padding-top: 1rem; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align:center; margin-bottom: 1rem;">
        <button onclick="window.print()" style="padding:.5rem 1.5rem; background:#0e7c6b; color:#fff; border:none; border-radius:8px; cursor:pointer; font-size:.85rem;">
            Cetak / Simpan PDF
        </button>
        <button onclick="window.close()" style="padding:.5rem 1.5rem; background:#f0eeea; color:#1a1a1a; border:1px solid #ddd9d0; border-radius:8px; cursor:pointer; font-size:.85rem; margin-left:.5rem;">
            Tutup
        </button>
    </div>

    <div class="header">
        <h1>Laporan Data Buku Tamu</h1>
        <p>Wisata Kolam Renang Villa Buaya — Soreang, Ciherang</p>
    </div>

    <div class="meta">
        <span>Tanggal cetak: <?= date('d F Y, H:i') ?> WIB</span>
        <span>Total data: <?= count($rows) ?> pengunjung<?= $search ? ' (filter: "' . htmlspecialchars($search) . '")' : '' ?></span>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:40px">No</th>
                <th>Nama</th>
                <th>No HP</th>
                <th>Email</th>
                <th>Tgl Reservasi</th>
                <th>Tgl Daftar</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $i => $row): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= htmlspecialchars($row['nama']) ?></td>
                <td><?= htmlspecialchars($row['no_hp']) ?></td>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($row['tanggal'])) ?></td>
                <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <p>Dokumen ini dicetak otomatis oleh sistem Villa Buaya Admin Panel</p>
    </div>

    <script>
        // Auto print saat halaman dibuka
        window.onload = function() { window.print(); };
    </script>
</body>
</html>
<?php
    exit;
}

// Fallback: type tidak dikenali
header("Location: /ujikom1/admin/?error=Format export tidak valid.");
exit;
?>
