<?php
require_once __DIR__ . '/includes/header.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$return_url = sanitize($_GET['return_url'] ?? '');

if (!empty($return_url)) {
    $parsed = parse_url($return_url);
    if (!empty($parsed['host']) || !empty($parsed['scheme'])) {
        $return_url = 'pengeluaran.php';
    }
} else {
    $return_url = 'pengeluaran.php';
}

$stmt = mysqli_prepare($conn, "SELECT * FROM pengeluaran_header WHERE id = ?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$h = mysqli_fetch_assoc($res);

if (!$h) {
    set_flash('error', 'Gagal', 'Data pengeluaran tidak ditemukan.');
    header('Location: ' . $return_url);
    exit;
}

$stmt_d = mysqli_prepare($conn, "SELECT * FROM pengeluaran_detail WHERE pengeluaran_id = ? ORDER BY id ASC");
mysqli_stmt_bind_param($stmt_d, "i", $id);
mysqli_stmt_execute($stmt_d);
$res_d = mysqli_stmt_get_result($stmt_d);
$details = [];
while ($row = mysqli_fetch_assoc($res_d)) {
    $details[] = $row;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-1 text-dark">Edit Pengeluaran Kas</h3>
        <p class="text-muted mb-0">Ubah transaksi pengeluaran <strong>#<?= e($h['custom_id']); ?></strong></p>
    </div>
    <a href="<?= e($return_url); ?>" class="btn btn-secondary-custom">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
</div>

<form action="proses_edit_pengeluaran.php" method="POST" id="formEditPengeluaran">
    <?= csrf_field(); ?>
    <input type="hidden" name="id" value="<?= $h['id']; ?>">
    <input type="hidden" name="return_url" value="<?= e($return_url); ?>">

    <!-- Header Transaksi -->
    <div class="glass-card p-4 mb-4">
        <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-calendar-day text-warning me-2"></i> Header Transaksi Pengeluaran</h5>
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label text-muted small fw-semibold">TANGGAL TRANSAKSI *</label>
                <input type="date" name="tanggal" class="form-control" value="<?= e($h['tanggal']); ?>" required>
            </div>
            <div class="col-md-8">
                <label class="form-label text-muted small fw-semibold">KETERANGAN / CATATAN UTAMA</label>
                <input type="text" name="keterangan" class="form-control" value="<?= e($h['keterangan']); ?>">
            </div>
        </div>
    </div>

    <!-- Items Detail -->
    <div class="glass-card p-4 mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-list-check text-warning me-2"></i> Rincian Item Pengeluaran</h5>
            <button type="button" class="btn btn-sm btn-outline-success" onclick="addExpenseRow()">
                <i class="fa-solid fa-plus me-1"></i> Tambah Item
            </button>
        </div>

        <div id="expenseContainer">
            <?php foreach ($details as $idx => $d): ?>
                <div class="expense-row p-3 rounded bg-light border mb-3" data-index="<?= $idx; ?>" id="exp_row_<?= $idx; ?>">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label text-muted small fw-semibold">NAMA ITEM PENGELUARAN *</label>
                            <input type="text" name="nama_item_<?= $idx; ?>" class="form-control" value="<?= e($d['nama_item']); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label text-muted small fw-semibold">KATEGORI</label>
                            <select name="kategori_<?= $idx; ?>" class="form-select">
                                <?php 
                                $cats = ['Operasional', 'Utility', 'Gaji & Bonus', 'Sewa & Fasilitas', 'Pemeliharaan', 'Konsumsi & Logistik', 'Lain-lain'];
                                foreach ($cats as $cat): 
                                ?>
                                    <option value="<?= $cat; ?>" <?= ($d['kategori'] === $cat) ? 'selected' : ''; ?>><?= $cat; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted small fw-semibold">JUMLAH (QTY)</label>
                            <input type="number" step="0.01" name="jumlah_<?= $idx; ?>" id="exp_jumlah_<?= $idx; ?>" class="form-control" value="<?= (float)$d['jumlah']; ?>" oninput="calculateExpRow(<?= $idx; ?>)" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label text-muted small fw-semibold">SATUAN</label>
                            <input type="text" name="satuan_<?= $idx; ?>" class="form-control" value="<?= e($d['satuan']); ?>" required>
                        </div>
                    </div>

                    <div class="row g-3 mt-1">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">HARGA SATUAN (RP)</label>
                            <input type="text" name="harga_satuan_<?= $idx; ?>" id="exp_harga_<?= $idx; ?>" class="form-control rupiah-input" value="<?= formatRupiah($d['harga_satuan']); ?>" oninput="calculateExpRow(<?= $idx; ?>)" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">TOTAL HARGA ITEM</label>
                            <input type="text" id="exp_subtotal_<?= $idx; ?>" class="form-control fw-bold text-danger bg-white" value="<?= formatRupiah($d['total_harga']); ?>" readonly>
                        </div>
                    </div>

                    <?php if ($idx > 0): ?>
                        <div class="text-end mt-2">
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeExpRow(<?= $idx; ?>)">
                                <i class="fa-solid fa-trash me-1"></i> Hapus Item Ini
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
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
            <h3 class="fw-bolder mb-0" id="expGrandTotalDisplay" style="font-size: 1.8rem; color: #ffd700 !important; text-shadow: 0 2px 4px rgba(0,0,0,0.4);"><?= formatRupiah($h['total_pengeluaran']); ?></h3>
        </div>
    </div>

    <div class="text-end mb-5">
        <button type="submit" class="btn btn-lg px-5 rounded-pill shadow-lg fw-bold text-white" style="background: linear-gradient(135deg, #7A1E33 0%, #4a0b18 100%); border: none;">
            <i class="fa-solid fa-floppy-disk me-2 text-warning"></i> Update Pengeluaran Kas
        </button>
    </div>
</form>

<script>
let expRowIndex = <?= count($details); ?>;

function calculateExpRow(idx) {
    const qty = parseFloat(document.getElementById(`exp_jumlah_${idx}`).value) || 0;
    const hargaStr = document.getElementById(`exp_harga_${idx}`).value;
    const harga = unformatRupiahJS(hargaStr);
    const total = qty * harga;

    document.getElementById(`exp_subtotal_${idx}`).value = formatRupiahJS(total, 'Rp ');
    calculateExpGrandTotal();
}

function calculateExpGrandTotal() {
    let grandTotal = 0;
    document.querySelectorAll('.expense-row').forEach(row => {
        const idx = row.getAttribute('data-index');
        const qty = parseFloat(document.getElementById(`exp_jumlah_${idx}`).value) || 0;
        const harga = unformatRupiahJS(document.getElementById(`exp_harga_${idx}`).value);
        grandTotal += (qty * harga);
    });
    document.getElementById('expGrandTotalDisplay').innerText = formatRupiahJS(grandTotal, 'Rp ');
}

function addExpenseRow() {
    const idx = expRowIndex++;
    const container = document.getElementById('expenseContainer');

    const html = `
    <div class="expense-row p-3 rounded bg-light border mb-3" data-index="${idx}" id="exp_row_${idx}">
        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label text-muted small fw-semibold">NAMA ITEM PENGELUARAN *</label>
                <input type="text" name="nama_item_${idx}" class="form-control" placeholder="Nama item..." required>
            </div>
            <div class="col-md-3">
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
            <div class="col-md-2">
                <label class="form-label text-muted small fw-semibold">JUMLAH (QTY)</label>
                <input type="number" step="0.01" name="jumlah_${idx}" id="exp_jumlah_${idx}" class="form-control" value="1.00" oninput="calculateExpRow(${idx})" required>
            </div>
            <div class="col-md-2">
                <label class="form-label text-muted small fw-semibold">SATUAN</label>
                <input type="text" name="satuan_${idx}" class="form-control" value="unit" required>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <label class="form-label text-muted small fw-semibold">HARGA SATUAN (RP)</label>
                <input type="text" name="harga_satuan_${idx}" id="exp_harga_${idx}" class="form-control rupiah-input" placeholder="Rp 0" oninput="calculateExpRow(${idx})" required>
            </div>
            <div class="col-md-6">
                <label class="form-label text-muted small fw-semibold">TOTAL HARGA ITEM</label>
                <input type="text" id="exp_subtotal_${idx}" class="form-control fw-bold text-danger bg-white" value="Rp 0" readonly>
            </div>
        </div>

        <div class="text-end mt-2">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeExpRow(${idx})">
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
