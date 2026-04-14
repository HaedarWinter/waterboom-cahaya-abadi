<?php
// file ini buat ngancurin session (proses logout) biar orang lain gabisa buka admin panel
// panggil sessionnya dulu biar php tau session mana yg mau diancurin
session_start();

// bomb! semua data login (username, status) bakal kehapus dari memory server
session_destroy();

// abis kehapus, usernya dilempar balik ke halaman paling depan (index.php)
header("Location: index.php");
// exit biar aman
exit;
?>
