<?php
// mulai session buat ngecek dia udah punya 'karcis' login belom
session_start();
// kalo misal ternyata dia udah login sblumnya (session loginnya = true)
// yaudah langsung diculik bawa aja ke halaman admin.php gausah suruh login lagi cape
if (isset($_SESSION['login'])) {
    header("Location: /ujikom1/admin/");
    exit;
}

// Cek jika ada input login sebelumnya yang gagal biar gausah capek ngetik ulang
$old = isset($_SESSION['login_data']) ? $_SESSION['login_data'] : [];
// Bersihin sessionnya
unset($_SESSION['login_data']);

require '../config/security.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login Admin - Waterboom Cahaya Abadi</title>
    <script>
        (function() {
            try {
                var forced = new URLSearchParams(location.search).get('theme');
                if (forced !== 'dark' && forced !== 'light') forced = localStorage.getItem('theme') || 'light';
                if (forced === 'dark') {
                    document.documentElement.style.colorScheme = 'dark';
                    window.addEventListener('DOMContentLoaded', function() {
                        document.body.classList.add('dark-theme');
                    });
                }
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="assets/dist/css/app.min.css">
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

<div class="login-wrapper">
    <div class="login-box">
        <p style="font-size:.8rem; color:var(--accent); font-weight:600; letter-spacing:.5px; text-transform:uppercase; margin-bottom:6px;">Cahaya Abadi</p>
        <h2>Login Administrator</h2>
        <p class="sub">Masukkan akun Anda untuk mengakses panel admin</p>

        <form action="../app/Http/Controllers/Admin/cek_login.php" method="post">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="username">Username</label>
            <input type="text" id="username" name="username" placeholder="Masukkan username" value="<?= htmlspecialchars($old['username'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label for="password">Password</label>
                <!-- Untuk password dilarang keras diisi 'old' karena bahaya kalo ke-record browser atau inspecting element -->
                <input type="password" id="password" name="password" placeholder="Masukkan password" required>
            </div>
            <button type="submit" class="btn" style="width:100%;">Masuk</button>
        </form>
        <p style="text-align:center; margin-top:14px; font-size:12px;">
            <a href="index.php" style="color:var(--accent); text-decoration:none; font-weight:500;">← Kembali ke Beranda</a>
        </p>
    </div>
</div>

<script>
//  SWEETALERT2 HANDLER — LOGIN PAGE 

const params = new URLSearchParams(window.location.search);

// kalo ada param ?error= berarti login gagal, tampilkan modal error
if (params.has('error')) {
    Swal.fire({
        icon: 'error',
        title: 'Login Gagal!',
        text: params.get('error'),
        confirmButtonColor: '#0e7c6b',
        confirmButtonText: 'Coba Lagi'
    }).then(() => {
        // bersihin param dari URL biar ga muncul lagi
        const url = new URL(window.location.href);
        url.searchParams.delete('error');
        window.history.replaceState({}, '', url);
    });
}
</script>

</body>
</html>
