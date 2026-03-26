<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/admin_layout.php';

admin_guard();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'save') {
        $id         = sanitize_int($_POST['id'] ?? 0);
        $catId      = sanitize_int($_POST['category_id'] ?? 0);
        $code       = trim($_POST['code'] ?? '');
        $name       = trim($_POST['name'] ?? '');
        $emoji      = trim($_POST['emoji'] ?? '');
        $desc       = trim($_POST['description'] ?? '');
        $unit       = trim($_POST['unit'] ?? 'cái');
        $profitRate = sanitize_float($_POST['profit_rate'] ?? 0);
        $stock      = sanitize_int($_POST['stock'] ?? 0);
        $isActive   = isset($_POST['is_active']) ? 1 : 0;

        if (!$name || !$catId || !$code) { $msg='error:Vui lòng điền đầy đủ thông tin bắt buộc.'; }
        else {
            // Handle image upload
            $imagePath = $_POST['existing_image'] ?? '';
            if (!empty($_FILES['image']['name'])) {
                $newImg = upload_product_image($_FILES['image']);
                if ($newImg) {
                    // Delete old image if replacing
                    if ($id && $imagePath) delete_product_image($imagePath);
                    $imagePath = $newImg;
                }
            }
            if ($_POST['remove_image']??'' === '1') { delete_product_image($imagePath); $imagePath = ''; }

            if ($id) {
                db_exec('UPDATE products SET category_id=?,code=?,name=?,emoji=?,description=?,unit=?,profit_rate=?,image_path=?,is_active=? WHERE id=?',
                    [$catId,$code,$name,$emoji,$desc,$unit,$profitRate,$imagePath,$isActive,$id]);
                $msg='success:Cập nhật sản phẩm thành công.';
            } else {
                db_insert('INSERT INTO products (category_id,code,name,emoji,description,unit,cost_price,profit_rate,stock,image_path,is_active) VALUES (?,?,?,?,?,?,0,?,?,?,?)',
                    [$catId,$code,$name,$emoji,$desc,$unit,$profitRate,$stock,$imagePath,1]);
                $msg='success:Thêm sản phẩm thành công.';
            }
        }
    }
    if ($action === 'toggle') {
        $id = sanitize_int($_POST['id']??0);
        db_exec('UPDATE products SET is_active = NOT is_active WHERE id=?',[$id]);
        $msg='success:Đã thay đổi trạng thái.';
    }
    if ($action === 'delete') {
        $id = sanitize_int($_POST['id']??0);
        $hasOrders = db_val('SELECT COUNT(*) FROM order_items WHERE product_id=?',[$id]);
        if ($hasOrders) { db_exec('UPDATE products SET is_active=0 WHERE id=?',[$id]); $msg='success:Đã ẩn sản phẩm (đã có trong đơn hàng).'; }
        else {
            $p = db_row('SELECT image_path FROM products WHERE id=?',[$id]);
            if ($p) delete_product_image($p['image_path']);
            db_exec('DELETE FROM products WHERE id=?',[$id]);
            $msg='success:Đã xoá sản phẩm.';
        }
    }
}

$search = trim($_GET['search']??'');
$catF   = sanitize_int($_GET['cat']??0);
$statusF= $_GET['status']??'';
$page   = max(1,sanitize_int($_GET['page']??1));
$cats   = db_query('SELECT * FROM categories ORDER BY name');

$where=['1=1']; $params=[];
if ($search) { $where[]='(p.name LIKE ? OR p.code LIKE ?)'; $params=["%$search%","%$search%"]; }
if ($catF)   { $where[]='p.category_id=?'; $params[]=$catF; }
if ($statusF==='active') $where[]='p.is_active=1';
if ($statusF==='hidden') $where[]='p.is_active=0';

$sql = 'SELECT p.*,c.name AS cat_name FROM products p JOIN categories c ON c.id=p.category_id WHERE '.implode(' AND ',$where).' ORDER BY p.id DESC';
$paged = db_paginate($sql,$params,$page,ADMIN_PAGE_SIZE);

