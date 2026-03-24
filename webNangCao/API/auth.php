<?php require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? (getBody()['action'] ?? '');

if ($method === 'GET') {
    // GET ?action=current
    if ($action === 'current') {
        if (empty($_SESSION['user'])) respond(['ok'=>true,'data'=>null]);
        // Lấy user mới nhất từ DB
        $id = (int)$_SESSION['user']['id'];
        $u  = fetchOne("SELECT id,name,email,phone,address,role,active FROM users WHERE id=$id");
        $_SESSION['user'] = $u;
        respond(['ok'=>true,'data'=>$u]);
    }
    respond(['ok'=>false,'msg'=>'Action không hợp lệ'], 400);
}

if ($method === 'POST') {
    $body = getBody();
    $action = $body['action'] ?? '';

    // ---- ĐĂNG NHẬP ----
    if ($action === 'login') {
        $email   = esc($body['email'] ?? '');
        $pass    = esc($body['password'] ?? '');
        $isAdmin = !empty($body['isAdmin']);

        $u = fetchOne("SELECT * FROM users WHERE email='$email' AND password='$pass'");
        if (!$u)                          respond(['ok'=>false,'msg'=>'Email hoặc mật khẩu không đúng.']);
        if (!$u['active'])                respond(['ok'=>false,'msg'=>'Tài khoản đã bị khóa.']);
        if ($isAdmin && $u['role']!=='admin')   respond(['ok'=>false,'msg'=>'Không có quyền truy cập admin.']);
        if (!$isAdmin && $u['role']==='admin')  respond(['ok'=>false,'msg'=>'Vui lòng dùng trang đăng nhập admin.']);

        unset($u['password']);
        $_SESSION['user'] = $u;
        respond(['ok'=>true,'data'=>$u]);
    }

    // ---- ĐĂNG XUẤT ----
    if ($action === 'logout') {
        session_destroy();
        respond(['ok'=>true]);
    }

    // ---- ĐĂNG KÝ ----
    if ($action === 'register') {
        $name    = esc($body['name'] ?? '');
        $email   = esc($body['email'] ?? '');
        $phone   = esc($body['phone'] ?? '');
        $address = esc($body['address'] ?? '');
        $pass    = esc($body['password'] ?? '');

        if (!$name || !$email || !$pass) respond(['ok'=>false,'msg'=>'Thiếu thông tin bắt buộc.']);
        if (strlen($pass) < 6)           respond(['ok'=>false,'msg'=>'Mật khẩu tối thiểu 6 ký tự.']);

        $exists = fetchOne("SELECT id FROM users WHERE email='$email'");
        if ($exists) respond(['ok'=>false,'msg'=>'Email đã được sử dụng.']);

        q("INSERT INTO users (name,email,phone,address,password,role,active)
           VALUES ('$name','$email','$phone','$address','$pass','customer',1)");
        $id = lastId();
        $u  = fetchOne("SELECT id,name,email,phone,address,role,active FROM users WHERE id=$id");
        $_SESSION['user'] = $u;
        respond(['ok'=>true,'data'=>$u]);
    }

    respond(['ok'=>false,'msg'=>'Action không hợp lệ'], 400);
}

if ($method === 'PUT') {
    // Cập nhật profile
    requireLogin();
    $id   = (int)($_GET['id'] ?? 0);
    $body = getBody();
    $sets = [];
    foreach (['name','email','phone','address','password'] as $f) {
        if (isset($body[$f])) $sets[] = "$f='".esc($body[$f])."'";
    }
    if (!$sets) respond(['ok'=>false,'msg'=>'Không có dữ liệu cập nhật']);
    q("UPDATE users SET ".implode(',',$sets)." WHERE id=$id");
    $u = fetchOne("SELECT id,name,email,phone,address,role,active FROM users WHERE id=$id");
    $_SESSION['user'] = $u;
    respond(['ok'=>true,'data'=>$u]);
}
