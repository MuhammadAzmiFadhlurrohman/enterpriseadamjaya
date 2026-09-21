<?php
// ========================================================
// KONFIGURASI DATABASE SMART DUAL-ENVIRONMENT: ADAM JAYA
// ========================================================

// Aktifkan Error Reporting untuk diagnosa live server
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 0. Auto-Load file .env jika ada (DomaiNesia / Production)
if (file_exists(__DIR__ . '/../.env')) {
    $env_lines = @file(__DIR__ . '/../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
    foreach ($env_lines as $env_line) {
        $trimmed = trim($env_line);
        if ($trimmed === '' || strpos($trimmed, '#') === 0 || strpos($trimmed, '=') === false) continue;
        list($env_name, $env_val) = explode('=', $trimmed, 2);
        $env_name = trim($env_name);
        $env_val = trim($env_val, " \t\n\r\0\x0B\"'");
        if (!getenv($env_name)) {
            putenv("{$env_name}={$env_val}");
            $_ENV[$env_name] = $env_val;
            $_SERVER[$env_name] = $env_val;
        }
    }
}

// 1. Cek jika ada file konfigurasi khusus produksi di DomaiNesia/Hostinger (Di-ignore oleh Git)
if (file_exists(__DIR__ . '/database.production.php')) {
    require_once __DIR__ . '/database.production.php';
} else {
    // 2. Auto Detect Environment (Localhost XAMPP vs Production Live Domain)
    $http_host = $_SERVER['HTTP_HOST'] ?? '';
    $server_name = $_SERVER['SERVER_NAME'] ?? '';

    $is_local = (
        strpos($http_host, 'localhost') !== false ||
        strpos($http_host, '127.0.0.1') !== false ||
        strpos($server_name, 'localhost') !== false ||
        strpos($server_name, '127.0.0.1') !== false ||
        php_sapi_name() === 'cli'
    );

    // Ambil dari .env atau fallback default
    $env_db_host = getenv('DB_HOST') ?: ($_ENV['DB_HOST'] ?? null);
    $env_db_user = getenv('DB_USERNAME') ?: (getenv('DB_USER') ?: ($_ENV['DB_USERNAME'] ?? ($_ENV['DB_USER'] ?? null)));
    $env_db_pass = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : (getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($_ENV['DB_PASSWORD'] ?? ($_ENV['DB_PASS'] ?? null)));
    $env_db_name = getenv('DB_DATABASE') ?: (getenv('DB_NAME') ?: ($_ENV['DB_DATABASE'] ?? ($_ENV['DB_NAME'] ?? null)));

    if ($env_db_name && $env_db_user) {
        // === MENGGUNAKAN KONFIGURASI DARI .ENV ===
        defined('DB_HOST') || define('DB_HOST', $env_db_host ?: 'localhost');
        defined('DB_USER') || define('DB_USER', $env_db_user);
        defined('DB_PASS') || define('DB_PASS', $env_db_pass ?: '');
        defined('DB_NAME') || define('DB_NAME', $env_db_name);
    } elseif ($is_local) {
        // === LOCALHOST XAMPP ENVIRONMENT ===
        defined('DB_HOST') || define('DB_HOST', 'localhost');
        defined('DB_USER') || define('DB_USER', 'root');
        defined('DB_PASS') || define('DB_PASS', '');
        defined('DB_NAME') || define('DB_NAME', 'adamjaya_db');
    } else {
        // === DOMAINESIA / HOSTINGER PRODUCTION LIVE SERVER ===
        defined('DB_HOST') || define('DB_HOST', 'localhost');
        defined('DB_USER') || define('DB_USER', 'u198399581_adamjaya');
        defined('DB_PASS') || define('DB_PASS', '');
        defined('DB_NAME') || define('DB_NAME', 'u198399581_adamjaya');
    }

    // Inisialisasi Koneksi MySQLi
    $conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if (!$conn) {
        die("Koneksi database gagal: " . mysqli_connect_error() . "<br><small style='color:#b91c1c;'>Tips: Isi file <code>.env</code> atau buat <code>config/database.production.php</code> di hosting Anda.</small>");
    }

    mysqli_set_charset($conn, "utf8mb4");
}

// Global Timezone Enforcement (WIB / Asia/Jakarta GMT+7)
date_default_timezone_set('Asia/Jakarta');
if (isset($conn) && $conn) {
    mysqli_set_charset($conn, "utf8mb4");
    @mysqli_query($conn, "SET time_zone = '+07:00'");
}
