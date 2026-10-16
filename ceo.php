<?php
require_once __DIR__ . '/includes/header.php';
require_login(); // Admin and CEO can view

// ────────────────────────────────────────────────
// Parameter Filter Interaktif Eksekutif
// ────────────────────────────────────────────────
$is_ajax = (isset($_GET['ajax']) && $_GET['ajax'] == '1');

$periode = sanitize($_GET['periode'] ?? 'bulan_ini');
$status_filter = sanitize($_GET['status'] ?? 'semua');

if (!in_array($periode, ['bulan_ini', 'tahun_ini', 'semua_waktu'])) {
    $periode = 'bulan_ini';
}
if (!in_array($status_filter, ['semua', 'lunas', 'piutang'])) {
    $status_filter = 'semua';
}

// ────────────────────────────────────────────────
// 1. Filter Query Pengajuan (Omzet, Pemasukan Riil, Piutang)
// ────────────────────────────────────────────────
$where_p = ["1=1"];

if ($periode === 'bulan_ini') {
    $where_p[] = "MONTH(p.created_at) = MONTH(CURRENT_DATE()) AND YEAR(p.created_at) = YEAR(CURRENT_DATE())";
} elseif ($periode === 'tahun_ini') {
    $where_p[] = "YEAR(p.created_at) = YEAR(CURRENT_DATE())";
}

if ($status_filter === 'lunas') {
    $where_p[] = "p.status_pembayaran = 'dibayar'";
} elseif ($status_filter === 'piutang') {
    $where_p[] = "(p.status_pembayaran IN ('belum_dibayar', 'cicilan') OR p.sisa_pembayaran > 0)";
}
$where_p_sql = implode(" AND ", $where_p);

$sql_kpi_p = "SELECT 
    COUNT(p.id) as total_trx,
    IFNULL(SUM(CAST(p.estimasi_dana AS DECIMAL(15,2))), 0) as total_omzet_bruto,
    IFNULL(SUM(CAST(p.jumlah_dibayar AS DECIMAL(15,2))), 0) as total_kas_diterima,
    IFNULL(SUM(CAST(p.sisa_pembayaran AS DECIMAL(15,2))), 0) as total_piutang_berjalan,
    COUNT(DISTINCT p.nama_pembeli) as total_pembeli_periode
FROM pengajuan p 
WHERE $where_p_sql";

$res_kpi_p = mysqli_query($conn, $sql_kpi_p);
$row_kpi_p = mysqli_fetch_assoc($res_kpi_p) ?: [
    'total_trx' => 0,
    'total_omzet_bruto' => 0.0,
    'total_kas_diterima' => 0.0,
    'total_piutang_berjalan' => 0.0,
    'total_pembeli_periode' => 0
];

// ────────────────────────────────────────────────
// 2. Filter Query Pengeluaran Kas (Tabel pengeluaran_header)
// ────────────────────────────────────────────────
$where_exp = ["1=1"];
if ($periode === 'bulan_ini') {
    $where_exp[] = "MONTH(tanggal) = MONTH(CURRENT_DATE()) AND YEAR(tanggal) = YEAR(CURRENT_DATE())";
} elseif ($periode === 'tahun_ini') {
    $where_exp[] = "YEAR(tanggal) = YEAR(CURRENT_DATE())";
}
$where_exp_sql = implode(" AND ", $where_exp);

$sql_kpi_exp = "SELECT 
    IFNULL(SUM(total_pengeluaran), 0) as total_pengeluaran_kas,
    COUNT(id) as total_trx_exp
FROM pengeluaran_header 
WHERE $where_exp_sql";

$res_kpi_exp = mysqli_query($conn, $sql_kpi_exp);
$row_kpi_exp = mysqli_fetch_assoc($res_kpi_exp) ?: [
    'total_pengeluaran_kas' => 0.0,
    'total_trx_exp' => 0
];

// Metrik Keuangan Eksekutif Utama
$total_omzet_bruto = (float)$row_kpi_p['total_omzet_bruto'];
$pemasukan_riil = (float)$row_kpi_p['total_kas_diterima'];
$total_piutang = (float)$row_kpi_p['total_piutang_berjalan'];
$pengeluaran_kas = (float)$row_kpi_exp['total_pengeluaran_kas'];
$estimasi_laba_bersih = $pemasukan_riil - $pengeluaran_kas;
$total_trx = (int)$row_kpi_p['total_trx'];
$total_trx_exp = (int)$row_kpi_exp['total_trx_exp'];

