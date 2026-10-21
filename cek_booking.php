<?php
require_once 'app/init.php';
use App\Models\Reservasi;
use App\Models\Setting;

$page_title = "Cek Status Booking";
$current_page = 'cek_booking';

$booking = null;
$error = '';
$success = '';

// Handle Upload Bukti Bayar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_bukti') {
    $kode = trim($_POST['kode'] ?? '');
    $booking = Reservasi::getByKode($kode);
    
    if (!validate_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Token keamanan tidak valid.";
    } elseif (!$booking) {
        $error = "Kode booking tidak valid.";
    } elseif ($booking['status'] !== 'Ditolak' && $booking['bukti_bayar'] && file_exists('assets/bukti_bayar/' . $booking['bukti_bayar'])) {
        $error = "Bukti pembayaran sudah pernah diunggah.";
    } elseif (!isset($_FILES['bukti']) || $_FILES['bukti']['error'] !== UPLOAD_ERR_OK) {
        $error = "Gagal mengunggah file. Pastikan file dipilih.";
    } else {
        $allowed = ['image/jpeg', 'image/png', 'image/webp'];
        $mime = '';
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $_FILES['bukti']['tmp_name']);
            finfo_close($finfo);
        } else {
            // fallback jika fileinfo extension tidak tersedia
            $mime = strtolower($_FILES['bukti']['type'] ?? '');
        }

        // Peta MIME → ekstensi aman. Ekstensi final TIDAK ambil dari nama asli
        // uploadan user (antisipasi file polos seperti 'shell.php.jpg').
        $ext_map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $ext = $ext_map[$mime] ?? '';

        if (!in_array($mime, $allowed) || $ext === '') {
            $error = "Format file tidak didukung. Gunakan JPG, PNG, atau WebP.";
        } elseif ($_FILES['bukti']['size'] > 3 * 1024 * 1024) {
            $error = "Ukuran file terlalu besar (Maks 3MB).";
        } else {
            if (!is_dir('assets/bukti_bayar')) {
                mkdir('assets/bukti_bayar', 0777, true);
            }
            $filename = 'bukti_' . $booking['kode_booking'] . '_' . time() . '.' . $ext;
            $target = 'assets/bukti_bayar/' . $filename;

            if (move_uploaded_file($_FILES['bukti']['tmp_name'], $target)) {
                $db->query("UPDATE reservasi SET bukti_bayar = ?, status = 'Pending' WHERE id = ?", [$filename, $booking['id']], "si");
                
                // NOTIFIKASI ADMIN
                $admin_wa = Setting::get('admin_wa_number', '');
                if (!empty($admin_wa)) {
                    $wa_msg = "🔔 *Pemberitahuan Admin*\nAda bukti pembayaran baru diunggah!\n\nNama: *{$booking['nama']}*\nKode Booking: *{$booking['kode_booking']}*\nTotal: *Rp " . format_harga($booking['total_harga']) . "*\n\nCek dashboard admin untuk verifikasi:\n" . base_url('admin/reservasi.php?status=Pending');
                    \App\Models\WhatsApp::send($admin_wa, $wa_msg);
                }

                $booking = Reservasi::getByKode($booking['kode_booking']);
                $success = "Bukti pembayaran berhasil diunggah! Antrean Anda sedang diverifikasi admin.";
            } else {
                $error = "Gagal menyimpan file.";
            }
        }
    }
    regenerate_csrf();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' || isset($_GET['kode'])) {
    $kode = trim($_POST['kode'] ?? $_GET['kode'] ?? '');
    if (!empty($kode)) {
        if (!$booking) {
            $booking = Reservasi::getByKode($kode);
        }
        if (!$booking) {
            $error = "Kode booking '$kode' tidak ditemukan. Pastikan kode booking benar.";
        }
    } elseif (!isset($_POST['action'])) {
        $error = "Silakan masukkan kode booking Anda.";
    }
}

// Get bank settings
$rekening_bank = Setting::get('rekening_bank', 'BCA');
$rekening_nomor = Setting::get('rekening_nomor', '1234567890');
$rekening_nama = Setting::get('rekening_nama', 'Waterboom Cahaya Abadi');


// Warna status
$status_colors = [
    'Pending'       => ['bg' => '#fffbeb', 'color' => '#92400e', 'border' => '#fef3c7'],
    'Dikonfirmasi'  => ['bg' => '#ecfdf5', 'color' => '#065f46', 'border' => '#d1fae5'],
    'Selesai'       => ['bg' => '#e8f0fa', 'color' => '#075985', 'border' => '#e0f2fe'],
    'Dibatalkan'    => ['bg' => '#fef2f2', 'color' => '#991b1b', 'border' => '#fee2e2'],
    'Ditolak'       => ['bg' => '#fff1f2', 'color' => '#e11d48', 'border' => '#ffe4e6'],
];