$editId = sanitize_int($_GET['edit']??0);
$editP  = $editId ? db_row('SELECT * FROM products WHERE id=?',[$editId]) : null;
$showModal = isset($_GET['add']) || $editP;

admin_layout_start('Quản lý sản phẩm','products');
[$t,$m]=explode(':',$msg?:':',2); if ($m) echo '<div class="alert alert-'.($t==='error'?'danger':'success').' mb-3">'.h($m).'</div>';
?>
<div class="filter-bar card" style="margin-bottom:1.5rem">
  <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center">
    <input type="text" name="search" value="<?= h($search) ?>" class="form-control" placeholder="Tìm tên, mã SP…" style="width:220px">
    <select name="cat" class="form-control" style="width:180px">
      <option value="">Tất cả danh mục</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= $c['id'] ?>" <?= $catF==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="form-control" style="width:150px">
      <option value="">Tất cả</option>
      <option value="active" <?= $statusF==='active'?'selected':'' ?>>Hiển thị</option>
      <option value="hidden" <?= $statusF==='hidden'?'selected':'' ?>>Ẩn</option>
    </select>
    <button type="submit" class="btn btn-outline">Lọc</button>
    <a href="?add=1" class="btn btn-primary" style="margin-left:auto">+ Thêm sản phẩm</a>
  </form>
</div>

<div class="card">
  <table class="admin-table">
    <thead><tr><th>Sản phẩm</th><th>Mã</th><th>Danh mục</th><th>Giá bán</th><th>Giá vốn</th><th>Tồn</th><th>TT</th><th>Thao tác</th></tr></thead>
    <tbody>
      <?php foreach ($paged['items'] as $p):
        $sell = calc_sell_price($p['cost_price'], $p['profit_rate']);
        $thumb = $p['image_path']
          ? '<img src="'.BASE_URL.'/uploads/products/'.h($p['image_path']).'" style="width:40px;height:40px;object-fit:cover;border-radius:6px" alt="">'
          : '<span style="font-size:1.6rem">'.h($p['emoji']).'</span>';
      ?>
      <tr>
        <td><div style="display:flex;align-items:center;gap:.75rem"><?= $thumb ?><strong><?= h($p['name']) ?></strong></div></td>
        <td><code style="background:var(--bg);padding:.15rem .4rem;border-radius:4px"><?= h($p['code']) ?></code></td>
        <td><?= h($p['cat_name']) ?></td>
        <td style="color:var(--primary);font-weight:600"><?= format_currency($sell) ?></td>
        <td style="color:var(--muted)"><?= $p['cost_price']>0 ? format_currency($p['cost_price']) : '—' ?></td>
        <td><span class="badge <?= $p['stock']<=5?'badge-danger':'badge-secondary' ?>"><?= $p['stock'] ?></span></td>
        <td><span class="badge <?= $p['is_active']?'badge-success':'badge-danger' ?>"><?= $p['is_active']?'Hiện':'Ẩn' ?></span></td>
        <td>
          <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <a href="?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline">Sửa</a>
            <form method="POST" style="display:inline"><?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button class="btn btn-sm <?= $p['is_active']?'btn-outline':'btn-success' ?>"><?= $p['is_active']?'Ẩn':'Hiện' ?></button>
            </form>
            <form method="POST" style="display:inline" onsubmit="return confirm('Xoá/ẩn sản phẩm này?')"><?= csrf_field() ?>
              <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $p['id'] ?>">
              <button class="btn btn-sm btn-danger">Xoá</button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?= admin_pagination($paged, ADMIN_URL.'/admin_products.php', array_filter(compact('search')+['cat'=>$catF,'status'=>$statusF])) ?>
</div>

