<?php require_once 'config.php';

$user = requireLogin();
$uid  = (int)$user['id'];

$method = $_SERVER['REQUEST_METHOD'];
$b      = getBody();
$action = $b['action'] ?? '';

function getCart($uid) {
    return fetchAll("SELECT c.product_id, c.qty, p.name, p.emoji, p.price, p.stock, p.image FROM cart c JOIN products p ON p.id=c.product_id WHERE c.user_id=$uid");
}

if ($method === 'GET') {
    respond(['ok'=>true,'data'=>getCart($uid)]);
}

if ($method === 'POST') {
    $pid = (int)($b['productId'] ?? 0);
    $qty = (int)($b['qty'] ?? 1);

    if ($action === 'add') {
        $p = fetchOne("SELECT id,stock FROM products WHERE id=$pid AND active=1");
        if (!$p) respond(['ok'=>false,'msg'=>'Sản phẩm không tồn tại']);
        $existing = fetchOne("SELECT qty FROM cart WHERE user_id=$uid AND product_id=$pid");
        if ($existing) {
            $newQty = min((int)$existing['qty'] + $qty, (int)$p['stock']);
            q("UPDATE cart SET qty=$newQty WHERE user_id=$uid AND product_id=$pid");
        } else {
            q("INSERT INTO cart (user_id,product_id,qty) VALUES ($uid,$pid,$qty)");
        }
        respond(['ok'=>true,'data'=>getCart($uid)]);
    }

    if ($action === 'update') {
        if ($qty <= 0) q("DELETE FROM cart WHERE user_id=$uid AND product_id=$pid");
        else           q("UPDATE cart SET qty=$qty WHERE user_id=$uid AND product_id=$pid");
        respond(['ok'=>true,'data'=>getCart($uid)]);
    }

    if ($action === 'remove') {
        q("DELETE FROM cart WHERE user_id=$uid AND product_id=$pid");
        respond(['ok'=>true,'data'=>getCart($uid)]);
    }

    if ($action === 'clear') {
        q("DELETE FROM cart WHERE user_id=$uid");
        respond(['ok'=>true,'data'=>[]]);
    }
}
