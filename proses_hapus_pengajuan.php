<?php
require_once __DIR__ . '/config/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$csrf_token = $_GET['csrf_token'] ?? '';
$return_url = trim($_GET['return_url'] ?? '');

if (!empty($return_url)) {
    $return_url = htmlspecialchars_decode($return_url, ENT_QUOTES);
    $parsed = parse_url($return_url);
    if (!empty($parsed['host']) || !empty($parsed['scheme'])) {
        $return_url = 'insert_admin.php';
    }
} else {
    $return_url = 'insert_admin.php';
}

if (!verify_csrf_token($csrf_token)) {
    set_flash('error', 'Gagal', 'Token CSRF tidak valid.');
    header("Location: $return_url");
    exit;
}

$user_id = current_user()['id'];

// Ambil data pengajuan
$stmt_p = mysqli_prepare($conn, "SELECT custom_id, bukti_pembelian, bukti_transfer, bukti_tunai FROM pengajuan WHERE id = ?");
mysqli_stmt_bind_param($stmt_p, "i", $id);
mysqli_stmt_execute($stmt_p);
$res_p = mysqli_stmt_get_result($stmt_p);
$p = mysqli_fetch_assoc($res_p);

if (!$p) {
    set_flash('error', 'Gagal', 'Pengajuan tidak ditemukan.');
    header("Location: $return_url");
    exit;
}

$custom_id = $p['custom_id'];

// Mulai Atomic Transaction
mysqli_autocommit($conn, FALSE);

try {
    // 1. Ambil detail barang transaksi
    $stmt_old = mysqli_prepare($conn, "SELECT * FROM pengajuan_detail WHERE pengajuan_id = ?");
    mysqli_stmt_bind_param($stmt_old, "i", $id);
    mysqli_stmt_execute($stmt_old);
    $res_old = mysqli_stmt_get_result($stmt_old);

    $restored_count = 0;
    $custom_count = 0;

    while ($item = mysqli_fetch_assoc($res_old)) {
        // KEMBALIKAN STOK: Hanya untuk barang reguler (bukan custom) dan memiliki jenis_id valid
        if (!$item['is_custom'] && !empty($item['jenis_id']) && (int)$item['jenis_id'] > 0) {
            $jenis_id = (int)$item['jenis_id'];
            $qty = (float)$item['jumlah'];

            // Lock row jenis_barang FOR UPDATE
            $stmt_l = mysqli_prepare($conn, "SELECT stok, nama_jenis FROM jenis_barang WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt_l, "i", $jenis_id);
            mysqli_stmt_execute($stmt_l);
            $res_l = mysqli_stmt_get_result($stmt_l);
            $row_l = mysqli_fetch_assoc($res_l);

            if ($row_l) {
                $stok_prev = (float)$row_l['stok'];
                $stok_reverted = $stok_prev + $qty;

                // Update stok jenis_barang (tambah kembali)
                $stmt_r = mysqli_prepare($conn, "UPDATE jenis_barang SET stok = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_r, "di", $stok_reverted, $jenis_id);
                if (!mysqli_stmt_execute($stmt_r)) {
                    throw new Exception("Gagal mengembalikan stok varian #$jenis_id.");
                }

                // Log audit trail ke riwayat_stok
                $ket_revert = "Pengembalian stok dari penghapusan transaksi Nota #$custom_id";
                $stmt_log_r = mysqli_prepare($conn, "INSERT INTO riwayat_stok (jenis_id, user_id, perubahan, stok_sebelum, stok_sesudah, aksi, keterangan, tanggal) VALUES (?, ?, ?, ?, ?, 'hapus', ?, NOW())");
                mysqli_stmt_bind_param($stmt_log_r, "iiddds", $jenis_id, $user_id, $qty, $stok_prev, $stok_reverted, $ket_revert);
                mysqli_stmt_execute($stmt_log_r);

                $restored_count++;
            }
        } else {
            // Barang custom: stok tidak dikembalikan (karena tidak terdaftar dalam master inventory)
            $custom_count++;
        }
    }

    // 2. Hapus riwayat cicilan jika ada
    $stmt_del_cicilan = mysqli_prepare($conn, "DELETE FROM riwayat_cicilan WHERE pengajuan_id = ?");
    if ($stmt_del_cicilan) {
        mysqli_stmt_bind_param($stmt_del_cicilan, "i", $id);
        mysqli_stmt_execute($stmt_del_cicilan);
    }

    // 3. Hapus pengajuan detail
    $stmt_del_det = mysqli_prepare($conn, "DELETE FROM pengajuan_detail WHERE pengajuan_id = ?");
    if ($stmt_del_det) {
        mysqli_stmt_bind_param($stmt_del_det, "i", $id);
        mysqli_stmt_execute($stmt_del_det);
    }

    // 4. Hapus data transaksi pengajuan induk
    $stmt_del = mysqli_prepare($conn, "DELETE FROM pengajuan WHERE id = ?");
    mysqli_stmt_bind_param($stmt_del, "i", $id);
    mysqli_stmt_execute($stmt_del);

    // 5. Hapus berkas fisik bukti pembayaran jika ada
    $bukti_fields = ['bukti_pembelian', 'bukti_transfer', 'bukti_tunai'];
    foreach ($bukti_fields as $bfield) {
        if (!empty($p[$bfield])) {
            $fpath = __DIR__ . '/' . ltrim($p[$bfield], '/\\');
            if (file_exists($fpath) && is_file($fpath)) {
                @unlink($fpath);
            }
        }
    }

    // Commit Transaction
    mysqli_commit($conn);
    mysqli_autocommit($conn, TRUE);

    $msg = "Transaksi Nota #$custom_id berhasil dihapus.";
    if ($restored_count > 0) {
        $msg .= " Stok untuk $restored_count varian barang reguler telah otomatis dikembalikan.";
    }
    if ($custom_count > 0) {
        $msg .= " ($custom_count barang custom tidak mempengaruhi stok).";
    }

    set_flash('success', 'Berhasil', $msg);
    header("Location: $return_url");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    mysqli_autocommit($conn, TRUE);

    set_flash('error', 'Hapus Gagal', $e->getMessage());
    header("Location: $return_url");
    exit;
}
