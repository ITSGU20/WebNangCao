<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cart_helper.php';
session_init();
if (!auth_check()) json_response(['status'=>'login']);
$productId = sanitize_int($_POST['product_id']??0);
$qty = max(1, sanitize_int($_POST['qty']??1));
if (!$productId) json_response(['status'=>'error','message'=>'Invalid product']);
$result = cart_add($productId, $qty);
json_response(['status'=>$result,'cart_count'=>cart_count()]);
