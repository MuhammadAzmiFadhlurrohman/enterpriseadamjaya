<?php
require_once __DIR__ . '/includes/header.php';
require_admin();
?>

<!-- HEADER CURVED EXECUTIVE BANNER -->
<header class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <span class="page-eyebrow d-none d-md-block"><i class="fa-solid fa-wallet"></i> Operational Cash &middot; Pengeluaran Kas</span>
            <h1 class="page-title">Pencatatan Pengeluaran Kas Baru</h1>
            <p class="page-subtitle mb-0">Catat transaksi pengeluaran operasional perusahaan (Multi-Item)</p>
        </div>
        <div class="header-action">
            <a href="pengeluaran.php" class="btn btn-outline-light btn-sm rounded-pill px-3 fw-semibold">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar
            </a>
        </div>
    </div>
</header>

<form action="proses_tambah_pengeluaran.php" method="POST" id="formPengeluaran">
    <?= csrf_field(); ?>

    <!-- SECTION 1: HEADER TRANSAKSI -->
    <div class="form-section-card mb-4">
        <div class="d-flex align-items-center gap-2 mb-3">
            <i class="fa-solid fa-calendar-day text-wine fs-5"></i>
            <h5 class="fw-bold text-wine mb-0">Header Transaksi Pengeluaran</h5>
        </div>
        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label text-muted small fw-semibold">TANGGAL TRANSAKSI *</label>
                <input type="date" name="tanggal" class="form-control" value="<?= date('Y-m-d'); ?>" required>
            </div>
            <div class="col-12 col-md-8">
                <label class="form-label text-muted small fw-semibold">KETERANGAN / CATATAN UTAMA</label>
                <input type="text" name="keterangan" class="form-control" placeholder="Contoh: Tagihan Operasional Kantor Bulanan">
            </div>
        </div>
    </div>

    <!-- SECTION 2: RINCIAN ITEM PENGELUARAN -->
    <div class="form-section-card mb-4">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-list-check text-wine fs-5"></i>
                <h5 class="fw-bold text-wine mb-0">Rincian Item Pengeluaran</h5>
            </div>
            <button type="button" class="btn btn-sm btn-wine-outline rounded-pill px-3 py-1.5 fw-bold" onclick="addExpenseRow()">
                <i class="fa-solid fa-plus-circle me-1"></i> Tambah Item
            </button>
        </div>

        <div id="expenseContainer">
            <div class="expense-row p-3.5 rounded-3 bg-light border mb-3 shadow-sm" data-index="0" id="exp_row_0">
                <div class="row g-3">
                    <div class="col-12 col-md-5">
                        <label class="form-label text-muted small fw-semibold">NAMA ITEM PENGELUARAN *</label>
                        <input type="text" name="nama_item_0" class="form-control" placeholder="Contoh: Tagihan PLN Juli" required>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label text-muted small fw-semibold">KATEGORI</label>
                        <select name="kategori_0" class="form-select">
                            <option value="Operasional">Operasional</option>
                            <option value="Utility">Utility (Listrik/Air/Internet)</option>
                            <option value="Gaji & Bonus">Gaji & Bonus</option>
                            <option value="Sewa & Fasilitas">Sewa & Fasilitas</option>
                            <option value="Pemeliharaan">Pemeliharaan & Service</option>
                            <option value="Konsumsi & Logistik">Konsumsi & Logistik</option>
                            <option value="Lain-lain">Lain-lain</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label text-muted small fw-semibold">JUMLAH (QTY)</label>
                        <input type="number" step="0.01" name="jumlah_0" id="exp_jumlah_0" class="form-control" value="1.00" oninput="calculateExpRow(0)" required>
                    </div>
                    <div class="col-6 col-md-2">
                        <label class="form-label text-muted small fw-semibold">SATUAN</label>
                        <input type="text" name="satuan_0" class="form-control" value="unit" required>
                    </div>
                </div>

                <div class="row g-3 mt-1">
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small fw-semibold">HARGA SATUAN (RP)</label>
                        <input type="text" name="harga_satuan_0" id="exp_harga_0" class="form-control rupiah-input" placeholder="Rp 0" oninput="calculateExpRow(0)" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label text-muted small fw-semibold">TOTAL HARGA ITEM</label>
                        <input type="text" id="exp_subtotal_0" class="form-control fw-bold text-wine bg-white" value="Rp 0" readonly>
                    </div>
                </div>
            </div>
        </div>

        <!-- GRAND TOTAL BANNER -->
        <div class="p-3 p-md-4 rounded-3 text-white shadow-sm mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: linear-gradient(135deg, #7A1E33 0%, #4a0b18 100%); border: 1px solid #7A1E33;">
            <div class="d-flex align-items-center gap-2">
                <i class="fa-solid fa-calculator fs-4 text-warning"></i>
                <div>
                    <h5 class="fw-bold mb-0 text-white" style="letter-spacing: 0.5px;">TOTAL PENGELUARAN KAS:</h5>
                    <small style="color: rgba(255,255,255,0.8); font-size: 0.75rem;">Kalkulasi otomatis dari seluruh rincian item pengeluaran</small>
                </div>
            </div>
            <h3 class="fw-bolder mb-0" id="expGrandTotalDisplay" style="font-size: 1.8rem; color: #ffd700 !important; text-shadow: 0 2px 4px rgba(0,0,0,0.4);">Rp 0</h3>
        </div>
    </div>

    <div class="text-end mb-5">
        <button type="submit" class="btn btn-lg px-5 rounded-pill shadow-lg fw-bold text-white" style="background: linear-gradient(135deg, #7A1E33 0%, #4a0b18 100%); border: none;">
            <i class="fa-solid fa-floppy-disk me-2 text-warning"></i> Simpan Pengeluaran Kas
        </button>
    </div>
