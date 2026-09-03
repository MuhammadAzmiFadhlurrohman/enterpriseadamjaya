<?php
require_once __DIR__ . '/../config/auth.php';
require_login();
$user = current_user();
$initial_letter = strtoupper(substr($user['username'], 0, 1));
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Adam Jaya Enterprise - Sistem Manajemen Operasional</title>
    
    <!-- Favicon / Logo Sidebar Tab Icon -->
    <link rel="icon" type="image/png" href="assets/adamjaya.png">
    <link rel="shortcut icon" type="image/png" href="assets/adamjaya.png">
    <link rel="apple-touch-icon" href="assets/adamjaya.png">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Custom Design Tokens -->
    <link rel="stylesheet" href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css'); ?>">
    
    <!-- Global JavaScript Helper Functions (Pre-loaded in Head) -->
    <script>
        function formatRupiahJS(angka, prefix = 'Rp ') {
            if (angka === null || angka === undefined || angka === '') return '';

            let num = null;
            if (typeof angka === 'number') {
                num = angka;
            } else {
                let strVal = String(angka).trim();
                if (strVal === '') return '';

                // MySQL decimal string or pure numeric like "100000.00" or "50.50"
                if (/^-?\d+(\.\d+)?$/.test(strVal) && !strVal.startsWith('Rp') && !strVal.includes(',')) {
                    num = parseFloat(strVal);
                } else {
                    let clean = strVal.replace(/[^0-9,-]/g, '').replace(',', '.');
                    num = parseFloat(clean);
                }
            }

            if (isNaN(num)) return '';

            // Bulatkan ke 2 desimal untuk menghilangkan artefak IEEE-754 (contoh: 4.4 * 55000 = 242000.00000000003)
            let rounded = Math.round(num * 100) / 100;

            // Jika bilangan bulat murni (tanpa pecahan sen)
            if (Math.abs(rounded - Math.round(rounded)) < 0.00001) {
                let intPart = String(Math.round(rounded)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                return prefix ? prefix + intPart : intPart;
            }

            // Jika terdapat pecahan sen non-nol
            let parts = rounded.toFixed(2).split('.');
            let intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            let decPart = ',' + parts[1].replace(/0+$/, '');
            return prefix ? prefix + intPart + decPart : intPart + decPart;
        }

        function unformatRupiah(str) {
            if (str === null || str === undefined || str === '') return 0;
            if (typeof str === 'number') return str;
            let s = String(str).trim();
            if (s === '') return 0;

            if (/^-?\d+\.\d+$/.test(s) && !s.includes(',') && !s.startsWith('Rp')) {
                return parseFloat(s) || 0;
            }

            let cleaned = s.replace(/[^0-9,-]/g, '').replace(',', '.');
            return parseFloat(cleaned) || 0;
        }

        function unformatRupiahJS(str) {
            return unformatRupiah(str);
        }

        function formatRupiah(num) {
            return formatRupiahJS(num, 'Rp ');
        }

        function formatRupiahInput(input) {
            if (!input) return;
            let val = input.value;
            let clean = String(val).replace(/\D/g, '');
            if (!clean) {
                input.value = '';
                return;
            }
            input.value = 'Rp ' + clean.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        window.unformatRupiah = unformatRupiah;
        window.unformatRupiahJS = unformatRupiah;
        window.formatRupiah = formatRupiah;
        window.formatRupiahJS = formatRupiahJS;
        window.formatRupiahInput = formatRupiahInput;
    </script>
</head>
<body>
    <!-- Top Global Progress Bar (Animated Load Indicator) -->
    <div id="global-progress-bar"></div>

    <!-- Floating Toast Container -->
    <div id="toast-container" class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 10800;"></div>

    <!-- Global Page & CRUD Action Loader (1 Detik Spinning Sidebar Logo Overlay) -->
    <div id="global-page-loader" class="login-loading-overlay" style="display: none;">
        <div class="login-loading-content text-center">
            <div class="login-spin-logo-wrapper">
                <img src="assets/adamjaya.png" alt="Adam Jaya Logo" class="login-spin-logo-img">
                <div class="login-spin-outer-ring"></div>
            </div>
            <h5 class="fw-bold text-white mb-1" style="font-family: 'Plus Jakarta Sans', sans-serif; letter-spacing: 0.04em;">ADAM JAYA ENTERPRISE</h5>
            <p id="global-loader-msg" class="text-gold small mb-0 fw-semibold" style="letter-spacing: 0.02em;">
                <i class="fa-solid fa-circle-notch fa-spin me-1"></i> Memuat data...
            </p>
        </div>
    </div>

    <!-- Slow Network / Connection Status Banner -->
    <div id="slow-net-banner" class="slow-net-banner" style="display: none;">
        <div class="d-flex align-items-center gap-2">
            <span class="spinner-border spinner-border-sm text-gold" role="status" aria-hidden="true"></span>
            <span id="slow-net-msg">Koneksi internet lambat / kurang stabil. Memuat data...</span>
        </div>
    </div>

    <div class="d-flex">
        <!-- Sidebar Navigation -->
        <?php include_once __DIR__ . '/sidebar.php'; ?>

        <!-- Main Workspace (Flex Grow 1 Min-Width 0 to fit perfectly alongside fixed Sidebar) -->
        <div id="main-content" class="flex-grow-1 min-w-0">
            <!-- Topbar Header -->
            <div class="top-navbar">
                <!-- Left: Mobile Toggle & Mobile Logo (Mobile Only) & Desktop Portal Tag -->
                <div class="d-flex align-items-center gap-3">
                    <!-- Toggle button ONLY on mobile (< 992px) -->
                    <button id="sidebar-toggle" class="btn-sidebar-toggle d-lg-none" title="Toggle Navigasi Sidebar">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    
                    <!-- Logo & Brand Title di Kanan Toggle (Dengan Spasi Lega Khusus Mobile) -->
                    <div class="d-flex align-items-center gap-2 d-lg-none ms-1 ps-1">
                        <img src="assets/adamjaya.png" alt="Adam Jaya" class="rounded-circle border border-warning shadow-sm" style="width: 32px; height: 32px; object-fit: contain; background: #ffffff;">
                        <span class="fw-bold text-white fs-6" style="letter-spacing: 0.05em; font-family: 'Plus Jakarta Sans', sans-serif;">ADAM JAYA</span>
                    </div>

                    <!-- System Portal Tag on Desktop -->
                    <div class="d-none d-lg-flex align-items-center">
                        <div class="topbar-portal-tag">
                            <i class="fa-solid fa-boxes-stacked me-2 text-gold"></i>
                            <span>PROCUREMENT & OPERATIONAL SYSTEM</span>
                        </div>
                    </div>
                </div>
                
                <!-- Right: Username, Avatar Initial Badge & Logout Button -->
                <div class="d-flex align-items-center gap-3">
                    <div class="d-flex align-items-center gap-2">
                        <!-- Username & Role (Berada di KIRI Inisial Avatar) -->
                        <div class="text-end">
                            <div class="user-name-text fw-bold text-white" style="font-size: 0.88rem; line-height: 1.1;"><?= e($user['username']); ?></div>
                            <div class="user-role-text text-gold small d-none d-sm-block" style="font-size: 0.7rem; font-weight: 600;"><?= e(strtoupper($user['role'])); ?></div>
                        </div>
                        
                        <!-- Avatar Initial Badge -->
                        <div class="user-initial-badge" title="User: <?= e($user['username']); ?> (<?= e(strtoupper($user['role'])); ?>)">
                            <?= $initial_letter; ?>
                        </div>
                    </div>

                    <!-- Logout Button -->
                    <a href="logout.php" class="btn-header-logout" title="Keluar dari Sistem">
                        <i class="fa-solid fa-right-from-bracket me-1"></i>
                        <span class="d-none d-md-inline">Logout</span>
                    </a>
                </div>
            </div>
