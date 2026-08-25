<?php
require_once __DIR__ . '/includes/header.php';
require_admin(); // Hanya Admin yang bisa buat pengajuan baru

// Ambil daftar barang induk untuk dropdown pilihan
$res_induk = mysqli_query($conn, "SELECT * FROM stok_barang ORDER BY nama_barang ASC");
$barang_induk_list = [];
while ($row = mysqli_fetch_assoc($res_induk)) {
    $barang_induk_list[] = $row;
}

// Ambil daftar favorit pembeli
$res_fav = mysqli_query($conn, "SELECT * FROM favorit_pembeli ORDER BY nama_pembeli ASC");
?>

<!-- Datalist Pilihan Preset Satuan -->
<datalist id="preset_satuan_list">
    <option value="unit">
    <option value="kg">
    <option value="pack">
    <option value="mm">
    <option value="meter">
    <option value="pcs">
</datalist>

<!-- HEADER CURVED EXECUTIVE BANNER -->
<header class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <span class="page-eyebrow d-none d-md-block"><i class="fa-solid fa-cart-flatbed"></i> Operational &middot; Pengadaan Barang</span>
            <h1 class="page-title">Formulir Pembelian Barang</h1>
            <p class="page-subtitle mb-0">Lengkapi rincian transaksi, data pembeli, rincian barang & pembayaran</p>
        </div>
        <div class="header-action">
            <a href="insert_admin.php" class="btn btn-outline-light btn-sm rounded-pill px-3 fw-semibold">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar
            </a>
        </div>
    </div>
</header>

