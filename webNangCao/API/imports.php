<?php require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    requireAdmin();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id) {
        $imp = fetchOne("SELECT * FROM imports WHERE id=$id");
        if (!$imp) respond(['ok'=>false,'msg'=>'Không tìm thấy']);
        $imp['items'] = fetchAll("SELECT ii.*,p.name as product_name,p.emoji FROM import_items ii LEFT JOIN products p ON p.id=ii.product_id WHERE ii.import_id=$id");
        respond(['ok'=>true,'data'=>$imp]);
    }
    $rows = fetchAll("SELECT * FROM imports ORDER BY created_at DESC");
    foreach ($rows as &$r) $r['items'] = fetchAll("SELECT ii.*,p.name as product_name,p.emoji FROM import_items ii LEFT JOIN products p ON p.id=ii.product_id WHERE ii.import_id={$r['id']}");
    respond(['ok'=>true,'data'=>$rows]);
}

if ($method === 'POST') {
    requireAdmin();
    $b      = getBody();
    $action = $b['action'] ?? 'create';
    $date   = esc($b['date'] ?? date('Y-m-d'));
    $items  = $b['items'] ?? [];
    $status = esc($b['status'] ?? 'pending');

    if (empty($items)) respond(['ok'=>false,'msg'=>'Phiếu nhập phải có ít nhất 1 sản phẩm']);
    if (!$date)        respond(['ok'=>false,'msg'=>'Vui lòng chọn ngày nhập']);

    q("INSERT INTO imports (date,status) VALUES ('$date','$status')");
    $impId = lastId();

    foreach ($items as $it) {
        $pid   = (int)($it['productId'] ?? $it['product_id'] ?? 0);
        $qty   = (int)($it['qty'] ?? 0);
        $cost  = (float)($it['costPrice'] ?? $it['cost_price'] ?? 0);
        if (!$pid || $qty <= 0) continue;
        q("INSERT INTO import_items (import_id,product_id,qty,cost_price) VALUES ($impId,$pid,$qty,$cost)");
    }

    // Nếu completed ngay lập tức → cập nhật kho
    if ($status === 'completed') applyImport($impId);

    $imp = fetchOne("SELECT * FROM imports WHERE id=$impId");
    $imp['items'] = fetchAll("SELECT ii.*,p.name as product_name,p.emoji FROM import_items ii LEFT JOIN products p ON p.id=ii.product_id WHERE ii.import_id=$impId");
    respond(['ok'=>true,'data'=>$imp,'msg'=>$status==='completed'?'Đã hoàn thành phiếu nhập & cập nhật kho':'Đã lưu phiếu nhập nháp']);
}

if ($method === 'PUT') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    $b  = getBody();
    $action = $b['action'] ?? 'complete';

    // Sửa phiếu nhập (chỉ khi pending)
    if ($action === 'edit') {
        $imp = fetchOne("SELECT status FROM imports WHERE id=$id");
        if (!$imp) respond(['ok'=>false,'msg'=>'Không tìm thấy']);
        if ($imp['status'] === 'completed') respond(['ok'=>false,'msg'=>'Không thể sửa phiếu đã hoàn thành']);

        $date  = esc($b['date'] ?? '');
        $items = $b['items'] ?? [];
        if ($date) q("UPDATE imports SET date='$date' WHERE id=$id");

        // Xóa items cũ, thêm mới
        q("DELETE FROM import_items WHERE import_id=$id");
        foreach ($items as $it) {
            $pid  = (int)($it['productId'] ?? $it['product_id'] ?? 0);
            $qty  = (int)($it['qty'] ?? 0);
            $cost = (float)($it['costPrice'] ?? $it['cost_price'] ?? 0);
            if (!$pid || $qty <= 0) continue;
            q("INSERT INTO import_items (import_id,product_id,qty,cost_price) VALUES ($id,$pid,$qty,$cost)");
        }
        $imp = fetchOne("SELECT * FROM imports WHERE id=$id");
        $imp['items'] = fetchAll("SELECT ii.*,p.name as product_name FROM import_items ii LEFT JOIN products p ON p.id=ii.product_id WHERE ii.import_id=$id");
        respond(['ok'=>true,'data'=>$imp,'msg'=>'Đã cập nhật phiếu nhập']);
    }

    // Hoàn thành phiếu nhập
    $imp = fetchOne("SELECT * FROM imports WHERE id=$id");
    if (!$imp) respond(['ok'=>false,'msg'=>'Không tìm thấy']);
    if ($imp['status'] === 'completed') respond(['ok'=>false,'msg'=>'Phiếu đã được hoàn thành']);

    applyImport($id);
    q("UPDATE imports SET status='completed' WHERE id=$id");
    respond(['ok'=>true,'msg'=>'Đã hoàn thành phiếu nhập & cập nhật kho']);
}

if ($method === 'DELETE') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    $imp = fetchOne("SELECT status FROM imports WHERE id=$id");
    if (!$imp) respond(['ok'=>false,'msg'=>'Không tìm thấy']);
    if ($imp['status'] === 'completed') respond(['ok'=>false,'msg'=>'Không thể xóa phiếu đã hoàn thành']);
    q("DELETE FROM imports WHERE id=$id");
    respond(['ok'=>true,'msg'=>'Đã xóa phiếu nhập']);
}

// ---- Áp dụng phiếu nhập: tính giá bình quân + cập nhật kho ----
function applyImport($impId) {
    $items = fetchAll("SELECT * FROM import_items WHERE import_id=$impId");
    foreach ($items as $it) {
        $pid  = (int)$it['product_id'];
        $qty  = (int)$it['qty'];
        $cost = (float)$it['cost_price'];

        // Tính giá nhập bình quân
        $avgCost = calcAvgCost($pid, $qty, $cost);
        $avgCost = round($avgCost, 2);

        // Lấy profit_rate hiện tại để tính lại price
        $p = fetchOne("SELECT profit_rate FROM products WHERE id=$pid");
        $pr = $p ? (float)$p['profit_rate'] : 0;
        $newPrice = round($avgCost * (1 + $pr / 100));

        // Cập nhật giá nhập bình quân và giá bán
        q("UPDATE products SET cost_price=$avgCost, price=$newPrice WHERE id=$pid");

        // Cập nhật tồn kho + ghi lịch sử
        updateStock($pid, $qty, 'import', "Phiếu nhập #$impId", $impId);
    }
}
