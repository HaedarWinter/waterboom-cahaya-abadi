<?php
require_once '../app/init.php';
use App\Models\Reservasi;
use App\Models\Setting;

$page_title = "Reservasi Online";
$current_page = 'reservasi';

$old = isset($_SESSION['form_reservasi']) ? $_SESSION['form_reservasi'] : [];
unset($_SESSION['form_reservasi']);

$harga_weekday = (int)Setting::get('harga_weekday', 20000);
$harga_weekend = (int)Setting::get('harga_weekend', 25000);
$rekening_bank = Setting::get('rekening_bank', 'BCA');
$rekening_nomor = Setting::get('rekening_nomor', '1234567890');
$rekening_nama = Setting::get('rekening_nama', 'Waterboom Cahaya Abadi');

// Jika ada kode booking di URL, ambil datanya untuk konfirmasi
$booking_info = null;
if (isset($_GET['sukses']) && isset($_GET['kode'])) {
    $booking_info = Reservasi::getByKode($_GET['kode']);
}

include '../resources/views/layouts/layouts/header.php';
include '../resources/views/layouts/layouts/navbar.php';
?>

<div class="main-content" style="grid-template-columns: 1fr; max-width: 700px; margin-top: 6rem;">

    <?php if ($booking_info): ?>
    <!-- KONFIRMASI BOOKING -->
    <div class="card" style="text-align:center; padding: 2.5rem;">
        <div style="width:64px;height:64px;border-radius:50%;background:#ecfdf5;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
            <i data-lucide="check-circle" style="width:32px;height:32px;color:#065f46;"></i>
        </div>
        <h2 style="font-size:1.3rem;font-weight:800;margin-bottom:4px;">Reservasi Berhasil!</h2>
        <p class="deskripsi" style="margin-bottom:1.5rem;">Simpan kode booking berikut untuk pengecekan status.</p>

        <div style="background:var(--accent-light);border:2px dashed var(--accent);border-radius:16px;padding:1.5rem;margin-bottom:1.5rem;">
            <div style="font-size:.7rem;text-transform:uppercase;letter-spacing:1px;color:var(--muted);font-weight:600;margin-bottom:4px;">Kode Booking</div>
            <div style="font-size:2rem;font-weight:900;color:var(--accent);letter-spacing:2px;"><?= htmlspecialchars($booking_info['kode_booking']) ?></div>
        </div>

        <div style="text-align:left;background:var(--subtle);border-radius:12px;padding:1.25rem;font-size:.88rem;line-height:2;">
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:4px;"><span style="color:var(--muted);">Nama</span><strong><?= htmlspecialchars($booking_info['nama']) ?></strong></div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:4px;"><span style="color:var(--muted);">Tanggal</span><strong><?= date('d M Y', strtotime($booking_info['tanggal_kunjungan'])) ?></strong></div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:4px;"><span style="color:var(--muted);">Tiket</span><strong><?= $booking_info['jenis_tiket'] ?></strong></div>
            <div style="display:flex;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:4px;"><span style="color:var(--muted);">Dewasa / Anak</span><strong><?= $booking_info['jumlah_dewasa'] ?> / <?= $booking_info['jumlah_anak'] ?></strong></div>
            <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">Total Harga</span><strong style="color:var(--accent);">Rp <?= format_harga($booking_info['total_harga']) ?></strong></div>
        </div>

        <p style="font-size:.78rem;color:var(--muted);margin-top:1rem;">Status: <strong>Menunggu Bayar</strong> — Silakan lakukan pembayaran di lokasi.</p>

        <div style="display:flex;gap:.75rem;margin-top:1.5rem;justify-content:center;flex-wrap:wrap;">
            <a href="reservasi.php" class="btn btn-secondary"><i data-lucide="plus"></i> Reservasi Lagi</a>
            <?php if ($booking_info['metode_bayar'] === 'Transfer Bank' && !$booking_info['bukti_bayar']): ?>
            <a href="cek_booking.php?kode=<?= htmlspecialchars($booking_info['kode_booking']) ?>" class="btn" style="background:#f59e0b;border-color:#f59e0b;"><i data-lucide="upload"></i> Upload Bukti Bayar</a>
            <?php endif; ?>
            <a href="cek_booking.php?kode=<?= htmlspecialchars($booking_info['kode_booking']) ?>" class="btn"><i data-lucide="search"></i> Cek Booking</a>
        </div>
    </div>

    <?php else: ?>
    <!-- FORM RESERVASI -->
    <div class="card">
        <div class="card-header">
            <i data-lucide="calendar-check" class="card-icon"></i>
            <h2>Reservasi Online</h2>
        </div>
        <p class="deskripsi">
            Pesan tiket Anda sekarang untuk menghindari antrean! Isi formulir berikut dan dapatkan kode booking.
        </p>

        <form action="../app/Http/Controllers/Admin/reservasi_handler.php" method="post" id="formReservasi">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="simpan">
            <div style="position:absolute;left:-9999px;" aria-hidden="true">
                <input type="text" name="website_url" tabindex="-1" autocomplete="off">
            </div>

            <div class="form-group">
                <label for="nama">Nama Lengkap</label>
                <input type="text" id="nama" name="nama" placeholder="Masukkan nama lengkap" value="<?= htmlspecialchars($old['nama'] ?? '') ?>" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="no_hp">Nomor HP</label>
                    <input type="tel" id="no_hp" name="no_hp" placeholder="08xxxxxxxxxx" value="<?= htmlspecialchars($old['no_hp'] ?? '') ?>" required>
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="text" id="email" name="email" placeholder="email@contoh.com atau -"
                           value="<?= htmlspecialchars($old['email'] ?? '') ?>" required>
                    <small style="font-size:11px;color:var(--muted);margin-top:4px;display:block;">* Gunakan '-' jika tidak memiliki email.</small>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="tanggal_kunjungan">Tanggal Kunjungan</label>
                    <input type="date" id="tanggal_kunjungan" name="tanggal_kunjungan"
                           min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($old['tanggal_kunjungan'] ?? '') ?>" required>
                    <!-- Kuota Badge -->
                    <div id="kuota-badge" style="display:none;align-items:center;gap:6px;margin-top:6px;padding:6px 12px;border-radius:8px;font-size:.78rem;font-weight:600;border:1.5px solid transparent;background:var(--accent-light);">
                        <span class="kuota-text" style="color:var(--accent);"></span>
                    </div>
                </div>
                <div class="form-group">
                    <label for="jenis_tiket">Jenis Tiket</label>
                    <select id="jenis_tiket" name="jenis_tiket" required style="pointer-events: none; background: var(--subtle); cursor: not-allowed; opacity: 0.9;">
                        <option value="">-- Pilih --</option>
                        <option value="Weekday" <?= ($old['jenis_tiket'] ?? '') === 'Weekday' ? 'selected' : '' ?>>Weekday (Rp <?= format_harga($harga_weekday) ?>)</option>
                        <option value="Weekend" <?= ($old['jenis_tiket'] ?? '') === 'Weekend' ? 'selected' : '' ?>>Weekend (Rp <?= format_harga($harga_weekend) ?>)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label for="metode_bayar">Metode Pembayaran</label>
                <select id="metode_bayar" name="metode_bayar" required>
                    <option value="Bayar di Lokasi" <?= ($old['metode_bayar'] ?? '') === 'Bayar di Lokasi' ? 'selected' : '' ?>>Bayar di Lokasi</option>
                    <option value="Transfer Bank" <?= ($old['metode_bayar'] ?? '') === 'Transfer Bank' ? 'selected' : '' ?>>Transfer Bank</option>
                </select>
            </div>

            <!-- INFO REKENING (muncul jika Transfer Bank) -->
            <div id="info-rekening" style="background:var(--accent-light);border-radius:12px;padding:1rem 1.25rem;margin-bottom:1rem;display:none;border:1.5px solid rgba(14,124,107,.12);">
                <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);font-weight:600;margin-bottom:8px;">Transfer ke Rekening</div>
                <div style="font-size:.88rem;line-height:2;">
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--muted);">Bank</span>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <?php if(strpos(strtoupper($rekening_bank), 'BCA') !== false): ?>
                                <img src="assets/bca_logo.png" alt="BCA" style="height:16px;">
                            <?php endif; ?>
                            <strong><?= htmlspecialchars($rekening_bank) ?></strong>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">No. Rekening</span><strong style="color:var(--accent);letter-spacing:1px;"><?= htmlspecialchars($rekening_nomor) ?></strong></div>
                    <div style="display:flex;justify-content:space-between;"><span style="color:var(--muted);">A/N</span><strong><?= htmlspecialchars($rekening_nama) ?></strong></div>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="jumlah_dewasa">Jumlah Dewasa</label>
                    <input type="number" id="jumlah_dewasa" name="jumlah_dewasa" min="1" max="50" value="<?= htmlspecialchars($old['jumlah_dewasa'] ?? '1') ?>" required>
                </div>
                <div class="form-group">
                    <label for="jumlah_anak">Jumlah Anak</label>
                    <input type="number" id="jumlah_anak" name="jumlah_anak" min="0" max="50" value="<?= htmlspecialchars($old['jumlah_anak'] ?? '0') ?>">
                </div>
            </div>

            <!-- RINGKASAN HARGA -->
            <div id="ringkasan-harga" style="background:var(--accent-light);border-radius:12px;padding:1rem 1.25rem;margin-bottom:1rem;display:none;">
                <div style="font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;color:var(--muted);font-weight:600;margin-bottom:6px;">Estimasi Harga</div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span id="detail-harga" style="font-size:.85rem;color:var(--fg);"></span>
                    <strong id="total-harga" style="font-size:1.15rem;color:var(--accent);"></strong>
                </div>
            </div>

            <div class="form-group">
                <label for="catatan">Catatan (Opsional)</label>
                <input type="text" id="catatan" name="catatan" placeholder="Pesan khusus, kebutuhan, dll..." value="<?= htmlspecialchars($old['catatan'] ?? '') ?>">
            </div>

            <button type="submit" class="btn" style="width:100%;margin-top:.5rem;">
                <i data-lucide="ticket"></i> Buat Reservasi
            </button>
        </form>

        <p style="text-align:center;margin-top:1rem;font-size:.82rem;color:var(--muted);">
            Sudah punya kode booking? <a href="cek_booking.php" style="color:var(--accent);font-weight:600;text-decoration:none;">Cek Status →</a>
        </p>
    </div>
    <?php endif; ?>

