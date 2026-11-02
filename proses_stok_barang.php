<?php
require_once __DIR__ . '/config/auth.php';
require_admin();

$action = $_REQUEST['action'] ?? '';
$csrf_token = $_REQUEST['csrf_token'] ?? '';
$search = sanitize($_REQUEST['search'] ?? '');
$search_query_str = !empty($search) ? '&search=' . urlencode($search) : '';

if (!verify_csrf_token($csrf_token)) {
    set_flash('error', 'Gagal', 'Token CSRF tidak valid.');
    header('Location: stok_barang.php' . (!empty($search) ? '?search=' . urlencode($search) : ''));
    exit;
}

/**
 * Helper untuk memproses file upload gambar produk barang
 * Mengembalikan array ['status' => bool, 'path' => string|null, 'error' => string|null]
 */
function handle_upload_gambar_barang($field_name = 'gambar') {
    // Jika tidak ada file yang dipilih sama sekali, anggap valid (gambar opsional)
    if (!isset($_FILES[$field_name]) || empty($_FILES[$field_name]['name']) || $_FILES[$field_name]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['status' => true, 'path' => null, 'error' => null];
    }

    $file = $_FILES[$field_name];

    // Cek error bawaan PHP upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $upload_errors = [
            UPLOAD_ERR_INI_SIZE   => 'Ukuran file gambar melebihi batas upload_max_filesize di server (maks 10MB).',
            UPLOAD_ERR_FORM_SIZE  => 'Ukuran file gambar melebihi batas form HTML.',
            UPLOAD_ERR_PARTIAL    => 'File gambar hanya terunggah sebagian. Silakan ulangi upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder temporary server tidak ditemukan.',
            UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file gambar ke penyimpanan disk server.',
            UPLOAD_ERR_EXTENSION  => 'Proses unggah dihentikan oleh konfigurasi ekstensi PHP server.'
        ];
        $msg = $upload_errors[$file['error']] ?? 'Terjadi kendala sistem saat mengunggah gambar.';
        return ['status' => false, 'path' => null, 'error' => $msg];
    }

    $file_info = pathinfo($file['name']);
    $ext = strtolower($file_info['extension'] ?? '');
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'jfif', 'avif', 'bmp'];

    if (!in_array($ext, $allowed)) {
        return [
            'status' => false, 
            'path' => null, 
            'error' => 'Format file gambar ("' . htmlspecialchars($ext) . '") tidak didukung. Format yang diizinkan: JPG, JPEG, PNG, WEBP, GIF, JFIF, AVIF.'
        ];
    }

    // Validasi batas ukuran file (maksimal 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['status' => false, 'path' => null, 'error' => 'Ukuran file gambar melebihi batas maksimal 10MB.'];
    }

    // Normalisasi ekstensi jfif menjadi jpg standar web
    $save_ext = ($ext === 'jfif') ? 'jpg' : $ext;
    $new_name = 'barang_' . time() . '_' . rand(100, 999) . '.' . $save_ext;
    $upload_dir = __DIR__ . '/uploads/barang/';

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $dest_path = $upload_dir . $new_name;
    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        $relative_path = 'uploads/barang/' . $new_name;

        // Sinkronisasi otomatis ke folder sibling adamjaya-enterprise jika ada
        $sibling_dir = dirname(__DIR__) . '/adamjaya-enterprise/uploads/barang/';
        if (is_dir($sibling_dir) && is_writable($sibling_dir)) {
            @copy($dest_path, $sibling_dir . $new_name);
        }

        return ['status' => true, 'path' => $relative_path, 'error' => null];
    } else {
        return ['status' => false, 'path' => null, 'error' => 'Gagal memindahkan file ke folder uploads/barang/. Pastikan hak akses folder benar.'];
    }
}

