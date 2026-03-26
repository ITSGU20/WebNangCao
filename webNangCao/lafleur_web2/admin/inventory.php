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
    if ($action === 'adjust') {
        $pid  = sanitize_int($_POST['product_id'] ?? 0);
        $type = $_POST['adj_type'] ?? 'set';
        $qty  = sanitize_int($_POST['qty'] ?? 0);
        if ($pid && $qty >= 0) {
            if ($type === 'add') db_exec('UPDATE products SET stock=stock+? WHERE id=?',[$qty,$pid]);
            elseif ($type === 'subtract') db_exec('UPDATE products SET stock=GREATEST(0,stock-?) WHERE id=?',[$qty,$pid]);
            else db_exec('UPDATE products SET stock=? WHERE id=?',[$qty,$pid]);
            $msg='success:Đã điều chỉnh tồn kho.';
        }
    }
}

$threshold = max(1, sanitize_int($_GET['threshold'] ?? 10));
$search    = trim($_GET['search'] ?? '');
$catF      = sanitize_int($_GET['cat'] ?? 0);
$stockF    = $_GET['stock'] ?? '';
$reportFrom= $_GET['rfrom'] ?? '';
$reportTo  = $_GET['rto']   ?? '';
$page      = max(1, sanitize_int($_GET['page'] ?? 1));
$cats      = db_query('SELECT * FROM categories ORDER BY name');

$where=['1=1']; $params=[];
if ($search) { $where[]='p.name LIKE ?'; $params[]="%$search%"; }
if ($catF)   { $where[]='p.category_id=?'; $params[]=$catF; }
if ($stockF==='out')  $where[]='p.stock<=0';
if ($stockF==='low')  { $where[]='p.stock>0 AND p.stock<=?'; $params[]=$threshold; }
if ($stockF==='ok')   { $where[]='p.stock>?'; $params[]=$threshold; }
$sql='SELECT p.*,c.name AS cat_name FROM products p JOIN categories c ON c.id=p.category_id WHERE '.implode(' AND ',$where).' ORDER BY p.stock ASC, p.name ASC';
$paged=db_paginate($sql,$params,$page,ADMIN_PAGE_SIZE);

// Stats
$totalStock = db_val('SELECT COALESCE(SUM(stock),0) FROM products WHERE is_active=1');
$outOfStock = db_val('SELECT COUNT(*) FROM products WHERE is_active=1 AND stock<=0');
$lowStock   = db_val('SELECT COUNT(*) FROM products WHERE is_active=1 AND stock>0 AND stock<=?',[$threshold]);
$stockValue = db_val('SELECT COALESCE(SUM(stock*cost_price),0) FROM products WHERE is_active=1');

// Report: nhap xuat theo khoang thoi gian
$report = [];
if ($reportFrom && $reportTo) {
    $report = db_query(
        'SELECT p.id,p.name,p.emoji,p.stock,
                COALESCE((SELECT SUM(ii.quantity) FROM import_items ii JOIN import_receipts ir ON ir.id=ii.receipt_id WHERE ii.product_id=p.id AND ir.status="completed" AND ir.import_date BETWEEN ? AND ?),0) AS total_imported,
                COALESCE((SELECT SUM(oi.quantity) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_id=p.id AND o.status<>"cancelled" AND DATE(o.created_at) BETWEEN ? AND ?),0) AS total_sold
         FROM products p WHERE p.is_active=1 ORDER BY p.name',
        [$reportFrom,$reportTo,$reportFrom,$reportTo]
    );
}

$editId = sanitize_int($_GET['adj'] ?? 0);
$adjProduct = $editId ? db_row('SELECT id,name,emoji,stock FROM products WHERE id=?',[$editId]) : null;

