<?php
require_once __DIR__ . '/config.php';

// ============================================================
// OUTPUT / SECURITY
// ============================================================
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
function redirect(string $url): never { header('Location: ' . $url); exit; }
function json_response(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// FORMATTING
// ============================================================
function format_currency(float $n): string {
    return number_format($n, 0, ',', '.') . ' đ';
}
function format_date(?string $d): string {
    if (!$d) return '—';
    try { return (new DateTime($d))->format('d/m/Y'); } catch (Exception) { return $d; }
}
function format_datetime(?string $d): string {
    if (!$d) return '—';
    try { return (new DateTime($d))->format('d/m/Y H:i'); } catch (Exception) { return $d; }
}

// ============================================================
// PRODUCT PRICE LOGIC
// Giá bán = giá nhập * (1 + tỉ lệ lợi nhuận / 100)
// ============================================================
function calc_sell_price(float $costPrice, float $profitRate): float {
    return round($costPrice * (1 + $profitRate / 100));
}
// Giá vốn bình quân: (tồn cũ * giá cũ + nhập mới * giá mới) / (tồn cũ + nhập mới)
function weighted_avg_cost(float $oldStock, float $oldCost, int $newQty, float $newCost): float {
    if ($oldStock <= 0) return $newCost;
    return round(($oldStock * $oldCost + $newQty * $newCost) / ($oldStock + $newQty), 2);
}

// ============================================================
// PAGINATION HTML
// ============================================================
function render_pagination(array $paged, string $baseUrl, array $extraParams = []): string {
    if ($paged['pages'] <= 1) return '';
    $html = '<div class="pagination">';
    for ($i = 1; $i <= $paged['pages']; $i++) {
        $extraParams['page'] = $i;
        $url = $baseUrl . '?' . http_build_query($extraParams);
        $cls = $i === $paged['page'] ? ' active' : '';
        $html .= "<a href=\"{$url}\" class=\"pg-btn{$cls}\">{$i}</a>";
    }
    $html .= '</div>';
    return $html;
}

// ============================================================
// STATUS LABELS
// ============================================================
function order_status_label(string $status): array {
    return match($status) {
        'new'        => ['text' => 'Mới đặt',    'cls' => 'badge-info'],
        'processing' => ['text' => 'Đang xử lý', 'cls' => 'badge-warning'],
        'delivered'  => ['text' => 'Đã giao',    'cls' => 'badge-success'],
        'cancelled'  => ['text' => 'Đã hủy',     'cls' => 'badge-danger'],
        default      => ['text' => $status,       'cls' => 'badge-secondary'],
    };
}
function payment_label(string $method): string {
    return match($method) {
        'cash'     => 'Tiền mặt',
        'transfer' => 'Chuyển khoản',
        'online'   => 'Trực tuyến',
        default    => $method,
    };
}

// ============================================================
// IMAGE UPLOAD (GD resize)
// ============================================================
function upload_product_image(array $file): string {
    if ($file['error'] !== UPLOAD_ERR_OK) return '';
    if ($file['size'] > MAX_FILE_SIZE) return '';
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, ALLOWED_TYPES)) return '';

    $ext = match($mime) {
        'image/jpeg' => 'jpg', 'image/png' => 'png',
        'image/webp' => 'webp', default => 'jpg',
    };
    $filename = uniqid('img_', true) . '.' . $ext;
    $dest     = UPLOAD_DIR . $filename;
    if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

    $src = match($mime) {
        'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => @imagecreatefrompng($file['tmp_name']),
        'image/webp' => @imagecreatefromwebp($file['tmp_name']),
        default      => false,
    };
    if (!$src) { move_uploaded_file($file['tmp_name'], $dest); return $filename; }

    [$w, $h] = [imagesx($src), imagesy($src)];
    $ratio = min(IMG_MAX_W / $w, IMG_MAX_H / $h, 1);
    $nw = (int)round($w * $ratio);
    $nh = (int)round($h * $ratio);
    $dst = imagecreatetruecolor($nw, $nh);
    if ($mime === 'image/png') { imagealphablending($dst, false); imagesavealpha($dst, true); }
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    match($mime) {
        'image/jpeg' => imagejpeg($dst, $dest, 85),
        'image/png'  => imagepng($dst, $dest, 8),
        'image/webp' => imagewebp($dst, $dest, 85),
        default      => imagejpeg($dst, $dest, 85),
    };
    imagedestroy($src); imagedestroy($dst);
    return $filename;
}
function delete_product_image(string $filename): void {
    if ($filename && file_exists(UPLOAD_DIR . $filename)) unlink(UPLOAD_DIR . $filename);
}

// ============================================================
// VALIDATION
// ============================================================
function validate_phone(string $phone): bool { return (bool)preg_match('/^0\d{9}$/', $phone); }
function validate_email(string $email): bool { return (bool)filter_var($email, FILTER_VALIDATE_EMAIL); }
function sanitize_int(mixed $v, int $default = 0): int {
    return filter_var($v, FILTER_VALIDATE_INT) !== false ? (int)$v : $default;
}
function sanitize_float(mixed $v, float $default = 0.0): float {
    return filter_var($v, FILTER_VALIDATE_FLOAT) !== false ? (float)$v : $default;
}

// ============================================================
// CSRF
// ============================================================
function csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['_csrf'])) $_SESSION['_csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['_csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="_csrf" value="' . h(csrf_token()) . '">'; }
function csrf_verify(): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return hash_equals($_SESSION['_csrf'] ?? '', $_POST['_csrf'] ?? '');
}
