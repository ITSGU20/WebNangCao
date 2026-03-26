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
    if ($action === 'save_price') {
        $id   = sanitize_int($_POST['id'] ?? 0);
        $rate = sanitize_float($_POST['profit_rate'] ?? 0);
        if ($id && $rate >= 0) {
            db_exec('UPDATE products SET profit_rate=? WHERE id=?', [$rate, $id]);
            $msg = 'success:Cập nhật % lợi nhuận thành công.';
        }
    }
    if ($action === 'bulk') {
        $catId = sanitize_int($_POST['bulk_cat'] ?? 0);
        $type  = $_POST['bulk_type'] ?? '';
        $val   = sanitize_float($_POST['bulk_val'] ?? 0);
        $where = $catId ? 'WHERE category_id=?' : '';
        $params= $catId ? [$catId] : [];
        if ($type === 'set_rate') {
            db_exec("UPDATE products SET profit_rate=? $where", array_merge([$val], $params));
        }
        $msg = 'success:Đã cập nhật hàng loạt.';
    }
}

$search  = trim($_GET['search'] ?? '');
$catF    = sanitize_int($_GET['cat'] ?? 0);
$page    = max(1, sanitize_int($_GET['page'] ?? 1));
$cats    = db_query('SELECT * FROM categories ORDER BY name');
$where   = ['1=1']; $params = [];
if ($search) { $where[] = 'p.name LIKE ?'; $params[] = "%$search%"; }
if ($catF)   { $where[] = 'p.category_id=?'; $params[] = $catF; }
$sql     = 'SELECT p.*,c.name AS cat_name FROM products p JOIN categories c ON c.id=p.category_id WHERE '.implode(' AND ',$where).' ORDER BY c.name,p.name';
$paged   = db_paginate($sql,$params,$page,ADMIN_PAGE_SIZE);

$editId  = sanitize_int($_GET['edit'] ?? 0);
$editP   = $editId ? db_row('SELECT p.*,c.name AS cat_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=?',[$editId]) : null;

admin_layout_start('Quản lý giá & lợi nhuận','pricing');
[$t,$m]=explode(':',$msg?:':',2); if ($m) echo '<div class="alert alert-'.($t==='error'?'danger':'success').' mb-3">'.h($m).'</div>';

// Stats
$avgRate = db_val('SELECT AVG(profit_rate) FROM products WHERE cost_price>0 AND is_active=1');
$noCost  = db_val('SELECT COUNT(*) FROM products WHERE cost_price=0 AND is_active=1');
?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:1rem;margin-bottom:1.5rem">
  <?php foreach ([
    [db_val('SELECT COUNT(*) FROM products WHERE is_active=1'),'Tổng SP',''],
    [round($avgRate??0,1).'%','LN trung bình',''],
    [$noCost,'Chưa có giá vốn','color:#e74c3c'],
  ] as [$val,$lbl,$style]): ?>
  <div style="background:var(--white);border-radius:var(--radius);padding:1rem 1.25rem;box-shadow:0 2px 12px var(--shadow)">
    <div style="font-size:1.4rem;font-weight:700;color:var(--espresso);<?= $style ?>"><?= h($val) ?></div>
    <div style="font-size:.78rem;color:var(--muted)"><?= h($lbl) ?></div>
  </div>
  <?php endforeach; ?>
  <!-- Bulk adjust inline form -->
  <div style="background:var(--white);border-radius:var(--radius);padding:1rem;box-shadow:0 2px 12px var(--shadow);grid-column:span 2">
    <div style="font-size:.75rem;font-weight:600;color:var(--gray);margin-bottom:.5rem">Đặt % LN hàng loạt</div>
    <form method="POST" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
      <?= csrf_field() ?><input type="hidden" name="action" value="bulk"><input type="hidden" name="bulk_type" value="set_rate">
      <select name="bulk_cat" class="form-control" style="width:140px;font-size:.82rem">
        <option value="">Tất cả SP</option>
        <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option><?php endforeach; ?>
      </select>
      <input type="number" name="bulk_val" class="form-control" placeholder="%" min="0" step="0.1" style="width:80px">
      <button type="submit" class="btn btn-sm btn-outline">Áp dụng</button>
    </form>
  </div>
</div>

