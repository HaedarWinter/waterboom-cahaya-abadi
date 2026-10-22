<?php
namespace App;

class PaginationHelper {
    /**
     * Render pagination links dengan angka 1,2,3... dan ellipsis.
     *
     * @param int   $current_page Halaman aktif
     * @param int   $total_pages  Total halaman
     * @param array $existing_params Parameter query yang harus dipertahankan (misal ['q' => 'foo'])
     * @return string HTML pagination
     */
    public static function renderPages($current_page, $total_pages, $existing_params = []) {
        if ($total_pages <= 1) return '';
        
        // Hapus page dari params yang ada agar tidak duplikat
        unset($existing_params['page']);
        $base_query = http_build_query($existing_params);
        $base = '?' . ($base_query ? $base_query . '&' : '');
        
        $html = '';
        $window = 2;
        
        $start_page = max(1, $current_page - $window);
        $end_page = min($total_pages, $current_page + $window);
        
        // Halaman 1 selalu tampil
        if ($start_page > 1) {
            $html .= '<a href="' . $base . 'page=1" class="page-btn">1</a>';
            if ($start_page > 2) {
                $html .= '<span class="page-ellipsis">…</span>';
            }
        }
        
        for ($i = $start_page; $i <= $end_page; $i++) {
            $active = ($i === $current_page) ? ' active' : '';
            $html .= '<a href="' . $base . 'page=' . $i . '" class="page-btn' . $active . '">' . $i . '</a>';
        }
        
        // Halaman terakhir selalu tampil
        if ($end_page < $total_pages) {
            if ($end_page < $total_pages - 1) {
                $html .= '<span class="page-ellipsis">…</span>';
            }
            $html .= '<a href="' . $base . 'page=' . $total_pages . '" class="page-btn">' . $total_pages . '</a>';
        }
        
        return $html;
    }
}