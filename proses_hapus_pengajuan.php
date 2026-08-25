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
$stmt_p = mysqli_prepare($conn, "SELECT custom_id FROM pengajuan WHERE id = ?");
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

// Mulai Transaction
mysqli_autocommit($conn, FALSE);

try {
    // Restorasi stok barang reguler
    $stmt_old = mysqli_prepare($conn, "SELECT * FROM pengajuan_detail WHERE pengajuan_id = ?");
    mysqli_stmt_bind_param($stmt_old, "i", $id);
    mysqli_stmt_execute($stmt_old);
    $res_old = mysqli_stmt_get_result($stmt_old);

    while ($item = mysqli_fetch_assoc($res_old)) {
        if (!$item['is_custom'] && !empty($item['jenis_id'])) {
            $jenis_id = (int)$item['jenis_id'];
            $qty = (float)$item['jumlah'];

            // Lock row FOR UPDATE
            $stmt_l = mysqli_prepare($conn, "SELECT stok FROM jenis_barang WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt_l, "i", $jenis_id);
            mysqli_stmt_execute($stmt_l);
            $res_l = mysqli_stmt_get_result($stmt_l);
            $row_l = mysqli_fetch_assoc($res_l);

            if ($row_l) {
                $stok_prev = (float)$row_l['stok'];
                $stok_reverted = $stok_prev + $qty;

                $stmt_r = mysqli_prepare($conn, "UPDATE jenis_barang SET stok = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_r, "di", $stok_reverted, $jenis_id);
                mysqli_stmt_execute($stmt_r);

                // Log audit trail
                $ket_revert = "Pengembalian stok dari pembatalan/penghapusan pengajuan #$custom_id";
                $stmt_log_r = mysqli_prepare($conn, "INSERT INTO riwayat_stok (jenis_id, user_id, perubahan, stok_sebelum, stok_sesudah, aksi, keterangan, tanggal) VALUES (?, ?, ?, ?, ?, 'hapus', ?, NOW())");
                mysqli_stmt_bind_param($stmt_log_r, "iiddss", $jenis_id, $user_id, $qty, $stok_prev, $stok_reverted, $ket_revert);
                mysqli_stmt_execute($stmt_log_r);
            }
        }
    }

    // Hapus pengajuan (detail terhapus otomatis via CASCADE)
    $stmt_del = mysqli_prepare($conn, "DELETE FROM pengajuan WHERE id = ?");
    mysqli_stmt_bind_param($stmt_del, "i", $id);
    mysqli_stmt_execute($stmt_del);

    mysqli_commit($conn);
    mysqli_autocommit($conn, TRUE);

    set_flash('success', 'Berhasil', "Pengajuan #$custom_id berhasil dihapus dan stok barang telah dikembalikan.");
    header("Location: $return_url");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    mysqli_autocommit($conn, TRUE);

    set_flash('error', 'Hapus Gagal', $e->getMessage());
    header("Location: $return_url");
    exit;
}