<form action="proses_tambah_pengajuan.php" method="POST" id="formPengajuan" enctype="multipart/form-data">
    <?= csrf_field(); ?>

    <!-- Hidden Inputs untuk Status & Metode Pembayaran (Diatur otomatis oleh pilihan Metode Pembayaran) -->
    <input type="hidden" name="status_pembayaran" id="form_status_pembayaran" value="belum_dibayar">
    <input type="hidden" name="metode_pembayaran" id="selected_metode_pembayaran" value="">

    <!-- SECTION 1 (TOP): CUSTOM PEMBELIAN -->
    <div class="form-section-card mb-4">
        <div class="d-flex align-items-center gap-2 mb-1">
            <i class="fa-solid fa-hashtag text-wine fs-5"></i>
            <h5 class="fw-bold text-wine mb-0">Custom Pembelian</h5>
        </div>
        <small class="text-muted d-block mb-3">Atur ID, tanggal, jam, dan status pengiriman secara manual</small>

        <div class="row g-3">
            <!-- ID Pengajuan Card -->
            <div class="col-6 col-md-3">
                <div class="custom-input-box pink">
                    <label class="fw-bold text-wine small mb-1 d-block"><i class="fa-solid fa-pen text-wine me-1"></i> ID Pengajuan</label>
                    <input type="number" step="1" name="custom_id" id="custom_id" class="form-control text-wine fw-bold" value="" placeholder="Otomatis jika kosong">
                </div>
            </div>

            <!-- Tanggal Pengajuan Card -->
            <div class="col-6 col-md-3">
                <div class="custom-input-box pink">
                    <label class="fw-bold text-wine small mb-1 d-block"><i class="fa-regular fa-calendar-days text-wine me-1"></i> Tanggal Pengajuan</label>
                    <input type="date" name="custom_tanggal" id="custom_tanggal" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                </div>
            </div>

            <!-- Jam Pengajuan Card -->
            <div class="col-6 col-md-3">
                <div class="custom-input-box pink">
                    <label class="fw-bold text-wine small mb-1 d-block"><i class="fa-regular fa-clock text-wine me-1"></i> Jam Pengajuan (WIB)</label>
                    <input type="time" name="custom_jam" id="custom_jam" class="form-control" value="<?= date('H:i'); ?>" required>
                </div>
            </div>

            <!-- Status Pengiriman Card -->
            <div class="col-6 col-md-3">
                <div class="custom-input-box blue">
                    <label class="fw-bold text-primary small mb-1 d-block"><i class="fa-solid fa-truck text-primary me-1"></i> Status Pengiriman</label>
                    <select name="status_pengiriman" class="form-select fw-bold text-primary" required>
                        <option value="belum_dikirim" selected>🔴 Belum Dikirim</option>
                        <option value="sudah_dikirim">🟢 Sudah Dikirim</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 2: DATA PEMBELI -->
    <div class="form-section-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-user-tag text-wine fs-5"></i>
                <h5 class="fw-bold text-wine mb-0">Data Pembeli</h5>
            </div>
            <button type="button" class="btn btn-sm btn-outline-warning text-dark border shadow-sm rounded-pill px-3 py-1.5 fw-bold d-inline-flex align-items-center gap-1.5" onclick="saveAsFavorit(event)" title="Klik untuk simpan nama pembeli ke Daftar Favorit">
                <i class="fa-solid fa-star text-warning fs-6"></i>
                <span class="small text-wine fw-bold" style="font-size:0.76rem;">Simpan Pembeli Favorit</span>
            </button>
        </div>

        <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Pilih Pembeli Tersimpan</label>
            <select id="select_favorit" class="form-select" onchange="applyFavorit(this)">
                <option value="">-- Pilih pembeli yang sudah disimpan --</option>
                <?php while ($f = mysqli_fetch_assoc($res_fav)): ?>
                    <option value="<?= $f['id']; ?>" data-nama="<?= e($f['nama_pembeli']); ?>" data-telepon="<?= e($f['telepon_pembeli']); ?>">
                        <?= e($f['nama_pembeli']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="row g-3">
            <div class="col-6 col-md-6">
                <label class="form-label text-muted small fw-semibold">Nama Pembeli (Opsional)</label>
                <input type="text" name="nama_pembeli" id="nama_pembeli" class="form-control" placeholder="Masukkan nama pembeli (opsional)">
            </div>
            <div class="col-6 col-md-6">
                <label class="form-label text-muted small fw-semibold">Nomor Telepon</label>
                <div class="input-group">
                    <span class="input-group-text bg-light">+62</span>
                    <input type="tel" name="telepon_pembeli" id="telepon_pembeli" class="form-control" inputmode="numeric" placeholder="81234567890">
                </div>
                <small class="text-muted" style="font-size:0.7rem;">Opsional, hanya valid jika diisi</small>
            </div>
        </div>
    </div>

    <!-- SECTION 3: BARANG DIBELI & INSTANT SEARCH -->
    <div class="form-section-card mb-4">
        <!-- FITUR PENCARIAN OVAL PILL BARANG (INSTANT AUTOCOMPLETE) -->
        <div class="position-relative mb-4">
            <div class="search-oval-wrapper">
                <i class="fa-solid fa-magnifying-glass search-oval-icon text-muted"></i>
                <input type="text" id="search_item_input" class="search-oval-input" placeholder="Cari barang atau jenis..." oninput="handleInstantItemSearch(this.value)" autocomplete="off">
            </div>
            
            <!-- Results Dropdown Box -->
            <div id="search_results_dropdown" class="dropdown-menu w-100 shadow-lg border mt-1" style="display: none; max-height: 320px; overflow-y: auto; z-index: 1070; top: 100%; left: 0;">
                <!-- Result items rendered via JS -->
            </div>
        </div>

        <div id="itemContainer">
            <!-- Item Row 0 -->
            <div class="item-card-row mb-3" data-index="0" id="row_0">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <div class="d-flex align-items-center gap-2">
                        <div class="badge-item-wine">
                            <i class="fa-solid fa-basket-shopping me-1"></i> Barang Dibeli #1
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <div class="form-check form-switch m-0">
                            <input class="form-check-input" type="checkbox" name="is_custom_0" value="1" id="is_custom_0" onchange="toggleCustomItem(0)">
                            <label class="form-check-label fw-bold text-wine small text-nowrap" for="is_custom_0">Custom Item</label>
                        </div>
                        <button type="button" class="btn-remove-circle d-none ms-1" id="btn_remove_0" onclick="removeRow(0)">&times;</button>
                    </div>
                </div>

                <!-- 1. Pilihan Barang / Custom Nama -->
                <div class="row g-3" id="reguler_fields_0">
                    <div class="col-6 col-md-6">
                        <label class="form-label text-muted small fw-semibold">Pilih Barang *</label>
                        <select name="barang_id_0" id="barang_id_0" class="form-select" onchange="loadVarianOptions(0)" required>
                            <option value="">-- Pilih Barang --</option>
                            <?php foreach ($barang_induk_list as $b): ?>
                                <option value="<?= $b['id']; ?>"><?= e($b['nama_barang']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6 col-md-6">
                        <label class="form-label text-muted small fw-semibold">Pilih Jenis *</label>
                        <select name="jenis_id_0" id="jenis_id_0" class="form-select" onchange="onVarianSelected(0)" required>
                            <option value="">-- Pilih Jenis --</option>
                        </select>
                    </div>
                </div>

                <!-- Custom Item Inputs Container (Awalnya d-none) -->
                <div class="row g-3 d-none" id="custom_fields_0">
                    <div class="col-6 col-md-6">
                        <label class="form-label text-muted small fw-semibold">Nama Barang Custom *</label>
                        <input type="text" name="custom_nama_0" id="custom_nama_0" class="form-control" placeholder="Contoh: Mesin giling kedelai pak ukat">
                    </div>
                    <div class="col-6 col-md-6">
                        <label class="form-label text-muted small fw-semibold">Spesifikasi / Varian Custom</label>
                        <input type="text" name="custom_jenis_0" id="custom_jenis_0" class="form-control" placeholder="Contoh: Ukuran 8in / Harga Manual">
                    </div>
                </div>

                <!-- 2. Jumlah, Satuan, Harga Satuan & Subtotal -->
                <div class="row g-3 mt-1">
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted small fw-semibold">Jumlah (QTY) *</label>
                        <input type="number" step="0.01" inputmode="decimal" name="jumlah_0" id="jumlah_0" class="form-control fw-bold" value="1" oninput="calculateRow(0)" placeholder="Jumlah" required>
                        <div class="mt-1 small text-muted" id="stok_info_wrapper_0">
                            <div id="stok_label_0"><i class="fa-solid fa-boxes-stacked me-1"></i> Stok tersedia: -</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted small fw-semibold">Satuan *</label>
                        <input type="text" name="satuan_0" id="satuan_0" class="form-control" list="preset_satuan_list" value="unit" placeholder="unit / kg / pcs" required readonly>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted small fw-semibold">Harga Satuan (Rp) *</label>
                        <input type="text" name="harga_0" id="harga_0" class="form-control rupiah-input fw-bold text-wine" inputmode="numeric" placeholder="Rp 0" oninput="calculateRow(0)" required>
                        <div class="mt-1 small text-muted" id="harga_info_wrapper_0">
                            <div id="harga_label_0"><i class="fa-solid fa-tag me-1"></i> Harga standar: -</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label text-muted small fw-semibold">Subtotal (Rp)</label>
                        <input type="text" id="subtotal_0" class="form-control fw-bold text-success bg-white" value="Rp 0" readonly>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tombol Aksi Bawah Item -->
        <div class="d-flex justify-content-between align-items-center mt-3 gap-2">
            <a href="insert_admin.php" class="btn btn-secondary py-2.5 px-4 fw-bold rounded-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali
            </a>
            <button type="button" class="btn btn-success py-2.5 px-4 fw-bold rounded-3" onclick="addItemRow()" style="background:#166D47; border:none;">
                <i class="fa-solid fa-plus-circle me-1"></i> Tambah Item
            </button>
        </div>

        <!-- Grand Total Footer (Executive Green Banner) -->
        <div class="p-3 p-md-4 rounded-3 text-white shadow-sm mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%); border: 1px solid #059669;">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-calculator fs-4 text-warning"></i>
                <div>
                    <h5 class="fw-bold mb-0 text-white" style="letter-spacing: 0.5px;">TOTAL ESTIMASI DANA:</h5>
                    <small style="color: rgba(255,255,255,0.85); font-size: 0.75rem;">Kalkulasi otomatis seluruh rincian item & varian</small>
                </div>
            </div>
            <h3 class="fw-bolder mb-0 text-white" id="grandTotalDisplay" style="font-size: 1.8rem; text-shadow: 0 2px 4px rgba(0,0,0,0.3);">Rp 0</h3>
        </div>
    </div>

    <!-- SECTION 4: METODE PEMBAYARAN & CICILAN / DP -->
    <div class="form-section-card mb-4">
        <div class="d-flex align-items-center gap-2 mb-1">
            <i class="fa-solid fa-credit-card text-wine fs-5"></i>
            <h5 class="fw-bold text-wine mb-0">Status & Metode Pembayaran</h5>
        </div>
        <small class="text-muted d-block mb-3">Tentukan status pelunasan transaksi dan metode pembayaran</small>

        <!-- Status Pelunasan Option Cards -->
        <label class="form-label text-muted small fw-semibold mb-2">Status Pelunasan Nota *</label>
        <div class="row g-2 mb-3">
            <div class="col-4">
                <div class="status-pay-card text-center p-3 rounded-3 border bg-light text-dark" id="card_status_belum" onclick="selectFormStatusBayar('belum_dibayar')">
                    <i class="fa-solid fa-circle-xmark text-danger fs-3 mb-1.5 d-block"></i>
                    <span class="fw-bold text-dark d-block small">Belum Dibayar</span>
                </div>
            </div>
            <div class="col-4">
                <div class="status-pay-card text-center p-3 rounded-3 border bg-light text-dark" id="card_status_cicilan" onclick="selectFormStatusBayar('cicilan')">
                    <i class="fa-solid fa-clock-rotate-left text-warning fs-3 mb-1.5 d-block"></i>
                    <span class="fw-bold text-dark d-block small">Cicilan / DP</span>
                </div>
            </div>
            <div class="col-4">
                <div class="status-pay-card text-center p-3 rounded-3 border bg-light text-dark" id="card_status_lunas" onclick="selectFormStatusBayar('dibayar')">
                    <i class="fa-solid fa-circle-check text-success fs-3 mb-1.5 d-block"></i>
                    <span class="fw-bold text-dark d-block small">Lunas</span>
                </div>
            </div>
        </div>

        <input type="hidden" name="status_pembayaran" id="input_status_pembayaran" value="belum_dibayar">

        <!-- Input Nominal Uang Muka / DP (Tampil saat pilih Cicilan) -->
        <div id="container_input_dp" class="p-3 rounded-3 border bg-warning-subtle border-warning mb-3 d-none">
            <label for="jumlah_dibayar_input" class="form-label text-dark fw-bold small mb-1">
                <i class="fa-solid fa-coins text-warning me-1"></i> Uang Muka (DP) / Nominal Dibayar Pertama (Rp)
            </label>
            <input type="text" name="jumlah_dibayar" id="jumlah_dibayar_input" class="form-control fw-bold" placeholder="Masukkan jumlah DP / bayar awal, misal: 500000" onkeyup="formatRupiahInput(this); calculateSisaPreview();">
            <div class="d-flex justify-content-between align-items-center mt-2 text-dark small">
                <span>Estimasi Sisa Piutang:</span>
                <span class="fw-bold text-danger fs-6" id="previewSisaPiutang">Rp 0</span>
            </div>
        </div>

        <!-- Section Metode Pembayaran (Hanya tampil jika Cicilan / Lunas) -->
        <div id="section_metode_pembayaran" class="d-none border-top pt-3 mt-3">
            <label class="form-label text-muted small fw-semibold mb-2">Pilih Metode Pembayaran *</label>
            <div class="row g-3 mb-3">
                <div class="col-6">
                    <div class="payment-card-btn text-center p-3.5 rounded-3 border bg-light" id="pay_card_transfer" onclick="selectFormPaymentMethod('transfer')">
                        <i class="fa-solid fa-building-columns text-primary fs-2 mb-2"></i>
                        <span class="fw-bold text-dark d-block fs-6">Transfer Bank</span>
                    </div>
                </div>
                <div class="col-6">
                    <div class="payment-card-btn text-center p-3.5 rounded-3 border bg-light" id="pay_card_tunai" onclick="selectFormPaymentMethod('tunai')">
                        <i class="fa-solid fa-money-bill-wave text-success fs-2 mb-2"></i>
                        <span class="fw-bold text-dark d-block fs-6">Tunai / Cash</span>
                    </div>
                </div>
            </div>

            <!-- Container Unggah Bukti Opsional -->
            <div id="bukti_upload_container" class="p-3 rounded-3 border bg-light d-none">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-dark small" id="bukti_method_title">
                        <i class="fa-solid fa-file-arrow-up text-wine me-1"></i> Unggah Bukti Pembayaran (Opsional)
                    </span>
                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2" style="font-size:0.72rem;" onclick="deselectFormPaymentMethod()">
                        <i class="fa-solid fa-xmark me-1"></i> Batalkan Pilih
                    </button>
                </div>
                <input type="file" name="bukti_file" id="bukti_file_input" class="form-control form-control-sm" accept="image/*,.pdf">
                <small class="text-muted d-block mt-1" style="font-size:0.72rem;">* Bukti pembayaran opsional (boleh dikosongkan jika belum ada berkas foto/pdf)</small>
            </div>
        </div>
    </div>

    <!-- SECTION 5: INFORMASI TAMBAHAN -->
    <div class="form-section-card mb-4">
        <div class="d-flex align-items-center gap-2 mb-2">
            <i class="fa-solid fa-file-lines text-wine fs-5"></i>
            <h5 class="fw-bold text-wine mb-0">Informasi Tambahan</h5>
        </div>

        <div class="mb-2">
            <label class="form-label text-muted small fw-semibold">Keterangan Pembelian</label>
            <textarea name="catatan" class="form-control" rows="3" placeholder="Tambahkan catatan atau keterangan mengenai pembelian ini"></textarea>
        </div>
    </div>

    <!-- SECTION 6 (BOTTOM): TOMBOL SUBMIT SIMPAN PEMBELIAN -->
    <div class="text-end mb-5">
        <button type="submit" class="btn btn-lg px-5 rounded-pill shadow-lg fw-bold text-white" style="background: linear-gradient(135deg, #10b981 0%, #047857 100%); border: none;">
            <i class="fa-solid fa-paper-plane me-2 text-warning"></i> Simpan Pembelian Barang
        </button>
    </div>
</form>

<script>
// Standalone Named Functions (Hoisted globally)
function unformatRupiah(str) {
    if (!str) return 0;
    let cleaned = str.toString().replace(/[^0-9,-]/g, '').replace(',', '.');
    return parseFloat(cleaned) || 0;
}
function unformatRupiahJS(str) {
    return unformatRupiah(str);
}
function formatRupiah(num) {
    if (typeof formatRupiahJS === 'function') return formatRupiahJS(num, 'Rp ');
    return 'Rp ' + (num || 0).toLocaleString('id-ID');
}
function formatRupiahInput(input) {
    if (!input) return;
    let val = input.value;
    if (typeof formatRupiahJS === 'function') {
        input.value = formatRupiahJS(val, 'Rp ');
    } else {
        let clean = val.toString().replace(/[^\d]/g, '');
        input.value = clean ? 'Rp ' + parseInt(clean, 10).toLocaleString('id-ID') : '';
    }
}
window.unformatRupiah = unformatRupiah;
window.unformatRupiahJS = unformatRupiah;
window.formatRupiah = formatRupiah;
window.formatRupiahInput = formatRupiahInput;

let rowIndexCounter = 1;
const barangIndukList = <?= json_encode($barang_induk_list); ?>;
let searchTimeout = null;

function saveAsFavorit(event) {
    if (event) event.preventDefault();
    
    const namaInput = document.getElementById('nama_pembeli');
    const teleponInput = document.getElementById('telepon_pembeli');
    
    const nama = namaInput ? namaInput.value.trim() : '';
    const telepon = teleponInput ? teleponInput.value.trim() : '';
    
    if (!nama) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'warning',
                title: 'Nama Pembeli Kosong',
                text: 'Silakan isi Nama Pembeli terlebih dahulu sebelum menyimpan ke favorit.',
                confirmColor: '#7A1E33'
            });
        } else {
            alert('Silakan isi Nama Pembeli terlebih dahulu!');
        }
        if (namaInput) namaInput.focus();
        return;
    }
    
    fetch('ajax_save_favorit.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ nama_pembeli: nama, telepon_pembeli: telepon })
    })
    .then(res => res.text())
    .then(text => {
        let data;
        try {
            data = JSON.parse(text);
        } catch (e) {
            console.error("Non-JSON Server Response:", text);
            throw new Error("Respon server tidak valid: " + text.substring(0, 80));
        }

        if (data.status === 'success') {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Tersimpan!',
                    text: data.message || 'Data pembeli berhasil disimpan ke Favorit!',
                    timer: 1800,
                    showConfirmButton: false
                });
            } else {
                alert(data.message || 'Data pembeli berhasil disimpan ke Favorit!');
            }
            
            // Tambahkan ke dropdown select_favorit jika belum ada
            const selectFav = document.getElementById('select_favorit');
            if (selectFav) {
                let exists = false;
                for (let i = 0; i < selectFav.options.length; i++) {
                    if (selectFav.options[i].value == data.id) {
                        exists = true;
                        selectFav.selectedIndex = i;
                        break;
                    }
                }
                if (!exists && data.id) {
                    const newOpt = document.createElement('option');
                    newOpt.value = data.id;
                    newOpt.text = data.nama_pembeli;
                    newOpt.setAttribute('data-nama', data.nama_pembeli);
                    newOpt.setAttribute('data-telepon', data.telepon_pembeli);
                    newOpt.selected = true;
                    selectFav.appendChild(newOpt);
                }
            }
        } else {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Gagal', text: data.message || 'Terjadi kesalahan.' });
            } else {
                alert(data.message || 'Terjadi kesalahan saat menyimpan.');
            }
        }
    })
    .catch(err => {
        console.error(err);
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'error', title: 'Kesalahan Sistem', text: err.message || 'Terjadi kesalahan koneksi saat menyimpan favorit.' });
        } else {
            alert(err.message || 'Terjadi kesalahan koneksi saat menyimpan favorit.');
        }
    });
}

