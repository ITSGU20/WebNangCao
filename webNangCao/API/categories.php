<?php require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id) {
        $row = fetchOne("SELECT * FROM categories WHERE id=$id");
        respond($row ? ['ok'=>true,'data'=>$row] : ['ok'=>false,'msg'=>'Không tìm thấy']);
    }
    $rows = fetchAll("SELECT * FROM categories ORDER BY id");
    respond(['ok'=>true,'data'=>$rows]);
}

if ($method === 'POST') {
    requireAdmin();
    $b = getBody();
    $name = esc($b['name'] ?? '');
    $emoji = esc($b['emoji'] ?? '');
    $desc  = esc($b['description'] ?? $b['desc'] ?? '');
    $active = isset($b['active']) ? (int)$b['active'] : 1;
    if (!$name) respond(['ok'=>false,'msg'=>'Tên danh mục không được trống']);
    q("INSERT INTO categories (name,emoji,description,active) VALUES ('$name','$emoji','$desc',$active)");
    $row = fetchOne("SELECT * FROM categories WHERE id=".lastId());
    respond(['ok'=>true,'data'=>$row,'msg'=>'Thêm danh mục thành công']);
}

if ($method === 'PUT') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    $b  = getBody();
    if (!$id) respond(['ok'=>false,'msg'=>'Thiếu ID']);
    $name   = esc($b['name'] ?? '');
    $emoji  = esc($b['emoji'] ?? '');
    $desc   = esc($b['description'] ?? $b['desc'] ?? '');
    $active = isset($b['active']) ? (int)$b['active'] : 1;
    if (!$name) respond(['ok'=>false,'msg'=>'Tên danh mục không được trống']);
    q("UPDATE categories SET name='$name',emoji='$emoji',description='$desc',active=$active WHERE id=$id");
    $row = fetchOne("SELECT * FROM categories WHERE id=$id");
    respond(['ok'=>true,'data'=>$row,'msg'=>'Cập nhật thành công']);
}

if ($method === 'DELETE') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) respond(['ok'=>false,'msg'=>'Thiếu ID']);
    q("DELETE FROM categories WHERE id=$id");
    respond(['ok'=>true,'msg'=>'Đã xóa danh mục']);
}
