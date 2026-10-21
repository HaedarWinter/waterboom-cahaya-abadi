<?php
/**
 * Security Helpers — CSRF, Session Timeout, Rate Limiting
 * Dipanggil di setiap halaman yang butuh proteksi keamanan
 */

// ─── SESSION TIMEOUT (30 MENIT) ───────────────────────────────────────
// Cek apakah session sudah expired berdasarkan waktu aktivitas terakhir
define('SESSION_TIMEOUT', 1800); // 30 menit dalam detik

function check_session_timeout() {
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        if ($elapsed > SESSION_TIMEOUT) {
            // Session sudah expired, hancurkan dan redirect ke login
            session_unset();
            session_destroy();
            session_start(); // start baru buat flash message
            header("Location: /ujikom1/login.php?error=Sesi Anda telah berakhir karena tidak aktif selama 30 menit. Silakan login kembali.");
            exit;
        }
    }
    // Update waktu aktivitas terakhir
    $_SESSION['last_activity'] = time();
}

// ─── STATUS CHECK (REAL-TIME) ────────────────────────────────────────
// Pastikan admin masih aktif (bukan Nonaktif)
function enforce_active_session($koneksi) {
    if (isset($_SESSION['admin_id'])) {
        $id = $_SESSION['admin_id'];
        $stmt = $koneksi->prepare("SELECT status FROM admin WHERE id = ? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();

        if (!$res || $res['status'] === 'Nonaktif') {
            session_unset();
            session_destroy();
            header("Location: /ujikom1/login.php?error=Akun Anda telah dinonaktifkan atau tidak ditemukan.");
            exit;
        }
    }
}

// ─── CSRF TOKEN ───────────────────────────────────────────────────────
// Generate token unik per session untuk mencegah Cross-Site Request Forgery

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Fungsi buat generate hidden input field CSRF
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

// Validasi token yang dikirim dari form
function validate_csrf($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Regenerate token setelah berhasil dipakai (one-time use)
function regenerate_csrf() {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ─── RATE LIMITING (SESSION-BASED) ────────────────────────────────────
// Batasi jumlah submit form dalam jangka waktu tertentu

define('RATE_LIMIT_MAX', 5);       // Maksimal 5 submit
define('RATE_LIMIT_WINDOW', 300);  // Dalam 5 menit (300 detik)

function check_rate_limit($form_name = 'default') {
    $key = 'rate_limit_' . $form_name;
    $now = time();

    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [];
    }

    // Buang timestamp yang sudah di luar window
    $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now) {
        return ($now - $timestamp) < RATE_LIMIT_WINDOW;
    });

    // Cek apakah sudah melebihi batas
    if (count($_SESSION[$key]) >= RATE_LIMIT_MAX) {
        return false; // Rate limited!
    }

    // Catat timestamp submit ini
    $_SESSION[$key][] = $now;
    return true;
}

// ─── PERMISSION CHECK ─────────────────────────────────────────────────
// Cek apakah admin memiliki hak akses tertentu
function has_permission($category, $action) {
    if (!isset($_SESSION['role'])) return false;
    
    // Super Admin selalu punya akses ke semua fitur
    if ($_SESSION['role'] === 'Super Admin') return true;

    // Untuk Admin biasa, cek array permissions di session
    if (isset($_SESSION['permissions'][$category][$action])) {
        return $_SESSION['permissions'][$category][$action] === true;
    }

    return false;
}

// Cek apakah admin aktif (status)
function is_active() {
    return isset($_SESSION['status']) && $_SESSION['status'] === 'Aktif';
}

// ─── ANTI-BOT (HONEYPOT) ──────────────────────────────────────────────
// Cek apakah field jebakan diisi. Jika diisi, kemungkinan besar itu bot.
function check_honeypot($field_name) {
    // Jika field tidak kosong, berarti bot (karena user normal tidak akan melihat field ini)
    return empty($_POST[$field_name]);
}
?>
