<?php
require_once __DIR__ . '/config/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: insert_admin.php');
    exit;
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    set_flash('error', 'Gagal', 'Token CSRF tidak valid.');
    header('Location: tambah_pengajuan.php');
    exit;
}

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

// Custom ID, Custom Tanggal, Custom Jam, Status Bayar & Kirim
$user_custom_id = sanitize($_POST['custom_id'] ?? '');
if (!empty($user_custom_id)) {
    $custom_id = $user_custom_id;
} else {
    $custom_id = generate_pengajuan_custom_id($conn);
}

$custom_tanggal = sanitize($_POST['custom_tanggal'] ?? date('Y-m-d'));
$custom_jam = sanitize($_POST['custom_jam'] ?? date('H:i'));
$created_at = "$custom_tanggal $custom_jam:00";

$status_pembayaran = sanitize($_POST['status_pembayaran'] ?? 'belum_dibayar');
if (!in_array($status_pembayaran, ['belum_dibayar', 'dibayar', 'cicilan'])) {
    $status_pembayaran = 'belum_dibayar';
}

$status_pengiriman = sanitize($_POST['status_pengiriman'] ?? 'belum_dikirim');
if (!in_array($status_pengiriman, ['belum_dikirim', 'sudah_dikirim'])) {
    $status_pengiriman = 'belum_dikirim';
}

if (empty($nama_pembeli)) {
    $nama_pembeli = 'Umum';
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
    header('Location: tambah_pengajuan.php');
    exit;
}

// Mulai ATOMIC TRANSACTION MYSQL
mysqli_autocommit($conn, FALSE);

