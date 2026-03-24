<?php require_once 'config.php';

requireAdmin();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $action = $_GET['action'] ?? 'list';

    // Tồn kho tại 1 thời điểm do người dùng chỉ định
    if ($action === 'at_date') {
        $pid  = (int)($_GET['product_id'] ?? 0);
        $date = esc($_GET['date'] ?? '');
        if (!$pid || !$date) respond(['ok'=>false,'msg'=>'Thiếu product_id hoặc date']);

        // Lấy stock_after gần nhất trước hoặc bằng thời điểm chỉ định
        $row = fetchOne("SELECT stock_after FROM stock_history WHERE product_id=$pid AND created_at<='$date 23:59:59' ORDER BY created_at DESC LIMIT 1");
        $stock = $row ? (int)$row['stock_after'] : 0;
        respond(['ok'=>true,'data'=>['stock'=>$stock,'date'=>$date]]);
    }

    // Báo cáo nhập-xuất theo khoảng thời gian
    if ($action === 'report') {
        $from = esc($_GET['from'] ?? '');
        $to   = esc($_GET['to'] ?? '');
        $pid  = isset($_GET['product_id']) ? (int)$_GET['product_id'] : 0;

        $where = ['1=1'];
        if ($from) $where[] = "sh.created_at>='$from 00:00:00'";
        if ($to)   $where[] = "sh.created_at<='$to 23:59:59'";
        if ($pid)  $where[] = "sh.product_id=$pid";

        $sql = "SELECT sh.product_id, p.name as product_name, p.emoji,
                       SUM(CASE WHEN sh.qty_change>0 THEN sh.qty_change ELSE 0 END) as total_import,
                       SUM(CASE WHEN sh.qty_change<0 THEN ABS(sh.qty_change) ELSE 0 END) as total_export
                FROM stock_history sh
                LEFT JOIN products p ON p.id=sh.product_id
                WHERE ".implode(' AND ',$where)."
                GROUP BY sh.product_id, p.name, p.emoji
                ORDER BY p.name";
        respond(['ok'=>true,'data'=>fetchAll($sql)]);
    }

    // Lịch sử tồn kho theo sản phẩm
    if ($action === 'history') {
        $pid = (int)($_GET['product_id'] ?? 0);
        if (!$pid) respond(['ok'=>false,'msg'=>'Thiếu product_id']);
        $rows = fetchAll("SELECT * FROM stock_history WHERE product_id=$pid ORDER BY created_at DESC LIMIT 100");
        respond(['ok'=>true,'data'=>$rows]);
    }

    // Mặc định: danh sách tồn kho hiện tại
    $rows = fetchAll("SELECT p.*,c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.cat_id ORDER BY p.stock ASC");
    respond(['ok'=>true,'data'=>$rows]);
}

if ($method === 'POST') {
    $b     = getBody();
    $pid   = (int)($b['productId'] ?? 0);
    $type  = esc($b['type'] ?? 'add');   // add | subtract | set
    $qty   = (int)($b['qty'] ?? 0);
    $note  = esc($b['note'] ?? 'Điều chỉnh thủ công');

    if (!$pid) respond(['ok'=>false,'msg'=>'Thiếu sản phẩm']);
    $p = fetchOne("SELECT stock FROM products WHERE id=$pid");
    if (!$p) respond(['ok'=>false,'msg'=>'Sản phẩm không tồn tại']);

    $before = (int)$p['stock'];
    $after  = $before;
    $change = 0;
    if ($type==='add')      { $after = $before + $qty;          $change = $qty; }
    if ($type==='subtract') { $after = max(0,$before - $qty);   $change = -($before - $after); }
    if ($type==='set')      { $after = $qty;                    $change = $qty - $before; }

    q("UPDATE products SET stock=$after WHERE id=$pid");
    q("INSERT INTO stock_history (product_id,type,qty_change,stock_before,stock_after,note)
       VALUES ($pid,'adjust',$change,$before,$after,'$note')");
    respond(['ok'=>true,'msg'=>"Đã cập nhật tồn kho: $before → $after",'before'=>$before,'after'=>$after]);
}

// Lấy / cập nhật cài đặt ngưỡng cảnh báo
if ($method === 'PUT') {
    $b = getBody();
    if (isset($b['low_stock_threshold'])) {
        $v = (int)$b['low_stock_threshold'];
        q("INSERT INTO settings (`key`,`value`) VALUES ('low_stock_threshold','$v') ON DUPLICATE KEY UPDATE `value`='$v'");
    }
    respond(['ok'=>true,'msg'=>'Đã cập nhật cài đặt']);
}
