<?php
// ============================================================
// LA FLEUR - CẤU HÌNH HỆ THỐNG
// ============================================================

// --- Database ---
define('DB_HOST', 'localhost');
define('DB_NAME', 'lafleur');
define('DB_USER', 'root');       // Thay bằng user thực tế trên host
define('DB_PASS', '');           // Thay bằng password thực tế
define('DB_CHARSET', 'utf8mb4');

// --- Đường dẫn (tương đối) ---
define('BASE_URL', '/lafleur');                     // Thư mục gốc trên server
define('UPLOAD_DIR', __DIR__ . '/../uploads/products/');
define('UPLOAD_URL', BASE_URL . '/uploads/products/');
define('ADMIN_URL',  BASE_URL . '/admin');

// --- Upload settings ---
define('MAX_FILE_SIZE', 5 * 1024 * 1024);  // 5MB
define('ALLOWED_TYPES', ['image/jpeg','image/png','image/webp','image/gif']);
define('IMG_MAX_W', 800);   // max width sau khi resize
define('IMG_MAX_H', 800);

// --- Phân trang ---
define('PAGE_SIZE', 8);           // SP/trang cho khách hàng
define('ADMIN_PAGE_SIZE', 15);    // rows/trang cho admin

// --- Session ---
define('SESSION_USER', 'lf_user');
define('SESSION_ADMIN', 'lf_admin');
define('SESSION_CART', 'lf_cart');

// --- Timezone ---
date_default_timezone_set('Asia/Ho_Chi_Minh');