try {
    $grand_total = 0.0;
    $items_to_insert = [];

    foreach ($item_indexes as $idx) {
        $is_custom = isset($_POST["is_custom_$idx"]) && $_POST["is_custom_$idx"] == '1' ? 1 : 0;
        $jumlah = parseJumlah($_POST["jumlah_$idx"] ?? 1);
        $satuan = sanitize($_POST["satuan_$idx"] ?? 'pcs');
        $harga = unformatRupiah($_POST["harga_$idx"] ?? 0);
        $subtotal = $jumlah * $harga;

        if ($is_custom) {
            $nama_barang = sanitize($_POST["custom_nama_$idx"] ?? '');
            $nama_jenis = sanitize($_POST["custom_jenis_$idx"] ?? '-');

            if (empty($nama_barang)) {
                if (count($item_indexes) > 1) {
                    continue;
                } else {
                    $item_num = (int)$idx + 1;
                    throw new Exception("Nama Barang Custom belum diisi pada Item #$item_num.");
                }
            }

            $items_to_insert[] = [
                'is_custom' => 1,
                'jenis_id' => null,
                'nama_barang' => $nama_barang,
                'nama_jenis' => $nama_jenis,
                'jumlah' => $jumlah,
                'satuan' => $satuan,
                'harga_satuan' => $harga
            ];
            $grand_total += $subtotal;
        } else {
            $barang_id = (int)($_POST["barang_id_$idx"] ?? 0);
            $jenis_id = (int)($_POST["jenis_id_$idx"] ?? 0);

            // Auto-fallback 1: Jika jenis_id terisi tapi barang_id <= 0 (misal dari pencarian instan), cari barang_id dari tabel jenis_barang
            if ($jenis_id > 0 && $barang_id <= 0) {
                $stmt_b = mysqli_prepare($conn, "SELECT barang_id FROM jenis_barang WHERE id = ?");
                if ($stmt_b) {
                    mysqli_stmt_bind_param($stmt_b, "i", $jenis_id);
                    mysqli_stmt_execute($stmt_b);
                    $res_b = mysqli_stmt_get_result($stmt_b);
                    if ($row_b = mysqli_fetch_assoc($res_b)) {
                        $barang_id = (int)$row_b['barang_id'];
                    }
                }
            }

            // Auto-fallback 2: Jika barang_id terisi tapi jenis_id <= 0, cari varian pertama barang tersebut
            if ($barang_id > 0 && $jenis_id <= 0) {
                $stmt_fb = mysqli_prepare($conn, "SELECT id FROM jenis_barang WHERE barang_id = ? ORDER BY id ASC LIMIT 1");
                if ($stmt_fb) {
                    mysqli_stmt_bind_param($stmt_fb, "i", $barang_id);
                    mysqli_stmt_execute($stmt_fb);
                    $res_fb = mysqli_stmt_get_result($stmt_fb);
                    if ($row_fb = mysqli_fetch_assoc($res_fb)) {
                        $jenis_id = (int)$row_fb['id'];
                    }
                }
            }

            // Jika baris ini benar-benar tidak memilih barang (barang_id = 0 & jenis_id = 0)
            if ($barang_id <= 0 || $jenis_id <= 0) {
                if (count($item_indexes) > 1) {
                    continue; // Skip baris tak terisi ini jika ada baris item lainnya
                } else {
                    $item_num = (int)$idx + 1;
                    throw new Exception("Barang atau varian jenis belum dipilih pada Item #$item_num. Silakan pilih varian barang terlebih dahulu.");
                }
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
                throw new Exception("Varian barang #$jenis_id tidak ditemukan di database.");
            }

            $stok_sebelum = (float)$row_lock['stok'];
            if ($stok_sebelum < $jumlah) {
                throw new Exception("Stok tidak mencukupi untuk item {$row_lock['nama_barang']} - {$row_lock['nama_jenis']}. Stok tersedia: $stok_sebelum {$row_lock['satuan']}, diminta: $jumlah.");
            }

            // Potong stok atomis
            $stok_sesudah = $stok_sebelum - $jumlah;
            $stmt_deduct = mysqli_prepare($conn, "UPDATE jenis_barang SET stok = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_deduct, "di", $stok_sesudah, $jenis_id);
            if (!mysqli_stmt_execute($stmt_deduct)) {
                throw new Exception("Gagal memotong stok varian barang.");
            }

            // Log riwayat_stok
            $ket_log = "Pengadaan Pembelian Nota #$custom_id";
            $perubahan = -$jumlah;
            $stmt_log = mysqli_prepare($conn, "INSERT INTO riwayat_stok (jenis_id, user_id, perubahan, stok_sebelum, stok_sesudah, aksi, keterangan, tanggal) VALUES (?, ?, ?, ?, ?, 'transaksi', ?, NOW())");
            mysqli_stmt_bind_param($stmt_log, "iiddds", $jenis_id, $user_id, $perubahan, $stok_sebelum, $stok_sesudah, $ket_log);
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
            $grand_total += $subtotal;
        }
    }

    if (empty($items_to_insert)) {
        throw new Exception("Tidak ada item barang valid yang dipilih. Silakan pilih minimal 1 barang.");
    }

    // Process Upload File Bukti Pembayaran (Opsional)
    $metode_pembayaran = sanitize($_POST['metode_pembayaran'] ?? '');
    $bukti_transfer = null;
    $bukti_tunai = null;
    $bukti_pembelian = null;

    if (isset($_FILES['bukti_file']) && $_FILES['bukti_file']['error'] === UPLOAD_ERR_OK) {
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        $file_ext = strtolower(pathinfo($_FILES['bukti_file']['name'], PATHINFO_EXTENSION));
        if (in_array($file_ext, $allowed_ext)) {
            $upload_dir = __DIR__ . '/uploads/bukti/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $filename = 'bukti_' . time() . '_' . uniqid() . '.' . $file_ext;
            if (move_uploaded_file($_FILES['bukti_file']['tmp_name'], $upload_dir . $filename)) {
                $file_path = 'uploads/bukti/' . $filename;
                $bukti_pembelian = $file_path;
                if ($metode_pembayaran === 'transfer') {
                    $bukti_transfer = $file_path;
                } else if ($metode_pembayaran === 'tunai') {
                    $bukti_tunai = $file_path;
                }
            }
        }
    }

    // Status Pembayaran & Perhitungan Cicilan
    $status_pembayaran_post = sanitize($_POST['status_pembayaran'] ?? 'belum_dibayar');
    $jumlah_dibayar_post = unformatRupiah($_POST['jumlah_dibayar'] ?? 0);

    if ($status_pembayaran_post === 'dibayar') {
        $status_pembayaran = 'dibayar';
        $jumlah_dibayar = $grand_total;
        $sisa_pembayaran = 0.00;
    } else if ($status_pembayaran_post === 'cicilan') {
        if ($jumlah_dibayar_post >= $grand_total) {
            $status_pembayaran = 'dibayar';
            $jumlah_dibayar = $grand_total;
            $sisa_pembayaran = 0.00;
        } else if ($jumlah_dibayar_post <= 0) {
            $status_pembayaran = 'belum_dibayar';
            $jumlah_dibayar = 0.00;
            $sisa_pembayaran = $grand_total;
        } else {
            $status_pembayaran = 'cicilan';
            $jumlah_dibayar = $jumlah_dibayar_post;
            $sisa_pembayaran = $grand_total - $jumlah_dibayar_post;
        }
    } else {
        $status_pembayaran = 'belum_dibayar';
        $jumlah_dibayar = 0.00;
        $sisa_pembayaran = $grand_total;
    }

    // Insert Header Pengajuan dengan Custom ID, Status, Jumlah Dibayar, Sisa & Bukti Upload
    $stmt_head = mysqli_prepare($conn, "INSERT INTO pengajuan (custom_id, user_id, jenis_pengajuan, status_pembayaran, jumlah_dibayar, sisa_pembayaran, status_pengiriman, bukti_transfer, bukti_tunai, bukti_pembelian, estimasi_dana, nama_pembeli, telepon_pembeli, created_at) VALUES (?, ?, 'stok', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $estimasi_str = (string)$grand_total;
    mysqli_stmt_bind_param($stmt_head, "sisddssssssss", 
        $custom_id, 
        $user_id, 
        $status_pembayaran,
        $jumlah_dibayar,
        $sisa_pembayaran,
        $status_pengiriman, 
        $bukti_transfer, 
        $bukti_tunai, 
        $bukti_pembelian, 
        $estimasi_str, 
        $nama_pembeli, 
        $telepon_pembeli, 
        $created_at
    );

    if (!mysqli_stmt_execute($stmt_head)) {
        throw new Exception("Gagal menyimpan header pengajuan (ID Nota mungkin sudah dipakai): " . mysqli_error($conn));
    }
    $pengajuan_id = mysqli_insert_id($conn);

    // Record initial payment in riwayat_cicilan only if it is a DP / partial payment (not direct full payment)
    if ($jumlah_dibayar > 0 && $status_pembayaran !== 'dibayar' && $sisa_pembayaran > 0) {
        $catatan_awal = 'Pembayaran DP / Uang Muka Awal';
        $metode_val = ($metode_pembayaran === 'transfer') ? 'Transfer' : 'Cash';
        $stmt_cicilan_awal = mysqli_prepare($conn, "INSERT INTO riwayat_cicilan (pengajuan_id, user_id, nominal_bayar, metode_pembayaran, sisa_sebelum, sisa_sesudah, catatan) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt_cicilan_awal, "iidsdds", $pengajuan_id, $user_id, $jumlah_dibayar, $metode_val, $grand_total, $sisa_pembayaran, $catatan_awal);
        mysqli_stmt_execute($stmt_cicilan_awal);
    }

    // Insert Items Detail
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
            throw new Exception("Gagal menyimpan detail pengajuan: " . mysqli_error($conn));
        }
    }

    // Commit Transaction
    mysqli_commit($conn);
    mysqli_autocommit($conn, TRUE);

    set_flash('success', 'Pengajuan Berhasil!', "Pengajuan #$custom_id berhasil dibuat dan stok telah terpotong.");
    header('Location: insert_admin.php');
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    mysqli_autocommit($conn, TRUE);

    set_flash('error', 'Transaksi Gagal', $e->getMessage());
    header('Location: tambah_pengajuan.php');
    exit;
}
