<?php
// Konfigurasi umum aplikasi
define('APP_NAME', 'Inventaris Perpustakaan');
define('APP_URL', 'http://localhost/inventaris-peminjaman/');
define('BASE_PATH', dirname(__DIR__));

// Konfigurasi database
define('DB_HOST', 'localhost');
define('DB_NAME', 'perpustakaan_inventaris');
define('DB_USER', 'root');
define('DB_PASS', '');

// Konfigurasi session
define('SESSION_LIFETIME', 3600); // 1 jam
define('SESSION_COOKIE_HTTPONLY', true);
define('SESSION_COOKIE_SAMESITE', 'Strict');

// Konfigurasi keamanan
define('CSRF_TOKEN_LENGTH', 32);
define('PASSWORD_RESET_TOKEN_LENGTH', 64);
define('PASSWORD_RESET_EXPIRE', 3600); // 1 jam

// Konfigurasi upload file
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']);
define('UPLOAD_PATH', BASE_PATH . '/uploads/');

// Konfigurasi pagination
define('ITEMS_PER_PAGE', 10);

// Konfigurasi denda
define('LATE_FINE_PER_DAY', 1000); // Rupiah per hari
?>