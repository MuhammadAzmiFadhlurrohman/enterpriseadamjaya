<?php
require_once __DIR__ . '/includes/header.php';
require_login();

$is_admin = is_admin();
$barang_id = (int)($_GET['barang_id'] ?? 0);

// Ambil daftar barang induk untuk dropdown filter / context
$query_induk = "SELECT * FROM stok_barang ORDER BY nama_barang ASC";
$res_induk = mysqli_query($conn, $query_induk);

// Query Varian
if ($barang_id > 0) {
    $stmt = mysqli_prepare($conn, "SELECT j.*, b.nama_barang FROM jenis_barang j JOIN stok_barang b ON j.barang_id = b.id WHERE j.barang_id = ? ORDER BY j.id DESC");
    mysqli_stmt_bind_param($stmt, "i", $barang_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
} else {
    $query = "SELECT j.*, b.nama_barang FROM jenis_barang j JOIN stok_barang b ON j.barang_id = b.id ORDER BY b.nama_barang ASC, j.nama_jenis ASC";
    $result = mysqli_query($conn, $query);
}
?>

<!-- Datalist pilihan preset satuan -->
<datalist id="preset_satuan_list">
    <option value="unit">
    <option value="kg">
    <option value="meter">
    <option value="mm">
    <option value="pcs">
</datalist>

<!-- HEADER CURVED EXECUTIVE BANNER -->
<header class="page-header mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <span class="page-eyebrow"><i class="fa-solid fa-layer-group"></i> Katalog Varian &middot; Adam Jaya</span>
            <h1 class="page-title">Varian & Stok Detail</h1>
            <p class="page-subtitle mb-0">Manajemen varian spesifikasi, harga standar, stok persediaan, dan satuan.</p>
        </div>
        <div class="header-action d-flex align-items-center gap-2">
            <a href="stok_barang.php" id="btnBackToStok" class="btn btn-outline-light btn-sm rounded-pill px-3 fw-semibold text-nowrap">
                <i class="fa-solid fa-arrow-left me-1"></i> Kembali
            </a>
        </div>
    </div>
</header>

<?php if ($is_admin): ?>
<!-- CARD FORM TAMBAH VARIAN BARU (INLINE AT TOP) -->
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
    <div class="card-header text-white py-3 px-3.5 d-flex align-items-center gap-2" style="background: linear-gradient(135deg, #7A1E33 0%, #5A1224 100%);">
        <i class="fa-solid fa-circle-plus fs-5 text-gold"></i>
        <h6 class="mb-0 fw-bold text-white fs-6">Tambah Varian Baru</h6>
    </div>
    <div class="card-body p-3.5 bg-white">
        <form action="proses_jenis_barang.php" method="POST">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="barang_id" value="<?= $barang_id; ?>">
            <input type="hidden" name="redirect_barang_id" value="<?= $barang_id; ?>">

            <div class="mb-3">
                <label class="form-label text-muted small fw-bold">Nama Varian / Spesifikasi *</label>
                <input type="text" name="nama_jenis" class="form-control form-control-lg fs-6 fw-semibold" placeholder="Contoh: Besi Polos 10mm (12m)" required>
            </div>

            <div class="row g-2 g-md-3 mb-3">
                <div class="col-6">
                    <label class="form-label text-muted small fw-bold">Stok Awal</label>
                    <input type="number" step="0.01" name="stok" class="form-control form-control-lg fs-6 fw-semibold" value="0" required>
                </div>
                <div class="col-6">
                    <label class="form-label text-muted small fw-bold">Satuan *</label>
                    <select name="satuan" class="form-select form-select-lg fs-6 fw-semibold" required>
                        <option value="unit" selected>unit</option>
                        <option value="kg">kg</option>
                        <option value="pack">pack</option>
                        <option value="mm">mm</option>
                        <option value="meter">meter</option>
                        <option value="pcs">pcs</option>
                    </select>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label text-muted small fw-bold">Harga Standar Satuan (Rp) *</label>
                <input type="text" name="harga" class="form-control form-control-lg fs-6 fw-semibold rupiah-input" placeholder="Contoh: 1250000" oninput="formatRupiahInput(this)" onkeyup="formatRupiahInput(this)" required>
            </div>

            <button type="submit" class="btn text-white w-100 py-2.5 fw-bold rounded-3 shadow-sm fs-6" style="background: linear-gradient(135deg, #7A1E33 0%, #5A1224 100%); border: none;">
                <i class="fa-solid fa-plus me-1"></i> Tambah Varian
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Desktop Table Container (>= 768px) -->
<div class="table-container d-none d-md-block">
    <div class="table-container-header">
        <h2><i class="fa-solid fa-layer-group me-2 text-wine"></i> Daftar Varian Barang & Spesifikasi</h2>
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th width="45">ID</th>
                    <th>Barang Induk</th>
                    <th>Nama Varian / Spesifikasi</th>
                    <th>Stok Tersedia</th>
                    <th>Satuan</th>
                    <th>Harga Satuan</th>
                    <th width="150" class="text-end">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (mysqli_num_rows($result) > 0): 
                    mysqli_data_seek($result, 0);
                ?>
                    <?php while ($j = mysqli_fetch_assoc($result)): ?>
                        <tr id="row-jenis-<?= $j['id']; ?>">
                            <td><strong>#<?= $j['id']; ?></strong></td>
                            <td>
                                <span class="badge rounded-2 px-2.5 py-1.5" style="background: #FDF5F6; color: #7A1E33; border: 1px solid #F5D5DA; font-weight: 700; font-size: 0.8rem;">
                                    <i class="fa-solid fa-box-archive me-1 text-gold"></i> <?= e($j['nama_barang']); ?>
                                </span>
                            </td>
                            <td><strong class="text-dark"><?= e($j['nama_jenis']); ?></strong></td>
                            <td>
                                <?php if ($j['stok'] <= 10): ?>
                                    <span class="badge-status danger"><i class="fa-solid fa-triangle-exclamation"></i> <?= format_stok($j['stok']); ?></span>
                                <?php else: ?>
                                    <span class="badge-status success"><i class="fa-solid fa-check"></i> <?= format_stok($j['stok']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="text-muted fw-semibold"><?= e($j['satuan']); ?></span></td>
                            <td><span class="fw-bold text-success"><?= formatRupiah($j['harga']); ?></span></td>
                            <td class="text-end">
                                <div class="action-btns justify-content-end">
                                    <?php if ($is_admin): ?>
                                        <button type="button" class="action-btn btn-edit" onclick="editJenis(<?= htmlspecialchars(json_encode($j)); ?>)">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </button>
                                        <a href="#" class="action-btn btn-delete" 
                                           onclick="confirmDelete(event, 'proses_jenis_barang.php?action=delete&id=<?= $j['id']; ?>&redirect_barang_id=<?= $barang_id; ?>&csrf_token=<?= generate_csrf_token(); ?>')">
                                            <i class="fa-solid fa-trash"></i> Hapus
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">Read-Only</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center py-4 text-muted">Belum ada varian barang terdaftar.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Mobile Executive Card View (< 768px) -->
<div class="d-md-none" id="mobileVarianList">
    <div class="d-flex justify-content-between align-items-center mb-3 px-1">
        <h6 class="fw-bold text-wine mb-0" style="font-size:0.95rem;"><i class="fa-solid fa-layer-group me-1.5"></i> Daftar Varian Barang</h6>
    </div>

    <?php if (mysqli_num_rows($result) > 0): 
        mysqli_data_seek($result, 0);
    ?>
        <?php while ($j = mysqli_fetch_assoc($result)): ?>
            <div class="audit-card-mobile mb-3.5 varian-card-item" id="mobile-row-jenis-<?= $j['id']; ?>">
                <!-- Top Header Row -->
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2.5 border-bottom">
                    <div class="d-flex align-items-center gap-2">
                        <span class="id-chip" style="font-size:0.7rem; padding:3px 8px;">#<?= $j['id']; ?></span>
                        <span class="badge rounded-2 px-2.5 py-1" style="background: #FDF5F6; color: #7A1E33; border: 1px solid #F5D5DA; font-weight: 700; font-size: 0.75rem;">
                            <i class="fa-solid fa-box-archive me-1 text-gold"></i> <?= e($j['nama_barang']); ?>
                        </span>
                    </div>
                </div>

                <!-- Varian Name Title -->
                <div class="mb-3">
                    <h5 class="fw-bold text-dark mb-0" style="font-size:1.02rem; line-height:1.3;"><?= e($j['nama_jenis']); ?></h5>
                </div>

                <!-- Specs & Price Grid Box -->
                <div class="varian-card-box mb-3 d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted" style="font-size:0.65rem; font-weight:700; letter-spacing:0.04em;">HARGA SATUAN</div>
                        <div class="fw-bold text-success" style="font-size:0.95rem; margin-top:1px;"><?= formatRupiah($j['harga']); ?></div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted" style="font-size:0.65rem; font-weight:700; letter-spacing:0.04em;">STOK TERSEDIA</div>
                        <div style="margin-top:2px;">
                            <?php if ($j['stok'] <= 10): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.75rem;">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i><?= format_stok($j['stok']); ?> <?= e($j['satuan']); ?>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill px-2.5 py-1 fw-bold" style="font-size:0.75rem;">
                                    <i class="fa-solid fa-check me-1"></i><?= format_stok($j['stok']); ?> <?= e($j['satuan']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Action Footer Buttons -->
                <?php if ($is_admin): ?>
                    <div class="action-btns ms-auto justify-content-end pt-1">
                        <button type="button" class="action-btn btn-edit" onclick="editJenis(<?= htmlspecialchars(json_encode($j)); ?>)">
                            <i class="fa-solid fa-pen me-1"></i> Edit
                        </button>
                        <a href="#" class="action-btn btn-delete" 
                           onclick="confirmDelete(event, 'proses_jenis_barang.php?action=delete&id=<?= $j['id']; ?>&redirect_barang_id=<?= $barang_id; ?>&csrf_token=<?= generate_csrf_token(); ?>')">
                            <i class="fa-solid fa-trash me-1"></i> Hapus
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="glass-card text-center py-4">
            <i class="fa-solid fa-layer-group fs-1 text-muted mb-2"></i>
            <h6 class="fw-bold text-dark mb-0">Belum ada varian barang terdaftar.</h6>
        </div>
    <?php endif; ?>
</div>

<?php if ($is_admin): ?>
<!-- Modal Tambah Varian -->
<div class="modal fade" id="addJenisModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-list-check me-2 text-indigo"></i> Tambah Varian Barang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_jenis_barang.php" method="POST">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="redirect_barang_id" value="<?= $barang_id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">PILIH BARANG INDUK</label>
                        <select name="barang_id" class="form-select" required>
                            <option value="">-- Pilih Barang Induk --</option>
                            <?php 
                            mysqli_data_seek($res_induk, 0);
                            while ($b = mysqli_fetch_assoc($res_induk)): 
                            ?>
                                <option value="<?= $b['id']; ?>" <?= ($barang_id == $b['id']) ? 'selected' : ''; ?>>
                                    <?= e($b['nama_barang']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">NAMA VARIAN / SPESIFIKASI</label>
                        <input type="text" name="nama_jenis" class="form-control" placeholder="Contoh: Besi Polos 10mm (12m)" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">STOK AWAL</label>
                            <input type="number" step="0.01" name="stok" class="form-control" value="0" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted small fw-semibold">SATUAN</label>
                            <select name="satuan" class="form-select" required>
                                <option value="unit" selected>unit</option>
                                <option value="kg">kg</option>
                                <option value="pack">pack</option>
                                <option value="mm">mm</option>
                                <option value="meter">meter</option>
                                <option value="pcs">pcs</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label text-muted small fw-semibold">HARGA STANDAR SATUAN (RP)</label>
                        <input type="text" name="harga" class="form-control rupiah-input" placeholder="Rp 0" oninput="formatRupiahInput(this)" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-custom">Simpan Varian</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Varian -->
<div class="modal fade" id="editJenisModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-pen-to-square me-2 text-gold"></i> Edit Varian Barang</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_jenis_barang.php" method="POST">
                <?= csrf_field(); ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="edit_j_id">
                <input type="hidden" name="barang_id" id="edit_j_barang_id">
                <input type="hidden" name="redirect_barang_id" value="<?= $barang_id; ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">NAMA VARIAN / SPESIFIKASI</label>
                        <input type="text" name="nama_jenis" id="edit_j_nama" class="form-control" required>
                    </div>
                    <div class="row g-2 g-md-3">
                        <div class="col-6">
                            <label class="form-label text-muted small fw-semibold">STOK TERSEDIA</label>
                            <input type="number" step="0.01" name="stok" id="edit_j_stok" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label text-muted small fw-semibold">SATUAN</label>
                            <select name="satuan" id="edit_j_satuan" class="form-select" required>
                                <option value="unit">unit</option>
                                <option value="kg">kg</option>
                                <option value="pack">pack</option>
                                <option value="mm">mm</option>
                                <option value="meter">meter</option>
                                <option value="pcs">pcs</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label text-muted small fw-semibold">HARGA STANDAR SATUAN (RP)</label>
                        <input type="text" name="harga" id="edit_j_harga" class="form-control rupiah-input" oninput="formatRupiahInput(this)" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-custom">Update Varian</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function filterVarianLive(query) {
    const q = query.toLowerCase().trim();
    
    // Filter Desktop Table Rows
    const rows = document.querySelectorAll('tbody tr[id^="row-jenis-"]');
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
    
    // Filter Mobile Varian Cards
    const cards = document.querySelectorAll('.varian-card-item');
    cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(q) ? '' : 'none';
    });
}

function editJenis(j) {
    document.getElementById('edit_j_id').value = j.id;
    document.getElementById('edit_j_barang_id').value = j.barang_id;
    document.getElementById('edit_j_nama').value = j.nama_jenis;
    
    // Format stok tanpa desimal .00 jika angka bulat saat modal edit dibuka
    const stokNum = parseFloat(j.stok) || 0;
    document.getElementById('edit_j_stok').value = (stokNum % 1 === 0) ? stokNum.toFixed(0) : stokNum;
    
    const selectSatuan = document.getElementById('edit_j_satuan');
    selectSatuan.value = j.satuan;
    if (selectSatuan.value !== j.satuan && j.satuan) {
        const customOpt = document.createElement('option');
        customOpt.value = j.satuan;
        customOpt.textContent = j.satuan;
        selectSatuan.appendChild(customOpt);
        selectSatuan.value = j.satuan;
    }
    document.getElementById('edit_j_harga').value = formatRupiahJS(j.harga, 'Rp ');
    new bootstrap.Modal(document.getElementById('editJenisModal')).show();
}

document.addEventListener("DOMContentLoaded", function() {
    const savedSearch = sessionStorage.getItem('stok_barang_search');
    const btnBack = document.getElementById('btnBackToStok');
    if (savedSearch && savedSearch.trim() !== '' && btnBack) {
        btnBack.href = 'stok_barang.php?search=' + encodeURIComponent(savedSearch);
    }

    const urlParams = new URLSearchParams(window.location.search);
    const scrollTo = urlParams.get('scroll_to') || window.location.hash.replace('#', '');
    if (scrollTo) {
        setTimeout(() => {
            const target = document.getElementById(scrollTo);
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                target.classList.add('row-highlight-pulse');
                setTimeout(() => target.classList.remove('row-highlight-pulse'), 2500);
            }
        }, 250);
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
