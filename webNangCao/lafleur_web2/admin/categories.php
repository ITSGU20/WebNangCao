<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout.php';

admin_guard();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id   = sanitize_int($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $emoji= trim($_POST['emoji'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if (!$name) { $msg='error:Vui lòng nhập tên danh mục.'; }
        elseif ($id) {
            db_exec('UPDATE categories SET name=?,emoji=?,description=? WHERE id=?',[$name,$emoji,$desc,$id]);
            $msg='success:Cập nhật danh mục thành công.';
        } else {
            db_insert('INSERT INTO categories (name,emoji,description) VALUES (?,?,?)',[$name,$emoji,$desc]);
            $msg='success:Thêm danh mục thành công.';
        }
    }
    if ($action === 'toggle') {
        $id = sanitize_int($_POST['id']??0);
        db_exec('UPDATE categories SET is_active = NOT is_active WHERE id=?',[$id]);
        $msg='success:Đã thay đổi trạng thái.';
    }
    if ($action === 'delete') {
        $id = sanitize_int($_POST['id']??0);
        $count = db_val('SELECT COUNT(*) FROM products WHERE category_id=?',[$id]);
        if ($count) db_exec('UPDATE categories SET is_active=0 WHERE id=?',[$id]);
        else db_exec('DELETE FROM categories WHERE id=?',[$id]);
        $msg='success:Đã xử lý danh mục.';
    }
}

$search = trim($_GET['search']??'');
$page   = max(1,sanitize_int($_GET['page']??1));
$params = $search ? ["%$search%"] : [];
$where  = $search ? 'WHERE name LIKE ?' : '';
$paged  = db_paginate("SELECT * FROM categories $where ORDER BY id",$params,$page,ADMIN_PAGE_SIZE);

$editId = sanitize_int($_GET['edit']??0);
$editCat= $editId ? db_row('SELECT * FROM categories WHERE id=?',[$editId]) : null;
$showModal = isset($_GET['add']) || $editCat;

admin_layout_start('Quản lý danh mục','categories');
[$t,$m] = $msg ? explode(':',$msg,2) : ['',''];
if ($m) echo '<div class="alert alert-'.($t==='error'?'danger':'success').' mb-3">'.h($m).'</div>';
?>
<div class="filter-bar card" style="margin-bottom:1.5rem">
  <form method="GET" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap">
    <input type="text" name="search" value="<?= h($search) ?>" class="form-control" placeholder="Tìm tên danh mục…" style="width:250px">
    <button type="submit" class="btn btn-outline">Lọc</button>
    <a href="?add=1" class="btn btn-primary" style="margin-left:auto">+ Thêm danh mục</a>
  </form>
</div>
<div class="card">
  <table class="admin-table">
    <thead><tr><th>Emoji</th><th>Tên danh mục</th><th>Mô tả</th><th>Số SP</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
    <tbody>
      <?php foreach ($paged['items'] as $c):
        $cnt = db_val('SELECT COUNT(*) FROM products WHERE category_id=?',[$c['id']]); ?>
      <tr>
        <td style="font-size:1.8rem"><?= h($c['emoji']) ?></td>
        <td><strong><?= h($c['name']) ?></strong></td>
        <td style="color:var(--muted)"><?= h($c['description']?:'—') ?></td>
        <td><span class="badge badge-secondary"><?= $cnt ?> SP</span></td>
        <td><span class="badge <?= $c['is_active']?'badge-success':'badge-danger' ?>"><?= $c['is_active']?'Hiển thị':'Ẩn' ?></span></td>
        <td>
          <div style="display:flex;gap:.4rem">
            <a href="?edit=<?= $c['id'] ?>" class="btn btn-sm btn-outline">Sửa</a>
            <form method="POST" style="display:inline"><?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button class="btn btn-sm <?= $c['is_active']?'btn-outline':'btn-success' ?>"><?= $c['is_active']?'Ẩn':'Hiện' ?></button>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('Xoá danh mục này?')"><?= csrf_field() ?>
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $c['id'] ?>">
              <button class="btn btn-sm btn-danger">Xoá</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?= admin_pagination($paged, ADMIN_URL.'/categories.php', $search?['search'=>$search]:[]) ?>
</div>

<?php if ($showModal): ?>
<div class="modal-overlay active" onclick="if(event.target===this)location.href='<?= ADMIN_URL ?>/categories.php'">
  <div class="modal" style="max-width:480px">
    <div class="modal-header">
      <h3><?= $editCat?'Sửa danh mục':'Thêm danh mục' ?></h3>
      <a href="<?= ADMIN_URL ?>/categories.php" class="modal-close">✕</a>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editCat['id']??0 ?>">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Emoji</label>
          <input type="text" name="emoji" class="form-control" value="<?= h($editCat['emoji']??'') ?>" placeholder="🎂" style="width:80px;font-size:1.5rem;text-align:center"></div>
        <div class="form-group"><label class="form-label">Tên danh mục *</label>
          <input type="text" name="name" class="form-control" value="<?= h($editCat['name']??'') ?>" required></div>
        <div class="form-group"><label class="form-label">Mô tả</label>
          <textarea name="description" class="form-control" rows="3"><?= h($editCat['description']??'') ?></textarea></div>
      </div>
      <div class="modal-footer">
        <a href="<?= ADMIN_URL ?>/categories.php" class="btn btn-outline">Huỷ</a>
        <button type="submit" class="btn btn-primary">Lưu</button>
      </div>
    </form>
  </div>
</div>
<?php endif;
admin_layout_end(); ?>
