<?php
require_once __DIR__ . '/includes/header.php';
require_login();

$is_admin = is_admin();

$search = sanitize($_GET['search'] ?? '');

// Check for explicit reset flag
if (isset($_GET['reset']) && $_GET['reset'] === '1') {
    $search = '';
}

$where_clause = "";
$params = [];
$types = "";

if (!empty($search)) {
    $where_clause = " WHERE b.nama_barang LIKE ? OR EXISTS (SELECT 1 FROM jenis_barang j2 WHERE j2.barang_id = b.id AND j2.nama_jenis LIKE ?) ";
    $s_term = "%$search%";
    $params[] = $s_term;
    $params[] = $s_term;
    $types = "ss";
}

// Urutkan dari yang terlama ke yang baru (ASC)
$query = "SELECT b.*, COUNT(j.id) as total_varian, IFNULL(SUM(j.stok), 0) as total_stok_varian 
          FROM stok_barang b 
          LEFT JOIN jenis_barang j ON b.id = j.barang_id 
          $where_clause
          GROUP BY b.id 
          ORDER BY b.id ASC";

$stmt = mysqli_prepare($conn, $query);
if (!empty($types)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$total_induk = mysqli_num_rows($result);
?>

<!-- HEADER CURVED EXECUTIVE BANNER -->
<header class="page-header mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <span class="page-eyebrow"><i class="fa-solid fa-boxes-stacked"></i> Master Persediaan &middot; Adam Jaya</span>
            <h1 class="page-title">Master Barang Induk</h1>
            <p class="page-subtitle">Kelola kelompok barang utama yang memiliki berbagai varian & spesifikasi persediaan.</p>
        </div>
    </div>
</header>

<?php if ($is_admin): ?>
<!-- CARD FORM TAMBAH BARANG BARU (INLINE AT TOP) -->
<div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden">
    <div class="card-header text-white py-3 px-3.5 d-flex align-items-center gap-2" style="background: linear-gradient(135deg, #7A1E33 0%, #5A1224 100%);">
        <i class="fa-solid fa-circle-plus fs-5 text-gold"></i>
        <h6 class="mb-0 fw-bold text-white fs-6">Tambah Barang Baru</h6>
    </div>
    <div class="card-body p-3.5 bg-white">
        <form action="proses_stok_barang.php" method="POST" enctype="multipart/form-data">
            <?= csrf_field(); ?>
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="search" value="<?= e($search); ?>">
            
            <div class="mb-3">
                <label class="form-label text-muted small fw-bold">Nama Barang</label>
                <input type="text" name="nama_barang" class="form-control form-control-lg fs-6 fw-semibold" placeholder="Contoh: Tangok" required>
            </div>

            <div class="mb-3">
                <label class="form-label text-muted small fw-bold">Gambar Barang (Opsional)</label>
                <input type="file" name="gambar" class="form-control" accept="image/*" onchange="previewUpload(this, 'preview_add_top')">
                <div id="preview_add_top" class="mt-2" style="display:none;"></div>
                <small class="text-muted d-block mt-1" style="font-size:0.75rem;">JPG, JPEG, PNG, WEBP, GIF, JFIF (Maks 10MB)</small>
            </div>

            <button type="submit" class="btn text-white w-100 py-2.5 fw-bold rounded-3 shadow-sm fs-6" style="background: linear-gradient(135deg, #7A1E33 0%, #5A1224 100%); border: none;">
                <i class="fa-solid fa-plus me-1"></i> Tambah Barang
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Filter & Realtime Search Bar Card -->
<div class="filter-card mb-4">
    <form method="GET" action="stok_barang.php" class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div class="d-flex align-items-center gap-2 flex-grow-1" style="max-width: 550px;">
            <div class="position-relative w-100">
                <i class="fa-solid fa-magnifying-glass position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"></i>
                <input type="text" 
                       name="search" 
                       id="searchInputBarang" 
                       class="form-control ps-5 pe-4 rounded-pill" 
                       placeholder="Cari barang..." 
                       value="<?= e($search); ?>"
                       onkeyup="filterTableLive(this.value)"
                       autocomplete="off">
                <?php if (!empty($search)): ?>
                    <a href="stok_barang.php?reset=1" onclick="sessionStorage.removeItem('stok_barang_search')" class="position-absolute top-50 end-0 translate-middle-y me-3 text-muted text-decoration-none" title="Reset">
                        <i class="fa-solid fa-xmark"></i>
                    </a>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-wine px-4 rounded-pill fw-semibold text-nowrap" style="background: linear-gradient(135deg, #7A1E33 0%, #5A1224 100%); border: none; color: white;">
                <i class="fa-solid fa-magnifying-glass me-1"></i> Cari
            </button>
        </div>

        <?php if (!empty($search)): ?>
            <div>
                <a href="stok_barang.php?reset=1" onclick="sessionStorage.removeItem('stok_barang_search')" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-medium">
                    <i class="fa-solid fa-rotate-left me-1"></i> Reset Pencarian
                </a>
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- Table Container -->
<div class="table-container">
    <div class="table-container-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <i class="fa-solid fa-list text-wine fs-5"></i>
            <h2 class="mb-0 fw-bold fs-5 text-dark">Daftar Barang</h2>
        </div>
        <span class="badge bg-warning-subtle text-dark border border-warning px-3 py-1.5 rounded-pill fw-bold" style="font-size:0.78rem;"><?= $total_induk; ?> barang</span>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th width="45">NO</th>
                    <th width="80">GAMBAR</th>
                    <th>NAMA BARANG</th>
                    <th>JUMLAH JENIS</th>
                    <th width="280" class="text-end">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($total_induk > 0): ?>
                    <?php $no_b = 1; while ($b = mysqli_fetch_assoc($result)): ?>
                        <tr id="row-barang-<?= $b['id']; ?>">
                            <td data-label="NO">
                                <span class="fw-semibold text-muted"><?= $no_b++; ?></span>
                            </td>
                            <td data-label="Gambar">
                                <?php 
                                $g_raw = $b['gambar'] ?? '';
                                $g_clean = ltrim($g_raw, '/\\');
                                $g_found = false;
                                $g_url = '';

                                if (!empty($g_clean)) {
                                    if (file_exists(__DIR__ . '/' . $g_clean)) {
                                        $g_found = true;
                                        $g_url = $g_clean;
                                    } elseif (file_exists(dirname(__DIR__) . '/adamjaya-enterprise/' . $g_clean)) {
                                        $g_found = true;
                                        $g_url = '../adamjaya-enterprise/' . $g_clean;
                                    }
                                }
                                ?>
                                <?php if ($g_found): ?>
                                    <img src="<?= e($g_url); ?>" 
                                         alt="<?= e($b['nama_barang']); ?>" 
                                         class="rounded border shadow-sm" 
                                         style="width: 46px; height: 46px; object-fit: cover; cursor: pointer; transition: transform 0.2s;" 
                                         onmouseover="this.style.transform='scale(1.1)'" 
                                         onmouseout="this.style.transform='scale(1)'" 
                                         onclick="previewImage('<?= e($g_url); ?>', '<?= e(addslashes($b['nama_barang'])); ?>')"
                                         title="Klik untuk memperbesar gambar">
                                <?php else: ?>
                                    <div class="bg-light text-muted border rounded d-flex align-items-center justify-content-center" style="width: 46px; height: 46px;">
                                        <i class="fa-solid fa-image fs-5 text-gold"></i>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Nama Barang">
                                <strong class="text-dark fs-6"><?= e($b['nama_barang']); ?></strong>
                            </td>
                            <td data-label="Jumlah Jenis">
                                <span class="badge bg-warning-subtle text-dark border border-warning px-2.5 py-1 rounded-2 fw-semibold" style="font-size:0.75rem;"><i class="fa-solid fa-layer-group me-1 text-gold"></i> <?= $b['total_varian']; ?> jenis</span>
                            </td>
                            <td data-label="Aksi" class="text-end">
                                <div class="action-btns justify-content-end">
                                    <a href="jenis_barang.php?barang_id=<?= $b['id']; ?>&search=<?= urlencode($search); ?>" class="action-btn btn-detail" title="Lihat Jenis Barang">
                                        <i class="fa-solid fa-eye me-1"></i> Lihat Jenis
                                    </a>
                                    <?php if ($is_admin): ?>
                                        <button type="button" class="action-btn btn-edit" onclick="editBarang(<?= htmlspecialchars(json_encode($b)); ?>)">
                                            <i class="fa-solid fa-pen me-1"></i> Edit
                                        </button>
                                        <a href="#" class="action-btn btn-delete" 
                                           onclick="confirmDelete(event, 'proses_stok_barang.php?action=delete&id=<?= $b['id']; ?>&csrf_token=<?= generate_csrf_token(); ?>&search=<?= urlencode($search); ?>')">
                                            <i class="fa-solid fa-trash me-1"></i> Hapus
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">
                            <div class="no-data py-4 text-center">
                                <i class="fa-solid fa-box-open fs-1 text-muted mb-2"></i>
                                <h5 class="fw-bold text-dark">Belum Ada Barang</h5>
                                <p class="text-muted small mb-0">Silakan gunakan form Tambah Barang Baru di atas untuk mendaftarkan barang.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Preview Gambar Produk -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-modal text-center border-0 overflow-hidden">
            <div class="modal-header bg-dark text-white border-0">
                <h5 class="modal-title fw-bold text-gold" id="imagePreviewTitle"><i class="fa-solid fa-image me-2"></i> Preview Gambar Produk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-dark d-flex justify-content-center align-items-center">
                <img id="imagePreviewSrc" src="" class="img-fluid rounded border shadow-lg" style="max-height: 70vh; object-fit: contain;">
            </div>
            <div class="modal-footer bg-dark border-0 justify-content-between">
                <span class="small text-muted"><i class="fa-solid fa-building me-1 text-gold"></i> Adam Jaya Enterprise</span>
                <button type="button" class="btn btn-secondary-custom px-4 fw-bold" data-bs-dismiss="modal">
                    <i class="fa-solid fa-xmark me-1"></i> Tutup
                </button>
            </div>
        </div>
    </div>
</div>

<?php if ($is_admin): ?>
<!-- Modal Tambah Barang Induk -->
<div class="modal fade" id="addBarangModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-box-archive me-2"></i> Tambah Barang Induk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_stok_barang.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="search" value="<?= e($search); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">NAMA BARANG INDUK *</label>
                        <input type="text" name="nama_barang" class="form-control" placeholder="Contoh: Semen Gresik 40kg" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">GAMBAR PRODUK (OPSIONAL)</label>
                        <input type="file" name="gambar" class="form-control" accept="image/*" onchange="previewUpload(this, 'preview_add_modal')">
                        <div id="preview_add_modal" class="mt-2" style="display:none;"></div>
                        <small class="text-muted d-block mt-1" style="font-size:0.75rem;">JPG, JPEG, PNG, WEBP, GIF, JFIF (Maks 10MB)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-custom">Simpan Barang Induk</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Edit Barang Induk -->
<div class="modal fade" id="editBarangModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-modal">
            <div class="modal-header">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-pen-to-square me-2"></i> Edit Barang Induk</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="proses_stok_barang.php" method="POST" enctype="multipart/form-data">
                <div class="modal-body">
                    <?= csrf_field(); ?>
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="id" id="edit_id">
                    <input type="hidden" name="search" value="<?= e($search); ?>">
                    
                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">NAMA BARANG INDUK *</label>
                        <input type="text" name="nama_barang" id="edit_nama_barang" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-muted small fw-semibold">GANTI GAMBAR PRODUK (OPSIONAL)</label>
                        <input type="file" name="gambar" id="edit_file_gambar" class="form-control" accept="image/*" onchange="previewUpload(this, 'edit_gambar_preview_new')">
                        <div id="edit_gambar_preview_new" class="mt-2" style="display:none;"></div>
                        <div id="edit_gambar_preview" class="mt-2"></div>
                        <div id="edit_hapus_gambar_wrapper" class="form-check mt-2" style="display:none;">
                            <input class="form-check-input" type="checkbox" name="hapus_gambar" value="1" id="hapusGambarCheck">
                            <label class="form-check-label text-danger small fw-semibold" for="hapusGambarCheck">
                                <i class="fa-solid fa-trash-can me-1"></i> Hapus gambar produk saat ini
                            </label>
                        </div>
                        <small class="text-muted d-block mt-1" style="font-size:0.75rem;">JPG, JPEG, PNG, WEBP, GIF, JFIF (Maks 10MB)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary-custom" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary-custom">Update Barang Induk</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function previewUpload(input, targetId) {
    const target = document.getElementById(targetId);
    if (!target) return;
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validasi ekstensi di frontend
        const ext = file.name.split('.').pop().toLowerCase();
        const allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'jfif', 'avif', 'bmp'];
        if (!allowed.includes(ext)) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Format Tidak Didukung',
                    text: 'Format file "' + ext + '" tidak didukung. Silakan gunakan format JPG, PNG, WEBP, GIF, atau JFIF.',
                    confirmButtonColor: '#7A1E33'
                });
            } else {
                alert('Format file "' + ext + '" tidak didukung. Silakan gunakan JPG, PNG, WEBP, GIF, atau JFIF.');
            }
            input.value = '';
            target.style.display = 'none';
            target.innerHTML = '';
            return;
        }

        // Validasi ukuran di frontend (maks 10MB)
        if (file.size > 10 * 1024 * 1024) {
            const sizeMB = (file.size / (1024 * 1024)).toFixed(2);
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Ukuran Gambar Terlalu Besar',
                    text: 'Ukuran file gambar maksimal 10MB. File Anda: ' + sizeMB + ' MB.',
                    confirmButtonColor: '#7A1E33'
                });
            } else {
                alert('Ukuran file gambar maksimal 10MB. File Anda: ' + sizeMB + ' MB.');
            }
            input.value = '';
            target.style.display = 'none';
            target.innerHTML = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            target.style.display = 'block';
            target.innerHTML = `
                <div class="d-flex align-items-center gap-2 p-2 border rounded-3 bg-light shadow-sm">
                    <img src="${e.target.result}" class="rounded border" style="width: 52px; height: 52px; object-fit: cover;">
                    <div class="small flex-grow-1 overflow-hidden">
                        <span class="fw-bold text-dark d-block text-truncate">${file.name}</span>
                        <span class="text-muted" style="font-size:0.75rem;">${(file.size / 1024).toFixed(1)} KB &middot; <span class="text-success fw-semibold"><i class="fa-solid fa-check-circle me-1"></i>Siap diupload</span></span>
                    </div>
                </div>
            `;
        };
        reader.readAsDataURL(file);
    } else {
        target.style.display = 'none';
        target.innerHTML = '';
    }
}