admin_layout_start('Tồn kho & Báo cáo','inventory');
[$t,$m]=explode(':',$msg?:':',2); if ($m) echo '<div class="alert alert-'.($t==='error'?'danger':'success').' mb-3">'.h($m).'</div>';
?>
<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem">
  <?php foreach ([
    ['📦','#e8f4f8',$totalStock,'Tổng tồn kho',''],
    ['❌','#f8d7da',$outOfStock,'Hết hàng','color:#e74c3c'],
    ['⚠️','#fff3cd',$lowStock,'Sắp hết (≤'.$threshold.')','color:#f39c12'],
    ['💰','#d4edda',format_currency($stockValue),'Giá trị tồn','font-size:1rem'],
  ] as [$icon,$bg,$val,$lbl,$style]): ?>
  <div class="admin-stat-card">
    <div class="stat-icon" style="background:<?= $bg ?>"><?= $icon ?></div>
    <div class="stat-info"><div class="stat-value" style="<?= $style ?>"><?= h($val) ?></div><div class="stat-label"><?= h($lbl) ?></div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Low stock alert -->
<?php $lowList = db_query('SELECT name,emoji,stock FROM products WHERE is_active=1 AND stock<=? AND stock>0 ORDER BY stock',[$threshold]);
if ($lowList): ?>
<div class="card" style="margin-bottom:1.5rem;border-left:4px solid #f39c12">
  <h4 style="color:#f39c12;margin-bottom:.75rem">⚠️ Sản phẩm sắp hết hàng (≤ <?= $threshold ?> cái)</h4>
  <div style="display:flex;flex-wrap:wrap;gap:.5rem">
    <?php foreach ($lowList as $lp): ?>
    <span style="background:var(--bg);border:1px solid #f39c1233;border-radius:8px;padding:.3rem .75rem;font-size:.85rem">
      <?= h($lp['emoji'].' '.$lp['name']) ?> <strong style="color:#f39c12">(<?= $lp['stock'] ?>)</strong>
    </span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- Report -->
<div class="card" style="margin-bottom:1.5rem">
  <h4 style="font-family:var(--font-display);color:var(--chocolate);margin-bottom:1rem">📊 Báo cáo nhập – xuất – tồn</h4>
  <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem">
    <input type="hidden" name="threshold" value="<?= $threshold ?>">
    <label style="font-size:.83rem;font-weight:600;color:var(--muted)">Từ ngày:</label>
    <input type="date" name="rfrom" value="<?= h($reportFrom) ?>" class="form-control" style="width:155px">
    <label style="font-size:.83rem;font-weight:600;color:var(--muted)">Đến ngày:</label>
    <input type="date" name="rto" value="<?= h($reportTo) ?>" class="form-control" style="width:155px">
    <button type="submit" class="btn btn-outline">📊 Xem báo cáo</button>
  </form>
  <?php if ($report): ?>
  <table class="admin-table">
    <thead><tr><th>Sản phẩm</th><th>Nhập kho</th><th>Bán ra</th><th>Tồn hiện tại</th></tr></thead>
    <tbody>
      <?php foreach ($report as $r): ?>
      <tr>
        <td><?= h($r['emoji'].' '.$r['name']) ?></td>
        <td style="color:#27ae60;font-weight:600">+<?= $r['total_imported'] ?></td>
        <td style="color:#e74c3c;font-weight:600">-<?= $r['total_sold'] ?></td>
        <td><span class="badge <?= $r['stock']<=0?'badge-danger':($r['stock']<=$threshold?'badge-warning':'badge-success') ?>"><?= $r['stock'] ?></span></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php elseif ($reportFrom && $reportTo): ?>
  <p style="color:var(--muted)">Không có dữ liệu trong khoảng thời gian này.</p>
  <?php else: ?>
  <p style="color:var(--muted);font-size:.85rem">Chọn khoảng thời gian để xem báo cáo.</p>
  <?php endif; ?>
</div>

<!-- Filters -->
<div class="filter-bar card" style="margin-bottom:1.5rem">
  <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center">
    <input type="text" name="search" value="<?= h($search) ?>" class="form-control" placeholder="Tìm sản phẩm…" style="width:200px">
    <select name="cat" class="form-control" style="width:170px">
      <option value="">Tất cả danh mục</option>
      <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $catF==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
    </select>
    <select name="stock" class="form-control" style="width:180px">
      <option value="">Tất cả mức tồn</option>
      <option value="out" <?= $stockF==='out'?'selected':'' ?>>Hết hàng (0)</option>
      <option value="low" <?= $stockF==='low'?'selected':'' ?>>Sắp hết (≤ ngưỡng)</option>
      <option value="ok" <?= $stockF==='ok'?'selected':'' ?>>Còn hàng (> ngưỡng)</option>
    </select>
    <div style="display:flex;align-items:center;gap:.4rem">
      <label style="font-size:.82rem;color:var(--muted);white-space:nowrap">⚠️ Ngưỡng:</label>
      <input type="number" name="threshold" value="<?= $threshold ?>" min="1" max="999" class="form-control" style="width:70px;text-align:center">
    </div>
    <button type="submit" class="btn btn-outline">Lọc</button>
  </form>