// ────────────────────────────────────────────────
// Respon AJAX jika diminta oleh filter frontend
// ────────────────────────────────────────────────
if ($is_ajax) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'periode' => $periode,
        'status' => $status_filter,
        'total_omzet_bruto' => $total_omzet_bruto,
        'total_omzet_bruto_fmt' => formatRupiah($total_omzet_bruto),
        'pemasukan_riil' => $pemasukan_riil,
        'pemasukan_riil_fmt' => formatRupiah($pemasukan_riil),
        'total_piutang' => $total_piutang,
        'total_piutang_fmt' => formatRupiah($total_piutang),
        'pengeluaran_kas' => $pengeluaran_kas,
        'pengeluaran_kas_fmt' => formatRupiah($pengeluaran_kas),
        'estimasi_laba_bersih' => $estimasi_laba_bersih,
        'estimasi_laba_bersih_fmt' => formatRupiah($estimasi_laba_bersih),
        'total_trx' => $total_trx,
        'total_trx_exp' => $total_trx_exp,
        'is_surplus' => ($estimasi_laba_bersih >= 0)
    ]);
    exit;
}

// ────────────────────────────────────────────────
// Data Persediaan Stok & Klien
// ────────────────────────────────────────────────
$res_stok = mysqli_query($conn, "SELECT COUNT(id) as total_varian, IFNULL(SUM(stok), 0) as total_unit FROM jenis_barang");
$row_stok = mysqli_fetch_assoc($res_stok) ?: ['total_varian' => 0, 'total_unit' => 0];

$res_pembeli = mysqli_query($conn, "SELECT COUNT(DISTINCT nama_pembeli) as total_pembeli FROM pengajuan");
$row_pembeli = mysqli_fetch_assoc($res_pembeli) ?: ['total_pembeli' => 0];

// ────────────────────────────────────────────────
// Data Grafik Barang Paling Banyak Terjual (Top 6)
// ────────────────────────────────────────────────
$query_top_sold = "SELECT 
    d.nama_barang,
    d.nama_jenis,
    SUM(d.jumlah) as total_qty 
FROM pengajuan_detail d 
JOIN pengajuan p ON d.pengajuan_id = p.id 
WHERE $where_p_sql 
GROUP BY d.nama_barang, d.nama_jenis 
ORDER BY total_qty DESC LIMIT 6";

$res_top_sold = mysqli_query($conn, $query_top_sold);
$sold_labels = [];
$sold_values = [];
if ($res_top_sold && mysqli_num_rows($res_top_sold) > 0) {
    while ($s = mysqli_fetch_assoc($res_top_sold)) {
        $name = trim($s['nama_barang']);
        if (!empty($s['nama_jenis']) && $s['nama_jenis'] !== '-') {
            $name .= ' (' . trim($s['nama_jenis']) . ')';
        }
        $name = trim(rtrim($name, ')'));
        $sold_labels[] = $name;
        $sold_values[] = (float)$s['total_qty'];
    }
}
if (empty($sold_labels)) {
    $sold_labels = ['Belum Ada Transaksi'];
    $sold_values = [0];
}

// ────────────────────────────────────────────────
// Data Grafik Pengadaan Bulanan
// ────────────────────────────────────────────────
$query_monthly = "SELECT DATE_FORMAT(p.created_at, '%b %Y') as m_label, SUM(CAST(p.estimasi_dana AS DECIMAL(15,2))) as total 
                  FROM pengajuan p 
                  GROUP BY YEAR(p.created_at), MONTH(p.created_at) 
                  ORDER BY p.created_at ASC LIMIT 12";
$res_monthly = mysqli_query($conn, $query_monthly);
$month_labels = [];
$month_values = [];
while ($m = mysqli_fetch_assoc($res_monthly)) {
    $month_labels[] = $m['m_label'];
    $month_values[] = (float)$m['total'];
}
if (empty($month_labels)) {
    $month_labels = [date('M Y')];
    $month_values = [0];
}

// ────────────────────────────────────────────────
// Audit Persediaan Stok Rendah
// ────────────────────────────────────────────────
$query_audit = "SELECT j.*, b.nama_barang 
                FROM jenis_barang j 
                JOIN stok_barang b ON j.barang_id = b.id 
                ORDER BY j.stok ASC LIMIT 10";
$res_audit = mysqli_query($conn, $query_audit);
$audit_items = [];
if ($res_audit) {
    while ($row = mysqli_fetch_assoc($res_audit)) {
        $audit_items[] = $row;
    }
}