function selectFormPaymentMethod(method) {
    const hiddenStatus = document.getElementById("form_status_pembayaran");
    const hiddenMetode = document.getElementById("selected_metode_pembayaran");
    const cardTransfer = document.getElementById("pay_card_transfer");
    const cardTunai = document.getElementById("pay_card_tunai");
    const containerBukti = document.getElementById("bukti_upload_container");
    const titleBukti = document.getElementById("bukti_method_title");

    cardTransfer.classList.remove("selected-payment-active");
    cardTunai.classList.remove("selected-payment-active");

    if (method === 'transfer') {
        hiddenStatus.value = 'dibayar';
        hiddenMetode.value = 'transfer';
        cardTransfer.classList.add("selected-payment-active");
        titleBukti.innerHTML = `<i class="fa-solid fa-file-arrow-up text-primary me-1"></i> Unggah Bukti Transfer (Opsional)`;
        containerBukti.classList.remove("d-none");
    } else if (method === 'tunai') {
        hiddenStatus.value = 'dibayar';
        hiddenMetode.value = 'tunai';
        cardTunai.classList.add("selected-payment-active");
        titleBukti.innerHTML = `<i class="fa-solid fa-file-arrow-up text-success me-1"></i> Unggah Bukti Tunai / Kwitansi (Opsional)`;
        containerBukti.classList.remove("d-none");
    }
}