</div>

<!-- Stock table -->
<div class="card">
  <table class="admin-table">
    <thead><tr><th>Sản phẩm</th><th>Danh mục</th><th>Tồn kho</th><th>Mức tồn</th><th>Giá vốn</th><th>Giá trị tồn</th><th>Thao tác</th></tr></thead>
    <tbody>
      <?php foreach ($paged['items'] as $p):
        $pct  = $p['stock'] <= 0 ? 0 : min(100, round($p['stock'] / max(1, 100) * 100));
        $barClr = $p['stock'] <= 0 ? '#e74c3c' : ($p['stock'] <= $threshold ? '#f39c12' : '#27ae60');
      ?>
      <tr>
        <td><div style="display:flex;align-items:center;gap:.5rem"><span><?= h($p['emoji']) ?></span><strong><?= h($p['name']) ?></strong></div></td>
        <td><?= h($p['cat_name']) ?></td>
        <td style="font-size:1.1rem;font-weight:700;color:<?= $barClr ?>"><?= $p['stock'] ?></td>
        <td><div style="width:80px;height:6px;background:var(--border);border-radius:3px;overflow:hidden"><div style="width:<?= $pct ?>%;height:100%;background:<?= $barClr ?>;border-radius:3px"></div></div></td>
        <td style="color:var(--muted)"><?= $p['cost_price']>0 ? format_currency($p['cost_price']) : '—' ?></td>
        <td style="font-weight:600"><?= $p['cost_price']>0&&$p['stock']>0 ? format_currency($p['stock']*$p['cost_price']) : '—' ?></td>
        <td><a href="?adj=<?= $p['id'] ?>&threshold=<?= $threshold ?>" class="btn btn-sm btn-outline">Điều chỉnh</a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?= admin_pagination($paged, ADMIN_URL.'/inventory.php', array_filter(['search'=>$search,'cat'=>$catF,'stock'=>$stockF,'threshold'=>$threshold])) ?>
</div>

<!-- Adjust Modal -->
<?php if ($adjProduct): ?>
<div class="modal-overlay active" onclick="if(event.target===this)location.href='<?= ADMIN_URL ?>/inventory.php'">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <h3>Điều chỉnh tồn kho</h3>
      <a href="<?= ADMIN_URL ?>/inventory.php" class="modal-close">✕</a>
    </div>
    <form method="POST">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="adjust">
      <input type="hidden" name="product_id" value="<?= $adjProduct['id'] ?>">
      <div class="modal-body">
        <p style="font-weight:600;margin-bottom:1rem"><?= h($adjProduct['emoji'].' '.$adjProduct['name']) ?></p>
        <div style="background:var(--bg);border-radius:8px;padding:.8rem 1rem;margin-bottom:1rem;font-size:.87rem">
          Tồn kho hiện tại: <strong style="font-size:1.1rem"><?= $adjProduct['stock'] ?></strong>
        </div>
        <div class="form-group"><label class="form-label">Loại điều chỉnh</label>
          <select name="adj_type" class="form-control">
            <option value="add">Cộng thêm</option>
            <option value="subtract">Trừ bớt</option>
            <option value="set">Đặt về mức</option>
          </select></div>
        <div class="form-group"><label class="form-label">Số lượng *</label>
          <input type="number" name="qty" class="form-control" min="0" required placeholder="0"></div>
      </div>
      <div class="modal-footer">
        <a href="<?= ADMIN_URL ?>/inventory.php" class="btn btn-outline">Huỷ</a>
        <button type="submit" class="btn btn-primary">Xác nhận</button>
      </div>
    </form>
  </div>
</div>
<?php endif;
admin_layout_end(); ?>
