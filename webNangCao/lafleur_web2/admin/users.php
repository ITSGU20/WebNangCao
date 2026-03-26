<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/admin_layout.php';

admin_guard();

$msg = '';

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id       = sanitize_int($_POST['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $address  = trim($_POST['address'] ?? '');
        $role     = in_array($_POST['role']??'', ['customer','admin']) ? $_POST['role'] : 'customer';
        $password = $_POST['password'] ?? '';

        if (!$name || !$email) { $msg = 'error:Vui lòng điền đầy đủ họ tên và email.'; }
        elseif (!validate_email($email)) { $msg = 'error:Email không hợp lệ.'; }
        else {
            $taken = db_val('SELECT COUNT(*) FROM users WHERE email=? AND id<>?', [$email, $id]);
            if ($taken) { $msg = 'error:Email đã được sử dụng.'; }
            elseif ($id) {
                db_exec('UPDATE users SET name=?,email=?,phone=?,address=?,role=? WHERE id=?', [$name,$email,$phone,$address,$role,$id]);
                $msg = 'success:Cập nhật người dùng thành công.';
            } else {
                if (strlen($password) < 6) { $msg = 'error:Mật khẩu tối thiểu 6 ký tự.'; }
                else {
                    db_insert('INSERT INTO users (name,email,phone,address,password,role) VALUES (?,?,?,?,?,?)',
                        [$name,$email,$phone,$address,password_hash($password,PASSWORD_DEFAULT),$role]);
                    $msg = 'success:Thêm người dùng thành công.';
                }
            }
        }
    }

    if ($action === 'toggle') {
        $id = sanitize_int($_POST['id']??0);
        $admin = auth_admin();
        if ($id && $id != $admin['id']) {
            db_exec('UPDATE users SET is_active = NOT is_active WHERE id=?', [$id]);
            $msg = 'success:Đã thay đổi trạng thái tài khoản.';
        }
    }

    if ($action === 'reset_password') {
        $id  = sanitize_int($_POST['id']??0);
        $pwd = $_POST['new_password'] ?? '';
        if (strlen($pwd) < 6) { $msg = 'error:Mật khẩu tối thiểu 6 ký tự.'; }
        else {
            db_exec('UPDATE users SET password=? WHERE id=?', [password_hash($pwd,PASSWORD_DEFAULT), $id]);
            $msg = 'success:Đặt lại mật khẩu thành công.';
        }
    }
}

$search = trim($_GET['search'] ?? '');
$role   = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';
$page   = max(1, sanitize_int($_GET['page']??1));