function deselectFormPaymentMethod() {
    document.getElementById("form_status_pembayaran").value = 'belum_dibayar';
    document.getElementById("selected_metode_pembayaran").value = '';
    document.getElementById("pay_card_transfer").classList.remove("selected-payment-active");
    document.getElementById("pay_card_tunai").classList.remove("selected-payment-active");
    document.getElementById("bukti_upload_container").classList.add("d-none");
    document.getElementById("bukti_file_input").value = "";
}

function formatStokJS(val) {
    const num = parseFloat(val) || 0;
    if (num % 1 === 0) return num.toFixed(0);
    return parseFloat(num.toFixed(4)).toString().replace('.', ',');
}

// Close dropdown on outside click
document.addEventListener("click", function(e) {
    const dropdown = document.getElementById("search_results_dropdown");
    const input = document.getElementById("search_item_input");
    if (dropdown && !dropdown.contains(e.target) && e.target !== input) {
        hideSearchDropdown();
    }
});

function hideSearchDropdown() {
    const dropdown = document.getElementById("search_results_dropdown");
    if (dropdown) {
        dropdown.style.display = "none";
        dropdown.classList.remove("show");
    }
}

function showSearchDropdown() {
    const dropdown = document.getElementById("search_results_dropdown");
    if (dropdown) {
        dropdown.style.display = "block";
        dropdown.classList.add("show");
    }
}

