<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cart_helper.php';

session_init();
if (!auth_check()) json_response(['status' => 'login']);

// Clear entire cart
if (isset($_POST['clear'])) {
    cart_clear();
    json_response(['status' => 'ok', 'cart_count' => 0, 'cart_total' => 0]);
}

$productId = sanitize_int($_POST['product_id'] ?? 0);
$qty       = sanitize_int($_POST['qty'] ?? 0);
if (!$productId) json_response(['status' => 'error', 'message' => 'Invalid product']);
cart_update($productId, $qty);
json_response(['status' => 'ok', 'cart_count' => cart_count(), 'cart_total' => cart_total()]);
