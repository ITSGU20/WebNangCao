<?php require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    requireAdmin();
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id) {
        $u = fetchOne("SELECT id,name,email,phone,address,role,active,created_at FROM users WHERE id=$id");
        respond($u ? ['ok'=>true,'data'=>$u] : ['ok'=>false,'msg'=>'Không tìm thấy']);
    }
    $rows = fetchAll("SELECT id,name,email,phone,address,role,active,created_at FROM users ORDER BY created_at DESC");
    respond(['ok'=>true,'data'=>$rows]);
}

if ($method === 'POST') {
    requireAdmin();
    $b = getBody();
    $name  = esc($b['name'] ?? '');
    $email = esc($b['email'] ?? '');
    $phone = esc($b['phone'] ?? '');
    $addr  = esc($b['address'] ?? '');
    $pass  = esc($b['password'] ?? '');
    $role  = esc($b['role'] ?? 'customer');
    if (!$name||!$email||!$pass) respond(['ok'=>false,'msg'=>'Thiếu thông tin bắt buộc']);
    if (fetchOne("SELECT id FROM users WHERE email='$email'")) respond(['ok'=>false,'msg'=>'Email đã được sử dụng']);
    q("INSERT INTO users (name,email,phone,address,password,role,active) VALUES ('$name','$email','$phone','$addr','$pass','$role',1)");
    $u = fetchOne("SELECT id,name,email,phone,address,role,active,created_at FROM users WHERE id=".lastId());
    respond(['ok'=>true,'data'=>$u,'msg'=>'Thêm người dùng thành công']);
}

if ($method === 'PUT') {
    requireAdmin();
    $id = (int)($_GET['id'] ?? 0);
    $b  = getBody();
    $action = $b['action'] ?? '';

    if ($action === 'toggle') {
        // Khóa / mở khóa tài khoản
        $cur = fetchOne("SELECT active FROM users WHERE id=$id");
        if (!$cur) respond(['ok'=>false,'msg'=>'Không tìm thấy']);
        $newActive = $cur['active'] ? 0 : 1;
        q("UPDATE users SET active=$newActive WHERE id=$id");
        respond(['ok'=>true,'msg'=>$newActive ? 'Đã mở khóa tài khoản' : 'Đã khóa tài khoản','active'=>$newActive]);
    }

    if ($action === 'reset_password') {
        $pass = esc($b['password'] ?? '123456');
        q("UPDATE users SET password='$pass' WHERE id=$id");
        respond(['ok'=>true,'msg'=>'Đã đặt lại mật khẩu']);
    }

    // Cập nhật thông tin
    $sets = [];
    foreach (['name','email','phone','address','role'] as $f) {
        if (isset($b[$f])) $sets[] = "$f='".esc($b[$f])."'";
    }
    if ($sets) q("UPDATE users SET ".implode(',',$sets)." WHERE id=$id");
    $u = fetchOne("SELECT id,name,email,phone,address,role,active,created_at FROM users WHERE id=$id");
    respond(['ok'=>true,'data'=>$u,'msg'=>'Cập nhật thành công']);
}
