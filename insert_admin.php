<?php
require_once __DIR__ . '/includes/header.php';
require_login();

$is_admin = is_admin();

// Parameter Filter (Default bulan ini)
$bulan = isset($_GET['bulan']) ? sanitize($_GET['bulan']) : date('m');
$tahun = sanitize($_GET['tahun'] ?? date('Y'));
$status_pembayaran = sanitize($_GET['status_pembayaran'] ?? '');
$status_pengiriman = sanitize($_GET['status_pengiriman'] ?? '');
$search = sanitize($_GET['search'] ?? '');
$sort = sanitize($_GET['sort'] ?? 'datetime_desc');
$filter_query_params = [
    'bulan' => $bulan,
    'tahun' => $tahun,
    'status_pembayaran' => $status_pembayaran,
    'status_pengiriman' => $status_pengiriman,
    'search' => $search,
    'sort' => $sort
];
$current_filter_url = 'insert_admin.php?' . http_build_query($filter_query_params);

$where_clauses = ["1=1"];
$params = [];
$types = "";

if (!empty($bulan)) {
    $where_clauses[] = "MONTH(p.created_at) = ?";
    $params[] = (int)$bulan;
    $types .= "i";
}

if (!empty($tahun)) {
    $where_clauses[] = "YEAR(p.created_at) = ?";
    $params[] = (int)$tahun;
    $types .= "i";
}

if (!empty($status_pembayaran)) {
    $where_clauses[] = "p.status_pembayaran = ?";
    $params[] = $status_pembayaran;
    $types .= "s";
}

if (!empty($status_pengiriman)) {
    $where_clauses[] = "p.status_pengiriman = ?";
    $params[] = $status_pengiriman;
    $types .= "s";
}

if (!empty($search)) {
    $where_clauses[] = "(p.custom_id LIKE ? OR p.nama_pembeli LIKE ?)";
    $s_term = "%$search%";
    $params[] = $s_term;
    $params[] = $s_term;
    $types .= "ss";
}

$where_sql = implode(" AND ", $where_clauses);

// Dynamic Sorting (Default: Tanggal & Jam Terbaru)
$order_by = "p.created_at DESC, p.id DESC";
if ($sort === 'datetime_asc') {
    $order_by = "p.created_at ASC, p.id ASC";
} elseif ($sort === 'id_desc') {
    $order_by = "CAST(p.custom_id AS UNSIGNED) DESC, p.id DESC";
} elseif ($sort === 'id_asc') {
    $order_by = "CAST(p.custom_id AS UNSIGNED) ASC, p.id ASC";
} elseif ($sort === 'nominal_desc') {
    $order_by = "CAST(p.estimasi_dana AS DECIMAL(15,2)) DESC, p.created_at DESC";
} elseif ($sort === 'nominal_asc') {
    $order_by = "CAST(p.estimasi_dana AS DECIMAL(15,2)) ASC, p.created_at DESC";
} else {
    $sort = 'datetime_desc';
    $order_by = "p.created_at DESC, p.id DESC";
}

// Query Data Pengajuan (Urutkan tanggal & jam secara presisi)
$query = "SELECT p.*, u.username FROM pengajuan p JOIN users u ON p.user_id = u.id WHERE $where_sql ORDER BY $order_by";
$stmt = mysqli_prepare($conn, $query);

