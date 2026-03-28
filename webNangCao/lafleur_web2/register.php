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
    $ward     = trim($_POST['ward'] ?? '');
    $city     = trim($_POST['city'] ?? 'TP.HCM');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if (!$name)                          $errors['name']     = 'Vui lòng nhập họ tên';
    if (!validate_phone($phone))         $errors['phone']    = 'SĐT cần 10 chữ số, bắt đầu bằng 0';
    if (!validate_email($email))         $errors['email']    = 'Email không hợp lệ';
    if (!$street)                        $errors['street']   = 'Vui lòng nhập địa chỉ chi tiết';
    if (!$ward)                          $errors['ward']     = 'Vui lòng chọn phường/xã';
    if (strlen($password) < 6)           $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự';
    if ($password !== $confirm)          $errors['confirm']  = 'Mật khẩu nhập lại không khớp';

    if (empty($errors)) {
        // Check email unique
        if (db_val('SELECT COUNT(*) FROM users WHERE email=?', [$email])) {
            $errors['email'] = 'Email đã được sử dụng';
        } else {
            $address = $street . ', ' . $ward . ', ' . $city;
            db_insert(
                'INSERT INTO users (name,email,phone,password,address,ward,city,role) VALUES (?,?,?,?,?,?,?,?)',
                [$name, $email, $phone, password_hash($password, PASSWORD_DEFAULT), $address, $ward, $city, 'customer']
            );
            // Auto login
            $user = db_row('SELECT * FROM users WHERE email=?', [$email]);
            auth_login_user($user);
            redirect(BASE_URL . '/index.php');
        }
    }
}