</form>

<script>
let expRowIndex = 1;

function calculateExpRow(idx) {
    const qtyElem = document.getElementById(`exp_jumlah_${idx}`);
    const rawQty = qtyElem ? qtyElem.value : '';
    const qty = parseFloat(String(rawQty).replace(',', '.')) || 0;
    const hargaStr = document.getElementById(`exp_harga_${idx}`).value;
    const harga = unformatRupiahJS(hargaStr);
    const total = Math.round(qty * harga * 100) / 100;

    document.getElementById(`exp_subtotal_${idx}`).value = formatRupiahJS(total, 'Rp ');
    calculateExpGrandTotal();
}

function calculateExpGrandTotal() {
    let grandTotal = 0;
    document.querySelectorAll('.expense-row').forEach(row => {
        const idx = row.getAttribute('data-index');
        const qtyElem = document.getElementById(`exp_jumlah_${idx}`);
        const rawQty = qtyElem ? qtyElem.value : '';
        const qty = parseFloat(String(rawQty).replace(',', '.')) || 0;
        const harga = unformatRupiahJS(document.getElementById(`exp_harga_${idx}`).value);
        grandTotal += (qty * harga);
    });
    grandTotal = Math.round(grandTotal * 100) / 100;
    document.getElementById('expGrandTotalDisplay').innerText = formatRupiahJS(grandTotal, 'Rp ');
}

function addExpenseRow() {
    const idx = expRowIndex++;
    const container = document.getElementById('expenseContainer');

    const html = `
    <div class="expense-row p-3.5 rounded-3 bg-light border mb-3 shadow-sm" data-index="${idx}" id="exp_row_${idx}">
        <div class="row g-3">
            <div class="col-12 col-md-5">
                <label class="form-label text-muted small fw-semibold">NAMA ITEM PENGELUARAN *</label>
                <input type="text" name="nama_item_${idx}" class="form-control" placeholder="Nama item..." required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label text-muted small fw-semibold">KATEGORI</label>
                <select name="kategori_${idx}" class="form-select">
                    <option value="Operasional">Operasional</option>
                    <option value="Utility">Utility (Listrik/Air/Internet)</option>
                    <option value="Gaji & Bonus">Gaji & Bonus</option>
                    <option value="Sewa & Fasilitas">Sewa & Fasilitas</option>
                    <option value="Pemeliharaan">Pemeliharaan & Service</option>
                    <option value="Konsumsi & Logistik">Konsumsi & Logistik</option>
                    <option value="Lain-lain">Lain-lain</option>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label text-muted small fw-semibold">JUMLAH (QTY)</label>
                <input type="number" step="0.01" name="jumlah_${idx}" id="exp_jumlah_${idx}" class="form-control" value="1.00" oninput="calculateExpRow(${idx})" required>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label text-muted small fw-semibold">SATUAN</label>
                <input type="text" name="satuan_${idx}" class="form-control" value="unit" required>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-12 col-md-6">
                <label class="form-label text-muted small fw-semibold">HARGA SATUAN (RP)</label>
                <input type="text" name="harga_satuan_${idx}" id="exp_harga_${idx}" class="form-control rupiah-input" placeholder="Rp 0" oninput="calculateExpRow(${idx})" required>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label text-muted small fw-semibold">TOTAL HARGA ITEM</label>
                <input type="text" id="exp_subtotal_${idx}" class="form-control fw-bold text-wine bg-white" value="Rp 0" readonly>
            </div>
        </div>

        <div class="text-end mt-2">
            <button type="button" class="btn btn-sm btn-outline-danger rounded-pill px-3" onclick="removeExpRow(${idx})">
                <i class="fa-solid fa-trash me-1"></i> Hapus Item Ini
            </button>
        </div>
    </div>`;

    container.insertAdjacentHTML('beforeend', html);
    initRupiahMasking();
}

function removeExpRow(idx) {
    const elem = document.getElementById(`exp_row_${idx}`);
    if (elem) {
        elem.remove();
        calculateExpGrandTotal();
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
