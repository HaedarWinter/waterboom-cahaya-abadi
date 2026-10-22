<?php
/**
 * Pagination component - renders design system pagination
 * 
 * @param int $current_page Current page number
 * @param int $total_pages Total pages
 * @param array $params Extra query params to preserve
 * @param string $variant 'default' | 'compact' | 'wrapper'
 * 
 * Usage:
 * include 'resources/views/components/pagination.php';
 * render_pagination($page, $total_pages, ['status' => 'LUNAS']);
 */

function render_pagination(int $current_page, int $total_pages, array $params = [], string $variant = 'default'): string {
    if ($total_pages <= 1) return '';
    
    unset($params['page']);
    $base_query = http_build_query($params);
    $base = '?' . ($base_query ? $base_query . '&' : '');
    
    $html = '';
    $window = 2;
    $start_page = max(1, $current_page - $window);
    $end_page = min($total_pages, $current_page + $window);
    
    $classes = match ($variant) {
        'compact' => 'c-pagination c-pagination--compact',
        'wrapper' => 'c-pagination-wrapper',
        default => 'c-pagination',
    };
    
    $html = "<nav class=\"{$classes}\" aria-label=\"Pagination\">\n";
    
    if ($variant === 'wrapper') {
        $html .= "  <span class=\"c-pagination-wrapper__info\">Halaman {$current_page} dari {$total_pages}</span>\n";
        $html .= "  <div class=\"c-pagination\">\n";
    }
    
    // Previous
    if ($current_page > 1) {
        $html .= "    <a href=\"{$base}page=" . ($current_page - 1) . "\" class=\"c-pagination__btn c-pagination__prev\" rel=\"prev\">\n";
        $html .= "      <svg class=\"c-pagination__icon\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"15 18 9 12 15 6\"></polyline></svg>\n";
        $html .= "      Sebelumnya\n";
        $html .= "    </a>\n";
    }
    
    // Page 1
    if ($start_page > 1) {
        $html .= "    <a href=\"{$base}page=1\" class=\"c-pagination__btn\">1</a>\n";
        if ($start_page > 2) {
            $html .= "    <span class=\"c-pagination__ellipsis\" aria-hidden=\"true\">…</span>\n";
        }
    }
    
    // Middle pages
    for ($i = $start_page; $i <= $end_page; $i++) {
        $active = ($i === $current_page) ? ' active' : '';
        $html .= "    <a href=\"{$base}page={$i}\" class=\"c-pagination__btn{$active}\" " . ($i === $current_page ? 'aria-current="page"' : '') . ">{$i}</a>\n";
    }
    
    // Last page
    if ($end_page < $total_pages) {
        if ($end_page < $total_pages - 1) {
            $html .= "    <span class=\"c-pagination__ellipsis\" aria-hidden=\"true\">…</span>\n";
        }
        $html .= "    <a href=\"{$base}page={$total_pages}\" class=\"c-pagination__btn\">{$total_pages}</a>\n";
    }
    
    // Next
    if ($current_page < $total_pages) {
        $html .= "    <a href=\"{$base}page=" . ($current_page + 1) . "\" class=\"c-pagination__btn c-pagination__next\" rel=\"next\">\n";
        $html .= "      Selanjutnya\n";
        $html .= "      <svg class=\"c-pagination__icon\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"2\"><polyline points=\"9 18 15 12 9 6\"></polyline></svg>\n";
        $html .= "    </a>\n";
    }
    
    if ($variant === 'wrapper') {
        $html .= "  </div>\n";
    }
    
    $html .= "</nav>\n";
    return $html;
}