</div>

<?php 
$extra_footer = <<<HTML
<script>
// Auto-detect weekend/weekday
var tglInput = document.getElementById("tanggal_kunjungan");
var jenisSelect = document.getElementById("jenis_tiket");
if (tglInput) {
    tglInput.addEventListener("change", function() {
        var date = new Date(this.value);
        var day = date.getDay();
        jenisSelect.value = (day === 0 || day === 6) ? "Weekend" : "Weekday";
        hitungHarga();
        cekKuota(this.value);
    });
}

// Show/hide rekening info
var metodeSelect = document.getElementById("metode_bayar");
if (metodeSelect) {
    metodeSelect.addEventListener("change", function() {
        document.getElementById("info-rekening").style.display = this.value === "Transfer Bank" ? "block" : "none";
    });
    if (metodeSelect.value === "Transfer Bank") {
        document.getElementById("info-rekening").style.display = "block";
    }
}

window.sisaKuota = null;
function cekKuota(tanggal) {
    fetch("../app/Http/Controllers/Admin/reservasi_handler.php", {
        method: "POST",
        headers: {"Content-Type": "application/x-www-form-urlencoded"},
        body: "action=cek_kuota&tanggal=" + tanggal
    }).then(r => r.json()).then(data => {
        var badge = document.getElementById("kuota-badge");
        if (badge && data.success) {
            var sisa = data.sisa;
            var max = data.max;
            window.sisaKuota = sisa;
            
            var btnSubmit = document.querySelector('button[type="submit"]');
            var inputDewasa = document.getElementById("jumlah_dewasa");
            var inputAnak = document.getElementById("jumlah_anak");
            
            badge.style.display = "flex";
            badge.querySelector(".kuota-text").textContent = "Tersedia di tanggal ini sisa: " + sisa + " / " + max + " slot";
            
            if (sisa <= 0) {
                badge.style.background = "var(--danger-light)";
                badge.querySelector(".kuota-text").style.color = "var(--danger)";
                badge.querySelector(".kuota-text").textContent = "KUOTA PENUH";
                
                if(btnSubmit) {
                    btnSubmit.disabled = true;
                    btnSubmit.style.opacity = "0.5";
                    btnSubmit.style.cursor = "not-allowed";
                }
                if(inputDewasa) inputDewasa.max = 0;
                if(inputAnak) inputAnak.max = 0;
            } else {
                badge.style.background = "var(--accent-light)";
                badge.querySelector(".kuota-text").style.color = "var(--accent)";
                
                if(btnSubmit) {
                    btnSubmit.disabled = false;
                    btnSubmit.style.opacity = "1";
                    btnSubmit.style.cursor = "pointer";
                }
                
                if(inputDewasa) inputDewasa.max = sisa;
                if(inputAnak) inputAnak.max = sisa;
                
                // Panggil validasi saat tanggal diganti
                if (typeof validasiInputKuota === 'function') {
                    validasiInputKuota();
                }
            }
        }
    });
}

