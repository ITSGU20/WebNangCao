<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
session_init();
if (auth_admin_check()) redirect(ADMIN_URL . '/dashboard.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']??''); $password = $_POST['password']??'';
    if (!$email||!$password) { $error='Vui lòng nhập đủ thông tin.'; }
    else {
        $user = db_row("SELECT * FROM users WHERE email=? AND role='admin'",[$email]);
        if (!$user||!password_verify($password,$user['password'])) $error='Email hoặc mật khẩu không đúng.';
        elseif (!$user['is_active']) $error='Tài khoản bị khóa.';
        else { auth_login_admin($user); redirect(ADMIN_URL.'/dashboard.php'); }
    }
}
?>
<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Admin Login - La Fleur</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<style>.pw-wrap{position:relative}.pw-wrap .form-control{padding-right:2.8rem}.pw-toggle{position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:rgba(255,255,255,.5);font-size:1rem;padding:0}</style>
</head><body>
<div class="auth-page" style="background:linear-gradient(135deg,var(--espresso),#3D1C02)">
  <div class="auth-card" style="max-width:420px">
    <div class="auth-header" style="background:linear-gradient(135deg,#1A0900,#3D1C02)">
      <a href="<?= BASE_URL ?>/index.php" class="logo" style="color:white">La Fleur <span style="color:var(--caramel-light)">Admin</span></a>
      <p>Đăng nhập quản trị viên</p>
    </div>
    <div class="auth-body">
      <div style="text-align:center;margin-bottom:1.5rem;font-size:3rem">🔐</div>
      <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
      <form method="POST" novalidate><?= csrf_field() ?>
        <div class="form-group"><label class="form-label">Email Admin</label>
          <input type="email" name="email" class="form-control" value="<?= h($_POST['email']??'admin@lafleur.com') ?>" autofocus></div>
        <div class="form-group"><label class="form-label">Mật khẩu</label>
          <div class="pw-wrap"><input type="password" name="password" id="pw" class="form-control" placeholder="••••••••">
          <button type="button" class="pw-toggle" onclick="const e=document.getElementById('pw');e.type=e.type==='password'?'text':'password'">👁️</button></div></div>
        <button type="submit" class="btn btn-primary btn-block btn-lg mt-3" style="background:var(--espresso)">Đăng nhập</button>
      </form>
      <div style="margin-top:1.2rem;padding:.9rem;background:var(--gray-light);border-radius:var(--radius-sm);font-size:.8rem;color:var(--gray)">
        <strong>Demo:</strong> admin@lafleur.com / admin123
      </div>
    </div>
    <div class="auth-footer"><a href="<?= BASE_URL ?>/index.php" style="color:var(--caramel)">← Về trang khách hàng</a></div>
  </div>
</div>
</body></html>
