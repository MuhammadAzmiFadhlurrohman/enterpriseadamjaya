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
        $return_url = 'pengeluaran.php';
    }
} else {
    $return_url = 'pengeluaran.php';
}

if (!verify_csrf_token($csrf_token)) {
    set_flash('error', 'Gagal', 'Token CSRF tidak valid.');
    header("Location: $return_url");
    exit;
}

if ($id > 0) {
    // Delete detail items first to prevent foreign key constraint failures
    $stmt_del_det = mysqli_prepare($conn, "DELETE FROM pengeluaran_detail WHERE pengeluaran_id = ?");
    if ($stmt_del_det) {
        mysqli_stmt_bind_param($stmt_del_det, "i", $id);
        mysqli_stmt_execute($stmt_del_det);
    }
    
    $stmt = mysqli_prepare($conn, "DELETE FROM pengeluaran_header WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        set_flash('success', 'Berhasil', 'Catatan pengeluaran kas berhasil dihapus.');
    } else {
        set_flash('error', 'Gagal', 'Gagal menghapus catatan pengeluaran: ' . mysqli_error($conn));
    }
} else {
    set_flash('error', 'Gagal', 'ID Pengeluaran tidak valid.');
}

header("Location: $return_url");
exit;
