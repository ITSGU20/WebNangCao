<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

session_init();
if (auth_check()) redirect(BASE_URL . '/index.php');

$error = '';
$redirect = $_GET['redirect'] ?? BASE_URL . '/index.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Vui lòng nhập đầy đủ email và mật khẩu.';
    } else {
        $user = db_row('SELECT * FROM users WHERE email = ? AND role = "customer"', [$email]);
        if (!$user || !password_verify($password, $user['password'])) {
            $error = 'Email hoặc mật khẩu không đúng.';
        } elseif (!$user['is_active']) {
            $error = 'Tài khoản đã bị khóa. Vui lòng liên hệ hỗ trợ.';
        } else {
            auth_login_user($user);
            redirect($redirect);
        }
    }
}

render_head('Đăng nhập - La Fleur');
?>
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-header">
      <a href="<?= BASE_URL ?>/index.php" class="logo">La Fleur Pâtisserie</a>
      <p>Chào mừng quay trở lại!</p>
    </div>
    <div class="auth-body">
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
      <?php endif; ?>
      <form method="POST" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
        <div class="form-group">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= h($_POST['email'] ?? '') ?>" placeholder="your@email.com" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">Mật khẩu</label>
          <div class="pw-wrap">
            <input type="password" name="password" id="pw" class="form-control" placeholder="••••••••" required>
            <button type="button" class="pw-toggle" onclick="togglePw()">👁️</button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg mt-3">Đăng nhập</button>
      </form>
    </div>
    <div class="auth-footer">Chưa có tài khoản? <a href="<?= BASE_URL ?>/register.php">Đăng ký ngay</a></div>
  </div>
</div>
<div id="toast"></div>
<style>.pw-wrap{position:relative}.pw-wrap .form-control{padding-right:2.8rem}.pw-toggle{position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray);font-size:1rem;padding:0}</style>
<script>function togglePw(){const el=document.getElementById('pw');el.type=el.type==='password'?'text':'password'}</script>
</body></html>