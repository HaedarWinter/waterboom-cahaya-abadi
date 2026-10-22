<?php
require_once '../app/init.php';

// Redirect ke halaman cek booking karena fitur upload sudah digabung ke sana
$kode = $_GET['kode'] ?? '';
$url = base_url('cek_booking.php' . ($kode ? '?kode=' . urlencode($kode) : ''));

header("Location: $url");
exit;
