<?php
/**
 * Halaman Pengaturan Website — CMS Sederhana
 * Hanya Super Admin yang bisa mengakses
 */
require_once '../app/init.php';
enforce_active_session($koneksi);

// Cek hak akses: settings.manage
if (!has_canonical_permission('settings.manage')) {
    header("Location: /ujikom1/admin/?error=Akses ditolak! Anda tidak memiliki izin untuk mengubah pengaturan.");
    exit;
}

// Ambil semua settings, kelompokkan per kategori
$result = $koneksi->query("SELECT * FROM settings ORDER BY kategori, setting_key");
$settings = [];
while ($row = $result->fetch_assoc()) {
    $settings[$row['kategori']][] = $row;
}

// Label kategori untuk tampilan
$kategori_labels = [
    'tiket'        => ['icon' => 'ticket', 'title' => 'Harga Tiket'],
    'operasional'  => ['icon' => 'clock',  'title' => 'Jam Operasional'],
    'fasilitas'    => ['icon' => 'lightbulb', 'title' => 'Fasilitas'], 
    'kontak'       => ['icon' => 'phone',  'title' => 'Informasi Kontak'],
    'pembayaran'   => ['icon' => 'banknote', 'title' => 'Pembayaran'],
    'whatsapp'     => ['icon' => 'message-square', 'title' => 'WhatsApp Gateway'],
    'umum'         => ['icon' => 'globe',  'title' => 'Umum'],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pengaturan Website — Cahaya Abadi</title>
        <!-- Design System (Atomic CSS) -->
    <link rel="stylesheet" href="../public/assets/dist/css/app.min.css">
    
    <!-- Legacy Admin Styles (fallback) -->
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin_style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="admin-body">
<?php include 'includes/sidebar.php'; ?>

<!-- MAIN -->
<main class="admin-main">
    <header class="admin-header">
        <button class="sidebar-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">&#9776;</button>
        <div class="header-title">
            <h1 class="page-heading">Pengaturan Website</h1>
            <span class="breadcrumb"><a href="/ujikom1/admin/">Dashboard</a> / Pengaturan</span>
        </div>
        <a href="logout.php?token=<?= e(csrf_token()) ?>" class="header-logout swal-logout">
            <i data-lucide="log-out"></i>
            <span>Keluar</span>
        </a>
    </header>

    <div class="admin-content">
        <form action="handlers/settings_handler.php" method="post">
            <?= csrf_field() ?>

            <?php foreach ($kategori_labels as $kat_key => $kat_info): ?>
                <?php if (isset($settings[$kat_key])): ?>
                <div class="section-card">
                    <div class="section-header">
                        <div>
                            <h2 class="section-title">
                                <i data-lucide="<?= $kat_info['icon'] ?>" style="width:18px;height:18px;vertical-align:middle;margin-right:6px;color:#0e7c6b"></i>
                                <?= $kat_info['title'] ?>
                            </h2>
                            <p class="section-sub">Kelola informasi <?= strtolower($kat_info['title']) ?> yang ditampilkan di website</p>
                        </div>

                    </div>


                    <div style="padding: 1.5rem;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                            <?php foreach ($settings[$kat_key] as $s): ?>
                            <div class="form-group" style="margin-bottom: 0;">
                                <label for="<?= $s['setting_key'] ?>"><?= htmlspecialchars($s['label']) ?></label>
                                <?php if ($kat_key === 'fasilitas'): ?>
                                    <div class="tags-input-container" data-for="<?= $s['setting_key'] ?>" onclick="this.querySelector('input').focus()">
                                        <div class="tags-list"></div>
                                        <input type="text" class="tag-input" placeholder="Ketik lalu Enter..." autocomplete="off">
                                    </div>
                                    <input type="hidden" name="settings[<?= $s['setting_key'] ?>]" id="<?= $s['setting_key'] ?>"
                                           value="<?= htmlspecialchars($s['setting_value']) ?>">
                                <?php elseif (strlen($s['setting_value']) > 100): ?>
                                    <textarea name="settings[<?= $s['setting_key'] ?>]" id="<?= $s['setting_key'] ?>"
                                              rows="4" style="width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--radius);font-family:var(--font);font-size:.86rem;color:var(--fg);resize:vertical;outline:none;"
                                    ><?= htmlspecialchars($s['setting_value']) ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="settings[<?= $s['setting_key'] ?>]" id="<?= $s['setting_key'] ?>"
                                           value="<?= htmlspecialchars($s['setting_value']) ?>" required>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <div style="display:flex; gap:.75rem; justify-content:flex-end; padding-top:.5rem;">
                <a href="/ujikom1/admin/" class="btn btn-secondary">
                    <i data-lucide="x-circle"></i> Batal
                </a>
                <button type="submit" class="btn">
                    <i data-lucide="save"></i> Simpan Pengaturan
                </button>
            </div>
        </form>
    </div>
</main>

<script>
// SweetAlert notifications
const params = new URLSearchParams(window.location.search);

if (params.has('pesan')) {
    Swal.fire({
        icon: 'success', title: 'Berhasil!', text: params.get('pesan'),
        confirmButtonColor: '#0e7c6b', timer: 3500, timerProgressBar: true
    }).then(() => {
        const url = new URL(window.location.href);
        url.searchParams.delete('pesan');
        window.history.replaceState({}, '', url);
    });
}

if (params.has('error')) {
    Swal.fire({
        icon: 'error', title: 'Gagal!', text: params.get('error'),
        confirmButtonColor: '#0e7c6b'
    }).then(() => {
        const url = new URL(window.location.href);
        url.searchParams.delete('error');
        window.history.replaceState({}, '', url);
    });
}

// Logout confirmation
document.querySelectorAll('.swal-logout').forEach(function(el) {
    el.addEventListener('click', function(e) {
        e.preventDefault();
        var tujuan = this.getAttribute('href');
        Swal.fire({
            icon: 'question', title: 'Yakin ingin logout?',
            text: 'Sesi admin kamu akan diakhiri.',
            showCancelButton: true, confirmButtonColor: '#0e7c6b',
            cancelButtonColor: '#e4e4e7', confirmButtonText: 'Ya, Logout', cancelButtonText: 'Batal'
        }).then(function(result) {
            if (result.isConfirmed) window.location.href = tujuan;
        });
    });
});

// TAGS INPUT LOGIC
document.querySelectorAll('.tags-input-container').forEach(container => {
    const hiddenInput = document.getElementById(container.dataset.for);
    const tagsList = container.querySelector('.tags-list');
    const input = container.querySelector('.tag-input');
    
    let tags = hiddenInput.value.split(',').map(t => t.trim()).filter(t => t !== '');

    function renderTags() {
        tagsList.innerHTML = '';
        tags.forEach((tag, index) => {
            const tagEl = document.createElement('div');
            tagEl.className = 'tag-item';
            tagEl.innerHTML = `<span>${tag}</span><span class="tag-remove" data-index="${index}">&times;</span>`;
            tagEl.querySelector('.tag-remove').addEventListener('click', (e) => {
                e.stopPropagation();
                removeTag(index);
            });
            tagsList.appendChild(tagEl);
        });
        hiddenInput.value = tags.join(', ');
    }

    function addTag(value) {
        value = value.trim().replace(/,/g, '');
        if (value && !tags.includes(value)) {
            tags.push(value);
            renderTags();
        }
        input.value = '';
    }

    function removeTag(index) {
        tags.splice(index, 1);
        renderTags();
    }

    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            addTag(input.value);
        } else if (e.key === 'Backspace' && input.value === '' && tags.length > 0) {
            removeTag(tags.length - 1);
        }
    });

    input.addEventListener('input', (e) => {
        if (input.value.includes(',')) {
            addTag(input.value);
        }
    });

    input.addEventListener('blur', () => {
        addTag(input.value);
    });

    renderTags();
});

lucide.createIcons();
</script>

</body>
</html>