// Label Periode untuk UI
$periode_text_map = [
    'bulan_ini' => 'Bulan Ini (' . date('F Y') . ')',
    'tahun_ini' => 'Tahun Ini (' . date('Y') . ')',
    'semua_waktu' => 'Sepanjang Waktu (All-Time)'
];
$status_text_map = [
    'semua' => 'Semua Transaksi',
    'lunas' => 'Hanya Lunas (100%)',
    'piutang' => 'Hanya Piutang / Cicilan'
];
?>

<!-- HEADER CURVED EXECUTIVE BANNER -->
<header class="page-header">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <span class="page-eyebrow"><i class="fa-solid fa-crown text-gold"></i> Executive Suite &middot; Adam Jaya Enterprise</span>
            <h1 class="page-title">Executive Management Dashboard</h1>
            <p class="page-subtitle">Ringkasan performa bisnis, audit persediaan, dan analisis tren pengadaan perusahaan.</p>
        </div>
        <div class="header-action">
            <?php if (is_ceo()): ?>
                <span class="badge bg-warning text-dark px-3 py-2 fw-bold fs-6 shadow-sm">
                    <i class="fa-solid fa-eye me-1"></i> CEO READ-ONLY MONITORING MODE
                </span>
            <?php else: ?>
                <a href="insert_admin.php" class="btn-pengajuan-header">
                    <i class="fa-solid fa-list-check"></i> Kelola Transaksi
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>

<style>
/* Executive Compact Layout Styles */
.filter-bar-compact {
    background: #ffffff;
    border: 1px solid rgba(122, 30, 51, 0.12) !important;
    border-radius: 12px;
}
.exec-kpi-grid {
    display: grid;
    grid-template-columns: repeat(5, minmax(0, 1fr));
    gap: 0.75rem;
}
@media (max-width: 1199.98px) {
    .exec-kpi-grid {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }
}
@media (max-width: 767.98px) {
    .exec-kpi-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}
@media (max-width: 480px) {
    .exec-kpi-grid {
        grid-template-columns: 1fr;
    }
}