function handleInstantItemSearch(query) {
    clearTimeout(searchTimeout);
    
    if (!query || query.trim().length === 0) {
        hideSearchDropdown();
        return;
    }

    searchTimeout = setTimeout(() => {
        fetch(`ajax_search_barang.php?q=${encodeURIComponent(query.trim())}`)
            .then(res => res.json())
            .then(data => {
                const dropdown = document.getElementById("search_results_dropdown");
                if (!data || data.length === 0) {
                    dropdown.innerHTML = `<div class="p-3 text-muted small text-center"><i class="fa-solid fa-circle-exclamation me-1 text-warning"></i> Tidak ada barang / jenis yang sesuai.</div>`;
                } else {
                    let html = `<div class="dropdown-header text-uppercase small fw-bold text-wine"><i class="fa-solid fa-magnifying-glass me-1"></i> Hasil Pencarian Barang:</div>`;
                    data.forEach(item => {
                        const namaB = escapeJs(item.nama_barang);
                        const namaJ = escapeJs(item.nama_jenis);
                        const sat = escapeJs(item.satuan);
                        const stokFmt = formatStokJS(item.stok);
                        
                        html += `
                        <button type="button" class="dropdown-item py-2 border-bottom text-wrap" onclick="onSelectItemFromSearch(event, ${item.barang_id}, '${namaB}', ${item.jenis_id}, '${namaJ}', '${sat}', ${item.harga}, ${item.stok})">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-dark d-block">${item.nama_barang} &mdash; ${item.nama_jenis}</strong>
                                    <small class="text-muted"><i class="fa-solid fa-box me-1"></i> Stok Ready: ${stokFmt} ${item.satuan}</small>
                                </div>
                                <div class="text-end ms-2">
                                    <span class="badge bg-success">${formatRupiahJS(item.harga, 'Rp ')}</span>
                                    <small class="d-block text-wine fw-bold mt-1" style="font-size:0.75rem;"><i class="fa-solid fa-plus-circle"></i> Klik Tambah</small>
                                </div>
                            </div>
                        </button>`;
                    });
                    dropdown.innerHTML = html;
                }
                showSearchDropdown();
            });
    }, 150);
}

