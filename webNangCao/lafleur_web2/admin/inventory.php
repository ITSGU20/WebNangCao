<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/admin_layout.php';

admin_guard();

$threshold  = max(1, sanitize_int($_GET['threshold'] ?? 10));
$search     = trim($_GET['search'] ?? '');
$catF       = sanitize_int($_GET['cat'] ?? 0);
$stockF     = $_GET['stock'] ?? '';
$reportFrom = $_GET['rfrom'] ?? '';
$reportTo   = $_GET['rto']   ?? '';
$viewDate   = $_GET['view_date'] ?? '';
$today = date('Y-m-d');
$reportError = '';

// 1. Chặn ngày tương lai
if (($reportFrom && $reportFrom > $today) || ($reportTo && $reportTo > $today)) {
    $reportError = "Ngày báo cáo không được vượt quá hôm nay.";
    $reportFrom = $reportTo = ''; // Xóa ngày sai để ẩn báo cáo
}

// 2. Chặn ngày bắt đầu lớn hơn ngày kết thúc
if ($reportFrom && $reportTo && $reportFrom > $reportTo) {
    $reportError = "Ngày bắt đầu không được lớn hơn ngày kết thúc.";
    $reportFrom = $reportTo = ''; 
}
$dateError  = '';

// Ép lỗi nếu chọn ngày tương lai
if ($viewDate && $viewDate > $today) {
    $dateError = "Vui lòng không chọn ngày ở tương lai. Dữ liệu chưa phát sinh!";
    $viewDate = '';      // Xóa ngày sai
    $isFiltered = false; // Buộc ẩn bảng
}
$detailPid  = sanitize_int($_GET['detail_pid'] ?? 0);
$page       = max(1, sanitize_int($_GET['page'] ?? 1));
$cats       = db_query('SELECT * FROM categories ORDER BY name');

// Biến cờ: Kiểm tra xem người dùng đã bấm nút Lọc/Xem chưa
$isFiltered = !empty($_GET['filter_applied']);

// ==========================================
// 1. TÍNH TỒN KHO (KHÔNG ÂM) & ÁP DỤNG BỘ LỌC
// ==========================================
$where = ['p.is_active=1']; 
$params = [];

if ($search) { $where[] = 'p.name LIKE ?'; $params[] = "%$search%"; }
if ($catF)   { $where[] = 'p.category_id=?'; $params[] = $catF; }

// Xử lý tồn kho: Dùng GREATEST(0, ...) để ép số âm về 0
$subParams = [];
if ($viewDate) {
    $stockSelect = "GREATEST(0, (
        COALESCE((SELECT SUM(ii.quantity) FROM import_items ii JOIN import_receipts ir ON ir.id=ii.receipt_id WHERE ii.product_id=p.id AND ir.status='completed' AND ir.import_date<=?),0)
        -
        COALESCE((SELECT SUM(oi.quantity) FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE oi.product_id=p.id AND o.status<>'cancelled' AND DATE(o.created_at)<=?),0)
    ))";
    $subParams[] = $viewDate;
    $subParams[] = $viewDate;
} else {
    $stockSelect = "GREATEST(0, p.stock)";
}

$innerSql = "SELECT p.*, c.name AS cat_name, $stockSelect AS dynamic_stock 
             FROM products p JOIN categories c ON c.id=p.category_id 
             WHERE " . implode(' AND ', $where);

$sqlParams = array_merge($subParams, $params);

$outerWhere = ['1=1'];
if ($stockF === 'out') { $outerWhere[] = "dynamic_stock <= 0"; }
if ($stockF === 'low') { $outerWhere[] = "dynamic_stock > 0 AND dynamic_stock <= ?"; $sqlParams[] = $threshold; }
if ($stockF === 'ok')  { $outerWhere[] = "dynamic_stock > ?"; $sqlParams[] = $threshold; }

$sql = "SELECT * FROM ($innerSql) AS temp WHERE " . implode(' AND ', $outerWhere) . " ORDER BY dynamic_stock ASC, name ASC";
$paged = db_paginate($sql, $sqlParams, $page, ADMIN_PAGE_SIZE);

