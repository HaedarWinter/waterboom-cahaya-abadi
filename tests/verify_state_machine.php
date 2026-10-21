<?php
/**
 * Tes state machine booking — jalankan: php tests/verify_state_machine.php
 * Butuh MySQL aktif dan sql/migration_v3_state_machine.sql sudah diterapkan.
 */
declare(strict_types=1);
error_reporting(E_ALL & ~E_WARNING);

$pass = 0;
$fail = 0;
function check(string $name, bool $ok): void {
    global $pass, $fail;
    $ok ? $pass++ : $fail++;
    echo ($ok ? 'PASS' : 'FAIL') . "  $name\n";
}

require __DIR__ . '/../app/init.php';

use App\Services\BookingStateService as State;

$db = \App\Core\Database::getInstance();
$tgl = '2099-02-02';

$db->query("DELETE FROM booking_status_history WHERE booking_id IN (SELECT id FROM reservasi WHERE tanggal_kunjungan = ?)", [$tgl], "s");
$db->query("DELETE FROM reservasi WHERE tanggal_kunjungan = ?", [$tgl], "s");
$db->query("DELETE FROM kuota_harian WHERE tanggal = ?", [$tgl], "s");
$db->query("INSERT INTO kuota_harian (tanggal, kuota_max, terisi) VALUES (?, 10, 0)", [$tgl], "s");

function buatBooking(string $tgl, int $orang = 2, string $metode = 'Transfer Bank'): array {
    $kode = \App\Models\Reservasi::create([
        'nama' => 'Tes State',
        'no_hp' => '081200000000',
        'email' => 'state@test.local',
        'tanggal' => $tgl,
        'jenis' => 'Weekday',
        'dewasa' => $orang,
        'anak' => 0,
        'total_harga' => 40000,
        'catatan' => '',
        'metode' => $metode,
    ]);
    $db = \App\Core\Database::getInstance();
    $res = $db->query("SELECT * FROM reservasi WHERE kode_booking = ? LIMIT 1", [$kode], "s");
    return $res && $res->num_rows ? $res->fetch_assoc() : null;
}

function terisi(string $tgl): int {
    $db = \App\Core\Database::getInstance();
    $row = $db->query("SELECT terisi FROM kuota_harian WHERE tanggal = ?", [$tgl], "s")->fetch_assoc();
    return (int)($row['terisi'] ?? -1);
}

// 1. Transisi ilegal ditolak, kuota tetap terkunci.
$a = buatBooking($tgl);
check('booking baru berstatus MENUNGGU_BAYAR', $a['status'] === State::MENUNGGU_BAYAR);
$out = State::transition((int)$a['id'], State::LUNAS, null, 'TES', 'lompat status');
check('MENUNGGU_BAYAR -> LUNAS ditolak', !$out['success']);
check('kuota tetap terkunci setelah transisi ilegal', terisi($tgl) === 2);

// 2. Upload bukti memindahkan status dan mencatat riwayat.
$out = State::submitProof((int)$a['id'], 'bukti_tes.jpg');
check('upload bukti -> MENUNGGU_VERIFIKASI', $out['success']);
$a = \App\Models\Reservasi::getById((int)$a['id']);
check('status tersimpan MENUNGGU_VERIFIKASI', $a['status'] === State::MENUNGGU_VERIFIKASI);
$h = $db->query("SELECT COUNT(*) n FROM booking_status_history WHERE booking_id = ?", [$a['id']], "i")->fetch_assoc();
check('riwayat transisi tercatat', (int)$h['n'] === 1);

// 3. Verifikasi atomik: sukses sekali, percobaan kedua ditolak.
$out1 = State::transition((int)$a['id'], State::LUNAS, null, 'TES', 'verifikasi');
$out2 = State::transition((int)$a['id'], State::LUNAS, null, 'TES', 'verifikasi ulang');
check('verifikasi pertama sukses', $out1['success']);
check('verifikasi kedua ditolak', !$out2['success']);
$a = \App\Models\Reservasi::getById((int)$a['id']);
check('QR token dibuat saat LUNAS', !empty($a['qr_token']));