function escapeJs(str) {
    return (str || '').replace(/'/g, "\\'").replace(/"/g, '\\"');
}

function onSelectItemFromSearch(event, barangId, namaBarang, jenisId, namaJenis, satuan, harga, stok) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }
    
    hideSearchDropdown();
    const searchInput = document.getElementById("search_item_input");
    if (searchInput) searchInput.value = "";

    const row0Barang = document.getElementById("barang_id_0");
    const row0Jenis = document.getElementById("jenis_id_0");
    if (row0Barang && (!row0Barang.value || row0Barang.value == 0) && (!row0Jenis || !row0Jenis.value)) {
        populateRowWithData(0, barangId, namaBarang, jenisId, namaJenis, satuan, harga, stok);
    } else {
        const newIdx = addItemRow();
        populateRowWithData(newIdx, barangId, namaBarang, jenisId, namaJenis, satuan, harga, stok);
    }
}

function populateRowWithData(idx, barangId, namaBarang, jenisId, namaJenis, satuan, harga, stok) {
    const barangSelect = document.getElementById(`barang_id_${idx}`);
    if (barangSelect) {
        let exists = false;
        for (let i = 0; i < barangSelect.options.length; i++) {
            if (barangSelect.options[i].value == barangId) {
                exists = true;
                break;
            }
        }
        if (!exists && barangId) {
            const opt = document.createElement('option');
            opt.value = barangId;
            opt.text = namaBarang || `Barang #${barangId}`;
            barangSelect.appendChild(opt);
        }
        barangSelect.value = barangId;
    }

    const stokFmt = formatStokJS(stok);
    const selectJenis = document.getElementById(`jenis_id_${idx}`);
    if (selectJenis) {
        selectJenis.innerHTML = `<option value="${jenisId}" data-stok="${stok}" data-satuan="${satuan}" data-harga="${harga}" selected>${namaJenis} (Stok: ${stokFmt} ${satuan})</option>`;
        selectJenis.value = jenisId;
    }
    
    const elemSatuan = document.getElementById(`satuan_${idx}`);
    if (elemSatuan) elemSatuan.value = satuan;

    const elemHarga = document.getElementById(`harga_${idx}`);
    if (elemHarga) elemHarga.value = formatRupiahJS(harga, 'Rp ');

    const elemStokLabel = document.getElementById(`stok_label_${idx}`);
    if (elemStokLabel) elemStokLabel.innerHTML = `<i class="fa-solid fa-boxes-stacked me-1"></i> Stok tersedia: <strong>${stokFmt} ${satuan}</strong>`;

    const elemHargaLabel = document.getElementById(`harga_label_${idx}`);
    if (elemHargaLabel) elemHargaLabel.innerHTML = `<i class="fa-solid fa-tag me-1"></i> Harga standar: <strong>${formatRupiahJS(harga, 'Rp ')}</strong>`;
    
    calculateRow(idx);
}

function applyFavorit(selectElem) {
    const opt = selectElem.options[selectElem.selectedIndex];
    if (opt.value) {
        document.getElementById('nama_pembeli').value = opt.getAttribute('data-nama') || '';
        document.getElementById('telepon_pembeli').value = opt.getAttribute('data-telepon') || '';
    }
}

function loadVarianOptions(idx) {
    const barangId = document.getElementById(`barang_id_${idx}`).value;
    const selectJenis = document.getElementById(`jenis_id_${idx}`);
    selectJenis.innerHTML = '<option value="">-- Memuat jenis... --</option>';

    if (!barangId) {
        selectJenis.innerHTML = '<option value="">-- Pilih Jenis --</option>';
        document.getElementById(`stok_label_${idx}`).innerHTML = '<i class="fa-solid fa-boxes-stacked me-1"></i> Stok tersedia: -';
        document.getElementById(`harga_label_${idx}`).innerHTML = '<i class="fa-solid fa-tag me-1"></i> Harga standar: -';
        return;
    }

    fetch(`ajax_get_jenis.php?barang_id=${barangId}`)
        .then(res => res.json())
        .then(data => {
            let html = '<option value="">-- Pilih Jenis --</option>';
            if (Array.isArray(data) && data.length > 0) {
                data.forEach(j => {
                    const stokFmt = formatStokJS(j.stok);
                    html += `<option value="${j.id}" data-stok="${j.stok}" data-satuan="${j.satuan}" data-harga="${j.harga}">${j.nama_jenis} (Stok: ${stokFmt} ${j.satuan})</option>`;
                });
                selectJenis.innerHTML = html;

                // Auto-select jika varian hanya ada 1
                if (data.length === 1) {
                    selectJenis.selectedIndex = 1;
                    onVarianSelected(idx);
                }
            } else {
                selectJenis.innerHTML = '<option value="">-- Tidak ada varian --</option>';
            }
        })
        .catch(err => {
            selectJenis.innerHTML = '<option value="">-- Gagal memuat jenis --</option>';
        });
}