var hargaWeekday = $harga_weekday;
var hargaWeekend = $harga_weekend;

function hitungHarga() {
    var jenis = jenisSelect.value;
    var dewasa = parseInt(document.getElementById("jumlah_dewasa").value) || 0;
    var anak = parseInt(document.getElementById("jumlah_anak").value) || 0;
    var total_orang = dewasa + anak;
    var ringkasan = document.getElementById("ringkasan-harga");

    if (jenis && total_orang > 0) {
        var harga = jenis === "Weekend" ? hargaWeekend : hargaWeekday;
        var total = harga * total_orang;
        document.getElementById("detail-harga").textContent = total_orang + " orang × Rp " + harga.toLocaleString("id-ID");
        document.getElementById("total-harga").textContent = "Rp " + total.toLocaleString("id-ID");
        ringkasan.style.display = "block";
    } else {
        ringkasan.style.display = "none";
    }
}

if (jenisSelect) jenisSelect.addEventListener("change", hitungHarga);

function validasiInputKuota() {
    if (window.sisaKuota === null) return;
    var inputDewasa = document.getElementById('jumlah_dewasa');
    var inputAnak = document.getElementById('jumlah_anak');
    var d = parseInt(inputDewasa.value) || 0;
    var a = parseInt(inputAnak.value) || 0;
    
    if (d + a > window.sisaKuota) {
        Swal.fire({
            icon: 'warning',
            title: 'Kuota Tidak Cukup',
            text: 'Sisa kuota untuk tanggal ini hanya ' + window.sisaKuota + ' orang.',
            confirmButtonColor: '#0e7c6b'
        });
        
        if (d > window.sisaKuota) {
            inputDewasa.value = window.sisaKuota;
            inputAnak.value = 0;
        } else {
            inputAnak.value = window.sisaKuota - d;
        }
    }
}

function onInputJumlah() {
    validasiInputKuota();
    hitungHarga();
}

document.getElementById("jumlah_dewasa").addEventListener("input", onInputJumlah);
document.getElementById("jumlah_anak").addEventListener("input", onInputJumlah);

const params = new URLSearchParams(window.location.search);
if (params.has("error")) {
    Swal.fire({ icon: "error", title: "Oops!", text: params.get("error"), confirmButtonColor: "#0e7c6b" });
}
</script>
HTML;
include '../resources/views/layouts/layouts/footer.php'; 
?>
</body>
</html>
