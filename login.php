<?php 
// mulai session buat ngecek dia udah punya 'karcis' login belom
session_start();
// kalo misal ternyata dia udah login sblumnya (session loginnya = true)
// yaudah langsung diculik bawa aja ke halaman admin.php gausah suruh login lagi cape
if (isset($_SESSION['login'])) {
    header("Location: admin.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Wisata Cipamingkis</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="login-wrapper">
    <div class="login-box">
        <h2>Login Administrator</h2>
        <p class="sub">Masukkan akun Anda untuk mengakses panel admin</p>

        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <form action="cek_login.php" method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Masukkan username" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" placeholder="Masukkan password" required>
            </div>
            <button type="submit" class="btn">Masuk</button>
        </form>

        <p class="login-hint">Default: admin / admin123</p>
        <p style="text-align:center; margin-top:14px; font-size:12px;">
            <a href="index.php" style="color:#388e3c; text-decoration:none;">Kembali ke Beranda</a>
        </p>
    </div>
</div>

</body>
</html>