$where  = ['1=1'];
$params = [];
if ($search) { $where[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)'; $params = array_merge($params, ["%$search%","%$search%","%$search%"]); }
if ($role)   { $where[] = 'role=?'; $params[] = $role; }
if ($status === 'active')  { $where[] = 'is_active=1'; }
if ($status === 'locked')  { $where[] = 'is_active=0'; }

$sql   = 'SELECT * FROM users WHERE ' . implode(' AND ', $where) . ' ORDER BY created_at DESC';
$paged = db_paginate($sql, $params, $page, ADMIN_PAGE_SIZE);

// Edit user
$editId   = sanitize_int($_GET['edit'] ?? 0);
$editUser = $editId ? db_row('SELECT * FROM users WHERE id=?', [$editId]) : null;

admin_layout_start('Quản lý người dùng', 'users');

[$msgType, $msgText] = $msg ? explode(':', $msg, 2) : ['',''];
if ($msgText): ?>
<div class="alert alert-<?= $msgType === 'error' ? 'danger' : 'success' ?> mb-3"><?= h($msgText) ?></div>
<?php endif; ?>

<!-- Filter bar -->
<div class="filter-bar card" style="margin-bottom:1.5rem">
  <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center">
    <input type="text" name="search" value="<?= h($search) ?>" class="form-control" placeholder="Tìm tên, email, SĐT…" style="width:250px">
    <select name="role" class="form-control" style="width:150px">
      <option value="">Tất cả vai trò</option>
      <option value="customer" <?= $role==='customer'?'selected':'' ?>>Khách hàng</option>
      <option value="admin" <?= $role==='admin'?'selected':'' ?>>Admin</option>
    </select>
    <select name="status" class="form-control" style="width:160px">
      <option value="">Tất cả TT</option>
      <option value="active" <?= $status==='active'?'selected':'' ?>>Hoạt động</option>
      <option value="locked" <?= $status==='locked'?'selected':'' ?>>Đã khoá</option>
    </select>
    <button type="submit" class="btn btn-outline">Lọc</button>
    <a href="<?= ADMIN_URL ?>/users.php?add=1" class="btn btn-primary" style="margin-left:auto">+ Thêm người dùng</a>
  </form>
</div>

<div class="card">
  <table class="admin-table">
    <thead><tr><th>Họ tên</th><th>Email</th><th>SĐT</th><th>Vai trò</th><th>Trạng thái</th><th>Ngày tạo</th><th>Thao tác</th></tr></thead>
    <tbody>
      <?php if (empty($paged['items'])): ?>
      <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:2rem">Không có người dùng nào</td></tr>
      <?php else: foreach ($paged['items'] as $u): ?>
      <tr>
        <td><strong><?= h($u['name']) ?></strong></td>
        <td><?= h($u['email']) ?></td>
        <td><?= h($u['phone'] ?: '—') ?></td>
        <td><span class="badge <?= $u['role']==='admin'?'badge-primary':'badge-secondary' ?>"><?= $u['role']==='admin'?'Admin':'Khách hàng' ?></span></td>
        <td><span class="badge <?= $u['is_active']?'badge-success':'badge-danger' ?>"><?= $u['is_active']?'Hoạt động':'Đã khoá' ?></span></td>
        <td><?= format_date($u['created_at']) ?></td>
        <td>
          <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline">Sửa</a>
            <a href="?reset=<?= $u['id'] ?>" class="btn btn-sm btn-outline">Đặt MK</a>
            <?php $curAdmin = auth_admin(); if ($u['id'] != $curAdmin['id']): ?>
            <form method="POST" style="display:inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $u['id'] ?>">
              <button type="submit" class="btn btn-sm <?= $u['is_active']?'btn-danger':'btn-success' ?>"><?= $u['is_active']?'Khoá':'Mở khoá' ?></button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
  <?= admin_pagination($paged, ADMIN_URL . '/users.php', array_filter(compact('search','role','status'))) ?>
</div>

<!-- Add/Edit Modal -->
<?php
$showModal = isset($_GET['add']) || $editUser;
$resetId   = sanitize_int($_GET['reset'] ?? 0);
$resetUser = $resetId ? db_row('SELECT id,name FROM users WHERE id=?', [$resetId]) : null;
?>
<?php if ($showModal): ?>
<div class="modal-overlay active" onclick="if(event.target===this)location.href='<?= ADMIN_URL ?>/users.php'">
  <div class="modal" style="max-width:520px">
    <div class="modal-header">
      <h3><?= $editUser ? 'Sửa người dùng' : 'Thêm người dùng' ?></h3>
      <a href="<?= ADMIN_URL ?>/users.php" class="modal-close">✕</a>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editUser['id'] ?? 0 ?>">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Họ tên *</label>
          <input type="text" name="name" class="form-control" value="<?= h($editUser['name']??'') ?>" required></div>
        <div class="form-group"><label class="form-label">Email *</label>
          <input type="email" name="email" class="form-control" value="<?= h($editUser['email']??'') ?>" required></div>
        <div class="form-group"><label class="form-label">Số điện thoại</label>
          <input type="tel" name="phone" class="form-control" value="<?= h($editUser['phone']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Địa chỉ</label>
          <input type="text" name="address" class="form-control" value="<?= h($editUser['address']??'') ?>"></div>
        <div class="form-group"><label class="form-label">Vai trò</label>
          <select name="role" class="form-control">
            <option value="customer" <?= ($editUser['role']??'customer')==='customer'?'selected':'' ?>>Khách hàng</option>
            <option value="admin" <?= ($editUser['role']??'')==='admin'?'selected':'' ?>>Admin</option>
          </select></div>
        <?php if (!$editUser): ?>
        <div class="form-group"><label class="form-label">Mật khẩu *</label>
          <input type="password" name="password" class="form-control" placeholder="Tối thiểu 6 ký tự"></div>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <a href="<?= ADMIN_URL ?>/users.php" class="btn btn-outline">Huỷ</a>
        <button type="submit" class="btn btn-primary">Lưu</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- Reset Password Modal -->
<?php if ($resetUser): ?>
<div class="modal-overlay active" onclick="if(event.target===this)location.href='<?= ADMIN_URL ?>/users.php'">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <h3>Đặt lại mật khẩu</h3>
      <a href="<?= ADMIN_URL ?>/users.php" class="modal-close">✕</a>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="reset_password">
      <input type="hidden" name="id" value="<?= $resetUser['id'] ?>">
      <div class="modal-body">
        <p style="margin-bottom:1rem">Người dùng: <strong><?= h($resetUser['name']) ?></strong></p>
        <div class="form-group"><label class="form-label">Mật khẩu mới *</label>
          <input type="password" name="new_password" class="form-control" placeholder="Tối thiểu 6 ký tự"></div>
      </div>
      <div class="modal-footer">
        <a href="<?= ADMIN_URL ?>/users.php" class="btn btn-outline">Huỷ</a>
        <button type="submit" class="btn btn-primary">Xác nhận</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php admin_layout_end(); ?>