function filterTableLive(query) {
    if (query !== undefined) {
        sessionStorage.setItem('stok_barang_search', query);
    }
    const q = (query || '').toLowerCase().trim();
    const rows = document.querySelectorAll('tbody tr[id^="row-barang-"]');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        if (text.includes(q)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function previewImage(src, title) {
    document.getElementById('imagePreviewSrc').src = src;
    document.getElementById('imagePreviewTitle').innerHTML = `<i class="fa-solid fa-image me-2"></i> ${title || 'Preview Gambar Produk'}`;
    new bootstrap.Modal(document.getElementById('imagePreviewModal')).show();
}

function editBarang(barang) {
    document.getElementById('edit_id').value = barang.id;
    document.getElementById('edit_nama_barang').value = barang.nama_barang;

    // Reset input file & preview upload baru
    const fileInput = document.getElementById('edit_file_gambar');
    if (fileInput) fileInput.value = '';
    const newPreview = document.getElementById('edit_gambar_preview_new');
    if (newPreview) {
        newPreview.style.display = 'none';
        newPreview.innerHTML = '';
    }

    const hapusWrapper = document.getElementById('edit_hapus_gambar_wrapper');
    const hapusCheck = document.getElementById('hapusGambarCheck');
    if (hapusCheck) hapusCheck.checked = false;
    
    const preview = document.getElementById('edit_gambar_preview');
    if (barang.gambar) {
        preview.innerHTML = `
            <small class="text-muted d-block mb-1 fw-semibold">Gambar Saat Ini:</small>
            <div class="d-flex align-items-center gap-2 p-2 border rounded-3 bg-light">
                <img src="${barang.gambar}" class="rounded border shadow-sm" style="width: 52px; height: 52px; object-fit: cover; cursor: pointer;" onclick="previewImage('${barang.gambar}', '${barang.nama_barang.replace(/'/g, "\\'")}')" title="Klik untuk memperbesar">
                <div class="small">
                    <span class="text-dark fw-bold d-block">Gambar terpasang</span>
                    <span class="text-muted" style="font-size:0.75rem;">Klik gambar untuk memperbesar</span>
                </div>
            </div>
        `;
        if (hapusWrapper) hapusWrapper.style.display = 'block';
    } else {
        preview.innerHTML = '<span class="badge bg-secondary-subtle text-muted px-2.5 py-1 rounded-pill"><i class="fa-solid fa-circle-info me-1"></i> Belum ada gambar produk</span>';
        if (hapusWrapper) hapusWrapper.style.display = 'none';
    }

    const editModal = new bootstrap.Modal(document.getElementById('editBarangModal'));
    editModal.show();
}

document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const searchParam = urlParams.get('search');
    const isReset = urlParams.get('reset');
    
    if (isReset === '1') {
        sessionStorage.removeItem('stok_barang_search');
    } else if (searchParam !== null) {
        sessionStorage.setItem('stok_barang_search', searchParam);
    } else {
        const savedSearch = sessionStorage.getItem('stok_barang_search');
        if (savedSearch && savedSearch.trim() !== '') {
            window.location.href = 'stok_barang.php?search=' + encodeURIComponent(savedSearch);
            return;
        }
    }

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

<?php require_once __DIR__ . '/includes/footer.php'; ?>