if (!empty($types)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// KPI Stats
$total_count = 0;
$total_nominal = 0.0;
$total_dibayar = 0.0;
$total_belum_dibayar = 0.0;

$rows_data = [];
while ($row = mysqli_fetch_assoc($result)) {
    $rows_data[] = $row;
    $total_count++;
    $nom = (float)$row['estimasi_dana'];
    $total_nominal += $nom;
    if ($row['status_pembayaran'] === 'dibayar') {
        $total_dibayar += $nom;
    } else {
        $total_belum_dibayar += $nom;
    }
}
?>

<!-- HEADER CURVED EXECUTIVE BANNER -->
<header class="page-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <span class="page-eyebrow d-none d-md-block"><i class="fa-solid fa-store"></i> Panel Operational &middot; Pengadaan Barang</span>
            <h1 class="page-title">Daftar Pembelian & Transaksi</h1>
            <p class="page-subtitle mb-0">Pantau, verifikasi, dan kelola seluruh pengajuan pembelian dari satu tempat.</p>
        </div>
        <div class="header-action">
            <a href="export_excel.php?<?= http_build_query(['bulan' => $bulan, 'tahun' => $tahun, 'status_pembayaran' => $status_pembayaran, 'status_pengiriman' => $status_pengiriman, 'search' => $search, 'sort' => $sort]); ?>" class="btn btn-outline-light btn-sm rounded-pill px-3 fw-semibold">
                <i class="fa-solid fa-file-excel me-1"></i> Ekspor Excel
            </a>
            <?php if ($is_admin): ?>
                <a href="tambah_pengajuan.php" class="btn-pengajuan-header">
                    <i class="fa-solid fa-plus-circle"></i> Buat Pembelian Baru
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- Summary KPI Cards -->
<div class="row g-2.5 stats-row mb-3">
    <div class="col-6 col-md-3">
        <div class="stats-card">
            <div class="card-body">
                <div class="stat-icon icon-wine"><i class="fa-solid fa-file-lines"></i></div>
                <div>
                    <div class="stat-value"><?= number_format($total_count); ?></div>
                    <div class="stat-label">Total Pengajuan</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stats-card">
            <div class="card-body">
                <div class="stat-icon icon-gold"><i class="fa-solid fa-sack-dollar"></i></div>
                <div>
                    <div class="stat-value"><?= formatRupiah($total_nominal); ?></div>
                    <div class="stat-label">Total Nilai Transaksi</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stats-card">
            <div class="card-body">
                <div class="stat-icon icon-success"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                    <div class="stat-value"><?= formatRupiah($total_dibayar); ?></div>
                    <div class="stat-label">Sudah Lunas Dibayar</div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stats-card">
            <div class="card-body">
                <div class="stat-icon icon-danger"><i class="fa-solid fa-hourglass-half"></i></div>
                <div>
                    <div class="stat-value"><?= formatRupiah($total_belum_dibayar); ?></div>
                    <div class="stat-label">Belum Dibayar</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Card Bar -->
<div class="filter-card">
    <form method="GET" action="insert_admin.php">
        <div class="filter-row">
            <div class="filter-group">
                <label>BULAN</label>
                <select name="bulan" class="form-select">
                    <option value="">Semua Bulan</option>
                    <?php 
                    $months = [
                        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
                        '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
                        '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'
                    ];
                    foreach ($months as $m_num => $m_name): 
                    ?>
                        <option value="<?= $m_num; ?>" <?= ($bulan === $m_num) ? 'selected' : ''; ?>><?= $m_name; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>TAHUN</label>
                <select name="tahun" class="form-select">
                    <?php for ($y = date('Y'); $y >= 2024; $y--): ?>
                        <option value="<?= $y; ?>" <?= ($tahun == $y) ? 'selected' : ''; ?>><?= $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="filter-group">
                <label>STATUS BAYAR</label>
                <select name="status_pembayaran" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="belum_dibayar" <?= ($status_pembayaran === 'belum_dibayar') ? 'selected' : ''; ?>>Belum Dibayar</option>
                    <option value="dibayar" <?= ($status_pembayaran === 'dibayar') ? 'selected' : ''; ?>>Dibayar (Lunas)</option>
                </select>
            </div>

            <div class="filter-group">
                <label>STATUS KIRIM</label>
                <select name="status_pengiriman" class="form-select">
                    <option value="">Semua Status</option>
                    <option value="belum_dikirim" <?= ($status_pengiriman === 'belum_dikirim') ? 'selected' : ''; ?>>Belum Dikirim</option>
                    <option value="sudah_dikirim" <?= ($status_pengiriman === 'sudah_dikirim') ? 'selected' : ''; ?>>Sudah Dikirim</option>
                </select>
            </div>

            <div class="filter-group">
                <label>URUTKAN</label>
                <select name="sort" class="form-select">
                    <option value="datetime_desc" <?= ($sort === 'datetime_desc') ? 'selected' : ''; ?>>🕒 Tanggal & Jam (Terbaru)</option>
                    <option value="datetime_asc" <?= ($sort === 'datetime_asc') ? 'selected' : ''; ?>>🕒 Tanggal & Jam (Terlama)</option>
                    <option value="id_desc" <?= ($sort === 'id_desc') ? 'selected' : ''; ?>># ID Nota (Tertinggi)</option>
                    <option value="id_asc" <?= ($sort === 'id_asc') ? 'selected' : ''; ?>># ID Nota (Terkecil)</option>
                    <option value="nominal_desc" <?= ($sort === 'nominal_desc') ? 'selected' : ''; ?>>💰 Nominal (Tertinggi)</option>
                    <option value="nominal_asc" <?= ($sort === 'nominal_asc') ? 'selected' : ''; ?>>💰 Nominal (Terendah)</option>
                </select>
            </div>

            <div class="filter-actions">
                <button type="submit" class="btn btn-filter-primary"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                <a href="insert_admin.php" class="btn btn-filter-reset"><i class="fa-solid fa-rotate-left me-1"></i> Reset</a>
            </div>
        </div>

        <div class="filter-search-row">
            <div class="filter-search-group">
                <label><i class="fa-solid fa-magnifying-glass me-1"></i> Cari ID Nota / Nama Pembeli</label>
                <div class="search-input-wrapper">
                    <i class="fa-solid fa-search search-icon"></i>
                    <input type="text" name="search" class="search-input-field" placeholder="Ketik nomor ID nota atau nama pembeli..." value="<?= e($search); ?>">
                    <?php if (!empty($search)): ?>
                        <a href="insert_admin.php" class="search-clear-btn">&times;</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Table Container -->
<div class="table-container">
    <div class="table-container-header">
        <h2><i class="fa-solid fa-list-check me-2 text-wine"></i> Daftar Transaksi Pengajuan</h2>
        <span class="badge-count"><?= $total_count; ?> Record Ditemukan</span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th width="75">ID Nota</th>
                    <th>Pelanggan & Tanggal Waktu</th>
                    <th>Admin</th>
                    <th class="text-nowrap">Estimasi Dana</th>
                    <th class="text-nowrap">Status Bayar</th>
                    <th class="text-nowrap">Status Kirim</th>
                    <th class="text-center text-nowrap" width="160">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($rows_data) > 0): ?>
                    <?php foreach ($rows_data as $p): 
                        $status_class = ($p['status_pembayaran'] === 'dibayar' && $p['status_pengiriman'] === 'sudah_dikirim') ? 'status-selesai' : (($p['status_pembayaran'] === 'dibayar') ? 'status-proses' : 'status-belum');
                    ?>
                        <tr id="row-nota-<?= $p['id']; ?>" class="<?= $status_class; ?>">
                            <td data-label="ID Nota" class="text-nowrap">
                                <span class="id-chip <?= $status_class; ?>">#<?= e($p['custom_id']); ?></span>
                            </td>
                            <td data-label="Pelanggan & Waktu">
                                <strong class="text-dark d-block fs-6"><?= e($p['nama_pembeli'] ?: '-'); ?></strong>
                                <small class="text-muted d-block mt-1" style="font-size: 0.75rem;">
                                    <i class="fa-regular fa-clock me-1 text-gold"></i> <?= format_tanggal_indo($p['created_at']); ?>
                                </small>
                            </td>
                            <td data-label="Admin Input" class="text-nowrap">
                                <span class="badge bg-light text-dark border px-2 py-1 rounded-pill fw-medium" style="font-size: 0.82rem;">
                                    <i class="fa-solid fa-user-circle me-1 text-wine"></i> <?= e($p['username'] ?: 'Admin'); ?>
                                </span>
                            </td>
                            <td data-label="Estimasi Dana" class="text-nowrap">
                                <strong class="text-success amount-cell fs-6"><?= formatRupiah($p['estimasi_dana']); ?></strong>
                            </td>
                            <td data-label="Status Bayar" class="text-end text-md-start">
                                <div class="status-badge-group">
                                    <?= render_status_pembayaran_badge($p['status_pembayaran'], $p['jumlah_dibayar'] ?? 0, $p['sisa_pembayaran'] ?? 0); ?>
                                    <span class="d-inline-block d-md-none">
                                        <?php if ($p['status_pengiriman'] === 'sudah_dikirim'): ?>
                                            <span class="badge-status info"><i class="fa-solid fa-truck-fast"></i> DIKIRIM</span>
                                        <?php else: ?>
                                            <span class="badge-status warning"><i class="fa-solid fa-box"></i> PENDING</span>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </td>
                            <td data-label="Status Kirim" class="d-none d-md-table-cell">
                                <?php if ($p['status_pengiriman'] === 'sudah_dikirim'): ?>
                                    <span class="badge-status info"><i class="fa-solid fa-truck-fast"></i> DIKIRIM</span>
                                <?php else: ?>
                                    <span class="badge-status warning"><i class="fa-solid fa-box"></i> PENDING</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Aksi" class="text-end">
                                <div class="action-btns justify-content-end">
                                    <!-- Detail Modal Trigger -->
                                    <button class="action-btn btn-detail" onclick="openDetailModal(<?= $p['id']; ?>, 'row-nota-<?= $p['id']; ?>')">
                                        <i class="fa-solid fa-eye"></i> Detail
                                    </button>

                                    <?php if ($is_admin): ?>
                                        <a href="edit_pengajuan.php?id=<?= $p['id']; ?>&return_row=row-nota-<?= $p['id']; ?>&return_url=<?= urlencode($current_filter_url); ?>" class="action-btn btn-edit">
                                            <i class="fa-solid fa-pen"></i> Edit
                                        </a>
                                        <a href="#" class="action-btn btn-delete" 
                                           onclick="confirmDeletePengajuan(event, 'proses_hapus_pengajuan.php?id=<?= $p['id']; ?>&csrf_token=<?= generate_csrf_token(); ?>&return_url=<?= urlencode($current_filter_url); ?>', '<?= e($p['custom_id']); ?>')">
                                            <i class="fa-solid fa-trash"></i> Hapus
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr class="no-data-row">
                        <td colspan="6" class="no-data-td">
                            <div class="no-data">
                                <i class="fa-solid fa-folder-open"></i>
                                <h5>Data Pengajuan Tidak Ditemukan</h5>
                                <p class="text-muted">Tidak ada transaksi pengajuan pembelian yang sesuai dengan kriteria filter.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Container Dynamic Detail -->