.kpi-card-compact {
    background: #ffffff;
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 12px;
    padding: 0.8rem 0.95rem;
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.04);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 114px;
    transition: transform 0.18s ease, box-shadow 0.18s ease;
    position: relative;
    overflow: hidden;
}
.kpi-card-compact:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(30, 24, 20, 0.08);
}
.kpi-card-compact::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
}
.kpi-border-wine::before { background: #7A1E33; }
.kpi-border-success::before { background: #1E8A5B; }
.kpi-border-warning::before { background: #C9973E; }
.kpi-border-danger::before { background: #DC2626; }

.kpi-card-compact.kpi-hero {
    background: linear-gradient(135deg, #1A0308 0%, #3d0a15 60%, #58101F 100%);
    border: 1.5px solid #C9A84C !important;
    color: #ffffff;
}
.kpi-card-compact.kpi-hero::before {
    background: #ffd700;
}

.kpi-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.35rem;
}
.kpi-tag {
    font-size: 0.65rem;
    font-weight: 700;
    letter-spacing: 0.3px;
    padding: 2px 7px;
    border-radius: 5px;
    text-transform: uppercase;
}
.kpi-tag-wine { background: rgba(122, 30, 51, 0.08); color: #7A1E33; }
.kpi-tag-success { background: rgba(30, 138, 91, 0.09); color: #1E8A5B; }
.kpi-tag-warning { background: rgba(201, 151, 62, 0.12); color: #B45309; }
.kpi-tag-danger { background: rgba(220, 38, 38, 0.08); color: #DC2626; }
.kpi-tag-hero { background: rgba(201, 168, 76, 0.22); color: #ffd700; border: 1px solid rgba(201, 168, 76, 0.4); }

.kpi-icon {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.8rem;
    flex-shrink: 0;
}
.kpi-icon-wine { background: rgba(122, 30, 51, 0.08); color: #7A1E33; }
.kpi-icon-success { background: rgba(30, 138, 91, 0.09); color: #1E8A5B; }
.kpi-icon-warning { background: rgba(201, 151, 62, 0.1); color: #C9973E; }
.kpi-icon-danger { background: rgba(220, 38, 38, 0.08); color: #DC2626; }

.kpi-val {
    font-size: 1.15rem;
    font-weight: 800;
    font-family: 'Plus Jakarta Sans', sans-serif;
    line-height: 1.25;
    letter-spacing: -0.3px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    margin-bottom: 2px;
}
.kpi-val.val-dark { color: #1E2430; }
.kpi-val.val-success { color: #15803D; }
.kpi-val.val-warning { color: #B45309; }
.kpi-val.val-danger { color: #DC2626; }
.kpi-val.val-hero { color: #FFD700 !important; text-shadow: 0 1px 3px rgba(0,0,0,0.4); }
.kpi-title {
    font-size: 0.74rem;
    font-weight: 600;
    color: #4b5563;
    line-height: 1.2;
}
.kpi-sub {
    font-size: 0.68rem;
    color: #8c959f;
    line-height: 1.2;
    margin-top: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>

<!-- ========================================================
     BAR FILTER INTERAKTIF REALTIME (COMPACT & SLEEK)
     ======================================================== -->
<div class="card border-0 shadow-sm mb-3 rounded-3 filter-bar-compact">
    <div class="card-body py-2 px-3">
        <form id="filterExecutiveForm" method="GET" action="ceo.php" class="d-flex align-items-center justify-content-between flex-wrap gap-2.5">
            <div class="d-flex align-items-center gap-2">
                <div class="d-inline-flex align-items-center justify-content-center rounded-2" style="width: 32px; height: 32px; background: rgba(122, 30, 51, 0.09); color: #7A1E33;">
                    <i class="fa-solid fa-sliders fs-6"></i>
                </div>
                <div>
                    <span class="fw-bold text-wine d-block" style="font-size: 0.85rem; line-height: 1.15;">Filter Dashboard</span>
                    <small class="text-muted" style="font-size: 0.7rem;">Kalkulasi Realtime KPI</small>
                </div>
            </div>

            <div class="d-flex align-items-center flex-wrap gap-2.5 ms-auto">
                <div class="d-flex align-items-center gap-1.5">
                    <label for="filterPeriode" class="small fw-semibold text-muted text-nowrap mb-0" style="font-size: 0.75rem;">
                        <i class="fa-regular fa-calendar text-wine me-1"></i> Periode:
                    </label>
                    <select name="periode" id="filterPeriode" class="form-select form-select-sm fw-semibold border-secondary-subtle py-1" style="min-width: 165px; font-size: 0.78rem;" onchange="triggerFilterChange()">
                        <option value="bulan_ini" <?= $periode === 'bulan_ini' ? 'selected' : ''; ?>>Bulan Ini (<?= date('M Y'); ?>)</option>
                        <option value="tahun_ini" <?= $periode === 'tahun_ini' ? 'selected' : ''; ?>>Tahun Ini (<?= date('Y'); ?>)</option>
                        <option value="semua_waktu" <?= $periode === 'semua_waktu' ? 'selected' : ''; ?>>Sepanjang Waktu</option>
                    </select>
                </div>

                <div class="d-flex align-items-center gap-1.5">
                    <label for="filterStatus" class="small fw-semibold text-muted text-nowrap mb-0" style="font-size: 0.75rem;">
                        <i class="fa-solid fa-receipt text-wine me-1"></i> Status:
                    </label>
                    <select name="status" id="filterStatus" class="form-select form-select-sm fw-semibold border-secondary-subtle py-1" style="min-width: 175px; font-size: 0.78rem;" onchange="triggerFilterChange()">
                        <option value="semua" <?= $status_filter === 'semua' ? 'selected' : ''; ?>>Semua Transaksi</option>
                        <option value="lunas" <?= $status_filter === 'lunas' ? 'selected' : ''; ?>>Hanya Lunas (100%)</option>
                        <option value="piutang" <?= $status_filter === 'piutang' ? 'selected' : ''; ?>>Hanya Piutang / Cicilan</option>
                    </select>
                </div>

                <button type="button" class="btn btn-sm btn-light border rounded-pill px-2.5 py-1 text-muted fw-semibold" style="font-size: 0.75rem;" onclick="resetFilter()" title="Kembalikan ke Default">
                    <i class="fa-solid fa-arrow-rotate-left me-1"></i> Reset
                </button>
                <div id="filterSpinner" class="spinner-border spinner-border-sm text-wine d-none" role="status" aria-hidden="true"></div>
            </div>
        </form>
    </div>
</div>

<!-- ========================================================
     5 KARTU RINGKASAN KPI KEUANGAN (KOMPAK & RAPI)
     ======================================================== -->
<div class="exec-kpi-grid mb-3">
    <!-- 1. Total Omzet / Transaksi (Bruto) -->
    <div class="kpi-card-compact kpi-border-wine dash-reveal" style="--reveal-delay: 0.05s">
        <div class="kpi-header">
            <span class="kpi-tag kpi-tag-wine">OMZET BRUTO</span>
            <div class="kpi-icon kpi-icon-wine"><i class="fa-solid fa-file-invoice-dollar"></i></div>
        </div>
        <div>
            <div class="kpi-val val-dark" id="kpi_omzet_bruto"><?= formatRupiah($total_omzet_bruto); ?></div>
            <div class="kpi-title">Total Omzet / Transaksi</div>
            <div class="kpi-sub" id="kpi_omzet_sub"><?= number_format($total_trx); ?> Transaksi Pembelian</div>
        </div>
    </div>

    <!-- 2. Pemasukan Riil (Kas Diterima) -->
    <div class="kpi-card-compact kpi-border-success dash-reveal" style="--reveal-delay: 0.10s">
        <div class="kpi-header">
            <span class="kpi-tag kpi-tag-success">KAS DITERIMA</span>
            <div class="kpi-icon kpi-icon-success"><i class="fa-solid fa-hand-holding-dollar"></i></div>
        </div>
        <div>
            <div class="kpi-val val-success" id="kpi_pemasukan_riil"><?= formatRupiah($pemasukan_riil); ?></div>
            <div class="kpi-title">Pemasukan Riil Kas</div>
            <div class="kpi-sub">Kas Nyata Masuk (Lunas & DP)</div>
        </div>
    </div>

    <!-- 3. Total Piutang Berjalan -->
    <div class="kpi-card-compact kpi-border-warning dash-reveal" style="--reveal-delay: 0.15s">
        <div class="kpi-header">
            <span class="kpi-tag kpi-tag-warning">PIUTANG BERJALAN</span>
            <div class="kpi-icon kpi-icon-warning"><i class="fa-solid fa-clock-rotate-left"></i></div>
        </div>
        <div>
            <div class="kpi-val val-warning" id="kpi_piutang_berjalan"><?= formatRupiah($total_piutang); ?></div>
            <div class="kpi-title">Total Piutang Berjalan</div>
            <div class="kpi-sub">Sisa Tagihan di Pelanggan</div>
        </div>
    </div>

    <!-- 4. Pengeluaran Kas Operasional -->
    <div class="kpi-card-compact kpi-border-danger dash-reveal" style="--reveal-delay: 0.20s">
        <div class="kpi-header">
            <span class="kpi-tag kpi-tag-danger">KAS OPERASIONAL</span>
            <div class="kpi-icon kpi-icon-danger"><i class="fa-solid fa-arrow-trend-down"></i></div>
        </div>
        <div>
            <div class="kpi-val val-danger" id="kpi_pengeluaran_kas"><?= formatRupiah($pengeluaran_kas); ?></div>
            <div class="kpi-title">Pengeluaran Kas</div>
            <div class="kpi-sub" id="kpi_pengeluaran_sub"><?= number_format($total_trx_exp); ?> Catatan Pengeluaran</div>
        </div>
    </div>

    <!-- 5. Estimasi Laba / Untung Bersih (Hero Card) -->
    <div class="kpi-card-compact kpi-hero dash-reveal" style="--reveal-delay: 0.25s">
        <div class="kpi-header">
            <span class="kpi-tag kpi-tag-hero"><i class="fa-solid fa-crown me-1 text-gold"></i> LABA BERSIH</span>
            <div id="kpi_laba_badge">
                <?php if ($estimasi_laba_bersih >= 0): ?>
                    <span class="badge bg-success px-2 py-0.5 text-white fw-bold shadow-sm" style="font-size: 0.65rem;">
                        <i class="fa-solid fa-circle-check me-0.5"></i> Surplus
                    </span>
                <?php else: ?>
                    <span class="badge bg-danger px-2 py-0.5 text-white fw-bold shadow-sm" style="font-size: 0.65rem;">
                        <i class="fa-solid fa-circle-exclamation me-0.5"></i> Defisit
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <div class="kpi-val val-hero" id="kpi_laba_bersih"><?= formatRupiah($estimasi_laba_bersih); ?></div>
            <div class="kpi-title" style="color: #f1ebd8;">Estimasi Laba Bersih</div>
            <div class="kpi-sub" style="color: rgba(255,255,255,0.72);">Kas Masuk &minus; Biaya Kas</div>
        </div>
    </div>
</div>

<!-- Quick Strip Persediaan & Pelanggan Aktif -->
<div class="p-2 px-3 mb-4 rounded-3 d-flex justify-content-between align-items-center flex-wrap gap-2 shadow-sm border" style="background: #ffffff; border-color: rgba(0,0,0,0.06) !important; font-size: 0.78rem;">
    <div class="d-flex align-items-center gap-3 flex-wrap">
        <div class="d-flex align-items-center gap-1.5">
            <i class="fa-solid fa-boxes-stacked text-wine"></i>
            <span class="text-muted">Gudang:</span>
            <strong class="text-dark"><?= format_stok($row_stok['total_unit']); ?> Unit (<?= $row_stok['total_varian']; ?> Varian)</strong>
        </div>
        <div class="vr d-none d-sm-block" style="height: 16px;"></div>
        <div class="d-flex align-items-center gap-1.5">
            <i class="fa-solid fa-users text-wine"></i>
            <span class="text-muted">Pelanggan:</span>
            <strong class="text-dark"><?= number_format($row_pembeli['total_pembeli']); ?> Klien Terdaftar</strong>
        </div>
    </div>
    <div>
        <span class="badge bg-light text-secondary border px-2 py-1 fw-semibold" style="font-size: 0.72rem;">
            <i class="fa-solid fa-filter me-1 text-wine"></i>
            <?= $periode_text_map[$periode] ?? 'Bulan Ini'; ?> &bull; <?= $status_text_map[$status_filter] ?? 'Semua'; ?>
        </span>
    </div>
</div>

<!-- ========================================================
     SECTION ANALISIS EXEC: CHART PENGADAAN & BARANG TERJUAL
     ======================================================== -->
<div class="row g-4 mb-4">
    <div class="col-md-6 dash-reveal" style="--reveal-delay: 0.40s">
        <div class="glass-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-chart-column text-wine me-2"></i> Tren Nilai Pengadaan</h5>
                <span class="badge bg-gold-soft text-wine small fw-bold">Grafik Pertumbuhan</span>
            </div>
            <div style="position: relative; height: 260px;">
                <canvas id="chartPengadaan"></canvas>
            </div>
        </div>
    </div>

    <div class="col-md-6 dash-reveal" style="--reveal-delay: 0.48s">
        <div class="glass-card p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-fire text-wine me-2"></i> Barang Paling Banyak Terjual</h5>
                <span class="badge bg-gold-soft text-wine small fw-bold">Top Selling Items</span>
            </div>
            <div style="position: relative; height: 260px;">
                <canvas id="chartTerjual"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- ========================================================
     AUDIT STOK DAFTAR PERSEDIAAN BARANG
     ======================================================== -->
<div class="table-container mb-4">
    <div class="table-container-header d-flex justify-content-between align-items-center flex-wrap gap-2 p-3 border-bottom">
        <h2 class="mb-0"><i class="fa-solid fa-shield-halved me-2 text-wine"></i> Audit Persediaan Stok Varian Terendah</h2>
        <a href="stok_barang.php" class="btn btn-sm btn-outline-indigo px-3 rounded-pill fw-bold" style="font-size: 0.78rem;">Lihat Seluruh Stok &rarr;</a>
    </div>

    <!-- DESKTOP VIEW (Table Format) -->
    <div class="table-responsive d-none d-md-block p-2">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Barang Induk & Varian</th>
                    <th>Satuan</th>
                    <th>Harga Satuan</th>
                    <th>Stok Tersedia</th>
                    <th>Status Stok</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($audit_items as $audit): 
                    $stok_val = (float)$audit['stok'];
                ?>
                    <tr>
                        <td>
                            <strong class="text-dark d-block"><?= e($audit['nama_barang']); ?></strong>
                            <small class="text-muted"><?= e($audit['nama_jenis']); ?></small>
                        </td>
                        <td><span class="badge bg-light text-dark border"><?= e($audit['satuan']); ?></span></td>
                        <td><strong class="text-success"><?= formatRupiah($audit['harga']); ?></strong></td>
                        <td>
                            <strong class="fs-6 <?= $stok_val <= 5 ? 'text-danger' : 'text-dark'; ?>">
                                <?= format_stok($stok_val); ?> <?= e($audit['satuan']); ?>
                            </strong>
                        </td>
                        <td>
                            <?php if ($stok_val <= 0): ?>
                                <span class="badge-status danger"><i class="fa-solid fa-triangle-exclamation"></i> STOK HABIS</span>
                            <?php elseif ($stok_val <= 5): ?>
                                <span class="badge-status warning"><i class="fa-solid fa-circle-exclamation"></i> MENIPIS</span>
                            <?php else: ?>
                                <span class="badge-status success"><i class="fa-solid fa-check"></i> AMAN</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- MOBILE VIEW (2 Columns Grid) -->
    <div class="d-block d-md-none p-3">
        <div class="row g-3">
            <?php foreach ($audit_items as $audit): 
                $stok_val = (float)$audit['stok'];
            ?>
                <div class="col-6">
                    <div class="card h-100 border border-light-subtle shadow-sm rounded-3 p-3 bg-white position-relative d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge bg-light text-secondary border px-2 py-1 rounded-2" style="font-size: 0.68rem; font-weight: 600;">
                                <?= e($audit['satuan']); ?>
                            </span>
                            <?php if ($stok_val <= 0): ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-bold" style="font-size: 0.64rem; padding: 3px 7px;">HABIS</span>
                            <?php elseif ($stok_val <= 5): ?>
                                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle fw-bold" style="font-size: 0.64rem; padding: 3px 7px;">MENIPIS</span>
                            <?php else: ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle fw-bold" style="font-size: 0.64rem; padding: 3px 7px;">AMAN</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-2">
                            <h6 class="fw-bold text-dark mb-1 text-truncate" style="font-size: 0.86rem; line-height: 1.25;" title="<?= e($audit['nama_barang']); ?>">
                                <?= e($audit['nama_barang']); ?>
                            </h6>
                            <small class="text-muted d-block text-truncate" style="font-size: 0.72rem; line-height: 1.2;" title="<?= e($audit['nama_jenis']); ?>">
                                <?= e($audit['nama_jenis']); ?>
                            </small>
                        </div>

                        <div class="pt-2 border-top d-flex align-items-center justify-content-between mt-auto">
                            <div class="small fw-bold text-success" style="font-size: 0.76rem;">
                                <?= formatRupiah($audit['harga']); ?>
                            </div>
                            <div class="small fw-bold <?= $stok_val <= 5 ? 'text-danger' : 'text-dark'; ?>" style="font-size: 0.8rem;">
                                <?= format_stok($stok_val); ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Chart.js CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// ────────────────────────────────────────────────
// Logika Interaktif Filter Dashboard Eksekutif
// ────────────────────────────────────────────────
function triggerFilterChange() {
    const periode = document.getElementById('filterPeriode').value;
    const status = document.getElementById('filterStatus').value;
    const spinner = document.getElementById('filterSpinner');
    if (spinner) spinner.classList.remove('d-none');

    // Update query URL di address bar tanpa reload paksa
    const url = new URL(window.location.href);
    url.searchParams.set('periode', periode);
    url.searchParams.set('status', status);
    window.history.replaceState({ periode, status }, '', url.toString());

    // Ambil data terbaru via AJAX
    fetch(`ceo.php?ajax=1&periode=${encodeURIComponent(periode)}&status=${encodeURIComponent(status)}`)
        .then(res => {
            if (!res.ok) throw new Error('Network error');
            return res.json();
        })
        .then(data => {
            if (spinner) spinner.classList.add('d-none');
            if (data && data.success) {
                // Perbarui nilai kartu dengan efek transisi mulus
                animateCardValue('kpi_omzet_bruto', data.total_omzet_bruto_fmt);
                animateCardValue('kpi_pemasukan_riil', data.pemasukan_riil_fmt);
                animateCardValue('kpi_piutang_berjalan', data.total_piutang_fmt);
                animateCardValue('kpi_pengeluaran_kas', data.pengeluaran_kas_fmt);
                animateCardValue('kpi_laba_bersih', data.estimasi_laba_bersih_fmt);

                // Update sub label transaksi
                const omzetSub = document.getElementById('kpi_omzet_sub');
                if (omzetSub) {
                    omzetSub.innerText = `${Number(data.total_trx).toLocaleString('id-ID')} Transaksi Pembelian`;
                }
                const expSub = document.getElementById('kpi_pengeluaran_sub');
                if (expSub) {
                    expSub.innerText = `${Number(data.total_trx_exp).toLocaleString('id-ID')} Catatan Pengeluaran`;
                }

                // Update badge surplus/defisit
                const badgeEl = document.getElementById('kpi_laba_badge');
                if (badgeEl) {
                    if (data.is_surplus) {
                        badgeEl.innerHTML = `<span class="badge bg-success px-2 py-0.5 text-white fw-bold shadow-sm" style="font-size: 0.65rem;"><i class="fa-solid fa-circle-check me-0.5"></i> Surplus</span>`;
                    } else {
                        badgeEl.innerHTML = `<span class="badge bg-danger px-2 py-0.5 text-white fw-bold shadow-sm" style="font-size: 0.65rem;"><i class="fa-solid fa-circle-exclamation me-0.5"></i> Defisit</span>`;
                    }
                }
            }
        })
        .catch(err => {
            if (spinner) spinner.classList.add('d-none');
            // Fallback submit form biasa jika AJAX terkendala
            document.getElementById('filterExecutiveForm').submit();
        });
}

function animateCardValue(id, newText) {
    const el = document.getElementById(id);
    if (!el) return;
    el.style.transition = 'opacity 0.18s ease, transform 0.18s ease';
    el.style.opacity = '0.25';
    el.style.transform = 'scale(0.96)';
    setTimeout(() => {
        el.innerText = newText;
        el.style.opacity = '1';
        el.style.transform = 'scale(1)';
    }, 150);
}

function resetFilter() {
    document.getElementById('filterPeriode').value = 'bulan_ini';
    document.getElementById('filterStatus').value = 'semua';
    triggerFilterChange();
}

document.addEventListener("DOMContentLoaded", function() {

    // ── Staggered reveal (cards & chart containers) ──
    document.querySelectorAll('.dash-reveal').forEach(el => {
        el.style.animationDelay = el.style.getPropertyValue('--reveal-delay') || '0s';
        el.classList.add('dash-reveal-active');
    });

    // ────────────────────────────────────────────────
    // Chart 1: Doughnut — Barang Paling Banyak Terjual
    // ────────────────────────────────────────────────
    const soldData   = <?= json_encode($sold_values); ?>;
    const soldLabels = <?= json_encode($sold_labels); ?>;
    const soldColors = ['#7A1E33','#C9973E','#1E8A5B','#1A2A4A','#58101F','#A5334C'];
    let soldCurrent  = soldData.map(() => 0);

    const ctxSold = document.getElementById('chartTerjual').getContext('2d');
    const donutChart = new Chart(ctxSold, {
        type: 'doughnut',
        data: {
            labels: soldLabels,
            datasets: [{
                data: [...soldCurrent],
                backgroundColor: soldColors,
                borderWidth: 4,
                borderColor: '#ffffff',
                hoverOffset: 14,
                spacing: 2
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '62%',
            animation: { duration: 600, easing: 'easeOutQuart' },
            plugins: {
                legend: {
                    position: 'right',
                    labels: {
                        usePointStyle: true,
                        pointStyle: 'circle',
                        padding: 14,
                        font: { size: 12, weight: '600' }
                    }
                },
                tooltip: {
                    callbacks: {
                        label: (ctx) => '  ' + ctx.label + ': ' + ctx.parsed.toLocaleString('id-ID') + ' Qty'
                    }
                }
            }
        }
    });

    soldData.forEach((val, i) => {
        setTimeout(() => {
            soldCurrent[i] = val;
            donutChart.data.datasets[0].data = [...soldCurrent];
            donutChart.update();
        }, 400 + i * 350);
    });

    // ────────────────────────────────────────────────
    // Chart 2: Bar — Tren Nilai Pengadaan Bulanan
    // ────────────────────────────────────────────────
    const barData   = <?= json_encode($month_values); ?>;
    const barLabels = <?= json_encode($month_labels); ?>;
    let barCurrent  = barData.map(() => 0);

    const ctxMonthly = document.getElementById('chartPengadaan').getContext('2d');
    const barGrad = ctxMonthly.createLinearGradient(0, 0, 0, 260);
    barGrad.addColorStop(0, '#C9973E');
    barGrad.addColorStop(0.35, '#A5334C');
    barGrad.addColorStop(1, '#58101F');

    const barChart = new Chart(ctxMonthly, {
        type: 'bar',
        data: {
            labels: barLabels,
            datasets: [{
                label: 'Total Nilai (Rp)',
                data: [...barCurrent],
                backgroundColor: barGrad,
                borderColor: '#C9973E',
                borderWidth: 1.5,
                borderRadius: { topLeft: 8, topRight: 8 },
                borderSkipped: false
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 550, easing: 'easeOutQuart' },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1A0308',
                    borderColor: '#C9973E',
                    borderWidth: 1,
                    titleColor: '#E8D5A0',
                    bodyColor: '#ffffff',
                    padding: 12,
                    callbacks: {
                        label: (ctx) => '  Rp ' + ctx.parsed.y.toLocaleString('id-ID')
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    border: { display: false },
                    ticks: {
                        color: '#6B7280',
                        font: { size: 11 },
                        callback: (val) => 'Rp ' + (val / 1000000).toFixed(0) + ' Jt'
                    }
                },
                x: {
                    grid: { display: false },
                    border: { display: false },
                    ticks: { color: '#6B7280', font: { size: 11 } }
                }
            }
        }
    });

    barData.forEach((val, i) => {
        setTimeout(() => {
            barCurrent[i] = val;
            barChart.data.datasets[0].data = [...barCurrent];
            barChart.update();
        }, 300 + i * 200);
    });

});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