// Thống kê đồng bộ với lọc
$statSql = "SELECT 
                COALESCE(SUM(dynamic_stock), 0) AS total_qty,
                COALESCE(SUM(CASE WHEN dynamic_stock <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock,
                COALESCE(SUM(CASE WHEN dynamic_stock > 0 AND dynamic_stock <= ".(int)$threshold." THEN 1 ELSE 0 END), 0) AS low_stock,
                COALESCE(SUM(dynamic_stock * cost_price), 0) AS total_value
            FROM ($innerSql) AS temp WHERE " . implode(' AND ', $outerWhere);
$stats = db_row($statSql, $sqlParams);

$totalStock = $stats['total_qty'];
$outOfStock = $stats['out_of_stock'];
$lowStock   = $stats['low_stock'];
$stockValue = $stats['total_value'];

$lowList = db_query("SELECT name, emoji, dynamic_stock AS stock FROM ($innerSql) AS temp WHERE " . implode(' AND ', $outerWhere) . " AND dynamic_stock > 0 AND dynamic_stock <= ".(int)$threshold." ORDER BY dynamic_stock ASC", $sqlParams);

// ==========================================
// 2. BÁO CÁO NHẬP–XUẤT–TỒN (Tồn kho không âm)
// ==========================================
$report = [];
if ($reportFrom && $reportTo) {
    $repWhere = ['p.is_active=1'];
    $repParams = [];
    if ($search) { $repWhere[] = 'p.name LIKE ?'; $repParams[] = "%$search%"; }
    if ($catF)   { $repWhere[] = 'p.category_id=?'; $repParams[] = $catF; }
    
    $finalRepParams = array_merge([$reportFrom, $reportTo, $reportFrom, $reportTo], $repParams);
    
    $report = db_query(
        'SELECT p.id,p.name,p.emoji, GREATEST(0, p.stock) AS stock,
                COALESCE((SELECT SUM(ii.quantity) FROM import_items ii
                          JOIN import_receipts ir ON ir.id=ii.receipt_id
                          WHERE ii.product_id=p.id AND ir.status="completed"
                            AND ir.import_date BETWEEN ? AND ?),0) AS total_imported,
                COALESCE((SELECT SUM(oi.quantity) FROM order_items oi
                          JOIN orders o ON o.id=oi.order_id
                          WHERE oi.product_id=p.id AND o.status<>"cancelled"
                            AND DATE(o.created_at) BETWEEN ? AND ?),0) AS total_sold
         FROM products p WHERE ' . implode(' AND ', $repWhere) . ' ORDER BY p.name',
        $finalRepParams
    );
}

// ==========================================
// 3. CHI TIẾT THEO NGÀY CHO 1 SẢN PHẨM (MODAL)
// ==========================================
$detailProduct = null;
$dailyDetail   = [];
if ($detailPid && $reportFrom && $reportTo) {
    $detailProduct = db_row('SELECT id,name,emoji FROM products WHERE id=?',[$detailPid]);
    if ($detailProduct) {
        $dailyDetail = db_query(
            'SELECT d.activity_date,
                    COALESCE(imp.qty_imported,0) AS imported,
                    COALESCE(ord.qty_sold,0)     AS sold
             FROM (
                 SELECT ir.import_date AS activity_date
                 FROM import_receipts ir JOIN import_items ii ON ii.receipt_id=ir.id
                 WHERE ii.product_id=? AND ir.status="completed"
                   AND ir.import_date BETWEEN ? AND ?
                 UNION
                 SELECT DATE(o.created_at)
                 FROM orders o JOIN order_items oi ON oi.order_id=o.id
                 WHERE oi.product_id=? AND o.status<>"cancelled"
                   AND DATE(o.created_at) BETWEEN ? AND ?
             ) d
             LEFT JOIN (
                 SELECT ir.import_date, SUM(ii.quantity) AS qty_imported
                 FROM import_items ii JOIN import_receipts ir ON ir.id=ii.receipt_id
                 WHERE ii.product_id=? AND ir.status="completed"
                 GROUP BY ir.import_date
             ) imp ON imp.import_date=d.activity_date
             LEFT JOIN (
                 SELECT DATE(o.created_at) AS sale_date, SUM(oi.quantity) AS qty_sold
                 FROM order_items oi JOIN orders o ON o.id=oi.order_id
                 WHERE oi.product_id=? AND o.status<>"cancelled"
                 GROUP BY DATE(o.created_at)
             ) ord ON ord.sale_date=d.activity_date
             ORDER BY d.activity_date DESC',
            [$detailPid,$reportFrom,$reportTo,
             $detailPid,$reportFrom,$reportTo,
             $detailPid,$detailPid]
        );
    }
}

admin_layout_start('Tồn kho & Báo cáo','inventory');
?>

<?php if ($isFiltered): ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem">
  <?php foreach ([
    ['📦','#e8f4f8',$totalStock,'Tổng tồn kho (theo lọc)',''],
    ['❌','#f8d7da',$outOfStock,'Hết hàng (theo lọc)','color:#e74c3c'],
    ['⚠️','#fff3cd',$lowStock,'Sắp hết (≤'.$threshold.')','color:#f39c12'],
    ['💰','#d4edda',format_currency($stockValue),'Giá trị kho (theo lọc)','font-size:1rem'],
  ] as [$icon,$bg,$val,$lbl,$style]): ?>
  <div class="admin-stat-card">
    <div class="stat-icon" style="background:<?= $bg ?>"><?= $icon ?></div>
    <div class="stat-info"><div class="stat-value" style="<?= $style ?>"><?= h($val) ?></div><div class="stat-label"><?= h($lbl) ?></div></div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if ($lowList): ?>
<div class="card" style="margin-bottom:1.5rem;border-left:4px solid #f39c12">
  <h4 style="color:#f39c12;margin-bottom:.75rem">⚠️ Sản phẩm sắp hết (≤ <?= $threshold ?> cái) trong danh sách lọc</h4>
  <div style="display:flex;flex-wrap:wrap;gap:.5rem">
    <?php foreach ($lowList as $lp): ?>
    <span style="background:var(--bg);border:1px solid #f39c1233;border-radius:8px;padding:.3rem .75rem;font-size:.85rem">
      <?= h($lp['emoji'].' '.$lp['name']) ?> <strong style="color:#f39c12">(<?= $lp['stock'] ?>)</strong>
    </span>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:1.5rem">
  <h4 style="font-family:var(--font-display);color:var(--chocolate);margin-bottom:1rem">📊 Báo cáo nhập – xuất – tồn</h4>
  <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center;margin-bottom:1rem">
    <input type="hidden" name="filter_applied" value="<?= $isFiltered ? '1' : '' ?>">
    <input type="hidden" name="threshold" value="<?= $threshold ?>">
    <input type="hidden" name="view_date" value="<?= h($viewDate) ?>">
    <input type="hidden" name="search" value="<?= h($search) ?>">
    <input type="hidden" name="cat" value="<?= h($catF) ?>">
    <input type="hidden" name="stock" value="<?= h($stockF) ?>">
    <label style="font-size:.83rem;font-weight:600;color:var(--muted)">Từ ngày:</label>
    <input type="date" name="rfrom" value="<?= h($reportFrom) ?>" max="<?= $today ?>" class="form-control" style="width:155px">
    <label style="font-size:.83rem;font-weight:600;color:var(--muted)">Đến ngày:</label>
    <input type="date" name="rto" value="<?= h($reportTo) ?>" max="<?= $today ?>" class="form-control" style="width:155px">
    <button type="submit" class="btn btn-outline">📊 Xem báo cáo</button>
    <?php if ($reportError): ?>
    <div style="color:#e74c3c; font-size:0.85rem; margin-top:0.5rem">⚠️ <?= h($reportError) ?></div>
<?php endif; ?>
  </form>

  <?php if ($report): ?>
  <table class="admin-table">
    <thead><tr><th>Sản phẩm</th><th>Nhập kho</th><th>Bán ra</th><th>Tồn hiện tại</th><th>Chi tiết</th></tr></thead>
    <tbody>
      <?php foreach ($report as $r): ?>
      <tr>
        <td><?= h($r['emoji'].' '.$r['name']) ?></td>
        <td style="color:#27ae60;font-weight:600">+<?= $r['total_imported'] ?></td>
        <td style="color:#e74c3c;font-weight:600">−<?= $r['total_sold'] ?></td>
        <td><span class="badge <?= $r['stock']<=0?'badge-danger':($r['stock']<=$threshold?'badge-warning':'badge-success') ?>"><?= $r['stock'] ?></span></td>
        <td>
          <a href="?rfrom=<?= h($reportFrom) ?>&rto=<?= h($reportTo) ?>&detail_pid=<?= $r['id'] ?>&threshold=<?= $threshold ?>&view_date=<?= h($viewDate) ?>&search=<?= h($search) ?>&cat=<?= h($catF) ?>&stock=<?= h($stockF) ?><?= $isFiltered?'&filter_applied=1':'' ?>#detail-modal"
             class="btn btn-sm btn-outline">🔍 Chi tiết</a>
        </td>
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

<div class="filter-bar card" style="margin-bottom:1.5rem">
  <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center">
    <input type="hidden" name="filter_applied" value="1">
    
    <input type="hidden" name="rfrom" value="<?= h($reportFrom) ?>">
    <input type="hidden" name="rto" value="<?= h($reportTo) ?>">
    
    <input type="text" name="search" value="<?= h($search) ?>" class="form-control" placeholder="Tìm sản phẩm…" style="width:180px">
    <select name="cat" class="form-control" style="width:150px">
      <option value="">Tất cả danh mục</option>
      <?php foreach ($cats as $c): ?><option value="<?= $c['id'] ?>" <?= $catF==$c['id']?'selected':'' ?>><?= h($c['name']) ?></option><?php endforeach; ?>
    </select>
    
    <select name="stock" class="form-control" style="width:150px">
      <option value="">Tất cả mức tồn</option>
      <option value="out" <?= $stockF==='out'?'selected':'' ?>>Hết hàng (0)</option>
      <option value="low" <?= $stockF==='low'?'selected':'' ?>>Sắp hết (≤ ngưỡng)</option>
      <option value="ok"  <?= $stockF==='ok'?'selected':'' ?>>Còn hàng (> ngưỡng)</option>
    </select>
    
    <div style="display:flex;align-items:center;gap:.4rem">
      <label style="font-size:.82rem;color:var(--muted)">Ngưỡng:</label>
      <input type="number" name="threshold" value="<?= $threshold ?>" min="1" max="999" class="form-control" style="width:65px;text-align:center">
    </div>

    <div style="width:1px;height:30px;background:var(--border);margin:0 .2rem"></div>

    <div style="display:flex;align-items:center;gap:.4rem">
      <label style="font-size:.82rem;font-weight:600;color:var(--chocolate);white-space:nowrap">📅 Xem ngày:</label>
      <input type="date" name="view_date" value="<?= h($viewDate) ?>" max="<?= date('Y-m-d') ?>" class="form-control" style="width:140px">
    </div>

    <button type="submit" class="btn btn-primary">Lọc / Xem</button>
    <?php if ($isFiltered): ?>
      <a href="?threshold=10&rfrom=<?= h($reportFrom) ?>&rto=<?= h($reportTo) ?>" class="btn btn-secondary">✕ Bỏ lọc</a>
    <?php endif; ?>
  </form>

  <?php if ($viewDate): ?>
  <div style="background:#e8f4f8;border-radius:8px;padding:.75rem 1rem;font-size:.84rem;color:#0a4d68;margin-top:1.2rem">
    📌 Đang xem và lọc theo tồn kho tính đến cuối ngày <strong><?= date('d/m/Y',strtotime($viewDate)) ?></strong>
  </div>
  <?php endif; ?>
  <?php if ($dateError): ?>
  <div style="background:#f8d7da;border-radius:8px;padding:.75rem 1rem;font-size:.84rem;color:#721c24;margin-top:1.2rem;border-left:4px solid #e74c3c">
    ❌ <strong>Lỗi:</strong> <?= h($dateError) ?>
  </div>
  <?php endif; ?>
</div>

<?php if ($isFiltered): ?>
<div class="card">
  <table class="admin-table">
    <thead>
      <tr>
        <th>Sản phẩm</th>
        <th>Danh mục</th>
        <th><?= $viewDate ? 'Tồn ngày '.date('d/m/Y',strtotime($viewDate)) : 'Tồn hiện tại' ?></th>
        <th>Mức tồn</th>
        <th>Giá vốn</th>
        <th>Giá trị tồn</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($paged['items'] as $p):
        $qty = (int)$p['dynamic_stock'];
        $pct = min(100, max(0, round($qty / max(1, 100) * 100)));
        $barClr = $qty <= 0 ? '#e74c3c' : ($qty <= $threshold ? '#f39c12' : '#27ae60');
      ?>
      <tr>
        <td><div style="display:flex;align-items:center;gap:.5rem"><span><?= h($p['emoji']) ?></span><strong><?= h($p['name']) ?></strong></div></td>
        <td><?= h($p['cat_name']) ?></td>
        <td style="font-size:1.1rem;font-weight:700;color:<?= $barClr ?>"><?= $qty ?></td>
        <td><div style="width:80px;height:6px;background:var(--border);border-radius:3px;overflow:hidden"><div style="width:<?= $pct ?>%;height:100%;background:<?= $barClr ?>;border-radius:3px"></div></div></td>
        <td style="color:var(--muted)"><?= $p['cost_price']>0 ? format_currency($p['cost_price']) : '—' ?></td>
        <td style="font-weight:600"><?= ($p['cost_price']>0 && $qty>0) ? format_currency($qty*$p['cost_price']) : '—' ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($paged['items'])): ?>
      <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:1.5rem">Không tìm thấy sản phẩm nào phù hợp.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
  <?= admin_pagination($paged, ADMIN_URL.'/inventory.php', array_filter(['search'=>$search,'cat'=>$catF,'stock'=>$stockF,'threshold'=>$threshold,'view_date'=>$viewDate,'rfrom'=>$reportFrom,'rto'=>$reportTo,'filter_applied'=>1])) ?>
</div>
<?php else: ?>
<div class="card" style="text-align:center; padding: 3rem 1rem; border: 2px dashed var(--border); background: transparent;">
  <div style="font-size: 2.5rem; margin-bottom: 1rem;">🔍</div>
  <h4 style="color:var(--muted); font-weight: 500;">Bảng dữ liệu đang ẩn</h4>
  <p style="color:var(--muted); font-size: 0.9rem;">Vui lòng chọn ngày hoặc thiết lập bộ lọc ở trên và bấm <strong>"Lọc / Xem"</strong> để hiển thị danh sách tồn kho.</p>
</div>
<?php endif; ?>

<?php if ($detailProduct && $reportFrom && $reportTo): ?>
<div class="modal-overlay active" id="detail-modal"
     onclick="if(event.target===this)location.href='<?= ADMIN_URL ?>/inventory.php?rfrom=<?= h($reportFrom) ?>&rto=<?= h($reportTo) ?>&threshold=<?= $threshold ?>&view_date=<?= h($viewDate) ?>&search=<?= h($search) ?>&cat=<?= h($catF) ?>&stock=<?= h($stockF) ?><?= $isFiltered?'&filter_applied=1':'' ?>'">
  <div class="modal" style="max-width:580px">
    <div class="modal-header">
      <h3><?= h($detailProduct['emoji'].' '.$detailProduct['name']) ?> — Chi tiết theo ngày</h3>
      <a href="<?= ADMIN_URL ?>/inventory.php?rfrom=<?= h($reportFrom) ?>&rto=<?= h($reportTo) ?>&threshold=<?= $threshold ?>&view_date=<?= h($viewDate) ?>&search=<?= h($search) ?>&cat=<?= h($catF) ?>&stock=<?= h($stockF) ?><?= $isFiltered?'&filter_applied=1':'' ?>" class="modal-close">✕</a>
    </div>
    <div class="modal-body">
      <div style="background:var(--bg);border-radius:8px;padding:.7rem 1rem;font-size:.83rem;color:var(--muted);margin-bottom:1rem">
        📅 Khoảng thời gian: <strong><?= date('d/m/Y',strtotime($reportFrom)) ?> – <?= date('d/m/Y',strtotime($reportTo)) ?></strong>
      </div>

      <?php if (empty($dailyDetail)): ?>
        <p style="color:var(--muted);text-align:center;padding:1.5rem 0">Không có hoạt động nào trong khoảng thời gian này.</p>
      <?php else: ?>
      <table class="admin-table">
        <thead>
          <tr>
            <th>Ngày</th>
            <th style="color:#27ae60">📥 Nhập vào</th>
            <th style="color:#e74c3c">📤 Bán ra</th>
            <th>Chênh lệch</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($dailyDetail as $d):
            $net = (int)$d['imported'] - (int)$d['sold'];
          ?>
          <tr>
            <td><strong><?= date('d/m/Y',strtotime($d['activity_date'])) ?></strong></td>
            <td style="color:#27ae60;font-weight:600">
              <?= $d['imported']>0 ? '+'.$d['imported'] : '<span style="color:var(--muted)">—</span>' ?>
            </td>
            <td style="color:#e74c3c;font-weight:600">
              <?= $d['sold']>0 ? '−'.$d['sold'] : '<span style="color:var(--muted)">—</span>' ?>
            </td>
            <td style="font-weight:700;color:<?= $net>=0?'#27ae60':'#e74c3c' ?>">
              <?= $net>=0 ? '+'.$net : $net ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:var(--bg)">
            <td style="font-weight:600">Tổng cộng</td>
            <td style="color:#27ae60;font-weight:700">+<?= array_sum(array_column($dailyDetail,'imported')) ?></td>
            <td style="color:#e74c3c;font-weight:700">−<?= array_sum(array_column($dailyDetail,'sold')) ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
      <?php endif; ?>
    </div>
    <div class="modal-footer">
      <a href="<?= ADMIN_URL ?>/inventory.php?rfrom=<?= h($reportFrom) ?>&rto=<?= h($reportTo) ?>&threshold=<?= $threshold ?>&view_date=<?= h($viewDate) ?>&search=<?= h($search) ?>&cat=<?= h($catF) ?>&stock=<?= h($stockF) ?><?= $isFiltered?'&filter_applied=1':'' ?>" class="btn btn-outline">Đóng</a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php admin_layout_end(); ?>