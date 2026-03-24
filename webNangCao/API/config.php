<?php
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

// ---- Kết nối MySQL ----
$conn = mysqli_connect('localhost', 'root', '', 'lafleur');
if (!$conn) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'msg'=>'Lỗi kết nối database: '.mysqli_connect_error()]);
    exit;
}
mysqli_set_charset($conn, 'utf8mb4');

// ---- Helpers ----
function respond($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function getBody() {
    $raw = file_get_contents('php://input');
    return json_decode($raw, true) ?? [];
}

function requireLogin() {
    if (empty($_SESSION['user'])) respond(['ok'=>false,'msg'=>'Chưa đăng nhập'], 401);
    return $_SESSION['user'];
}

function requireAdmin() {
    $u = requireLogin();
    if ($u['role'] !== 'admin') respond(['ok'=>false,'msg'=>'Không có quyền admin'], 403);
    return $u;
}

function esc($v) {
    global $conn;
    return mysqli_real_escape_string($conn, $v ?? '');
}

function q($sql) {
    global $conn;
    $r = mysqli_query($conn, $sql);
    if (!$r) respond(['ok'=>false,'msg'=>'DB Error: '.mysqli_error($conn)], 500);
    return $r;
}

function fetchAll($sql) {
    return mysqli_fetch_all(q($sql), MYSQLI_ASSOC);
}

function fetchOne($sql) {
    $rows = fetchAll($sql);
    return $rows[0] ?? null;
}

function lastId() {
    global $conn;
    return mysqli_insert_id($conn);
}

// Cập nhật stock và ghi lịch sử
function updateStock($productId, $qtyChange, $type, $note, $refId = null) {
    $p = fetchOne("SELECT stock FROM products WHERE id=$productId");
    if (!$p) return;
    $before = (int)$p['stock'];
    $after  = max(0, $before + $qtyChange);
    q("UPDATE products SET stock=$after WHERE id=$productId");
    $refSql = $refId ? $refId : 'NULL';
    q("INSERT INTO stock_history (product_id,type,qty_change,stock_before,stock_after,note,ref_id)
       VALUES ($productId,'".esc($type)."',$qtyChange,$before,$after,'".esc($note)."',$refSql)");
}

// Tính giá nhập bình quân khi nhập hàng
// (ton * gia_cu + nhap * gia_moi) / (ton + nhap)
function calcAvgCost($productId, $newQty, $newCost) {
    $p = fetchOne("SELECT stock, cost_price, profit_rate FROM products WHERE id=$productId");
    if (!$p) return $newCost;
    $curStock = (int)$p['stock'];
    $curCost  = (float)$p['cost_price'];
    if ($curStock <= 0) return $newCost;
    return ($curStock * $curCost + $newQty * $newCost) / ($curStock + $newQty);
}