<div class="modal fade" id="modalDetailPengajuan" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-file-invoice-dollar me-2"></i> Rincian Detail Nota Pengajuan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailModalBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2">Memuat detail transaksi...</p>
                </div>
            </div>
            <div class="modal-footer bg-light border-top p-3 d-flex justify-content-between align-items-center">
                <span class="small text-muted"><i class="fa-solid fa-building me-1 text-gold"></i> Adam Jaya Enterprise System</span>
                <button type="button" class="btn btn-secondary-custom px-4 fw-bold" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i> Tutup Modal
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let lastActiveRowId = '';

function openDetailModal(id, rowId) {
    if (rowId) lastActiveRowId = rowId;
    const modalBody = document.getElementById('detailModalBody');
    modalBody.innerHTML = `
        <div class="text-center py-5">
            <div class="spinner-border text-wine" role="status"></div>
            <p class="text-muted mt-2">Memuat detail transaksi...</p>
        </div>
    `;
    const myModal = new bootstrap.Modal(document.getElementById('modalDetailPengajuan'));
    myModal.show();

    fetch(`get_pengajuan_detail.php?id=${id}`)
        .then(response => response.text())
        .then(html => {
            modalBody.innerHTML = html;
        })
        .catch(err => {
            modalBody.innerHTML = `<div class="alert alert-danger">Gagal memuat detail data pengajuan.</div>`;
        });
}

