<?php
/**
 * admin/handlers/buku_kas_handler.php
 * Export CSV untuk Buku Kas Ledger
 */
require_once '../../app/init.php';
enforce_active_session($koneksi);

if (!has_canonical_permission('ledger.view')) {
    header("Location: ../buku_kas.php?error=Akses ditolak!");
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'export_csv') {
    // SEC-2: ekspor hanya POST bertoken CSRF.
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Token keamanan tidak valid. Muat ulang halaman lalu coba lagi.');
    }

    $bulan = $_POST['bulan'] ?? date('m');
    $tahun = $_POST['tahun'] ?? date('Y');
    $kategori = $_POST['kategori'] ?? '';
    $direction = $_POST['direction'] ?? '';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Buku_Kas_'.$tahun.'_'.$bulan.'.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    fputcsv($output, ['Buku Kas - Waterboom Cahaya Abadi']);
    fputcsv($output, ['Periode:', date('F', mktime(0,0,0,$bulan,1)), $tahun]);
    fputcsv($output, []);
    
    fputcsv($output, ['No. Entri', 'Tanggal', 'Akun Kas', 'Arah', 'Kategori', 'Nominal', 'Sumber', 'Catatan', 'Petugas', 'Posted At']);
    
    $where = "MONTH(le.entry_date) = ? AND YEAR(le.entry_date) = ?";
    $params = [(int)$bulan, (int)$tahun];
    $types = "ii";
    
    if ($kategori) {
        $where .= " AND le.category = ?";
        $params[] = $kategori;
        $types .= "s";
    }
    if ($direction) {
        $where .= " AND le.direction = ?";
        $params[] = $direction;
        $types .= "s";
    }
    
    $sql = "SELECT le.*, ca.name as account_name, COALESCE(a.username, 'SYSTEM') as created_by_name
            FROM ledger_entries le
            LEFT JOIN cash_accounts ca ON le.cash_account_id = ca.id
            LEFT JOIN admin a ON le.created_by = a.id
            WHERE $where
            ORDER BY le.entry_date ASC, le.id ASC";
    $stmt = $koneksi->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $total_in = 0;
    $total_out = 0;
    
    while ($row = $result->fetch_assoc()) {
        $amount = (int)$row['amount'];
        if ($row['direction'] === 'IN') {
            $total_in += $amount;
        } else {
            $total_out += $amount;
        }
        
        fputcsv($output, [
            $row['entry_no'],
            $row['entry_date'],
            $row['account_name'] ?? 'Kas Utama',
            $row['direction'],
            $row['category'],
            $amount,
            $row['source_type'] . ' #' . $row['source_id'],
            $row['note'] ?? '-',
            $row['created_by_name'],
            $row['posted_at']
        ]);
    }
    
    fputcsv($output, []);
    fputcsv($output, ['RINGKASAN']);
    fputcsv($output, ['Total Arus Masuk', $total_in]);
    fputcsv($output, ['Total Arus Keluar', $total_out]);
    fputcsv($output, ['Surplus/Defisit', $total_in - $total_out]);
    
    fclose($output);
    exit;
}

header("Location: ../buku_kas.php");
exit;