$extra_head = '
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode/1.5.1/qrcode.min.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        @media (min-width: 992px) {
            .main-content {
                display: grid !important;
                grid-template-columns: 1fr 1.2fr !important;
                gap: 2rem !important;
                max-width: 1100px !important;
                margin-left: auto !important;
                margin-right: auto !important;
                align-items: start !important;
            }
            .search-card { 
                grid-column: 1 / -1; 
                max-width: 600px;
                margin-left: auto;
                margin-right: auto;
                width: 100%;
            }
        }
        /* Style for Payment Tabs */
        .payment-tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #f1f5f9;
            padding: 4px;
            border-radius: 12px;
            margin-bottom: 1.25rem;
        }
        .pay-tab {
            border: none;
            background: transparent;
            padding: 10px;
            border-radius: 9px;
            font-size: 0.85rem;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .pay-tab.active {
            background: #fff;
            color: var(--accent);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .payment-content { display: none; }
        .payment-content.active { display: block; animation: fadeIn 0.3s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }
        
        .qris-container {
            text-align: center;
            background: #fff;
            padding: 1rem;
            border-radius: 16px;
            border: 1px solid var(--border);
        }
        .qris-img {
            max-width: 100%;
            height: auto;
            border-radius: 8px;
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
    </style>';

include 'includes/layouts/header.php';
include 'includes/layouts/navbar.php';
?>

<div class="main-content" style="margin-top:6rem;">

    <!-- FORM CEK -->
    <div class="card search-card">
        <div class="card-header">
            <i data-lucide="search" class="card-icon"></i>
            <h2>Cek Status Booking</h2>
        </div>
        <p class="deskripsi">Masukkan kode booking yang Anda terima saat reservasi.</p>

        <form method="post" action="cek_booking.php">
            <div class="form-group">
                <label for="kode">Kode Booking</label>
                <input type="text" id="kode" name="kode" placeholder="Contoh: CA-A1B2C3"
                       value="<?= htmlspecialchars($_POST['kode'] ?? $_GET['kode'] ?? '') ?>"
                       style="text-transform:uppercase;letter-spacing:1px;font-weight:700;font-size:1.1rem;text-align:center;" required>
            </div>
            <button type="submit" class="btn" style="width:100%;">
                <i data-lucide="search"></i> Cari Booking
            </button>
        </form>
    </div>

    <?php if ($error): ?>
    <div class="card" style="text-align:center;padding:2rem;">
        <div style="width:56px;height:56px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
            <i data-lucide="x-circle" style="width:28px;height:28px;color:#991b1b;"></i>
        </div>
        <p style="color:#991b1b;font-weight:600;"><?= htmlspecialchars($error) ?></p>
    </div>
    <?php endif; ?>

    <?php if ($booking): ?>
    <?php $sc = $status_colors[$booking['status']] ?? $status_colors['Pending']; ?>
    <div class="card" style="padding:2rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.25rem;">
            <div>
                <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);font-weight:600;">Kode Booking</div>
                <div style="font-size:1.5rem;font-weight:900;color:var(--accent);letter-spacing:2px;"><?= htmlspecialchars($booking['kode_booking']) ?></div>
            </div>
            <span style="background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;border:1px solid <?= $sc['border'] ?>;padding:6px 14px;border-radius:50px;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.3px;"><?= $booking['status'] ?></span>
        </div>

        <div style="background:var(--subtle);border-radius:12px;padding:1.25rem;font-size:.88rem;line-height:2;">
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:4px;"><span style="color:var(--muted);">Nama</span><strong><?= htmlspecialchars($booking['nama']) ?></strong></div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:4px;"><span style="color:var(--muted);">Nomor HP</span><strong><?= htmlspecialchars($booking['no_hp']) ?></strong></div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:4px;"><span style="color:var(--muted);">Tanggal Kunjungan</span><strong><?= date('d M Y', strtotime($booking['tanggal_kunjungan'])) ?></strong></div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:4px;"><span style="color:var(--muted);">Jenis Tiket</span><strong><?= $booking['jenis_tiket'] ?></strong></div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:4px;"><span style="color:var(--muted);">Dewasa / Anak</span><strong><?= $booking['jumlah_dewasa'] ?> / <?= $booking['jumlah_anak'] ?></strong></div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:4px;"><span style="color:var(--muted);">Total Harga</span><strong style="color:var(--accent);">Rp <?= format_harga($booking['total_harga']) ?></strong></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Metode Bayar</span><strong><?= htmlspecialchars($booking['metode_bayar'] ?? 'Bayar di Lokasi') ?></strong></div>
        </div>

        </div>

        <div class="card" style="padding:2rem;">
            <!-- BUKTI PEMBAYARAN STATUS -->
            <?php if ($booking['metode_bayar'] === 'Transfer Bank'): ?>
            <div style="padding:1.25rem;border-radius:12px;border:1.5px solid var(--border);background:var(--subtle);margin-bottom:1.5rem;">
                <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);font-weight:600;margin-bottom:12px;">Informasi Pembayaran</div>
                
                <?php if ($booking['status'] === 'Dikonfirmasi' || $booking['status'] === 'Selesai'): ?>
                    <div style="display:flex;align-items:center;gap:8px;color:#065f46;font-size:.88rem;font-weight:600;">
                        <i data-lucide="check-circle" style="width:18px;height:18px;"></i>
                        Pembayaran Terverifikasi
                    </div>
                <?php elseif ($booking['status'] === 'Pending' && $booking['bukti_bayar']): ?>
                    <div style="display:flex;align-items:center;gap:8px;color:#92400e;font-size:.88rem;font-weight:600;margin-bottom:10px;">
                        <i data-lucide="clock" style="width:18px;height:18px;"></i>
                        Menunggu verifikasi admin
                    </div>
                    <img src="assets/bukti_bayar/<?= htmlspecialchars($booking['bukti_bayar']) ?>" alt="Bukti" style="max-width:100%;max-height:150px;border-radius:8px;border:1px solid var(--border);">
                <?php else: ?>
                    <!-- PEMBAYARAN BELUM UPLOAD / DITOLAK -->
                    <?php if ($booking['status'] === 'Ditolak'): ?>
                        <div style="background:#fff1f2; border:1px solid #ffe4e6; border-radius:10px; padding:12px; margin-bottom:15px; color:#e11d48;">
                            <div style="font-size:0.7rem; font-weight:700; text-transform:uppercase; margin-bottom:4px; display:flex; align-items:center; gap:5px;">
                                <i data-lucide="x-circle" style="width:14px;"></i> Pembayaran Ditolak
                            </div>
                            <div style="font-size:0.85rem;"><strong>Alasan:</strong> <?= htmlspecialchars($booking['catatan_tolak']) ?></div>
                        </div>
                    <?php endif; ?>

                    <div style="background:#fffbeb; border:1px solid #fef3c7; border-radius:10px; padding:12px; margin-bottom:15px;">
                        <div style="font-size:0.7rem; color:#92400e; font-weight:700; text-transform:uppercase; margin-bottom:4px;">Batas Waktu Pembayaran</div>
                        <div style="font-size:1rem; color:#b45309; font-weight:800; display:flex; align-items:center; gap:8px;">
                            <i data-lucide="clock" style="width:18px; height:18px;"></i>
                            <?= date('d M Y, H:i', strtotime($booking['created_at'] . ' +24 hours')) ?> WIB
                        </div>
                    </div>

                    <!-- Tabs Metode -->
                    <div class="payment-tabs">
                        <button class="pay-tab active" data-target="bank">
                            <i data-lucide="building-2" style="width:14px;"></i> Bank
                        </button>
                        <button class="pay-tab" data-target="qris">
                            <i data-lucide="qr-code" style="width:14px;"></i> QRIS
                        </button>
                    </div>

                    <div id="bank" class="payment-content active">
                        <div style="background:#fff;border-radius:10px;padding:1rem;border:1px solid var(--border);font-size:.85rem;">
                            <div style="display:flex;justify-content:space-between;margin-bottom:5px;"><span style="color:var(--muted);">Bank</span><strong><?= htmlspecialchars($rekening_bank) ?></strong></div>
                            <div style="display:flex;justify-content:space-between;margin-bottom:5px;"><span style="color:var(--muted);">No. Rekening</span><strong style="color:var(--accent);" id="noRek"><?= htmlspecialchars($rekening_nomor) ?></strong></div>
                            <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">A/N</span><strong><?= htmlspecialchars($rekening_nama) ?></strong></div>
                        </div>
                        <button onclick="navigator.clipboard.writeText('<?= $rekening_nomor ?>');Swal.fire({icon:'success',title:'Disalin!',timer:1000,showConfirmButton:false})" class="btn btn-outline" style="width:100%;margin-top:8px;font-size:.75rem;padding:8px;">
                            <i data-lucide="copy" style="width:14px;"></i> Salin Rekening
                        </button>
                    </div>

                    <div id="qris" class="payment-content">
                        <div class="qris-container">
                            <img src="assets/qrs_payment.png" alt="QRIS" class="qris-img">
                            <a href="assets/qrs_payment.png" download class="btn btn-outline" style="width:100%;font-size:.75rem;padding:8px;">
                                <i data-lucide="download" style="width:14px;"></i> Unduh QRIS
                            </a>
                        </div>
                    </div>

                    <hr style="margin:1.5rem 0; border:0; border-top:1px dashed var(--border);">

                    <!-- FORM UPLOAD -->
                    <form method="post" enctype="multipart/form-data" action="cek_booking.php">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="upload_bukti">
                        <input type="hidden" name="kode" value="<?= htmlspecialchars($booking['kode_booking']) ?>">
                        
                        <div class="form-group">
                            <label style="font-size:.75rem;">Upload Bukti Transfer</label>
                            <input type="file" name="bukti" id="bukti" accept="image/*" required style="font-size:.8rem;">
                        </div>

                        <div id="previewContainer" style="display:none;margin-bottom:1rem;">
                            <img id="previewImg" style="max-width:100%;max-height:200px;border-radius:8px;border:1px solid var(--border);">
                        </div>

                        <button type="submit" class="btn" style="width:100%;">
                            <i data-lucide="upload"></i> Kirim Bukti Pembayaran
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <!-- QR CODE TIKET DIGITAL -->
        <?php if ($booking['status'] === 'Dikonfirmasi' && $booking['qr_token']): ?>
        <div style="margin-top:1.5rem;text-align:center;padding:1.5rem;background:var(--accent-light);border-radius:16px;border:2px dashed var(--accent);">
            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:var(--accent);font-weight:700;margin-bottom:1rem;">🎫 Tiket Digital Anda</div>
            <canvas id="qrCanvas" width="200" height="200" style="margin:0 auto; background: white; border-radius: 8px; display: block;"></canvas>
            <p style="font-size:.75rem;color:var(--muted);margin-top:.75rem;">Tunjukkan QR Code ini di pintu masuk</p>
            <button onclick="downloadTiket()" class="btn" style="margin-top:.75rem;font-size:.82rem;">
                <i data-lucide="download"></i> Download Tiket
            </button>
        </div>
        <?php endif; ?>

        <p style="text-align:center;font-size:.75rem;color:var(--muted);margin-top:1rem;">Dipesan pada: <?= date('d M Y, H:i', strtotime($booking['created_at'])) ?> WIB</p>
    </div>
    <?php endif; ?>

</div>

<?php
$js_content = '';

// Script untuk QR Code (Hanya jika dikonfirmasi)
if ($booking && $booking['status'] === 'Dikonfirmasi' && $booking['qr_token']) {
    $js_content .= '
    const qrData = "' . $booking['qr_token'] . '";
    const qrCanvas = document.getElementById("qrCanvas");
    if (qrCanvas && typeof QRCode !== "undefined") {
        QRCode.toCanvas(qrCanvas, qrData, {
            width: 200,
            margin: 2,
            color: { dark: "#0e7c6b", light: "#ffffff" }
        }, function (error) {
            if (error) console.error("QR Code Generation Error:", error);
        });
    }

    function downloadTiket() {
        var printWin = window.open("", "_blank", "width=500,height=800");
        var canvas = document.getElementById("qrCanvas");
        var qrImg = canvas.toDataURL("image/png");
        
        printWin.document.write(`
            <html>
            <head>
                <title>Tiket Digital - ${' . json_encode($booking['kode_booking']) . '}</title>
                <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;800&display=swap" rel="stylesheet">
                <style>
                    body { font-family: "Plus Jakarta Sans", sans-serif; background: #f4f4f5; display: flex; justify-content: center; padding: 2rem; margin: 0; }
                    .ticket { background: #fff; width: 350px; border-radius: 20px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.1); border: 1px solid #e4e4e7; }
                    .header { background: #0e7c6b; color: #fff; padding: 2rem 1.5rem; text-align: center; position: relative; }
                    .header h1 { margin: 0; font-size: 1.25rem; font-weight: 800; }
                    .header p { margin: 5px 0 0; font-size: 0.75rem; opacity: 0.8; letter-spacing: 1px; }
                    .stub-dot { position: absolute; bottom: -10px; left: 0; right: 0; height: 20px; background-image: radial-gradient(circle, #f4f4f5 8px, transparent 8px); background-size: 24px 20px; background-position: -12px 0; }
                    
                    .content { padding: 2rem 1.5rem; text-align: center; }
                    .qr-box { background: #f8fafc; padding: 1rem; border-radius: 16px; display: inline-block; margin-bottom: 1.5rem; border: 1px solid #e2e8f0; }
                    .kode { font-size: 1.75rem; font-weight: 800; color: #0e7c6b; letter-spacing: 3px; margin: 0.5rem 0; }
                    
                    .info-list { text-align: left; margin: 1.5rem 0; }
                    .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #e2e8f0; font-size: 0.85rem; }
                    .info-label { color: #71717a; font-weight: 500; }
                    .info-value { color: #18181b; font-weight: 700; }
                    
                    .footer { padding: 1.5rem; background: #fafafa; border-top: 1px solid #f4f4f5; text-align: center; font-size: 0.7rem; color: #a1a1aa; line-height: 1.5; }
                    
                    @media print {
                        body { background: #fff; padding: 0; }
                        .ticket { box-shadow: none; border: none; width: 100%; max-width: 400px; margin: 0 auto; }
                    }
                </style>
            </head>
            <body>
                <div class="ticket">
                    <div class="header">
                        <h1>Cahaya Abadi</h1>
                        <p>WATERBOOM & PARK</p>
                        <div class="stub-dot"></div>
                    </div>
                    
                    <div class="content">
                        <p style="font-size: 0.7rem; color: #71717a; font-weight: 700; text-transform: uppercase;">Tiket Digital Entry</p>
                        <div class="kode">${' . json_encode($booking['kode_booking']) . '}</div>
                        
                        <div class="qr-box">
                            <img src="${qrImg}" width="180" height="180">
                        </div>

                        <div class="info-list">
                            <div class="info-row"><span class="info-label">Nama</span><span class="info-value">${' . json_encode($booking['nama']) . '}</span></div>
                            <div class="info-row"><span class="info-label">Tanggal</span><span class="info-value">${' . json_encode(date('d M Y', strtotime($booking['tanggal_kunjungan']))) . '}</span></div>
                            <div class="info-row"><span class="info-label">Jenis Tiket</span><span class="info-value">${' . json_encode($booking['jenis_tiket']) . '}</span></div>
                            <div class="info-row"><span class="info-label">Orang</span><span class="info-value">${' . json_encode($booking['jumlah_dewasa'] + $booking['jumlah_anak']) . '} Orang</span></div>
                            <div class="info-row"><span class="info-label">Harga</span><span class="info-value" style="color:#0e7c6b;">Rp ${' . json_encode(format_harga($booking['total_harga'])) . '}</span></div>
                        </div>
                    </div>
                    
                    <div class="footer">
                        Harap tunjukkan QR Code ini kepada petugas di pintu masuk.<br>
                        <strong>Berlaku: ${' . json_encode(date('d M Y', strtotime($booking['tanggal_kunjungan']))) . '}</strong>
                    </div>
                </div>
                <script>
                    window.onload = function() {
                        setTimeout(function() { window.print(); }, 500);
                    }
                <\/script>
            </body>
            </html>
        `);
        printWin.document.close();
    }
    ';
}

// Script untuk Tab & Upload Preview
$js_content .= '
document.querySelectorAll(".pay-tab").forEach(tab => {
    tab.addEventListener("click", () => {
        document.querySelectorAll(".pay-tab").forEach(t => t.classList.remove("active"));
        document.querySelectorAll(".payment-content").forEach(c => c.classList.remove("active"));
        tab.classList.add("active");
        document.getElementById(tab.getAttribute("data-target")).classList.add("active");
    });
});

var fileInput = document.getElementById("bukti");
if (fileInput) {
    fileInput.addEventListener("change", function(e) {
        var container = document.getElementById("previewContainer");
        var img = document.getElementById("previewImg");
        if (this.files && this.files[0]) {
            var reader = new FileReader();
            reader.onload = function(ev) {
                img.src = ev.target.result;
                container.style.display = "block";
            };
            reader.readAsDataURL(this.files[0]);
        } else {
            container.style.display = "none";
        }
    });
}
';

// SweetAlert notifications
if ($success) {
    $js_content .= "Swal.fire({ icon:'success', title:'Berhasil!', text:" . json_encode($success) . ", confirmButtonColor:'#0e7c6b' });";
}
if ($error && isset($_POST['action'])) {
    $js_content .= "Swal.fire({ icon:'error', title:'Oops!', text:" . json_encode($error) . ", confirmButtonColor:'#0e7c6b' });";
}

$extra_footer = '<script>' . $js_content . '</script>';
include 'includes/layouts/footer.php';
?>
</body>
</html>
