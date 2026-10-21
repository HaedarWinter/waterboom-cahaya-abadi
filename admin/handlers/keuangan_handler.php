<?php
/**
 * admin/handlers/keuangan_handler.php
 * Backend handler for Financial Audit (Keuangan)
 */
require_once '../../app/init.php';

enforce_active_session($koneksi);

// Skema laporan_keuangan dikelola oleh sql/migration_v3_foundation.sql.

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Check Manage Permission for non-export actions
if ($action !== 'export_csv' && !has_permission('finance', 'manage')) {
    header("Location: ../audit_keuangan.php?error=Akses ditolak! Anda tidak memiliki izin untuk mengelola keuangan.");
    exit;
}

// Check View Permission for export
if ($action === 'export_csv' && !has_permission('finance', 'view')) {
    header("Location: ../audit_keuangan.php?error=Akses ditolak!");
    exit;
}

// ─── ACTION: SIMPAN (TAMBAH/EDIT) ───
if ($action === 'simpan') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        header("Location: ../audit_keuangan.php?error=Token keamanan tidak valid. Silakan coba lagi.");
        exit;
    }
    
    $id        = (int)($_POST['id'] ?? 0);
    $tipe      = $_POST['tipe'] ?? 'Keluar';
    $kategori  = $_POST['kategori'] ?? 'Operasional';
    $nominal   = (float)str_replace(['.', ','], ['', '.'], $_POST['nominal'] ?? 0);
    $keterangan= $_POST['keterangan'] ?? '';
    $tanggal   = $_POST['tanggal'] ?? date('Y-m-d');
    $admin_id  = $_SESSION['admin_id'];
    $status    = $_POST['status'] ?? 'Final';

    // Upload Lampiran
    $lampiran_name = $_POST['old_lampiran'] ?? '';
    if (isset($_FILES['lampiran']) && $_FILES['lampiran']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['lampiran']['name'], PATHINFO_EXTENSION);
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        
        if (in_array(strtolower($ext), $allowed)) {
            $new_name = 'FIN_' . time() . '_' . rand(100, 999) . '.' . $ext;
            $upload_path = '../../assets/bukti_keuangan/' . $new_name;
            
            if (move_uploaded_file($_FILES['lampiran']['tmp_name'], $upload_path)) {
                // Hapus file lama jika ada
                if ($lampiran_name && file_exists('../../assets/bukti_keuangan/' . $lampiran_name)) {
                    unlink('../../assets/bukti_keuangan/' . $lampiran_name);
                }
                $lampiran_name = $new_name;
            }
        }
    }

    if ($id > 0) {
        // Update
        $stmt = $koneksi->prepare("UPDATE laporan_keuangan SET tipe=?, kategori=?, nominal=?, keterangan=?, lampiran=?, tanggal=?, status=? WHERE id=?");
        $stmt->bind_param("ssdssssi", $tipe, $kategori, $nominal, $keterangan, $lampiran_name, $tanggal, $status, $id);
    } else {
        // Insert
        $stmt = $koneksi->prepare("INSERT INTO laporan_keuangan (admin_id, tipe, kategori, nominal, keterangan, lampiran, tanggal, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issdssss", $admin_id, $tipe, $kategori, $nominal, $keterangan, $lampiran_name, $tanggal, $status);
    }

    if ($stmt->execute()) {
        header("Location: ../audit_keuangan.php?pesan=Data keuangan berhasil disimpan.");
    } else {
        header("Location: ../audit_keuangan.php?error=Gagal menyimpan data: " . $koneksi->error);
    }
    exit;
}

// ─── ACTION: HAPUS ───
if ($action === 'hapus') {
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        header("Location: ../audit_keuangan.php?error=Token keamanan tidak valid. Silakan coba lagi.");
        exit;
    }
    $id = (int)$_POST['id'];

    // Ambil info file buat dihapus
    $res = $koneksi->query("SELECT lampiran FROM laporan_keuangan WHERE id = $id");
    if ($res && $row = $res->fetch_assoc()) {
        if ($row['lampiran'] && file_exists('../../assets/bukti_keuangan/' . $row['lampiran'])) {
            unlink('../../assets/bukti_keuangan/' . $row['lampiran']);
        }
    }

    $stmt = $koneksi->prepare("DELETE FROM laporan_keuangan WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        header("Location: ../audit_keuangan.php?pesan=Data berhasil dihapus.");
    } else {
        header("Location: ../audit_keuangan.php?error=Gagal menghapus data.");
    }
    exit;
}

