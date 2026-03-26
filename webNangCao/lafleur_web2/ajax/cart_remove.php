<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/cart_helper.php';
session_init();
if (!auth_check()) json_response(['status'=>'login']);
cart_remove(sanitize_int($_POST['product_id']??0));
json_response(['status'=>'ok','cart_count'=>cart_count(),'cart_total'=>cart_total()]);
