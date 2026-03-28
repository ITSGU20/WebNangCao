<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/cart_helper.php';

session_init();
$user = auth_require();

$alertProfile  = '';
$alertPassword = '';
$alertTypeP    = 'success';
$alertTypePw   = 'success';

$wards = [
    // Quận 1
    'Phường Bến Nghé','Phường Bến Thành','Phường Cầu Kho','Phường Cầu Ông Lãnh',
    'Phường Cô Giang','Phường Đa Kao','Phường Nguyễn Cư Trinh','Phường Nguyễn Thái Bình',
    'Phường Phạm Ngũ Lão','Phường Tân Định',
    // Quận 3
    'Phường 1 (Q3)','Phường 2 (Q3)','Phường 3 (Q3)','Phường 4 (Q3)','Phường 5 (Q3)',
    'Phường 6 (Q3)','Phường 7 (Q3)','Phường 8 (Q3)','Phường 9 (Q3)','Phường 10 (Q3)',
    'Phường 11 (Q3)','Phường 12 (Q3)','Phường 13 (Q3)','Phường 14 (Q3)',
    // Quận 4
    'Phường 1 (Q4)','Phường 2 (Q4)','Phường 3 (Q4)','Phường 4 (Q4)','Phường 5 (Q4)',
    'Phường 6 (Q4)','Phường 7 (Q4)','Phường 8 (Q4)','Phường 9 (Q4)','Phường 10 (Q4)',
    'Phường 13 (Q4)','Phường 14 (Q4)','Phường 15 (Q4)','Phường 16 (Q4)','Phường 18 (Q4)',
    // Quận 5
    'Phường 1 (Q5)','Phường 2 (Q5)','Phường 3 (Q5)','Phường 4 (Q5)','Phường 5 (Q5)',
    'Phường 6 (Q5)','Phường 7 (Q5)','Phường 8 (Q5)','Phường 9 (Q5)','Phường 10 (Q5)',
    'Phường 11 (Q5)','Phường 12 (Q5)','Phường 13 (Q5)','Phường 14 (Q5)','Phường 15 (Q5)',
    // Quận 6
    'Phường 1 (Q6)','Phường 2 (Q6)','Phường 3 (Q6)','Phường 4 (Q6)','Phường 5 (Q6)',
    'Phường 6 (Q6)','Phường 7 (Q6)','Phường 8 (Q6)','Phường 9 (Q6)','Phường 10 (Q6)',
    'Phường 11 (Q6)','Phường 12 (Q6)','Phường 13 (Q6)','Phường 14 (Q6)',
    // Quận 7
    'Phường Bình Thuận','Phường Phú Mỹ','Phường Phú Thuận','Phường Tân Hưng',
    'Phường Tân Kiểng','Phường Tân Phong','Phường Tân Phú (Q7)','Phường Tân Quy',
    'Phường Tân Thuận Đông','Phường Tân Thuận Tây',
    // Quận 8
    'Phường 1 (Q8)','Phường 2 (Q8)','Phường 3 (Q8)','Phường 4 (Q8)','Phường 5 (Q8)',
    'Phường 6 (Q8)','Phường 7 (Q8)','Phường 8 (Q8)','Phường 9 (Q8)','Phường 10 (Q8)',
    'Phường 11 (Q8)','Phường 12 (Q8)','Phường 13 (Q8)','Phường 14 (Q8)',
    'Phường 15 (Q8)','Phường 16 (Q8)',
    // Quận 10
    'Phường 1 (Q10)','Phường 2 (Q10)','Phường 3 (Q10)','Phường 4 (Q10)','Phường 5 (Q10)',
    'Phường 6 (Q10)','Phường 7 (Q10)','Phường 8 (Q10)','Phường 9 (Q10)','Phường 10 (Q10)',
    'Phường 11 (Q10)','Phường 12 (Q10)','Phường 13 (Q10)','Phường 14 (Q10)','Phường 15 (Q10)',
    // Quận 11
    'Phường 1 (Q11)','Phường 2 (Q11)','Phường 3 (Q11)','Phường 4 (Q11)','Phường 5 (Q11)',
    'Phường 6 (Q11)','Phường 7 (Q11)','Phường 8 (Q11)','Phường 9 (Q11)','Phường 10 (Q11)',
    'Phường 11 (Q11)','Phường 12 (Q11)','Phường 13 (Q11)','Phường 14 (Q11)',
    'Phường 15 (Q11)','Phường 16 (Q11)',
    // Quận 12
    'Phường An Phú Đông','Phường Đông Hưng Thuận','Phường Hiệp Thành',
    'Phường Tân Chánh Hiệp','Phường Tân Hưng Thuận','Phường Tân Thới Hiệp',
    'Phường Tân Thới Nhất','Phường Thạnh Lộc','Phường Thạnh Xuân',
    'Phường Thới An','Phường Trung Mỹ Tây',
    // Bình Thạnh
    'Phường 1 (BT)','Phường 2 (BT)','Phường 3 (BT)','Phường 5 (BT)','Phường 6 (BT)',
    'Phường 7 (BT)','Phường 11 (BT)','Phường 12 (BT)','Phường 13 (BT)','Phường 14 (BT)',
    'Phường 15 (BT)','Phường 17 (BT)','Phường 19 (BT)','Phường 21 (BT)',
    'Phường 22 (BT)','Phường 24 (BT)','Phường 25 (BT)','Phường 26 (BT)',
    'Phường 27 (BT)','Phường 28 (BT)',
    // Gò Vấp
    'Phường 1 (GV)','Phường 3 (GV)','Phường 4 (GV)','Phường 5 (GV)','Phường 6 (GV)',
    'Phường 7 (GV)','Phường 8 (GV)','Phường 9 (GV)','Phường 10 (GV)','Phường 11 (GV)',
    'Phường 12 (GV)','Phường 13 (GV)','Phường 14 (GV)','Phường 15 (GV)',
    'Phường 16 (GV)','Phường 17 (GV)',
    // Tân Bình
    'Phường 1 (TB)','Phường 2 (TB)','Phường 3 (TB)','Phường 4 (TB)','Phường 5 (TB)',
    'Phường 6 (TB)','Phường 7 (TB)','Phường 8 (TB)','Phường 9 (TB)','Phường 10 (TB)',
    'Phường 11 (TB)','Phường 12 (TB)','Phường 13 (TB)','Phường 14 (TB)','Phường 15 (TB)',
    // Tân Phú
    'Phường Hiệp Tân','Phường Hoà Thạnh','Phường Phú Thạnh','Phường Phú Thọ Hoà',
    'Phường Tân Quý','Phường Tân Sơn Nhì','Phường Tân Thành','Phường Tân Thới Hoà',
    'Phường Tân Thới Nhứt (TP)','Phường Tây Thạnh','Phường Sơn Kỳ',
    // Phú Nhuận
    'Phường 1 (PN)','Phường 2 (PN)','Phường 3 (PN)','Phường 4 (PN)','Phường 5 (PN)',
    'Phường 7 (PN)','Phường 8 (PN)','Phường 9 (PN)','Phường 10 (PN)','Phường 11 (PN)',
    'Phường 12 (PN)','Phường 13 (PN)','Phường 14 (PN)','Phường 15 (PN)','Phường 17 (PN)',
    // TP. Thủ Đức (gộp Q2, Q9, Thủ Đức cũ)
    'Phường An Khánh','Phường An Lợi Đông','Phường An Phú','Phường Bình An',
    'Phường Bình Khánh','Phường Bình Trưng Đông','Phường Bình Trưng Tây',
    'Phường Cát Lái','Phường Thảo Điền','Phường Thủ Thiêm','Phường Thạnh Mỹ Lợi',
    'Phường Hiệp Phú','Phường Long Bình','Phường Long Phước','Phường Long Thạnh Mỹ',
    'Phường Long Trường','Phường Phú Hữu','Phường Tân Phú (TĐ)','Phường Tăng Nhơn Phú A',
    'Phường Tăng Nhơn Phú B','Phường Trường Thạnh',
    'Phường Bình Chiểu','Phường Bình Thọ','Phường Hiệp Bình Chánh','Phường Hiệp Bình Phước',
    'Phường Linh Chiểu','Phường Linh Đông','Phường Linh Tây','Phường Linh Trung',
    'Phường Linh Xuân','Phường Tam Bình','Phường Tam Phú','Phường Trường Thọ',
    // Bình Chánh
    'Thị trấn Tân Túc','Xã An Phú Tây','Xã Bình Chánh','Xã Bình Hưng',
    'Xã Bình Lợi','Xã Đa Phước','Xã Hưng Long','Xã Lê Minh Xuân','Xã Phạm Văn Hai',
    'Xã Phong Phú','Xã Quy Đức','Xã Tân Kiên','Xã Tân Nhựt','Xã Tân Quý Tây',
    'Xã Vĩnh Lộc A','Xã Vĩnh Lộc B',
    // Nhà Bè
    'Thị trấn Nhà Bè','Xã Hiệp Phước','Xã Long Thới','Xã Nhơn Đức','Xã Phú Xuân',
    'Xã Phước Kiển','Xã Phước Lộc',
    // Củ Chi
    'Thị trấn Củ Chi','Xã An Nhơn Tây','Xã An Phú','Xã Bình Mỹ','Xã Hòa Phú',
    'Xã Nhuận Đức','Xã Phạm Văn Cội','Xã Phú Hòa Đông','Xã Phú Mỹ Hưng',
    'Xã Tân An Hội','Xã Tân Phú Trung','Xã Tân Thạnh Đông','Xã Tân Thạnh Tây',
    'Xã Tân Thông Hội','Xã Thái Mỹ','Xã Trung An','Xã Trung Lập Hạ',
    'Xã Trung Lập Thượng',
    // Hóc Môn
    'Thị trấn Hóc Môn','Xã Bà Điểm','Xã Đông Thạnh','Xã Nhị Bình','Xã Tân Hiệp',
    'Xã Tân Thới Nhì','Xã Tân Xuân','Xã Thới Tam Thôn','Xã Trung Chánh','Xã Xuân Thới Đông',
    'Xã Xuân Thới Sơn','Xã Xuân Thới Thượng',
    // Cần Giờ
    'Thị trấn Cần Thạnh','Xã An Thới Đông','Xã Bình Khánh (CG)','Xã Long Hòa',
    'Xã Lý Nhơn','Xã Tam Thôn Hiệp','Xã Thạnh An',
];

