<?php
namespace App\Models;

use App\Core\Database;

class Dashboard {
    public static function getStats() {
        $db = Database::getInstance();
        $koneksi = $db->getConnection();
        
        $stats = [];
        
        // 1. Data Buku Tamu / Pengunjung
        $stats['total_visitors'] = $koneksi->query("SELECT COUNT(*) AS n FROM buku_tamu")->fetch_assoc()['n'];
        $stats['visitors_today'] = $koneksi->query("SELECT COUNT(*) AS n FROM buku_tamu WHERE DATE(tanggal) = CURDATE()")->fetch_assoc()['n'];
        
        // 2. Data Reservasi
        $stats['rsv_pending'] = $koneksi->query("SELECT COUNT(*) AS n FROM reservasi WHERE status = 'Pending'")->fetch_assoc()['n'];
        
        // 3. Pendapatan (Bulan Ini & Tahun Berjalan)
        $stats['rev_month'] = $koneksi->query("SELECT SUM(total_harga) AS s FROM reservasi WHERE status IN ('Dikonfirmasi', 'Selesai') AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetch_assoc()['s'] ?? 0;
        $stats['rev_ytd'] = $koneksi->query("SELECT SUM(total_harga) AS s FROM reservasi WHERE status IN ('Dikonfirmasi', 'Selesai') AND YEAR(created_at) = YEAR(CURDATE())")->fetch_assoc()['s'] ?? 0;
        
        // 4. Rating Rata-rata
        $stats['avg_rating'] = $koneksi->query("SELECT AVG(rating) as a FROM reviews WHERE status = 'Approved'")->fetch_assoc()['a'] ?? 0;
        
        // 5. Tren 14 Hari Terakhir (Visitors vs Revenue)
        $visitors_data = [];
        $res_v = $koneksi->query("SELECT DATE(tanggal) as tgl, COUNT(*) as n FROM buku_tamu WHERE tanggal >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(tanggal)");
        while($r = $res_v->fetch_assoc()) $visitors_data[$r['tgl']] = (int)$r['n'];
        
        $revenue_data = [];
        $res_r = $koneksi->query("SELECT DATE(created_at) as tgl, SUM(total_harga) as s FROM reservasi WHERE status IN ('Dikonfirmasi', 'Selesai') AND created_at >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) GROUP BY DATE(created_at)");
        while($r = $res_r->fetch_assoc()) $revenue_data[$r['tgl']] = (int)$r['s'];
        
        $chart_labels = [];
        $chart_visitors = [];
        $chart_revenue = [];
        $curr_week_rev = 0; $prev_week_rev = 0;
        $curr_week_visitors = 0; $prev_week_visitors = 0;
        
        for ($i = 13; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $chart_labels[] = date('d M', strtotime("-$i days"));
            
            $v = $visitors_data[$date] ?? 0;
            $r = $revenue_data[$date] ?? 0;
            
            $chart_visitors[] = $v;
            $chart_revenue[] = $r;
            
            if ($i < 7) {
                $curr_week_rev += $r;
                $curr_week_visitors += $v;
            } else {
                $prev_week_rev += $r;
                $prev_week_visitors += $v;
            }
        }
        
        $stats['rev_growth_pct'] = $prev_week_rev > 0 ? (($curr_week_rev - $prev_week_rev) / $prev_week_rev) * 100 : 0;
        $stats['vis_growth_pct'] = $prev_week_visitors > 0 ? (($curr_week_visitors - $prev_week_visitors) / $prev_week_visitors) * 100 : 0;
        $stats['chart_labels'] = $chart_labels;
        $stats['chart_visitors'] = $chart_visitors;
        $stats['chart_revenue'] = $chart_revenue;
        
        // 6. Distribusi Tiket (Weekday vs Weekend)
        $stats['dist_weekday'] = (int)$koneksi->query("SELECT COUNT(*) as n FROM reservasi WHERE jenis_tiket = 'Weekday'")->fetch_assoc()['n'];
        $stats['dist_weekend'] = (int)$koneksi->query("SELECT COUNT(*) as n FROM reservasi WHERE jenis_tiket = 'Weekend'")->fetch_assoc()['n'];
        
        // 7. Pendapatan Bulanan (Untuk Chart.js Per-Bulan)
        $monthly_revenue = array_fill(1, 12, 0);
        $res_m = $koneksi->query("SELECT MONTH(created_at) as bln, SUM(total_harga) as s FROM reservasi WHERE status IN ('Dikonfirmasi', 'Selesai') AND YEAR(created_at) = YEAR(CURDATE()) GROUP BY MONTH(created_at)");
        while($r = $res_m->fetch_assoc()) {
            $monthly_revenue[(int)$r['bln']] = (int)$r['s'];
        }
        $stats['monthly_revenue_data'] = array_values($monthly_revenue);
        $stats['monthly_labels'] = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];

        return $stats;
    }
}
