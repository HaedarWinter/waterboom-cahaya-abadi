<?php
/**
 * Settings Helper — Ambil pengaturan website dari tabel `settings`
 * Refactored: Sekarang menjembatani panggilan ke App\Models\Setting (MVC)
 */
use App\Models\Setting;

/**
 * Ambil value dari satu setting (Backward Compatibility)
 * @param string $key — nama setting_key
 * @param string $default — nilai default jika key tidak ditemukan
 * @return string
 */
function get_setting($key, $default = '') {
    // Memanggil class Model yang baru agar logic terpusat
    return Setting::get($key, $default);
}

/**
 * Format harga dari angka ke format Indonesia
 * @param string|int $amount — angka harga
 * @return string — contoh: "20.000"
 */
function format_harga($amount) {
    return number_format((int)$amount, 0, ',', '.');
}
