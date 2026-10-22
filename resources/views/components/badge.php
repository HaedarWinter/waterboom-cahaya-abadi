<?php
/**
 * Badge component - status badge synced with config/states.php
 * 
 * Usage:
 * echo render_badge('LUNAS');
 * echo render_badge('MENUNGGU_VERIFIKASI', 'lg');
 * 
 * Status → badge mapping:
 * - MENUNGGU_BAYAR, MENUNGGU_VERIFIKASI → warning (pending)
 * - LUNAS, SELESAI → success (verified/paid/completed)
 * - DITOLAK, KEDALUWARSA, DIBATALKAN, DIREFUND, TIDAK_HADIR → danger (cancelled/expired/rejected)
 * - REFUND_DIAJUKAN → info
 */

function render_badge(string $status, string $size = 'md'): string {
    $sizeClass = match ($size) {
        'sm' => 'c-badge--sm',
        'lg' => 'c-badge--lg',
        default => '',
    };
    
    // Map status ke variant design system
    $variant = match (true) {
        in_array($status, ['LUNAS', 'SELESAI'], true) => 'c-badge--verified',
        in_array($status, ['MENUNGGU_BAYAR', 'MENUNGGU_VERIFIKASI'], true) => 'c-badge--pending',
        in_array($status, ['DITOLAK', 'KEDALUWARSA', 'DIBATALKAN', 'DIREFUND', 'TIDAK_HADIR'], true) => 'c-badge--cancelled',
        $status === 'REFUND_DIAJUKAN' => 'c-badge--info',
        default => 'c-badge--neutral',
    };
    
    $label = \App\Services\BookingStateService::label($status);
    
    return "<span class=\"c-badge {$variant} {$sizeClass}\">{$label}</span>";
}

/**
 * Payment method badge
 */
function render_payment_badge(string $method): string {
    $variant = match (true) {
        $method === 'Transfer Bank' => 'c-badge--brand',
        $method === 'QRIS' => 'c-badge--info',
        $method === 'Bayar di Lokasi' => 'c-badge--success',
        default => 'c-badge--neutral',
    };
    return "<span class=\"c-badge {$variant}\">{$method}</span>";
}

/**
 * Channel badge (ONLINE/COUNTER)
 */
function render_channel_badge(string $channel): string {
    return $channel === 'ONLINE' 
        ? '<span class="c-badge c-badge--brand">Online</span>'
        : '<span class="c-badge c-badge--neutral">Counter</span>';
}