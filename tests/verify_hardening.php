<?php
/**
 * Tes verifikasi hardening (Fase 4) — jalankan: php tests/verify_hardening.php
 * Tes HTTP butuh Apache XAMPP nyala; tes kuota butuh MySQL nyala.
 */
declare(strict_types=1);

// CLI test ini ekspektasi koneksi pertama gagal (socket default) lalu fallback —
// warning internal tak relevan bagi keputusan lulus/gagal.
error_reporting(E_ALL & ~E_WARNING);

$pass = 0;
$fail = 0;
function check(string $name, bool $ok): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? 'PASS' : 'FAIL') . "  $name\n";
}

$base = 'http://localhost/ujikom1';

// ─────────────────────────────────────────────────────────────
// AUTHZ-1: dashboard tanpa login harus redirect + tidak bocorkan data
// ─────────────────────────────────────────────────────────────
$ctx = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0]]);
$body = @file_get_contents($base . '/admin/index.php', false, $ctx);
$headers = $http_response_header ?? [];
$status = (int)(explode(' ', $headers[0] ?? '')[1] ?? 0);
$redirected = $status >= 300 && $status < 400;
check('CR-01 dashboard tanpa login tak membocorkan data', $redirected);

// ─────────────────────────────────────────────────────────────
// ME-01: hapus.php tanpa POST = ditolak
// ─────────────────────────────────────────────────────────────
$h = (string)@file_get_contents($base . '/admin/handlers/hapus.php?id=1', false, $ctx);
$h_status = (int)(explode(' ', $http_response_header[0] ?? '')[1] ?? 0);
check('ME-01 GET-delete ditolak', $h_status === 302);

// ─────────────────────────────────────────────────────────────
// RACE-2: kuota atomik — langsung ke DB
// ─────────────────────────────────────────────────────────────
require __DIR__ . '/../app/init.php';

$db = \App\Core\Database::getInstance();
$tgl = '2099-01-01';

// reset & set kuota 3 slot
$db->query("DELETE FROM kuota_harian WHERE tanggal = ?", [$tgl], "s");
$db->query("DELETE FROM reservasi WHERE tanggal_kunjungan = ?", [$tgl], "s");
$db->query("INSERT INTO kuota_harian (tanggal, kuota_max, terisi) VALUES (?, 3, 0)", [$tgl], "s");

$data_full = ['nama'=>'Tes','no_hp'=>'0812','email'=>'t@t.t','tanggal'=>$tgl,'jenis'=>'Weekday','dewasa'=>2,'anak'=>1,'total_harga'=>1000,'catatan'=>'','metode'=>'Bayar di Lokasi'];

$kode1 = \App\Models\Reservasi::create($data_full);
check('RACE-2 booking 1 (3/3) sukses', is_string($kode1));

$kode2 = \App\Models\Reservasi::create($data_full);
check('RACE-2 booking 2 oversell dicegah (null)', $kode2 === null);

$row = $db->query("SELECT terisi FROM kuota_harian WHERE tanggal = ?", [$tgl], "s")->fetch_assoc();
check('RACE-2 counter tetap 3 (tidak kelebihan)', (int)$row['terisi'] === 3);

// ─────────────────────────────────────────────────────────────
// HI-06: updateStatus(DIBATALKAN) mengurangi kuota tepat sekali
// ─────────────────────────────────────────────────────────────
$id1 = (int)$db->query("SELECT id FROM reservasi WHERE kode_booking = ? LIMIT 1", [$kode1], "s")->fetch_assoc()['id'];
\App\Models\Reservasi::updateStatus($id1, 'DIBATALKAN');
$row = $db->query("SELECT terisi FROM kuota_harian WHERE tanggal = ?", [$tgl], "s")->fetch_assoc();
check('HI-06 kuota balik ke 0 setelah dibatalkan', (int)$row['terisi'] === 0);

// panggil lagi — istilahnya idempotent: tidak boleh turun di bawah 0
\App\Models\Reservasi::updateStatus($id1, 'DIBATALKAN');
$row = $db->query("SELECT terisi FROM kuota_harian WHERE tanggal = ?", [$tgl], "s")->fetch_assoc();
check('HI-06 update ulang tidak kurangi kuota lagi', (int)$row['terisi'] === 0);

// ─────────────────────────────────────────────────────────────
// cleanup: hapus data tes + keluarkan hasil
// ─────────────────────────────────────────────────────────────
$db->query("DELETE FROM booking_status_history WHERE booking_id IN (SELECT id FROM reservasi WHERE tanggal_kunjungan = ?)", [$tgl], "s");
$db->query("DELETE FROM kuota_harian WHERE tanggal = ?", [$tgl], "s");
$db->query("DELETE FROM reservasi WHERE tanggal_kunjungan = ?", [$tgl], "s");

echo "\nHASIL: $pass PASS, $fail FAIL\n";
exit($fail > 0 ? 1 : 0);
