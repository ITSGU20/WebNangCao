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

$districts = ['Quận 1','Quận 2','Quận 3','Quận 4','Quận 5','Quận 6','Quận 7','Quận 8','Quận 9','Quận 10','Quận 11','Quận 12','Bình Thạnh','Gò Vấp','Tân Bình','Tân Phú','Phú Nhuận','Bình Chánh','Nhà Bè','Củ Chi','Hóc Môn'];

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
        $district = trim($_POST['district'] ?? '');
        $city     = trim($_POST['city'] ?? 'TP.HCM');

        if (!$name)                        { $alertProfile = 'Vui lòng nhập họ tên'; $alertTypeP = 'danger'; }
        elseif (!validate_phone($phone))   { $alertProfile = 'Số điện thoại không hợp lệ'; $alertTypeP = 'danger'; }
        elseif (!validate_email($email))   { $alertProfile = 'Email không hợp lệ'; $alertTypeP = 'danger'; }
        elseif (!$street)                  { $alertProfile = 'Vui lòng nhập địa chỉ'; $alertTypeP = 'danger'; }
        elseif (!$district)                { $alertProfile = 'Vui lòng chọn quận/huyện'; $alertTypeP = 'danger'; }
        else {
            // Check email unique
            $taken = db_val('SELECT COUNT(*) FROM users WHERE email=? AND id<>?', [$email, $user['id']]);
            if ($taken) { $alertProfile = 'Email đã được sử dụng'; $alertTypeP = 'danger'; }
            else {
                $address = $street . ', ' . $district . ', ' . $city;
                db_exec('UPDATE users SET name=?,phone=?,email=?,address=?,district=?,city=? WHERE id=?',
                    [$name, $phone, $email, $address, $district, $city, $user['id']]);
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
$addrDistrict = $user['district'] ?: trim($addrParts[1] ?? '');
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
              <label class="form-label">Quận / Huyện *</label>
              <select name="district" class="form-control">
                <option value="">-- Chọn --</option>
                <?php foreach ($districts as $d): ?>
                  <option value="<?= h($d) ?>" <?= $addrDistrict===$d?'selected':'' ?>><?= h($d) ?></option>
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