$wards = [
    'Phường Bến Nghé','Phường Bến Thành','Phường Cầu Kho','Phường Cầu Ông Lãnh',
    'Phường Cô Giang','Phường Đa Kao','Phường Nguyễn Cư Trinh','Phường Nguyễn Thái Bình',
    'Phường Phạm Ngũ Lão','Phường Tân Định',
    'Phường 1 (Q3)','Phường 2 (Q3)','Phường 3 (Q3)','Phường 4 (Q3)','Phường 5 (Q3)',
    'Phường 6 (Q3)','Phường 7 (Q3)','Phường 8 (Q3)','Phường 9 (Q3)','Phường 10 (Q3)',
    'Phường 11 (Q3)','Phường 12 (Q3)','Phường 13 (Q3)','Phường 14 (Q3)',
    'Phường 1 (Q4)','Phường 2 (Q4)','Phường 3 (Q4)','Phường 4 (Q4)','Phường 5 (Q4)',
    'Phường 6 (Q4)','Phường 7 (Q4)','Phường 8 (Q4)','Phường 9 (Q4)','Phường 10 (Q4)',
    'Phường 13 (Q4)','Phường 14 (Q4)','Phường 15 (Q4)','Phường 16 (Q4)','Phường 18 (Q4)',
    'Phường 1 (Q5)','Phường 2 (Q5)','Phường 3 (Q5)','Phường 4 (Q5)','Phường 5 (Q5)',
    'Phường 6 (Q5)','Phường 7 (Q5)','Phường 8 (Q5)','Phường 9 (Q5)','Phường 10 (Q5)',
    'Phường 11 (Q5)','Phường 12 (Q5)','Phường 13 (Q5)','Phường 14 (Q5)','Phường 15 (Q5)',
    'Phường 1 (Q6)','Phường 2 (Q6)','Phường 3 (Q6)','Phường 4 (Q6)','Phường 5 (Q6)',
    'Phường 6 (Q6)','Phường 7 (Q6)','Phường 8 (Q6)','Phường 9 (Q6)','Phường 10 (Q6)',
    'Phường 11 (Q6)','Phường 12 (Q6)','Phường 13 (Q6)','Phường 14 (Q6)',
    'Phường Bình Thuận','Phường Phú Mỹ','Phường Phú Thuận','Phường Tân Hưng',
    'Phường Tân Kiểng','Phường Tân Phong','Phường Tân Phú (Q7)','Phường Tân Quy',
    'Phường Tân Thuận Đông','Phường Tân Thuận Tây',
    'Phường 1 (Q8)','Phường 2 (Q8)','Phường 3 (Q8)','Phường 4 (Q8)','Phường 5 (Q8)',
    'Phường 6 (Q8)','Phường 7 (Q8)','Phường 8 (Q8)','Phường 9 (Q8)','Phường 10 (Q8)',
    'Phường 11 (Q8)','Phường 12 (Q8)','Phường 13 (Q8)','Phường 14 (Q8)','Phường 15 (Q8)','Phường 16 (Q8)',
    'Phường 1 (Q10)','Phường 2 (Q10)','Phường 3 (Q10)','Phường 4 (Q10)','Phường 5 (Q10)',
    'Phường 6 (Q10)','Phường 7 (Q10)','Phường 8 (Q10)','Phường 9 (Q10)','Phường 10 (Q10)',
    'Phường 11 (Q10)','Phường 12 (Q10)','Phường 13 (Q10)','Phường 14 (Q10)','Phường 15 (Q10)',
    'Phường 1 (Q11)','Phường 2 (Q11)','Phường 3 (Q11)','Phường 4 (Q11)','Phường 5 (Q11)',
    'Phường 6 (Q11)','Phường 7 (Q11)','Phường 8 (Q11)','Phường 9 (Q11)','Phường 10 (Q11)',
    'Phường 11 (Q11)','Phường 12 (Q11)','Phường 13 (Q11)','Phường 14 (Q11)','Phường 15 (Q11)','Phường 16 (Q11)',
    'Phường An Phú Đông','Phường Đông Hưng Thuận','Phường Hiệp Thành',
    'Phường Tân Chánh Hiệp','Phường Tân Hưng Thuận','Phường Tân Thới Hiệp',
    'Phường Tân Thới Nhất','Phường Thạnh Lộc','Phường Thạnh Xuân','Phường Thới An','Phường Trung Mỹ Tây',
    'Phường 1 (BT)','Phường 2 (BT)','Phường 3 (BT)','Phường 5 (BT)','Phường 6 (BT)',
    'Phường 7 (BT)','Phường 11 (BT)','Phường 12 (BT)','Phường 13 (BT)','Phường 14 (BT)',
    'Phường 15 (BT)','Phường 17 (BT)','Phường 19 (BT)','Phường 21 (BT)',
    'Phường 22 (BT)','Phường 24 (BT)','Phường 25 (BT)','Phường 26 (BT)','Phường 27 (BT)','Phường 28 (BT)',
    'Phường 1 (GV)','Phường 3 (GV)','Phường 4 (GV)','Phường 5 (GV)','Phường 6 (GV)',
    'Phường 7 (GV)','Phường 8 (GV)','Phường 9 (GV)','Phường 10 (GV)','Phường 11 (GV)',
    'Phường 12 (GV)','Phường 13 (GV)','Phường 14 (GV)','Phường 15 (GV)','Phường 16 (GV)','Phường 17 (GV)',
    'Phường 1 (TB)','Phường 2 (TB)','Phường 3 (TB)','Phường 4 (TB)','Phường 5 (TB)',
    'Phường 6 (TB)','Phường 7 (TB)','Phường 8 (TB)','Phường 9 (TB)','Phường 10 (TB)',
    'Phường 11 (TB)','Phường 12 (TB)','Phường 13 (TB)','Phường 14 (TB)','Phường 15 (TB)',
    'Phường Hiệp Tân','Phường Hoà Thạnh','Phường Phú Thạnh','Phường Phú Thọ Hoà',
    'Phường Tân Quý','Phường Tân Sơn Nhì','Phường Tân Thành','Phường Tân Thới Hoà',
    'Phường Tân Thới Nhứt (TP)','Phường Tây Thạnh','Phường Sơn Kỳ',
    'Phường 1 (PN)','Phường 2 (PN)','Phường 3 (PN)','Phường 4 (PN)','Phường 5 (PN)',
    'Phường 7 (PN)','Phường 8 (PN)','Phường 9 (PN)','Phường 10 (PN)','Phường 11 (PN)',
    'Phường 12 (PN)','Phường 13 (PN)','Phường 14 (PN)','Phường 15 (PN)','Phường 17 (PN)',
    'Phường An Khánh','Phường An Lợi Đông','Phường An Phú','Phường Bình An',
    'Phường Bình Khánh','Phường Bình Trưng Đông','Phường Bình Trưng Tây',
    'Phường Cát Lái','Phường Thảo Điền','Phường Thủ Thiêm','Phường Thạnh Mỹ Lợi',
    'Phường Hiệp Phú','Phường Long Bình','Phường Long Phước','Phường Long Thạnh Mỹ',
    'Phường Long Trường','Phường Phú Hữu','Phường Tân Phú (TĐ)','Phường Tăng Nhơn Phú A','Phường Tăng Nhơn Phú B','Phường Trường Thạnh',
    'Phường Bình Chiểu','Phường Bình Thọ','Phường Hiệp Bình Chánh','Phường Hiệp Bình Phước',
    'Phường Linh Chiểu','Phường Linh Đông','Phường Linh Tây','Phường Linh Trung',
    'Phường Linh Xuân','Phường Tam Bình','Phường Tam Phú','Phường Trường Thọ',
    'Thị trấn Tân Túc','Xã An Phú Tây','Xã Bình Chánh','Xã Bình Hưng','Xã Bình Lợi',
    'Xã Đa Phước','Xã Hưng Long','Xã Lê Minh Xuân','Xã Phạm Văn Hai','Xã Phong Phú',
    'Xã Quy Đức','Xã Tân Kiên','Xã Tân Nhựt','Xã Tân Quý Tây','Xã Vĩnh Lộc A','Xã Vĩnh Lộc B',
    'Thị trấn Nhà Bè','Xã Hiệp Phước','Xã Long Thới','Xã Nhơn Đức','Xã Phú Xuân','Xã Phước Kiển','Xã Phước Lộc',
    'Thị trấn Củ Chi','Xã An Nhơn Tây','Xã An Phú (CC)','Xã Bình Mỹ','Xã Hòa Phú',
    'Xã Nhuận Đức','Xã Phạm Văn Cội','Xã Phú Hòa Đông','Xã Phú Mỹ Hưng (CC)',
    'Xã Tân An Hội','Xã Tân Phú Trung','Xã Tân Thạnh Đông','Xã Tân Thạnh Tây',
    'Xã Tân Thông Hội','Xã Thái Mỹ','Xã Trung An','Xã Trung Lập Hạ','Xã Trung Lập Thượng',
    'Thị trấn Hóc Môn','Xã Bà Điểm','Xã Đông Thạnh','Xã Nhị Bình','Xã Tân Hiệp (HM)',
    'Xã Tân Thới Nhì','Xã Tân Xuân','Xã Thới Tam Thôn','Xã Trung Chánh',
    'Xã Xuân Thới Đông','Xã Xuân Thới Sơn','Xã Xuân Thới Thượng',
    'Thị trấn Cần Thạnh','Xã An Thới Đông','Xã Bình Khánh (CG)','Xã Long Hòa','Xã Lý Nhơn','Xã Tam Thôn Hiệp','Xã Thạnh An',
];

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
            <label class="form-label">Phường / Xã *</label>
            <select name="ward" class="form-control <?= isset($errors['ward'])?'is-error':'' ?>">
              <option value="">-- Chọn phường/xã --</option>
              <?php foreach ($wards as $w): ?>
                <option value="<?= h($w) ?>" <?= ($old['ward']??'')===$w?'selected':'' ?>><?= h($w) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['ward'])): ?><div class="field-error"><?= h($errors['ward']) ?></div><?php endif; ?>
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
