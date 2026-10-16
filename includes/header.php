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
        function parseQtyJS(val) {
            if (val === null || val === undefined || val === '') return 0;
            if (typeof val === 'number') return isNaN(val) ? 0 : val;
            let s = String(val).trim();
            if (s === '') return 0;

            if (s.indexOf('.') !== -1 && s.indexOf(',') !== -1) {
                if (s.lastIndexOf(',') > s.lastIndexOf('.')) {
                    s = s.replace(/\./g, '').replace(',', '.');
                } else {
                    s = s.replace(/,/g, '');
                }
            } else if (s.indexOf(',') !== -1) {
                s = s.replace(',', '.');
            }
            return parseFloat(s) || 0;
        }

        function formatRupiahJS(angka, prefix = 'Rp ') {
            if (angka === null || angka === undefined || angka === '') return (prefix || '') + '0';

            let num = null;
            if (typeof angka === 'number') {
                num = angka;
            } else {
                let strVal = String(angka).trim();
                if (strVal === '') return (prefix || '') + '0';

                if (/^-?\d+(\.\d+)?$/.test(strVal) && !strVal.startsWith('Rp') && !strVal.includes(',')) {
                    num = parseFloat(strVal);
                } else {
                    num = unformatRupiahJS(strVal);
                }
            }

            if (isNaN(num)) return (prefix || '') + '0';

            let isNeg = num < 0;
            let absNum = Math.abs(num);
            let rounded = Math.round(absNum * 100) / 100;

            if (Math.abs(rounded - Math.round(rounded)) < 0.00001) {
                let intPart = String(Math.round(rounded)).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
                return (isNeg ? '-' : '') + (prefix ? prefix + intPart : intPart);
            }

            let parts = rounded.toFixed(2).split('.');
            let intPart = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
            let decPart = ',' + parts[1];
            return (isNeg ? '-' : '') + (prefix ? prefix + intPart + decPart : intPart + decPart);
        }

        function unformatRupiah(str) {
            if (str === null || str === undefined || str === '') return 0;
            if (typeof str === 'number') return isNaN(str) ? 0 : str;
            let s = String(str).trim();
            if (s === '') return 0;

            if (/^-?\d+(\.\d+)?$/.test(s) && !s.includes(',') && !s.startsWith('Rp')) {
                return parseFloat(s) || 0;
            }

            let cleaned = s.replace(/[^0-9,.-]/g, '');
            if (!cleaned) return 0;

            if (cleaned.includes('.') && cleaned.includes(',')) {
                if (cleaned.lastIndexOf(',') > cleaned.lastIndexOf('.')) {
                    cleaned = cleaned.replace(/\./g, '').replace(',', '.');
                } else {
                    cleaned = cleaned.replace(/,/g, '');
                }
            } else if (cleaned.includes(',')) {
                cleaned = cleaned.replace(',', '.');
            } else if (cleaned.includes('.')) {
                if (/^\d+\.\d{1,2}$/.test(cleaned)) {
                    // 1-2 desimal
                } else {
                    cleaned = cleaned.replace(/\./g, '');
                }
            }

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
            if (val === null || val === undefined || val === '') return;

            let isNeg = String(val).trim().startsWith('-');
            let s = String(val).replace(/^Rp\s*/i, '').replace(/[^0-9,.-]/g, '');
            if (!s) {
                input.value = '';
                return;
            }

            let hasComma = s.includes(',');
            let intPart = '';
            let decPart = '';
            let trailingComma = false;

            if (hasComma) {
                let parts = s.split(',');
                intPart = parts[0].replace(/\D/g, '');
                decPart = parts.slice(1).join('').replace(/\D/g, '').slice(0, 4);
                trailingComma = s.endsWith(',') && decPart.length === 0;
            } else {
                if (s.endsWith('.')) {
                    intPart = s.slice(0, -1).replace(/\D/g, '');
                    trailingComma = true;
                } else if (/^\d+\.\d{1,2}$/.test(s)) {
                    let parts = s.split('.');
                    intPart = parts[0];
                    decPart = parts[1];
                } else {
                    intPart = s.replace(/\D/g, '');
                }
            }

            if (!intPart && !decPart && !trailingComma) {
                input.value = '';
                return;
            }

            let formattedInt = intPart ? intPart.replace(/\B(?=(\d{3})+(?!\d))/g, '.') : '0';
            let res = 'Rp ' + (isNeg ? '-' : '') + formattedInt;
            if (trailingComma) {
                res += ',';
            } else if (decPart.length > 0) {
                res += ',' + decPart;
            }

            input.value = res;
        }

        window.parseQty = parseQtyJS;
        window.parseQtyJS = parseQtyJS;
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