<div class="filter-bar card" style="margin-bottom:1.5rem">
  <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center">
    <input type="text" name="search" value="<?= h($search) ?>" class="form-control" placeholder="Tìm sản phẩm…" style="width:220px">
    <select name="cat" class="form-control" style="width:180px">
      <option value="">Tất cả danh mục</option>
      <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $catF==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">Lọc</button>
  </form>
</div>

<div class="card">
  <table class="admin-table">
    <thead><tr><th>Sản phẩm</th><th>Danh mục</th><th>Giá vốn</th><th>% LN</th><th>Giá bán</th><th>Lợi nhuận</th><th>Thao tác</th></tr></thead>
    <tbody>
      <?php foreach ($paged['items'] as $p):
        $sell   = calc_sell_price($p['cost_price'], $p['profit_rate']);
        $profit = $sell - $p['cost_price'];
        $pctCls = $p['profit_rate']>=40 ? 'color:#27ae60;font-weight:600' : ($p['profit_rate']>=20 ? 'color:#f39c12;font-weight:600' : 'color:#e74c3c;font-weight:600');
      ?>
      <tr>
        <td><div style="display:flex;align-items:center;gap:.5rem"><span><?= h($p['emoji']) ?></span><strong><?= h($p['name']) ?></strong></div></td>
        <td><?= h($p['cat_name']) ?></td>
        <td><?= $p['cost_price']>0 ? format_currency($p['cost_price']) : '<span style="color:var(--muted)">Chưa có</span>' ?></td>
        <td><span style="<?= $pctCls ?>"><?= number_format($p['profit_rate'],1) ?>%</span></td>
        <td style="font-weight:600"><?= format_currency($sell) ?></td>
        <td><?= $p['cost_price']>0 ? format_currency($profit) : '—' ?></td>
        <td><a href="?edit=<?= $p['id'] ?>" class="btn btn-sm btn-outline">Cập nhật % LN</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?= admin_pagination($paged, ADMIN_URL.'/pricing.php', array_filter(['search'=>$search,'cat'=>$catF])) ?>
</div>

<?php if ($editP): ?>
<div class="modal-overlay active" onclick="if(event.target===this)location.href='<?= ADMIN_URL ?>/pricing.php'">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <h3>Cập nhật % lợi nhuận</h3>
      <a href="<?= ADMIN_URL ?>/pricing.php" class="modal-close">✕</a>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_price">
      <input type="hidden" name="id" value="<?= $editP['id'] ?>">
      <div class="modal-body">
        <p style="margin-bottom:1rem;font-weight:600"><?= h($editP['emoji'].' '.$editP['name']) ?></p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;font-size:.85rem;background:var(--bg);padding:.8rem;border-radius:8px">
          <div><span style="color:var(--muted);display:block;font-size:.75rem">Giá vốn hiện tại</span><strong><?= format_currency($editP['cost_price']) ?></strong></div>
          <div><span style="color:var(--muted);display:block;font-size:.75rem">Giá bán hiện tại</span><strong><?= format_currency(calc_sell_price($editP['cost_price'],$editP['profit_rate'])) ?></strong></div>
        </div>
        <div class="form-group"><label class="form-label">% Lợi nhuận mới *</label>
          <input type="number" name="profit_rate" id="pRate" class="form-control" value="<?= $editP['profit_rate'] ?>" min="0" step="0.01" oninput="previewPrice()"></div>
        <div style="background:var(--bg);border-radius:8px;padding:1rem;text-align:center">
          <div style="font-size:.82rem;color:var(--muted)">Giá bán mới</div>
          <div id="pricePreview" style="font-size:1.4rem;font-weight:700;color:var(--chocolate)"><?= format_currency(calc_sell_price($editP['cost_price'],$editP['profit_rate'])) ?></div>
        </div>
      </div>
      <div class="modal-footer">
        <a href="<?= ADMIN_URL ?>/pricing.php" class="btn btn-outline">Huỷ</a>
        <button type="submit" class="btn btn-primary">Lưu</button>
      </div>
    </form>
  </div>
</div>
<script>
const costPrice=<?= $editP['cost_price'] ?>;
function previewPrice(){
  const rate=parseFloat(document.getElementById('pRate').value)||0;
  const sell=Math.round(costPrice*(1+rate/100));
  document.getElementById('pricePreview').textContent=sell.toLocaleString('vi-VN')+' đ';
}
</script>
<?php endif;
admin_layout_end(); ?>
