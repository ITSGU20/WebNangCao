<?php require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id     = isset($_GET['id'])      ? (int)$_GET['id']      : 0;
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

    if ($id) {
        $order = fetchOne("SELECT * FROM orders WHERE id=$id");
        if (!$order) respond(['ok'=>false,'msg'=>'Không tìm thấy']);
        $order['items'] = fetchAll("SELECT * FROM order_items WHERE order_id=$id");
        respond(['ok'=>true,'data'=>$order]);
    }

    if ($userId) {
        // Khách hàng xem đơn của mình
        requireLogin();
        $rows = fetchAll("SELECT * FROM orders WHERE user_id=$userId ORDER BY created_at DESC");
        foreach ($rows as &$o) $o['items'] = fetchAll("SELECT * FROM order_items WHERE order_id={$o['id']}");
        respond(['ok'=>true,'data'=>$rows]);
    }

    // Admin xem tất cả đơn
    requireAdmin();
    $rows = fetchAll("SELECT * FROM orders ORDER BY created_at DESC");
    foreach ($rows as &$o) $o['items'] = fetchAll("SELECT * FROM order_items WHERE order_id={$o['id']}");
    respond(['ok'=>true,'data'=>$rows]);
}

if ($method === 'POST') {
    $user = requireLogin();
    $b    = getBody();

    $userId  = (int)$user['id'];
    $uName   = esc($b['userName'] ?? $b['user_name'] ?? $user['name']);
    $phone   = esc($b['phone']   ?? $user['phone'] ?? '');
    $address = esc($b['address'] ?? '');
    $payment = esc($b['paymentMethod'] ?? $b['payment_method'] ?? 'Tiền mặt');
    $items   = $b['items'] ?? [];
    $total   = (float)($b['total'] ?? 0);

    if (!$address || empty($items)) respond(['ok'=>false,'msg'=>'Thiếu thông tin đơn hàng']);

    q("INSERT INTO orders (user_id,user_name,phone,address,payment_method,status,total)
       VALUES ($userId,'$uName','$phone','$address','$payment','new',$total)");
    $orderId = lastId();

    foreach ($items as $it) {
        $pid   = (int)($it['productId'] ?? $it['product_id'] ?? 0);
        $iName = esc($it['name'] ?? '');
        $qty   = (int)($it['qty'] ?? 1);
        $price = (float)($it['price'] ?? 0);
        q("INSERT INTO order_items (order_id,product_id,name,qty,price) VALUES ($orderId,$pid,'$iName',$qty,$price)");
        // Trừ tồn kho + ghi lịch sử
        updateStock($pid, -$qty, 'sale', "Đơn hàng #$orderId", $orderId);
    }

    // Xóa giỏ hàng sau khi đặt
    q("DELETE FROM cart WHERE user_id=$userId");

    $order = fetchOne("SELECT * FROM orders WHERE id=$orderId");
    $order['items'] = fetchAll("SELECT * FROM order_items WHERE order_id=$orderId");
    respond(['ok'=>true,'data'=>$order,'msg'=>'Đặt hàng thành công']);
}

if ($method === 'PUT') {
    requireAdmin();
    $b      = getBody();
    $id     = (int)($b['id'] ?? 0);
    $status = esc($b['status'] ?? '');
    if (!$id || !$status) respond(['ok'=>false,'msg'=>'Thiếu thông tin']);

    $order = fetchOne("SELECT * FROM orders WHERE id=$id");
    if (!$order) respond(['ok'=>false,'msg'=>'Không tìm thấy đơn hàng']);

    // Khi hủy đơn: hoàn lại tồn kho
    if ($status === 'cancelled' && $order['status'] !== 'cancelled') {
        $items = fetchAll("SELECT * FROM order_items WHERE order_id=$id");
        foreach ($items as $it) {
            updateStock((int)$it['product_id'], (int)$it['qty'], 'cancel', "Hủy đơn #$id", $id);
        }
    }

    q("UPDATE orders SET status='$status' WHERE id=$id");
    respond(['ok'=>true,'msg'=>'Cập nhật trạng thái thành công']);
}