function onVarianSelected(idx) {
    const selectJenis = document.getElementById(`jenis_id_${idx}`);
    const opt = selectJenis.options[selectJenis.selectedIndex];
    
    if (opt && opt.value) {
        const stok = parseFloat(opt.getAttribute('data-stok') || 0);
        const satuan = opt.getAttribute('data-satuan') || 'unit';
        const harga = parseFloat(opt.getAttribute('data-harga') || 0);
        const stokFmt = formatStokJS(stok);

        document.getElementById(`satuan_${idx}`).value = satuan;
        document.getElementById(`harga_${idx}`).value = formatRupiahJS(harga, 'Rp ');
        document.getElementById(`stok_label_${idx}`).innerHTML = `<i class="fa-solid fa-boxes-stacked me-1"></i> Stok tersedia: <strong>${stokFmt} ${satuan}</strong>`;
        document.getElementById(`harga_label_${idx}`).innerHTML = `<i class="fa-solid fa-tag me-1"></i> Harga standar: <strong>${formatRupiahJS(harga, 'Rp ')}</strong>`;
        calculateRow(idx);
    }
}

function toggleCustomItem(idx) {
    const isCustom = document.getElementById(`is_custom_${idx}`).checked;
    const rowElem = document.getElementById(`row_${idx}`);
    const regFields = document.getElementById(`reguler_fields_${idx}`);
    const custFields = document.getElementById(`custom_fields_${idx}`);
    const stokWrapper = document.getElementById(`stok_info_wrapper_${idx}`);
    const hargaWrapper = document.getElementById(`harga_info_wrapper_${idx}`);
    
    const barangSelect = document.getElementById(`barang_id_${idx}`);
    const jenisSelect = document.getElementById(`jenis_id_${idx}`);
    const customNama = document.getElementById(`custom_nama_${idx}`);
    const satuanInput = document.getElementById(`satuan_${idx}`);

    if (rowElem) {
        if (isCustom) {
            rowElem.classList.add('is-custom-row');
        } else {
            rowElem.classList.remove('is-custom-row');
        }
    }

    if (satuanInput) {
        if (isCustom) {
            satuanInput.removeAttribute('readonly');
        } else {
            satuanInput.setAttribute('readonly', 'readonly');
        }
    }

    if (isCustom) {
        regFields.classList.add('d-none');
        custFields.classList.remove('d-none');
        if (stokWrapper) stokWrapper.classList.add('d-none');
        if (hargaWrapper) hargaWrapper.classList.add('d-none');
        
        barangSelect.removeAttribute('required');
        jenisSelect.removeAttribute('required');
        customNama.setAttribute('required', 'required');
    } else {
        custFields.classList.add('d-none');
        regFields.classList.remove('d-none');
        if (stokWrapper) stokWrapper.classList.remove('d-none');
        if (hargaWrapper) hargaWrapper.classList.remove('d-none');

        customNama.removeAttribute('required');
        barangSelect.setAttribute('required', 'required');
        jenisSelect.setAttribute('required', 'required');
    }
}

function calculateRow(idx) {
    const qty = parseFloat(document.getElementById(`jumlah_${idx}`).value) || 0;
    const hargaStr = document.getElementById(`harga_${idx}`).value;
    const harga = unformatRupiahJS(hargaStr);
    const subtotal = qty * harga;

    document.getElementById(`subtotal_${idx}`).value = formatRupiahJS(subtotal, 'Rp ');
    calculateGrandTotal();
}

function calculateGrandTotal() {
    let grandTotal = 0;
    document.querySelectorAll('.item-card-row').forEach(row => {
        const idx = row.getAttribute('data-index');
        const qty = parseFloat(document.getElementById(`jumlah_${idx}`).value) || 0;
        const harga = unformatRupiahJS(document.getElementById(`harga_${idx}`).value);
        grandTotal += (qty * harga);
    });
    document.getElementById('grandTotalDisplay').innerText = formatRupiahJS(grandTotal, 'Rp ');
}