// Always get fresh user from DB
function freshUser(int $id): array {
    return db_row('SELECT * FROM users WHERE id=?', [$id]) ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'profile') {
        $name     = trim($_POST['name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $street   = trim($_POST['street'] ?? '');
        $ward     = trim($_POST['ward'] ?? '');
        $city     = trim($_POST['city'] ?? 'TP.HCM');

        if (!$name)                        { $alertProfile = 'Vui lòng nhập họ tên'; $alertTypeP = 'danger'; }
        elseif (!validate_phone($phone))   { $alertProfile = 'Số điện thoại không hợp lệ'; $alertTypeP = 'danger'; }
        elseif (!validate_email($email))   { $alertProfile = 'Email không hợp lệ'; $alertTypeP = 'danger'; }
        elseif (!$street)                  { $alertProfile = 'Vui lòng nhập địa chỉ'; $alertTypeP = 'danger'; }
        elseif (!$ward)                    { $alertProfile = 'Vui lòng chọn phường/xã'; $alertTypeP = 'danger'; }
        else {
            // Check email unique
            $taken = db_val('SELECT COUNT(*) FROM users WHERE email=? AND id<>?', [$email, $user['id']]);
            if ($taken) { $alertProfile = 'Email đã được sử dụng'; $alertTypeP = 'danger'; }
            else {
                $address = $street . ', ' . $ward . ', ' . $city;
                db_exec('UPDATE users SET name=?,phone=?,email=?,address=?,ward=?,city=? WHERE id=?',
                    [$name, $phone, $email, $address, $ward, $city, $user['id']]);
                // Refresh session
                auth_login_user(freshUser($user['id']));
                $user = auth_user();
                $alertProfile = '✅ Cập nhật thông tin thành công!';
            }
        }
    }

    if ($action === 'password') {
        $fresh      = freshUser($user['id']);
        $oldPass    = $_POST['old_pass'] ?? '';
        $newPass    = $_POST['new_pass'] ?? '';
        $confirmPw  = $_POST['confirm_pass'] ?? '';

        if (!$oldPass)                              { $alertPassword = 'Vui lòng nhập mật khẩu hiện tại'; $alertTypePw='danger'; }
        elseif (!password_verify($oldPass, $fresh['password'])) { $alertPassword = 'Mật khẩu hiện tại không đúng'; $alertTypePw='danger'; }
        elseif (strlen($newPass) < 6)               { $alertPassword = 'Mật khẩu mới phải có ít nhất 6 ký tự'; $alertTypePw='danger'; }
        elseif ($newPass !== $confirmPw)             { $alertPassword = 'Mật khẩu nhập lại không khớp'; $alertTypePw='danger'; }
        elseif (password_verify($newPass, $fresh['password'])) { $alertPassword = 'Mật khẩu mới phải khác mật khẩu cũ'; $alertTypePw='danger'; }
        else {
            db_exec('UPDATE users SET password=? WHERE id=?', [password_hash($newPass, PASSWORD_DEFAULT), $user['id']]);
            $alertPassword = '🔒 Đổi mật khẩu thành công!';
        }
    }
}

// Parse address into parts
$addrParts = explode(',', $user['address'] ?? '');
$addrStreet   = trim($addrParts[0] ?? '');
$addrWard     = $user['ward'] ?: trim($addrParts[1] ?? '');
$addrCity     = $user['city'] ?: trim($addrParts[2] ?? 'TP.HCM');

render_head('Tài khoản - La Fleur');
render_navbar();
?>
<style>.pw-wrap{position:relative}.pw-wrap .form-control{padding-right:2.8rem}.pw-toggle{position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:var(--gray);font-size:1rem;padding:0}</style>

<div style="max-width:820px;margin:3rem auto;padding:0 2rem">
  <div style="display:flex;gap:2rem;flex-wrap:wrap">
    <div style="width:200px;flex-shrink:0">
      <div class="card card-sm" style="padding:1.5rem">
        <div style="text-align:center;margin-bottom:1.2rem">
          <div style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,var(--caramel-light),var(--rose-light));margin:0 auto .8rem;display:flex;align-items:center;justify-content:center;font-size:2rem">👤</div>
          <div style="font-weight:600;font-size:.9rem;color:var(--espresso)"><?= h($user['name']) ?></div>
          <div style="font-size:.75rem;color:var(--gray);word-break:break-all"><?= h($user['email']) ?></div>
        </div>
        <nav style="display:flex;flex-direction:column;gap:.3rem">
          <a href="<?= BASE_URL ?>/profile.php" style="padding:.6rem .8rem;border-radius:var(--radius-sm);background:var(--gray-light);color:var(--chocolate);font-size:.85rem;font-weight:600">👤 Hồ sơ</a>
          <a href="<?= BASE_URL ?>/orders.php" style="padding:.6rem .8rem;border-radius:var(--radius-sm);color:var(--gray);font-size:.85rem">📦 Đơn hàng</a>
          <a href="<?= BASE_URL ?>/cart.php" style="padding:.6rem .8rem;border-radius:var(--radius-sm);color:var(--gray);font-size:.85rem">🛒 Giỏ hàng</a>
          <a href="<?= BASE_URL ?>/logout.php" style="padding:.6rem .8rem;border-radius:var(--radius-sm);color:#C0392B;font-size:.85rem">🚪 Đăng xuất</a>
        </nav>
      </div>
    </div>

    <div style="flex:1;min-width:0">
      <div class="card">
        <h2 style="font-family:var(--font-display);font-size:1.5rem;color:var(--chocolate);margin-bottom:.3rem">Thông tin cá nhân</h2>
        <p class="text-muted mb-4">Cập nhật thông tin tài khoản và địa chỉ giao hàng</p>

        <?php if ($alertProfile): ?>
          <div class="alert alert-<?= $alertTypeP ?> mb-3"><?= h($alertProfile) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="profile">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Họ và tên *</label>
              <input type="text" name="name" class="form-control" value="<?= h($user['name']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Số điện thoại *</label>
              <input type="tel" name="phone" class="form-control" value="<?= h($user['phone']) ?>" maxlength="11">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Email *</label>
            <input type="email" name="email" class="form-control" value="<?= h($user['email']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Số nhà, tên đường *</label>
            <input type="text" name="street" class="form-control" value="<?= h($addrStreet) ?>" placeholder="123 Đường Lê Lợi">
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Phường / Xã *</label>
              <select name="ward" class="form-control">
                <option value="">-- Chọn --</option>
                <?php foreach ($wards as $w): ?>
                  <option value="<?= h($w) ?>" <?= $addrWard===$w?'selected':'' ?>><?= h($w) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Tỉnh / Thành phố</label>
              <select name="city" class="form-control">
                <?php foreach (['TP.HCM','Hà Nội','Đà Nẵng','Cần Thơ','Hải Phòng'] as $c): ?>
                  <option value="<?= h($c) ?>" <?= $addrCity===$c?'selected':'' ?>><?= h($c) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <button type="submit" class="btn btn-primary">💾 Lưu thay đổi</button>
        </form>

        <hr style="border:none;border-top:1px solid var(--border);margin:2rem 0">

        <h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--chocolate);margin-bottom:1.2rem">🔒 Đổi mật khẩu</h3>

        <?php if ($alertPassword): ?>
          <div class="alert alert-<?= $alertTypePw ?> mb-3"><?= h($alertPassword) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="password">
          <div class="form-group">
            <label class="form-label">Mật khẩu hiện tại *</label>
            <div class="pw-wrap">
              <input type="password" name="old_pass" id="pw0" class="form-control" placeholder="••••••••">
              <button type="button" class="pw-toggle" onclick="tpw('pw0',this)">👁️</button>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Mật khẩu mới *</label>
              <div class="pw-wrap">
                <input type="password" name="new_pass" id="pw1" class="form-control" placeholder="Tối thiểu 6 ký tự">
                <button type="button" class="pw-toggle" onclick="tpw('pw1',this)">👁️</button>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Nhập lại *</label>
              <div class="pw-wrap">
                <input type="password" name="confirm_pass" id="pw2" class="form-control" placeholder="••••••••">
                <button type="button" class="pw-toggle" onclick="tpw('pw2',this)">👁️</button>
              </div>
            </div>
          </div>
          <button type="submit" class="btn btn-outline">🔒 Đổi mật khẩu</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script>function tpw(id,btn){const el=document.getElementById(id);const s=el.type==='password';el.type=s?'text':'password';btn.textContent=s?'🙈':'👁️'}</script>
<?php render_footer(); ?>
