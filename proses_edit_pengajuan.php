<?php
require_once __DIR__ . '/config/auth.php';
require_admin();

$return_row = sanitize($_POST['return_row'] ?? '');
$return_url = trim($_POST['return_url'] ?? '');

if (!empty($return_url)) {
    $return_url = htmlspecialchars_decode($return_url, ENT_QUOTES);
    $parsed = parse_url($return_url);
    if (!empty($parsed['host']) || !empty($parsed['scheme'])) {
        $return_url = 'insert_admin.php';
    }
} else {
    $return_url = 'insert_admin.php';
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

$pengajuan_id = (int)($_POST['id'] ?? 0);
$user_id = (int)(current_user()['id'] ?? 0);
$u_check = mysqli_query($conn, "SELECT id FROM users WHERE id = $user_id");
if (!$u_check || mysqli_num_rows($u_check) === 0) {
    $u_fallback = mysqli_query($conn, "SELECT id FROM users ORDER BY id ASC LIMIT 1");
    if ($u_row = mysqli_fetch_assoc($u_fallback)) {
        $user_id = (int)$u_row['id'];
    }
}

$nama_pembeli = sanitize($_POST['nama_pembeli'] ?? '');
$telepon_pembeli = sanitize($_POST['telepon_pembeli'] ?? '');
if (empty($nama_pembeli)) {
    $nama_pembeli = 'Umum';
}

$custom_id = sanitize($_POST['custom_id'] ?? '');
$custom_tanggal = sanitize($_POST['custom_tanggal'] ?? date('Y-m-d'));
$custom_jam = sanitize($_POST['custom_jam'] ?? date('H:i'));
$created_at = "$custom_tanggal $custom_jam:00";

$status_pembayaran = sanitize($_POST['status_pembayaran'] ?? 'belum_dibayar');
if (!in_array($status_pembayaran, ['belum_dibayar', 'dibayar'])) {
    $status_pembayaran = 'belum_dibayar';
}

$status_pengiriman = sanitize($_POST['status_pengiriman'] ?? 'belum_dikirim');
if (!in_array($status_pengiriman, ['belum_dikirim', 'sudah_dikirim'])) {
    $status_pengiriman = 'belum_dikirim';
}

// Ambil pengajuan lama
$stmt_p = mysqli_prepare($conn, "SELECT custom_id FROM pengajuan WHERE id = ?");
mysqli_stmt_bind_param($stmt_p, "i", $pengajuan_id);
mysqli_stmt_execute($stmt_p);
$res_p = mysqli_stmt_get_result($stmt_p);
$p = mysqli_fetch_assoc($res_p);

if (!$p) {
    set_flash('error', 'Gagal', 'Pengajuan tidak ditemukan.');
    header("Location: $return_url");
    exit;
}

if (empty($custom_id)) {
    $custom_id = $p['custom_id'];
}

// Deteksi seluruh indeks item dari POST (_N)
$item_indexes = [];
foreach ($_POST as $key => $val) {
    if (strpos($key, 'jumlah_') === 0) {
        $idx = str_replace('jumlah_', '', $key);
        $item_indexes[] = $idx;
    }
}

if (empty($item_indexes)) {
    set_flash('error', 'Gagal', 'Tidak ada item barang dalam pengajuan.');
    $err_redirect = "edit_pengajuan.php?id=$pengajuan_id";
    if (!empty($return_row)) $err_redirect .= "&return_row=" . urlencode($return_row);
    if (!empty($return_url)) $err_redirect .= "&return_url=" . urlencode($return_url);
    header("Location: $err_redirect");
    exit;
}

// Mulai ATOMIC TRANSACTION MYSQL
mysqli_autocommit($conn, FALSE);

try {
    // 1. REVERT STOK LAMA
    $stmt_old = mysqli_prepare($conn, "SELECT * FROM pengajuan_detail WHERE pengajuan_id = ?");
    mysqli_stmt_bind_param($stmt_old, "i", $pengajuan_id);
    mysqli_stmt_execute($stmt_old);
    $res_old = mysqli_stmt_get_result($stmt_old);

    while ($old_item = mysqli_fetch_assoc($res_old)) {
        if (!$old_item['is_custom'] && !empty($old_item['jenis_id'])) {
            $jenis_id_old = (int)$old_item['jenis_id'];
            $qty_old = (float)$old_item['jumlah'];

            // Lock row FOR UPDATE
            $stmt_l = mysqli_prepare($conn, "SELECT stok FROM jenis_barang WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt_l, "i", $jenis_id_old);
            mysqli_stmt_execute($stmt_l);
            $res_l = mysqli_stmt_get_result($stmt_l);
            $row_l = mysqli_fetch_assoc($res_l);

            if ($row_l) {
                $stok_prev = (float)$row_l['stok'];
                $stok_reverted = $stok_prev + $qty_old;

                // Restorasi stok
                $stmt_r = mysqli_prepare($conn, "UPDATE jenis_barang SET stok = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_r, "di", $stok_reverted, $jenis_id_old);
                mysqli_stmt_execute($stmt_r);

                // Log audit trail
                $ket_revert = "Pengembalian stok lama sebelum update Nota #$custom_id";
                $stmt_log_r = mysqli_prepare($conn, "INSERT INTO riwayat_stok (jenis_id, user_id, perubahan, stok_sebelum, stok_sesudah, aksi, keterangan, tanggal) VALUES (?, ?, ?, ?, ?, 'edit', ?, NOW())");
                mysqli_stmt_bind_param($stmt_log_r, "iiddss", $jenis_id_old, $user_id, $qty_old, $stok_prev, $stok_reverted, $ket_revert);
                mysqli_stmt_execute($stmt_log_r);
            }
        }
    }

    // 2. HAPUS DETAIL LAMA
    $stmt_del_det = mysqli_prepare($conn, "DELETE FROM pengajuan_detail WHERE pengajuan_id = ?");
    mysqli_stmt_bind_param($stmt_del_det, "i", $pengajuan_id);
    mysqli_stmt_execute($stmt_del_det);

    // 3. PROSES DAN POTONG STOK ITEM BARU
    $grand_total = 0.0;
    $items_to_insert = [];

    foreach ($item_indexes as $idx) {
        $is_custom = isset($_POST["is_custom_$idx"]) && $_POST["is_custom_$idx"] == '1' ? 1 : 0;
        $jumlah = parseJumlah($_POST["jumlah_$idx"] ?? 1);
        $satuan = sanitize($_POST["satuan_$idx"] ?? 'pcs');
        $harga = unformatRupiah($_POST["harga_$idx"] ?? 0);
        $subtotal = $jumlah * $harga;
        $grand_total += $subtotal;

        if ($is_custom) {
            $nama_barang = sanitize($_POST["custom_nama_$idx"] ?? 'Custom Item');
            $nama_jenis = sanitize($_POST["custom_jenis_$idx"] ?? '-');

            $items_to_insert[] = [
                'is_custom' => 1,
                'jenis_id' => null,
                'nama_barang' => $nama_barang,
                'nama_jenis' => $nama_jenis,
                'jumlah' => $jumlah,
                'satuan' => $satuan,
                'harga_satuan' => $harga
            ];
        } else {
            $barang_id = (int)($_POST["barang_id_$idx"] ?? 0);
            $jenis_id = (int)($_POST["jenis_id_$idx"] ?? 0);

            if ($barang_id <= 0 || $jenis_id <= 0) {
                throw new Exception("Barang induk atau varian belum dipilih.");
            }

            // Lock Tabel Record jenis_barang (SELECT FOR UPDATE)
            $stmt_lock = mysqli_prepare($conn, "SELECT j.stok, j.harga, j.nama_jenis, j.satuan, b.nama_barang 
                                                FROM jenis_barang j 
                                                JOIN stok_barang b ON j.barang_id = b.id 
                                                WHERE j.id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt_lock, "i", $jenis_id);
            mysqli_stmt_execute($stmt_lock);
            $res_lock = mysqli_stmt_get_result($stmt_lock);
            $row_lock = mysqli_fetch_assoc($res_lock);

            if (!$row_lock) {
                throw new Exception("Varian barang #$jenis_id tidak ditemukan.");
            }

            $stok_sebelum = (float)$row_lock['stok'];
            $harga_standar = (float)$row_lock['harga'];

            // Jika harga yang diinput berbeda dari harga standar master, tandai sebagai Harga Custom (is_custom = 1)
            $item_is_custom = ($is_custom == 1 || abs($harga - $harga_standar) > 0.01) ? 1 : 0;

            if ($stok_sebelum < $jumlah) {
                throw new Exception("Stok tidak mencukupi untuk item {$row_lock['nama_barang']} - {$row_lock['nama_jenis']}. Stok tersedia: $stok_sebelum {$row_lock['satuan']}.");
            }

            // Pemotongan stok baru
            $stok_sesudah = $stok_sebelum - $jumlah;
            $stmt_deduct = mysqli_prepare($conn, "UPDATE jenis_barang SET stok = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_deduct, "di", $stok_sesudah, $jenis_id);
            mysqli_stmt_execute($stmt_deduct);

            // Log riwayat_stok
            $ket_log = "Pembaruan Pengadaan Nota #$custom_id";
            $perubahan = -$jumlah;
            $stmt_log = mysqli_prepare($conn, "INSERT INTO riwayat_stok (jenis_id, user_id, perubahan, stok_sebelum, stok_sesudah, aksi, keterangan, tanggal) VALUES (?, ?, ?, ?, ?, 'edit', ?, NOW())");
            mysqli_stmt_bind_param($stmt_log, "iiddss", $jenis_id, $user_id, $perubahan, $stok_sebelum, $stok_sesudah, $ket_log);
            mysqli_stmt_execute($stmt_log);

            $items_to_insert[] = [
                'is_custom' => 0,
                'jenis_id' => $jenis_id,
                'nama_barang' => $row_lock['nama_barang'],
                'nama_jenis' => $row_lock['nama_jenis'],
                'jumlah' => $jumlah,
                'satuan' => $satuan,
                'harga_satuan' => $harga
            ];
        }
    }

    // 4. UPDATE HEADER PENGAJUAN (Include custom_id, status_pembayaran, status_pengiriman, created_at)
    $stmt_head = mysqli_prepare($conn, "UPDATE pengajuan SET custom_id = ?, status_pembayaran = ?, status_pengiriman = ?, estimasi_dana = ?, nama_pembeli = ?, telepon_pembeli = ?, created_at = ? WHERE id = ?");
    $estimasi_str = (string)$grand_total;
    mysqli_stmt_bind_param($stmt_head, "sssssssi", $custom_id, $status_pembayaran, $status_pengiriman, $estimasi_str, $nama_pembeli, $telepon_pembeli, $created_at, $pengajuan_id);
    if (!mysqli_stmt_execute($stmt_head)) {
        throw new Exception("Gagal memperbarui header pengajuan: " . mysqli_error($conn));
    }

    // 5. INSERT DETAIL PENGAJUAN BARU
    foreach ($items_to_insert as $item) {
        if (empty($item['jenis_id']) || $item['jenis_id'] <= 0) {
            $stmt_det = mysqli_prepare($conn, "INSERT INTO pengajuan_detail (pengajuan_id, is_custom, jenis_id, nama_barang, nama_jenis, jumlah, satuan, harga_satuan) VALUES (?, ?, NULL, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_det, "iissdsd", 
                $pengajuan_id, 
                $item['is_custom'], 
                $item['nama_barang'], 
                $item['nama_jenis'], 
                $item['jumlah'], 
                $item['satuan'], 
                $item['harga_satuan']
            );
        } else {
            $stmt_det = mysqli_prepare($conn, "INSERT INTO pengajuan_detail (pengajuan_id, is_custom, jenis_id, nama_barang, nama_jenis, jumlah, satuan, harga_satuan) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_det, "iiissdsd", 
                $pengajuan_id, 
                $item['is_custom'], 
                $item['jenis_id'], 
                $item['nama_barang'], 
                $item['nama_jenis'], 
                $item['jumlah'], 
                $item['satuan'], 
                $item['harga_satuan']
            );
        }
        if (!mysqli_stmt_execute($stmt_det)) {
            throw new Exception("Gagal memperbarui detail pengajuan: " . mysqli_error($conn));
        }
    }

    // Commit Transaction
    mysqli_commit($conn);
    mysqli_autocommit($conn, TRUE);

    if (!empty($return_row)) {
        $parts = explode('#', $return_url);
        $base_and_query = $parts[0];
        if (strpos($base_and_query, 'scroll_to=') === false) {
            $separator = (strpos($base_and_query, '?') !== false) ? '&' : '?';
            $redirect_target = $base_and_query . $separator . "scroll_to=" . urlencode($return_row) . "#" . urlencode($return_row);
        } else {
            $redirect_target = $base_and_query . "#" . urlencode($return_row);
        }
    } else {
        $redirect_target = $return_url;
    }

    $redirect_target = htmlspecialchars_decode($redirect_target, ENT_QUOTES);

    set_flash('success', 'Berhasil Diperbarui!', "Pengajuan #$custom_id berhasil diperbarui dan stok telah disesuaikan.");
    header("Location: $redirect_target");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    mysqli_autocommit($conn, TRUE);

    set_flash('error', 'Update Gagal', $e->getMessage());
    $err_redirect = "edit_pengajuan.php?id=$pengajuan_id";
    if (!empty($return_row)) $err_redirect .= "&return_row=" . urlencode($return_row);
    if (!empty($return_url)) $err_redirect .= "&return_url=" . urlencode($return_url);
    header("Location: $err_redirect");
    exit;
}