<?php if ($showModal): ?>
<div class="modal-overlay active" onclick="if(event.target===this)location.href='<?= ADMIN_URL ?>/admin_products.php'">
  <div class="modal" style="max-width:640px">
    <div class="modal-header">
      <h3><?= $editP?'Sửa sản phẩm':'Thêm sản phẩm' ?></h3>
      <a href="<?= ADMIN_URL ?>/admin_products.php" class="modal-close">✕</a>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save">
      <input type="hidden" name="id" value="<?= $editP['id']??0 ?>">
      <input type="hidden" name="existing_image" value="<?= h($editP['image_path']??'') ?>">
      <input type="hidden" name="remove_image" id="removeImgFlag" value="0">
      <div class="modal-body">
        <!-- Image upload -->
        <div class="form-group">
          <label class="form-label">Hình ảnh sản phẩm</label>
          <?php if (!empty($editP['image_path'])): ?>
          <div style="margin-bottom:.8rem;position:relative;display:inline-block">
            <img src="<?= BASE_URL ?>/uploads/products/<?= h($editP['image_path']) ?>" style="height:100px;border-radius:8px;object-fit:cover" alt="current">
            <button type="button" onclick="document.getElementById('removeImgFlag').value='1';this.parentElement.remove();document.getElementById('imgPreview').style.display='none'" style="position:absolute;top:-6px;right:-6px;width:20px;height:20px;background:#e74c3c;color:white;border:none;border-radius:50%;cursor:pointer;font-size:.7rem">✕</button>
          </div>
          <?php endif; ?>
          <input type="file" name="image" class="form-control" accept="image/*" onchange="previewImg(this)">
          <img id="imgPreview" style="display:none;margin-top:.5rem;height:100px;border-radius:8px;object-fit:cover" alt="preview">
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group"><label class="form-label">Emoji (fallback)</label>
            <input type="text" name="emoji" class="form-control" value="<?= h($editP['emoji']??'') ?>" placeholder="🎂" style="font-size:1.3rem;text-align:center"></div>
          <div class="form-group"><label class="form-label">Mã SP *</label>
            <input type="text" name="code" class="form-control" value="<?= h($editP['code']??'') ?>" required></div>
          <div class="form-group" style="grid-column:1/-1"><label class="form-label">Tên sản phẩm *</label>
            <input type="text" name="name" class="form-control" value="<?= h($editP['name']??'') ?>" required></div>
          <div class="form-group"><label class="form-label">Danh mục *</label>
            <select name="category_id" class="form-control" required>
              <option value="">-- Chọn --</option>
              <?php foreach ($cats as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($editP['category_id']??0)==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option>
              <?php endforeach; ?>
            </select></div>
          <div class="form-group"><label class="form-label">Đơn vị tính</label>
            <input type="text" name="unit" class="form-control" value="<?= h($editP['unit']??'cái') ?>"></div>
          <div class="form-group"><label class="form-label">% Lợi nhuận mong muốn</label>
            <input type="number" name="profit_rate" class="form-control" value="<?= h($editP['profit_rate']??0) ?>" min="0" step="0.01" placeholder="60"></div>
          <?php if (!$editP): ?>
          <div class="form-group"><label class="form-label">Tồn kho ban đầu</label>
            <input type="number" name="stock" class="form-control" value="0" min="0"></div>
          <?php endif; ?>
          <div class="form-group" style="grid-column:1/-1"><label class="form-label">Mô tả</label>
            <textarea name="description" class="form-control" rows="3"><?= h($editP['description']??'') ?></textarea></div>
          <div class="form-group" style="display:flex;align-items:center;gap:.8rem">
            <input type="checkbox" name="is_active" id="isActive" value="1" <?= ($editP['is_active']??1)?'checked':'' ?>>
            <label for="isActive" class="form-label" style="margin:0">Hiển thị (đang bán)</label>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="<?= ADMIN_URL ?>/admin_products.php" class="btn btn-outline">Huỷ</a>
        <button type="submit" class="btn btn-primary">Lưu</button>
      </div>
    </form>
  </div>
</div>
<script>function previewImg(input){const r=new FileReader();r.onload=e=>{const i=document.getElementById('imgPreview');i.src=e.target.result;i.style.display='block'};r.readAsDataURL(input.files[0])}</script>
<?php endif;
admin_layout_end(); ?>
