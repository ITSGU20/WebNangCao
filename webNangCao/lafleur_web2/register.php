<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';

session_init();
if (auth_check()) redirect(BASE_URL . '/index.php');

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $street   = trim($_POST['street'] ?? '');
    $district = trim($_POST['district'] ?? '');
    $city     = trim($_POST['city'] ?? 'TP.HCM');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name)                          $errors['name']     = 'Vui lòng nhập họ tên';
    if (!validate_phone($phone))         $errors['phone']    = 'SĐT cần 10 chữ số, bắt đầu bằng 0';
    if (!validate_email($email))         $errors['email']    = 'Email không hợp lệ';
    if (!$street)                        $errors['street']   = 'Vui lòng nhập địa chỉ chi tiết';
    if (!$district)                      $errors['district'] = 'Vui lòng chọn quận/huyện';
    if (strlen($password) < 6)           $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự';
    if ($password !== $confirm)          $errors['confirm']  = 'Mật khẩu nhập lại không khớp';

    if (empty($errors)) {
        // Check email unique
        if (db_val('SELECT COUNT(*) FROM users WHERE email=?', [$email])) {
            $errors['email'] = 'Email đã được sử dụng';
        } else {
            $address = $street . ', ' . $district . ', ' . $city;
            db_insert(
                'INSERT INTO users (name,email,phone,password,address,district,city,role) VALUES (?,?,?,?,?,?,?,?)',
                [$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $address, $district, $city, 'customer']
            );
            // Auto login
            $user = db_row('SELECT * FROM users WHERE email=?', [$email]);
            auth_login_user($user);
            redirect(BASE_URL . '/index.php');
        }
    }
}

$districts = ['Quận 1','Quận 2','Quận 3','Quận 4','Quận 5','Quận 6','Quận 7','Quận 8','Quận 9','Quận 10','Quận 11','Quận 12','Bình Thạnh','Gò Vấp','Tân Bình','Tân Phú','Phú Nhuận','Bình Chánh','Nhà Bè','Củ Chi','Hóc Môn'];

render_head('Đăng ký - La Fleur');
?>
<style>.pw-wrap{position:relative}.pw-wrap .form-control{padding-right:2.8rem}.pw-toggle{position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray);font-size:1rem;padding:0}.field-error{font-size:.76rem;color:#C0392B;margin-top:.25rem}.form-control.is-error{border-color:#E74C3C!important}</style>
<div class="auth-page">
  <div class="auth-card" style="max-width:530px">
    <div class="auth-header">
      <a href="<?= BASE_URL ?>/index.php" class="logo">La Fleur Pâtisserie</a>
      <p>Tạo tài khoản mới</p>
    </div>
    <div class="auth-body">
      <form method="POST" novalidate>
        <?= csrf_field() ?>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Họ và tên *</label>
            <input type="text" name="name" class="form-control <?= isset($errors['name'])?'is-error':'' ?>" value="<?= h($old['name']??'') ?>" placeholder="Nguyễn Văn A">
            <?php if (isset($errors['name'])): ?><div class="field-error"><?= h($errors['name']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label">Số điện thoại *</label>
            <input type="tel" name="phone" class="form-control <?= isset($errors['phone'])?'is-error':'' ?>" value="<?= h($old['phone']??'') ?>" placeholder="0901234567" maxlength="11">
            <?php if (isset($errors['phone'])): ?><div class="field-error"><?= h($errors['phone']) ?></div><?php endif; ?>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Email *</label>
          <input type="email" name="email" class="form-control <?= isset($errors['email'])?'is-error':'' ?>" value="<?= h($old['email']??'') ?>" placeholder="your@email.com">
          <?php if (isset($errors['email'])): ?><div class="field-error"><?= h($errors['email']) ?></div><?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label">Số nhà, tên đường *</label>
          <input type="text" name="street" class="form-control <?= isset($errors['street'])?'is-error':'' ?>" value="<?= h($old['street']??'') ?>" placeholder="123 Đường Lê Lợi">
          <?php if (isset($errors['street'])): ?><div class="field-error"><?= h($errors['street']) ?></div><?php endif; ?>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Quận / Huyện *</label>
            <select name="district" class="form-control <?= isset($errors['district'])?'is-error':'' ?>">
              <option value="">-- Chọn quận --</option>
              <?php foreach ($districts as $d): ?>
                <option value="<?= h($d) ?>" <?= ($old['district']??'')===$d?'selected':'' ?>><?= h($d) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['district'])): ?><div class="field-error"><?= h($errors['district']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label">Tỉnh / Thành phố</label>
            <select name="city" class="form-control">
              <?php foreach (['TP.HCM','Hà Nội','Đà Nẵng','Cần Thơ','Hải Phòng'] as $c): ?>
                <option value="<?= h($c) ?>" <?= ($old['city']??'TP.HCM')===$c?'selected':'' ?>><?= h($c) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Mật khẩu *</label>
            <div class="pw-wrap">
              <input type="password" name="password" id="pw1" class="form-control <?= isset($errors['password'])?'is-error':'' ?>" placeholder="Tối thiểu 6 ký tự">
              <button type="button" class="pw-toggle" onclick="togglePw('pw1',this)">👁️</button>
            </div>
            <?php if (isset($errors['password'])): ?><div class="field-error"><?= h($errors['password']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label">Nhập lại mật khẩu *</label>
            <div class="pw-wrap">
              <input type="password" name="confirm" id="pw2" class="form-control <?= isset($errors['confirm'])?'is-error':'' ?>" placeholder="••••••••">
              <button type="button" class="pw-toggle" onclick="togglePw('pw2',this)">👁️</button>
            </div>
            <?php if (isset($errors['confirm'])): ?><div class="field-error"><?= h($errors['confirm']) ?></div><?php endif; ?>
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block btn-lg mt-2">Đăng ký</button>
      </form>
    </div>
    <div class="auth-footer">Đã có tài khoản? <a href="<?= BASE_URL ?>/login.php">Đăng nhập</a></div>
  </div>
</div>
<div id="toast"></div>
<script>function togglePw(id,btn){const el=document.getElementById(id);const s=el.type==='password';el.type=s?'text':'password';btn.textContent=s?'🙈':'👁️'}</script>
</body></html>