function addItemRow() {
    const idx = rowIndexCounter++;
    const container = document.getElementById('itemContainer');
    
    let optBarang = '<option value="">-- Pilih Barang --</option>';
    barangIndukList.forEach(b => {
        optBarang += `<option value="${b.id}">${b.nama_barang}</option>`;
    });

    const rowHtml = `
    <div class="item-card-row mb-3" data-index="${idx}" id="row_${idx}">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="d-flex align-items-center gap-2">
                <div class="badge-item-wine">
                    <i class="fa-solid fa-basket-shopping me-1"></i> Barang Dibeli #${idx + 1}
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <div class="form-check form-switch m-0">
                    <input class="form-check-input" type="checkbox" name="is_custom_${idx}" value="1" id="is_custom_${idx}" onchange="toggleCustomItem(${idx})">
                    <label class="form-check-label fw-bold text-wine small text-nowrap" for="is_custom_${idx}">Custom Item</label>
                </div>
                <button type="button" class="btn-remove-circle ms-1" id="btn_remove_${idx}" onclick="removeRow(${idx})">&times;</button>
            </div>
        </div>

        <div class="row g-3" id="reguler_fields_${idx}">
            <div class="col-6 col-md-6">
                <label class="form-label text-muted small fw-semibold">Pilih Barang *</label>
                <select name="barang_id_${idx}" id="barang_id_${idx}" class="form-select" onchange="loadVarianOptions(${idx})" required>
                    ${optBarang}
                </select>
            </div>
            <div class="col-6 col-md-6">
                <label class="form-label text-muted small fw-semibold">Pilih Jenis *</label>
                <select name="jenis_id_${idx}" id="jenis_id_${idx}" class="form-select" onchange="onVarianSelected(${idx})" required>
                    <option value="">-- Pilih Jenis --</option>
                </select>
            </div>
        </div>

        <div class="row g-3 d-none" id="custom_fields_${idx}">
            <div class="col-6 col-md-6">
                <label class="form-label text-muted small fw-semibold">Nama Barang Custom *</label>
                <input type="text" name="custom_nama_${idx}" id="custom_nama_${idx}" class="form-control" placeholder="Contoh: Mesin giling kedelai pak ukat">
            </div>
            <div class="col-6 col-md-6">
                <label class="form-label text-muted small fw-semibold">Spesifikasi / Varian Custom</label>
                <input type="text" name="custom_jenis_${idx}" id="custom_jenis_${idx}" class="form-control" placeholder="Contoh: Ukuran 8in / Harga Manual">
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-6 col-md-3">
                <label class="form-label text-muted small fw-semibold">Jumlah (QTY) *</label>
                <input type="number" step="0.01" inputmode="decimal" name="jumlah_${idx}" id="jumlah_${idx}" class="form-control fw-bold" value="1" oninput="calculateRow(${idx})" placeholder="Jumlah" required>
                <div class="mt-1 small text-muted" id="stok_info_wrapper_${idx}">
                    <div id="stok_label_${idx}"><i class="fa-solid fa-boxes-stacked me-1"></i> Stok tersedia: -</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label text-muted small fw-semibold">Satuan *</label>
                <input type="text" name="satuan_${idx}" id="satuan_${idx}" class="form-control" list="preset_satuan_list" value="unit" placeholder="unit / kg / pcs" required readonly>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label text-muted small fw-semibold">Harga Satuan (Rp) *</label>
                <input type="text" name="harga_${idx}" id="harga_${idx}" class="form-control rupiah-input fw-bold text-wine" inputmode="numeric" placeholder="Rp 0" oninput="calculateRow(${idx})" required>
                <div class="mt-1 small text-muted" id="harga_info_wrapper_${idx}">
                    <div id="harga_label_${idx}"><i class="fa-solid fa-tag me-1"></i> Harga standar: -</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label text-muted small fw-semibold">Subtotal (Rp)</label>
                <input type="text" id="subtotal_${idx}" class="form-control fw-bold text-success bg-white" value="Rp 0" readonly>
            </div>
        </div>
    </div>`;

    container.insertAdjacentHTML('beforeend', rowHtml);
    initRupiahMasking();
    return idx;
}

function removeRow(idx) {
    const elem = document.getElementById(`row_${idx}`);
    if (elem) {
        elem.remove();
        calculateGrandTotal();
        calculateSisaPreview();
    }
}

function selectFormStatusBayar(status) {
    const inputStatus = document.getElementById('input_status_pembayaran');
    const containerDP = document.getElementById('container_input_dp');
    const sectionMetode = document.getElementById('section_metode_pembayaran');

    if (inputStatus) inputStatus.value = status;

    ['card_status_belum', 'card_status_cicilan', 'card_status_lunas'].forEach(id => {
        const card = document.getElementById(id);
        if (card) {
            card.classList.remove('border-danger', 'bg-danger-subtle', 'border-warning', 'bg-warning-subtle', 'border-success', 'bg-success-subtle');
            card.classList.add('bg-light');
        }
    });

    if (status === 'cicilan') {
        const cardCicilan = document.getElementById('card_status_cicilan');
        if (cardCicilan) {
            cardCicilan.classList.remove('bg-light');
            cardCicilan.classList.add('border-warning', 'bg-warning-subtle');
        }
        if (containerDP) containerDP.classList.remove('d-none');
        if (sectionMetode) sectionMetode.classList.remove('d-none');
    } else if (status === 'dibayar') {
        const cardLunas = document.getElementById('card_status_lunas');
        if (cardLunas) {
            cardLunas.classList.remove('bg-light');
            cardLunas.classList.add('border-success', 'bg-success-subtle');
        }
        if (containerDP) containerDP.classList.add('d-none');
        if (sectionMetode) sectionMetode.classList.remove('d-none');
    } else {
        const cardBelum = document.getElementById('card_status_belum');
        if (cardBelum) {
            cardBelum.classList.remove('bg-light');
            cardBelum.classList.add('border-danger', 'bg-danger-subtle');
        }
        if (containerDP) containerDP.classList.add('d-none');
        if (sectionMetode) sectionMetode.classList.add('d-none');
        deselectFormPaymentMethod();
    }

    calculateSisaPreview();
}

function calculateSisaPreview() {
    const inputStatus = document.getElementById('input_status_pembayaran');
    const status = inputStatus ? inputStatus.value : 'belum_dibayar';
    const dpInput = document.getElementById('jumlah_dibayar_input');
    const previewSisa = document.getElementById('previewSisaPiutang');

    const parseRupiahVal = (str) => {
        if (typeof unformatRupiahJS === 'function') return unformatRupiahJS(str);
        if (!str) return 0;
        let num = parseInt(str.toString().replace(/[^\d]/g, ''), 10);
        return isNaN(num) ? 0 : num;
    };

    const formatRupiahVal = (num) => {
        if (typeof formatRupiahJS === 'function') return formatRupiahJS(num, 'Rp ');
        return 'Rp ' + (num || 0).toLocaleString('id-ID');
    };

    let totalVal = 0;
    document.querySelectorAll('[id^="subtotal_"]').forEach(input => {
        totalVal += parseRupiahVal(input.value);
    });

    if (status === 'cicilan') {
        const dpVal = parseRupiahVal(dpInput ? dpInput.value : 0);
        const sisa = Math.max(0, totalVal - dpVal);
        if (previewSisa) previewSisa.innerText = formatRupiahVal(sisa);
    }
}

document.addEventListener("DOMContentLoaded", function() {
    selectFormStatusBayar('belum_dibayar');
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