document.addEventListener("DOMContentLoaded", function() {
    const modalElem = document.getElementById('modalDetailPengajuan');
    if (modalElem) {
        modalElem.addEventListener('hidden.bs.modal', function () {
            if (window.needsPageReload) {
                const targetHash = lastActiveRowId ? '#' + lastActiveRowId : '';
                window.location.href = window.location.pathname + window.location.search + targetHash;
                window.location.reload();
            } else if (lastActiveRowId) {
                scrollToRow(lastActiveRowId);
            }
        });
    }

    const urlParams = new URLSearchParams(window.location.search);
    const scrollTo = urlParams.get('scroll_to') || window.location.hash.replace('#', '');
    if (scrollTo) {
        setTimeout(() => scrollToRow(scrollTo), 250);
    }
});

function scrollToRow(rowId) {
    const target = document.getElementById(rowId);
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        target.classList.add('row-highlight-pulse');
        setTimeout(() => target.classList.remove('row-highlight-pulse'), 2500);
    }
}

function selectPaymentMethod(metode) {
    const mainBox = document.getElementById('payment_main_buttons');
    const subBox = document.getElementById('payment_sub_box');
    const titleElem = document.getElementById('selected_method_title');
    const hiddenMetode = document.getElementById('modal_payment_metode');

    if (mainBox) mainBox.classList.add('d-none');
    if (subBox) subBox.classList.remove('d-none');
    if (hiddenMetode) hiddenMetode.value = metode;

    if (titleElem) {
        titleElem.innerText = 'Metode Dipilih: ' + (metode === 'transfer' ? 'Transfer Bank' : 'Cash / Tunai');
    }
    showProofUploadForm();
}