if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama_barang = sanitize($_POST['nama_barang'] ?? '');

    if (empty($nama_barang)) {
        set_flash('error', 'Gagal', 'Nama barang induk tidak boleh kosong.');
        header('Location: stok_barang.php' . (!empty($search) ? '?search=' . urlencode($search) : ''));
        exit;
    }

    $upload = handle_upload_gambar_barang('gambar');
    if (!$upload['status']) {
        set_flash('error', 'Gagal Upload Gambar', $upload['error']);
        header('Location: stok_barang.php' . (!empty($search) ? '?search=' . urlencode($search) : ''));
        exit;
    }

    $gambar_path = $upload['path'];

    $stmt = mysqli_prepare($conn, "INSERT INTO stok_barang (nama_barang, gambar) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, "ss", $nama_barang, $gambar_path);

    if (mysqli_stmt_execute($stmt)) {
        $new_id = mysqli_insert_id($conn);
        set_flash('success', 'Berhasil', 'Barang induk berhasil ditambahkan.');
        header("Location: stok_barang.php?scroll_to=row-barang-$new_id#row-barang-$new_id" . $search_query_str);
        exit;
    } else {
        set_flash('error', 'Gagal', 'Gagal menambah barang induk: ' . mysqli_error($conn));
        header('Location: stok_barang.php' . (!empty($search) ? '?search=' . urlencode($search) : ''));
        exit;
    }

} else if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);
    $nama_barang = sanitize($_POST['nama_barang'] ?? '');
    $hapus_gambar = isset($_POST['hapus_gambar']) && $_POST['hapus_gambar'] === '1';

    if (empty($nama_barang)) {
        set_flash('error', 'Gagal', 'Nama barang induk tidak boleh kosong.');
        header('Location: stok_barang.php' . (!empty($search) ? '?search=' . urlencode($search) : ''));
        exit;
    }

    // Ambil data barang lama untuk cek gambar sebelumnya
    $stmt_old = mysqli_prepare($conn, "SELECT gambar FROM stok_barang WHERE id = ?");
    mysqli_stmt_bind_param($stmt_old, "i", $id);
    mysqli_stmt_execute($stmt_old);
    $old_res = mysqli_stmt_get_result($stmt_old);
    $old_row = mysqli_fetch_assoc($old_res);
    $old_gambar = $old_row['gambar'] ?? null;

    $upload = handle_upload_gambar_barang('gambar');
    if (!$upload['status']) {
        set_flash('error', 'Gagal Upload Gambar', $upload['error']);
        header('Location: stok_barang.php' . (!empty($search) ? '?search=' . urlencode($search) : ''));
        exit;
    }

    $new_gambar_path = $upload['path'];

    if ($new_gambar_path !== null) {
        // Hapus file gambar lama jika ada
        if (!empty($old_gambar)) {
            $old_file = __DIR__ . '/' . ltrim($old_gambar, '/\\');
            if (file_exists($old_file)) @unlink($old_file);
            $sibling_old = dirname(__DIR__) . '/adamjaya-enterprise/' . ltrim($old_gambar, '/\\');
            if (file_exists($sibling_old)) @unlink($sibling_old);
        }

        $stmt = mysqli_prepare($conn, "UPDATE stok_barang SET nama_barang = ?, gambar = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "ssi", $nama_barang, $new_gambar_path, $id);
    } elseif ($hapus_gambar) {
        // User memilih untuk menghapus gambar
        if (!empty($old_gambar)) {
            $old_file = __DIR__ . '/' . ltrim($old_gambar, '/\\');
            if (file_exists($old_file)) @unlink($old_file);
            $sibling_old = dirname(__DIR__) . '/adamjaya-enterprise/' . ltrim($old_gambar, '/\\');
            if (file_exists($sibling_old)) @unlink($sibling_old);
        }

        $stmt = mysqli_prepare($conn, "UPDATE stok_barang SET nama_barang = ?, gambar = NULL WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $nama_barang, $id);
    } else {
        // Hanya update nama barang, pertahankan gambar lama
        $stmt = mysqli_prepare($conn, "UPDATE stok_barang SET nama_barang = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "si", $nama_barang, $id);
    }

    if (mysqli_stmt_execute($stmt)) {
        set_flash('success', 'Berhasil', 'Data barang induk berhasil diperbarui.');
        header("Location: stok_barang.php?scroll_to=row-barang-$id#row-barang-$id" . $search_query_str);
        exit;
    } else {
        set_flash('error', 'Gagal', 'Gagal memperbarui barang induk: ' . mysqli_error($conn));
        header('Location: stok_barang.php' . (!empty($search) ? '?search=' . urlencode($search) : ''));
        exit;
    }

} else if ($action === 'delete') {
    $id = (int)($_GET['id'] ?? 0);

    // Ambil gambar untuk dihapus dari disk
    $stmt_old = mysqli_prepare($conn, "SELECT gambar FROM stok_barang WHERE id = ?");
    mysqli_stmt_bind_param($stmt_old, "i", $id);
    mysqli_stmt_execute($stmt_old);
    $old_res = mysqli_stmt_get_result($stmt_old);
    if ($old_row = mysqli_fetch_assoc($old_res)) {
        if (!empty($old_row['gambar'])) {
            $old_file = __DIR__ . '/' . ltrim($old_row['gambar'], '/\\');
            if (file_exists($old_file)) @unlink($old_file);
            $sibling_old = dirname(__DIR__) . '/adamjaya-enterprise/' . ltrim($old_row['gambar'], '/\\');
            if (file_exists($sibling_old)) @unlink($sibling_old);
        }
    }

    $stmt = mysqli_prepare($conn, "DELETE FROM stok_barang WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);

    if (mysqli_stmt_execute($stmt)) {
        set_flash('success', 'Berhasil', 'Barang induk beserta seluruh variannya berhasil dihapus.');
    } else {
        set_flash('error', 'Gagal', 'Gagal menghapus barang induk.');
    }
    header('Location: stok_barang.php' . (!empty($search) ? '?search=' . urlencode($search) : ''));
    exit;
}

header('Location: stok_barang.php');
exit;
