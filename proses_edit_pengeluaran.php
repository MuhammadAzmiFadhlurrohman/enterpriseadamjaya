<?php
require_once __DIR__ . '/config/auth.php';
require_admin();

$return_url = sanitize($_POST['return_url'] ?? '');

if (!empty($return_url)) {
    $parsed = parse_url($return_url);
    if (!empty($parsed['host']) || !empty($parsed['scheme'])) {
        $return_url = 'pengeluaran.php';
    }
} else {
    $return_url = 'pengeluaran.php';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $return_url");
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    set_flash('error', 'Gagal', 'Token CSRF tidak valid.');
    header("Location: $return_url");
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$tanggal = sanitize($_POST['tanggal'] ?? date('Y-m-d'));
$keterangan = sanitize($_POST['keterangan'] ?? '');

$item_indexes = [];
foreach ($_POST as $key => $val) {
    if (strpos($key, 'nama_item_') === 0) {
        $idx = str_replace('nama_item_', '', $key);
        $item_indexes[] = $idx;
    }
}

if (empty($item_indexes)) {
    set_flash('error', 'Gagal', 'Tidak ada rincian item pengeluaran.');
    $err_redirect = "edit_pengeluaran.php?id=$id" . (!empty($return_url) ? "&return_url=" . urlencode($return_url) : "");
    header("Location: $err_redirect");
    exit;
}

mysqli_autocommit($conn, FALSE);

try {
    // Clear old details
    $stmt_del = mysqli_prepare($conn, "DELETE FROM pengeluaran_detail WHERE pengeluaran_id = ?");
    mysqli_stmt_bind_param($stmt_del, "i", $id);
    mysqli_stmt_execute($stmt_del);

    $grand_total = 0.0;
    $items_to_insert = [];

    foreach ($item_indexes as $idx) {
        $nama_item = sanitize($_POST["nama_item_$idx"] ?? 'Item Pengeluaran');
        $kategori = sanitize($_POST["kategori_$idx"] ?? 'Operasional');
        $jumlah = parseJumlah($_POST["jumlah_$idx"] ?? 1);
        $satuan = sanitize($_POST["satuan_$idx"] ?? 'unit');
        $harga_satuan = unformatRupiah($_POST["harga_satuan_$idx"] ?? 0);
        $total_harga = $jumlah * $harga_satuan;
        $grand_total += $total_harga;

        $items_to_insert[] = [
            'nama_item' => $nama_item,
            'kategori' => $kategori,
            'jumlah' => $jumlah,
            'satuan' => $satuan,
            'harga_satuan' => $harga_satuan,
            'total_harga' => $total_harga
        ];
    }

    // Update Header
    $stmt_head = mysqli_prepare($conn, "UPDATE pengeluaran_header SET tanggal = ?, total_pengeluaran = ?, keterangan = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt_head, "sdsi", $tanggal, $grand_total, $keterangan, $id);
    mysqli_stmt_execute($stmt_head);

    // Insert Details
    $stmt_det = mysqli_prepare($conn, "INSERT INTO pengeluaran_detail (pengeluaran_id, nama_item, kategori, jumlah, satuan, harga_satuan, total_harga) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($items_to_insert as $item) {
        mysqli_stmt_bind_param($stmt_det, "issdsdd", 
            $id, 
            $item['nama_item'], 
            $item['kategori'], 
            $item['jumlah'], 
            $item['satuan'], 
            $item['harga_satuan'], 
            $item['total_harga']
        );
        mysqli_stmt_execute($stmt_det);
    }

    mysqli_commit($conn);
    mysqli_autocommit($conn, TRUE);

    set_flash('success', 'Berhasil', 'Transaksi pengeluaran berhasil diperbarui.');
    header("Location: $return_url");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    mysqli_autocommit($conn, TRUE);

    set_flash('error', 'Gagal', $e->getMessage());
    $err_redirect = "edit_pengeluaran.php?id=$id" . (!empty($return_url) ? "&return_url=" . urlencode($return_url) : "");
    header("Location: $err_redirect");
    exit;
}