function resetPaymentSelection() {
    const mainBox = document.getElementById('payment_main_buttons');
    const subBox = document.getElementById('payment_sub_box');
    const proofForm = document.getElementById('formModalUploadProof');

    if (mainBox) mainBox.classList.remove('d-none');
    if (subBox) subBox.classList.add('d-none');
    if (proofForm) proofForm.classList.add('d-none');
}

function showProofUploadForm() {
    const proofForm = document.getElementById('formModalUploadProof');
    if (proofForm) proofForm.classList.remove('d-none');
}

function processPaymentWithoutProof(id, csrfToken) {
    const metodeInput = document.getElementById('modal_payment_metode');
    const metode = metodeInput ? metodeInput.value : 'tunai';

    Swal.fire({
        title: 'Konfirmasi Pembayaran',
        text: `Apakah Anda yakin ingin memproses pembayaran (${metode.toUpperCase()}) TANPA bukti dokumen?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Ya, Proses Lunas',
        cancelButtonText: 'Batal'
    }).then((res) => {
        if (res.isConfirmed) {
            fetch('bayar_metode.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}&metode=${metode}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('Berhasil!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Gagal!', data.message, 'error');
                }
            });
        }
    });
}

function submitPaymentWithProof(event, id) {
    event.preventDefault();
    const form = document.getElementById('formModalUploadProof');
    const formData = new FormData(form);

    fetch('proses_modal_action.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire('Berhasil!', data.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Gagal!', data.message, 'error');
        }
    });
}

function processDirectAction(action, id, csrfToken) {
    let confirmMsg = 'Apakah Anda yakin?';
    if (action === 'kirim') confirmMsg = 'Tandai transaksi ini SUDAH DIKIRIM?';
    if (action === 'batal_bayar') confirmMsg = 'Batalkan status pembayaran (set Belum Dibayar)?';
    if (action === 'batal_kirim') confirmMsg = 'Batalkan status pengiriman (set Pending)?';

    Swal.fire({
        title: 'Konfirmasi Aksi',
        text: confirmMsg,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Lanjutkan',
        cancelButtonText: 'Batal'
    }).then((res) => {
        if (res.isConfirmed) {
            let endpoint = 'proses_modal_action.php';
            fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `action=${action}&id=${id}&csrf_token=${csrfToken}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire('Berhasil!', data.message, 'success').then(() => location.reload());
                } else {
                    Swal.fire('Gagal!', data.message, 'error');
                }
            });
        }
    });
}

function submitKwitansiModal(event, id) {
    event.preventDefault();
    const form = document.getElementById('formKwitansiModal');
    const formData = new FormData(form);

    fetch('proses_modal_action.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            Swal.fire('Berhasil!', data.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Gagal!', data.message, 'error');
        }
    });
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>