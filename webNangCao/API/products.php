<?php require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id      = isset($_GET['id'])      ? (int)$_GET['id']      : 0;
    $catId   = isset($_GET['cat_id'])  ? (int)$_GET['cat_id']  : 0;
    $q       = isset($_GET['q'])       ? esc($_GET['q'])        : '';
    $minP    = isset($_GET['min_price'])? (float)$_GET['min_price'] : 0;
    $maxP    = isset($_GET['max_price'])? (float)$_GET['max_price'] : 0;

    $where = ['1=1'];
    if ($id)    { $row = fetchOne("SELECT p.*,c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.cat_id WHERE p.id=$id"); respond($row?['ok'=>true,'data'=>$row]:['ok'=>false,'msg'=>'Không tìm thấy']); }
    if ($catId) $where[] = "p.cat_id=$catId AND p.active=1";
    else        $where[] = "p.active=1";
    if ($q)     $where[] = "p.name LIKE '%$q%'";
    if ($minP)  $where[] = "p.price>=$minP";
    if ($maxP)  $where[] = "p.price<=$maxP";

    // Admin: lấy tất cả kể cả ẩn
    $isAdmin = !empty($_SESSION['user']) && $_SESSION['user']['role']==='admin';
    if ($isAdmin && !$catId && !$q && !$minP && !$maxP) {
        $rows = fetchAll("SELECT p.*,c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.cat_id ORDER BY p.id");
        respond(['ok'=>true,'data'=>$rows]);
    }

    $sql = "SELECT p.*,c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.cat_id WHERE ".implode(' AND ',$where)." ORDER BY p.name";
    respond(['ok'=>true,'data'=>fetchAll($sql)]);
}

if ($method === 'POST') {
    requireAdmin();

    // Upload hình ảnh nếu có
    $imageName = null;
    if (!empty($_FILES['image']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext,['jpg','jpeg','png','gif','webp'])) respond(['ok'=>false,'msg'=>'Định dạng ảnh không hợp lệ']);
        $imageName = 'prod_'.time().'_'.rand(100,999).'.'.$ext;
        $dest = dirname(__DIR__).'/uploads/products/'.$imageName;
        if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) respond(['ok'=>false,'msg'=>'Lỗi upload ảnh']);
        // POST từ form có $_POST
        $b = $_POST;
    } else {
        $b = getBody();
    }

    $catId      = (int)($b['cat_id'] ?? 0);
    $code       = esc($b['code'] ?? '');
    $name       = esc($b['name'] ?? '');
    $emoji      = esc($b['emoji'] ?? '');
    $unit       = esc($b['unit'] ?? 'cái');
    $costPrice  = (float)($b['cost_price'] ?? 0);
    $profitRate = (float)($b['profit_rate'] ?? 0);
    $price      = round($costPrice * (1 + $profitRate / 100));
    $stock      = (int)($b['stock'] ?? 0);
    $desc       = esc($b['description'] ?? $b['desc'] ?? '');
    $active     = isset($b['active']) ? (int)$b['active'] : 1;
    $imgSql     = $imageName ? "'$imageName'" : 'NULL';

    if (!$name || !$catId) respond(['ok'=>false,'msg'=>'Thiếu thông tin bắt buộc']);

    q("INSERT INTO products (cat_id,code,name,emoji,unit,cost_price,profit_rate,price,stock,description,image,active)
       VALUES ($catId,'$code','$name','$emoji','$unit',$costPrice,$profitRate,$price,$stock,'$desc',$imgSql,$active)");
    $row = fetchOne("SELECT p.*,c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.cat_id WHERE p.id=".lastId());
    respond(['ok'=>true,'data'=>$row,'msg'=>'Thêm sản phẩm thành công']);
}

if ($method === 'PUT') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respond(['ok'=>false,'msg'=>'Thiếu ID']);

    // Upload hình ảnh mới nếu có
    $imageName = null;
    if (!empty($_FILES['image']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $imageName = 'prod_'.time().'_'.rand(100,999).'.'.$ext;
        move_uploaded_file($_FILES['image']['tmp_name'], dirname(__DIR__).'/uploads/products/'.$imageName);
        $b = $_POST;
    } else {
        $b = getBody();
    }

    $sets = [];
    if (isset($b['cat_id']))      $sets[] = "cat_id=".(int)$b['cat_id'];
    if (isset($b['code']))        $sets[] = "code='".esc($b['code'])."'";
    if (isset($b['name']))        $sets[] = "name='".esc($b['name'])."'";
    if (isset($b['emoji']))       $sets[] = "emoji='".esc($b['emoji'])."'";
    if (isset($b['unit']))        $sets[] = "unit='".esc($b['unit'])."'";
    if (isset($b['profit_rate'])) $sets[] = "profit_rate=".(float)$b['profit_rate'];
    if (isset($b['description'])||isset($b['desc'])) $sets[] = "description='".esc($b['description']??$b['desc'])."'";
    if (isset($b['active']))      $sets[] = "active=".(int)$b['active'];
    if (isset($b['stock']))       $sets[] = "stock=".(int)$b['stock'];
    if ($imageName)               $sets[] = "image='$imageName'";
    if (isset($b['remove_image']) && $b['remove_image']) $sets[] = "image=NULL";

    // Nếu sửa cost_price hoặc profit_rate thì tính lại price
    if (isset($b['cost_price']) || isset($b['profit_rate'])) {
        $cur = fetchOne("SELECT cost_price,profit_rate FROM products WHERE id=$id");
        $cp  = isset($b['cost_price'])  ? (float)$b['cost_price']  : (float)$cur['cost_price'];
        $pr  = isset($b['profit_rate']) ? (float)$b['profit_rate'] : (float)$cur['profit_rate'];
        $sets[] = "cost_price=$cp";
        $sets[] = "profit_rate=$pr";
        $sets[] = "price=".round($cp * (1 + $pr/100));
    }

    if ($sets) q("UPDATE products SET ".implode(',',$sets)." WHERE id=$id");
    $row = fetchOne("SELECT p.*,c.name as cat_name FROM products p LEFT JOIN categories c ON c.id=p.cat_id WHERE p.id=$id");
    respond(['ok'=>true,'data'=>$row,'msg'=>'Cập nhật thành công']);
}

if ($method === 'DELETE') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respond(['ok'=>false,'msg'=>'Thiếu ID']);

    // Nếu đã từng nhập hàng → chỉ ẩn, không xóa hẳn
    $hasImport = fetchOne("SELECT id FROM import_items WHERE product_id=$id LIMIT 1");
    if ($hasImport) {
        q("UPDATE products SET active=0 WHERE id=$id");
        respond(['ok'=>true,'msg'=>'Sản phẩm đã được ẩn (đã có lịch sử nhập hàng)','hidden'=>true]);
    } else {
        q("DELETE FROM products WHERE id=$id");
        respond(['ok'=>true,'msg'=>'Đã xóa sản phẩm','deleted'=>true]);
    }
}
