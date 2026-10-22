<?php
require_once '../app/init.php';
check_session_timeout();
enforce_active_session($koneksi);

// Pastikan user punya akses mengelola reservasi
if (!has_canonical_permission('checkin.scan')) {
    header("Location: /ujikom1/admin/index.php?error=Anda tidak memiliki akses ke fitur ini.");
    exit;
}

$page_title = "Scanner Tiket Premium";
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?> — Cahaya Abadi</title>
        <!-- Design System (Atomic CSS) -->
    <link rel="stylesheet" href="../public/assets/dist/css/app.min.css">
    
    <!-- Legacy Admin Styles (fallback) -->
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/admin_style.css">
    <link rel="stylesheet" href="../css/scanner_style.css">
    <!-- html5-qrcode library -->
    <script src="https://unpkg.com/html5-qrcode"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="admin-body">

<?php include 'includes/sidebar.php'; ?>

<main class="admin-main">
    <header class="admin-header">
        <button class="sidebar-toggle">&#9776;</button>
        <div class="header-title">
            <h1 class="page-heading"><?= $page_title ?></h1>
            <span class="breadcrumb"><a href="index.php">Dashboard</a> / Scanner</span>
        </div>
    </header>

    <div class="admin-content">
        <div class="scanner-grid-layout">
            <div class="scanner-box" id="scanner-box">
                <div class="scanner-header">
                    <i data-lucide="scan-line" class="icon-scan"></i>
                    <h2>Validasi Tiket</h2>
                    <p>Arahkan kamera ke QR Code pengunjung</p>
                </div>
                
                <div class="camera-wrapper">
                    <div id="reader"></div>
                    <!-- CUSTOM OVERLAY -->
                    <div class="scan-overlay" id="scan-overlay">
                        <div class="scan-frame">
                            <div class="scan-corners"></div>
                            <div class="scan-corner-bottom"></div>
                            <div class="scan-line"></div>
                        </div>
                    </div>
                </div>

                <div class="scanner-controls">
                    <button id="btn-start" class="btn btn-scan-action btn-start">
                        <i data-lucide="camera"></i> Buka Kamera
                    </button>
                    <button id="btn-stop" class="btn btn-scan-action btn-stop hidden">
                        <i data-lucide="camera-off"></i> Tutup
                    </button>
                    <button id="btn-switch" class="btn btn-scan-action btn-switch hidden">
                        <i data-lucide="refresh-cw"></i> Ganti Kamera
                    </button>
                </div>
                
                <div class="scanner-footer">
                    <div class="manual-input-group">
                        <label><i data-lucide="keyboard"></i> Atau masukkan kode booking manual:</label>
                        <div class="manual-input-wrapper">
                            <div class="input-with-icon">
                                <i data-lucide="ticket" class="input-icon"></i>
                                <input type="text" id="manual-code" placeholder="Misal: CA-123456" class="form-input">
                            </div>
                            <button id="btn-manual" class="btn btn-primary">
                                <i data-lucide="search"></i> Validasi
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Result Card -->
            <div id="visitor-card" class="visitor-card hidden">
                <div class="card-header">
                    <div class="status-badge" id="v-status-badge">LUNAS</div>
                    <h3 id="v-nama">Nama Pengunjung</h3>
                    <p id="v-kode">Kode Booking</p>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <span class="label">Tanggal Kunjungan</span>
                            <span class="value" id="v-tanggal">-</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Jenis Tiket</span>
                            <span class="value" id="v-jenis">-</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Dewasa</span>
                            <span class="value" id="v-dewasa">0</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Anak</span>
                            <span class="value" id="v-anak">0</span>
                        </div>
                        <div class="info-item full">
                            <span class="label">Status Pembayaran</span>
                            <span class="value" id="v-pembayaran">-</span>
                        </div>
                    </div>
                </div>
                <div class="card-footer" id="v-footer">
                    <!-- Buttons injected here -->
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    lucide.createIcons();

    const html5QrCode = new Html5Qrcode("reader");
    const config = { fps: 15, qrbox: { width: 250, height: 250 } };
    
    let isScanning = false;
    let cameras = [];
    let currentCameraIndex = 0;
    let currentQrToken = null;

    // Elements
    const btnStart = document.getElementById('btn-start');
    const btnStop = document.getElementById('btn-stop');
    const btnSwitch = document.getElementById('btn-switch');
    const scanOverlay = document.getElementById('scan-overlay');
    const scannerBox = document.getElementById('scanner-box');

    // Load Cameras
    Html5Qrcode.getCameras().then(devices => {
        if (devices && devices.length) {
            cameras = devices;
            if (cameras.length > 1) btnSwitch.classList.remove('hidden');
        }
    }).catch(err => {
        console.error("Error getting cameras", err);
    });

    btnStart.addEventListener('click', () => startScanner());
    btnStop.addEventListener('click', () => stopScanner());
    btnSwitch.addEventListener('click', () => switchCamera());

    function startScanner() {
        if (cameras.length === 0) {
            Swal.fire('Error', 'Kamera tidak ditemukan atau izin ditolak.', 'error');
            return;
        }

        btnStart.classList.add('hidden');
        btnStop.classList.remove('hidden');
        scanOverlay.classList.add('active');

        const cameraId = cameras[currentCameraIndex].id;
        html5QrCode.start(
            cameraId, 
            config,
            (decodedText) => {
                // Success
                onScanSuccess(decodedText);
            },
            (errorMessage) => {
                // parse error, ignore
            }
        ).then(() => {
            isScanning = true;
        }).catch(err => {
            Swal.fire('Error', 'Gagal membuka kamera: ' + err, 'error');
            stopScanner();
        });
    }

    function stopScanner() {
        if (isScanning) {
            html5QrCode.stop().then(() => {
                isScanning = false;
                btnStart.classList.remove('hidden');
                btnStop.classList.add('hidden');
                scanOverlay.classList.remove('active');
            });
        } else {
            btnStart.classList.remove('hidden');
            btnStop.classList.add('hidden');
        }
    }

    function switchCamera() {
        if (cameras.length < 2) return;
        currentCameraIndex = (currentCameraIndex + 1) % cameras.length;
        if (isScanning) {
            html5QrCode.stop().then(() => {
                startScanner();
            });
        }
    }

    function onScanSuccess(decodedText) {
        // Pulse effect
        scannerBox.classList.add('pulse-success');
        setTimeout(() => scannerBox.classList.remove('pulse-success'), 500);

        // Optional: Play sound
        // const audio = new Audio('../assets/sounds/beep.mp3');
        // audio.play();

        verifyToken(decodedText);
    }

    // verification logic
    document.getElementById('btn-manual').addEventListener('click', () => {
        const code = document.getElementById('manual-code').value.trim();
        if (code) verifyToken(code, true);
    });

    function verifyToken(token, isManual = false) {
        currentQrToken = token;
        
        const formData = new FormData();
        formData.append('token', token);
        formData.append('action', 'verify');
        formData.append('csrf_token', <?= json_encode(csrf_token()) ?>);
        if(isManual) formData.append('type', 'kode');

        fetch('handlers/scanner_handler.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                showVisitorCard(res.data);
                if (res.data.is_checkin) {
                    Swal.fire('Peringatan', 'Tiket ini sudah pernah di-checkin pada ' + res.data.waktu_checkin, 'warning');
                }
            } else {
                hideVisitorCard();
                Swal.fire('Error', res.message, 'error');
            }
        });
    }

    function showVisitorCard(data) {
        const card = document.getElementById('visitor-card');
        card.classList.remove('hidden');
        
        const statusEl = document.getElementById('v-status-badge');
        statusEl.innerText = data.status;
        statusEl.className = 'status-badge status-' + data.status.toLowerCase();
        
        document.getElementById('v-nama').innerText = data.nama;
        document.getElementById('v-kode').innerText = data.kode_booking;
        document.getElementById('v-tanggal').innerText = data.tanggal_kunjungan;
        document.getElementById('v-jenis').innerText = data.jenis_tiket;
        document.getElementById('v-dewasa').innerText = data.jumlah_dewasa;
        document.getElementById('v-anak').innerText = data.jumlah_anak;
        document.getElementById('v-pembayaran').innerText = data.bukti_bayar ? 'Sudah Dibayar' : 'Belum Bayar / Bayar di Lokasi';
        
        const footer = document.getElementById('v-footer');
        if (data.is_checkin) {
            footer.innerHTML = `<div class="alert-info"><i data-lucide="info"></i> Sudah Check-in: ${data.waktu_checkin}</div>`;
        } else if (data.status !== 'LUNAS') {
            footer.innerHTML = `<div class="alert-warning"><i data-lucide="alert-triangle"></i> Tiket belum Lunas (${data.status})</div>`;
        } else {
            footer.innerHTML = `<button id="btn-checkin" onclick="performCheckin()" class="btn btn-success btn-full"><i data-lucide="check-circle"></i> Check-in Sekarang</button>`;
        }
        lucide.createIcons();
        card.scrollIntoView({ behavior: 'smooth' });
    }

    function hideVisitorCard() {
        document.getElementById('visitor-card').classList.add('hidden');
    }

    function performCheckin() {
        if (!currentQrToken) return;

        Swal.fire({
            title: 'Konfirmasi Hadir?',
            text: "Tiket akan ditandai sebagai 'Selesai'.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#0e7c6b',
            confirmButtonText: 'Ya, Check-in'
        }).then((result) => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('token', currentQrToken);
                formData.append('action', 'checkin');
                formData.append('csrf_token', <?= json_encode(csrf_token()) ?>);

                fetch('handlers/scanner_handler.php', { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        Swal.fire('Berhasil!', 'Check-in sukses.', 'success').then(() => {
                            verifyToken(currentQrToken);
                        });
                    } else {
                        Swal.fire('Gagal', res.message, 'error');
                    }
                });
            }
        });
    }
</script>
</body>
</html>