// ─── ACTION: EXPORT CSV ───
if ($action === 'export_csv') {
    $bulan = $_GET['bulan'] ?? date('m');
    $tahun = $_GET['tahun'] ?? date('Y');
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Laporan_Keuangan_'.$tahun.'_'.$bulan.'.csv');
    
    $output = fopen('php://output', 'w');
    // BOM for Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Header
    fputcsv($output, ['Laporan Keuangan Waterboom Cahaya Abadi']);
    fputcsv($output, ['Periode:', date('F', mktime(0,0,0,$bulan,1)), $tahun]);
    fputcsv($output, []); // Empty line
    
    fputcsv($output, ['ID', 'Tanggal', 'TIPE', 'KATEGORI', 'KETERANGAN', 'GROSS (Rp)', 'TAX (Rp)', 'NET (Rp)', 'INPUT OLEH']);
    
    // 1. Ambil Pajak Rate
    $tax_rate = (float)get_setting('pajak_persen', 10) / 100;
    
    // 2. Ambil Transaksi Manual
    $sql_manual = "SELECT l.*, a.username FROM laporan_keuangan l JOIN admin a ON l.admin_id = a.id WHERE MONTH(l.tanggal) = ? AND YEAR(l.tanggal) = ? ORDER BY l.tanggal ASC";
    $stmt_m = $koneksi->prepare($sql_manual);
    $stmt_m->bind_param("ss", $bulan, $tahun);
    $stmt_m->execute();
    $res_manual = $stmt_m->get_result();
    
    $total_gross = 0;
    $total_tax = 0;
    $total_net = 0;

    while ($row = $res_manual->fetch_assoc()) {
        $nominal = (float)$row['nominal'];
        $is_income = $row['tipe'] === 'Masuk';
        $multiplier = $is_income ? 1 : -1;
        
        $gross = $nominal * $multiplier;
        $row_tax = ($is_income && $row['kategori'] !== 'Operasional') ? ($gross * $tax_rate) : 0; // Simplified logic: manual income taxed if not op
        $net = $gross - $row_tax;

        fputcsv($output, [
            $row['id'],
            $row['tanggal'],
            strtoupper($row['tipe']),
            $row['kategori'],
            $row['keterangan'],
            $gross,
            $row_tax,
            $net,
            $row['username']
        ]);
        
        $total_gross += $gross;
        $total_tax += $row_tax;
        $total_net += $net;
    }
    
    // 3. Ambil Pendapatan Sistem (Reservasi Lunas & Selesai)
    $sql_sys = "SELECT * FROM reservasi WHERE status IN ('SELESAI', 'LUNAS') AND MONTH(created_at) = ? AND YEAR(created_at) = ? ORDER BY created_at ASC";
    $stmt_s = $koneksi->prepare($sql_sys);
    $stmt_s->bind_param("ss", $bulan, $tahun);
    $stmt_s->execute();
    $res_sys = $stmt_s->get_result();
    
    if ($res_sys->num_rows > 0) {
        fputcsv($output, []);
        fputcsv($output, ['PENDAPATAN SISTEM (RESERVASI)']);
        while ($row = $res_sys->fetch_assoc()) {
            $total_harga = (float)$row['total_harga'];
            // Tax calculation (Tax Inclusive): Total = Net + Tax -> Total = Net + (Net * rate) -> Net = Total / (1 + rate)
            $net = $total_harga / (1 + $tax_rate);
            $tax = $total_harga - $net;

            fputcsv($output, [
                $row['kode_booking'],
                date('Y-m-d', strtotime($row['created_at'])),
                'MASUK',
                'Reservasi',
                'Booking ' . $row['kode_booking'] . ' (' . $row['nama'] . ')',
                $total_harga,
                $tax,
                $net,
                'SYSTEM'
            ]);
            
            $total_gross += $total_harga;
            $total_tax += $tax;
            $total_net += $net;
        }
    }
    
    fputcsv($output, []);
    fputcsv($output, ['', '', '', '', 'TOTAL AKHIR', $total_gross, $total_tax, $total_net]);
    
    fclose($output);
    exit;
}

header("Location: ../audit_keuangan.php");
exit;