// 4. Check-in atomik: sukses sekali.
$out1 = State::checkIn((int)$a['id'], null, 'TES');
$out2 = State::checkIn((int)$a['id'], null, 'TES');
check('check-in pertama sukses', $out1['success']);
check('check-in kedua ditolak', !$out2['success']);
$a = \App\Models\Reservasi::getById((int)$a['id']);
check('status akhir SELESAI', $a['status'] === State::SELESAI);
check('waktu check-in terisi', !empty($a['waktu_checkin']));

// 5. Pembatalan melepas kuota tepat sekali.
$b = buatBooking($tgl);
$out1 = State::transition((int)$b['id'], State::DIBATALKAN, null, 'TES', 'batal');
$out2 = State::transition((int)$b['id'], State::DIBATALKAN, null, 'TES', 'batal ulang');
check('pembatalan sukses', $out1['success']);
check('pembatalan ulang ditolak', !$out2['success']);
check('kuota dilepas tepat sekali', terisi($tgl) === 2);

// 6. Penolakan pembayaran tidak melepas kuota.
$c = buatBooking($tgl);
State::submitProof((int)$c['id'], 'bukti_tes2.jpg');
State::transition((int)$c['id'], State::DITOLAK, null, 'TES', 'bukti buram');
check('DITOLAK tidak melepas kuota', terisi($tgl) === 4);
$out = State::submitProof((int)$c['id'], 'bukti_tes3.jpg');
check('upload ulang setelah DITOLAK diizinkan', $out['success']);

// 7. Expire idempoten dan melepas kuota.
$d = buatBooking($tgl);
$db->query("UPDATE reservasi SET created_at = DATE_SUB(NOW(), INTERVAL 3 HOUR) WHERE id = ?", [$d['id']], "i");
$count1 = State::expireOverdue();
$count2 = State::expireOverdue();
$d = \App\Models\Reservasi::getById((int)$d['id']);
check('job expire mengubah status', $d['status'] === State::KEDALUWARSA);
check('expire pertama memproses booking', $count1 >= 1);
check('expire kedua tidak memproses booking yang sama', $count2 === 0);
check('kuota dilepas saat expire', terisi($tgl) === 4);

// 8. Booking bayar di lokasi tidak ikut kedaluwarsa.
$f = buatBooking($tgl, 2, 'Bayar di Lokasi');
$db->query("UPDATE reservasi SET created_at = DATE_SUB(NOW(), INTERVAL 3 HOUR) WHERE id = ?", [$f['id']], "i");
State::expireOverdue();
$f = \App\Models\Reservasi::getById((int)$f['id']);
check('booking bayar di lokasi tidak kedaluwarsa', $f['status'] === State::MENUNGGU_BAYAR);
check('kuota booking bayar di lokasi tetap terkunci', terisi($tgl) === 6);

// 9. Tanggal baru tanpa baris kuota tetap aman.
$tglBaru = '2099-03-03';
$db->query("DELETE FROM reservasi WHERE tanggal_kunjungan = ?", [$tglBaru], "s");
$db->query("DELETE FROM kuota_harian WHERE tanggal = ?", [$tglBaru], "s");
$e = buatBooking($tglBaru);
check('baris kuota dibuat otomatis', $e !== null);
check('kuota tanggal baru terisi benar', terisi($tglBaru) === 2);

// cleanup
$db->query("DELETE FROM booking_status_history WHERE booking_id IN (SELECT id FROM reservasi WHERE tanggal_kunjungan IN (?, ?))", [$tgl, $tglBaru], "ss");
$db->query("DELETE FROM reservasi WHERE tanggal_kunjungan IN (?, ?)", [$tgl, $tglBaru], "ss");
$db->query("DELETE FROM kuota_harian WHERE tanggal IN (?, ?)", [$tgl, $tglBaru], "ss");

echo "\nHASIL: $pass PASS, $fail FAIL\n";
exit($fail > 0 ? 1 : 0);
