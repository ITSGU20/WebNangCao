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
    $viewDate = ''; // Xóa ngày sai → $isFiltered sẽ = false ở dòng bên dưới
}
$detailPid  = sanitize_int($_GET['detail_pid'] ?? 0);
$page       = max(1, sanitize_int($_GET['page'] ?? 1));
$cats       = db_query('SELECT * FROM categories ORDER BY name');

// Biến cờ: Chỉ hiện bảng khi đã bấm Lọc/Xem VÀ có chọn ngày hợp lệ
$isFiltered = !empty($_GET['filter_applied']) && !empty($viewDate);

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
    
    // Params thứ tự:
    // [1] reportFrom-1day (tồn đầu kỳ nhập)
    // [2] reportFrom-1day (tồn đầu kỳ bán)
    // [3] reportTo (tồn cuối kỳ nhập)
    // [4] reportTo (tồn cuối kỳ bán)
    // [5] reportFrom (nhập trong kỳ từ)
    // [6] reportTo   (nhập trong kỳ đến)
    // [7] reportFrom (bán trong kỳ từ)
    // [8] reportTo   (bán trong kỳ đến)
    // + repParams
    $dayBeforeFrom = date('Y-m-d', strtotime($reportFrom . ' -1 day'));
    $finalRepParams = array_merge([
        $dayBeforeFrom, $dayBeforeFrom, // tồn đầu kỳ
        $reportTo, $reportTo,           // tồn cuối kỳ
        $reportFrom, $reportTo,         // nhập trong kỳ
        $reportFrom, $reportTo,         // bán trong kỳ
    ], $repParams);

    $report = db_query(
        'SELECT p.id, p.name, p.emoji,
                -- Tồn đầu kỳ: tính đến ngày TRƯỚC reportFrom
                GREATEST(0,
                    COALESCE((SELECT SUM(ii0.quantity) FROM import_items ii0
                              JOIN import_receipts ir0 ON ir0.id=ii0.receipt_id
                              WHERE ii0.product_id=p.id AND ir0.status="completed"
                                AND ir0.import_date <= ?),0)
                    -
                    COALESCE((SELECT SUM(oi0.quantity) FROM order_items oi0
                              JOIN orders o0 ON o0.id=oi0.order_id
                              WHERE oi0.product_id=p.id AND o0.status<>"cancelled"
                                AND DATE(o0.created_at) <= ?),0)
                ) AS opening_stock,
                -- Tồn cuối kỳ: tính đến ngày reportTo
                GREATEST(0,
                    COALESCE((SELECT SUM(ii2.quantity) FROM import_items ii2
                              JOIN import_receipts ir2 ON ir2.id=ii2.receipt_id
                              WHERE ii2.product_id=p.id AND ir2.status="completed"
                                AND ir2.import_date <= ?),0)
                    -
                    COALESCE((SELECT SUM(oi2.quantity) FROM order_items oi2
                              JOIN orders o2 ON o2.id=oi2.order_id
                              WHERE oi2.product_id=p.id AND o2.status<>"cancelled"
                                AND DATE(o2.created_at) <= ?),0)
                ) AS stock,
                -- Nhập trong kỳ
                COALESCE((SELECT SUM(ii.quantity) FROM import_items ii
                          JOIN import_receipts ir ON ir.id=ii.receipt_id
                          WHERE ii.product_id=p.id AND ir.status="completed"
                            AND ir.import_date BETWEEN ? AND ?),0) AS total_imported,
                -- Bán trong kỳ
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
$detailProduct  = null;
$dailyDetail    = [];   // [{activity_date, imported, sold}]
$importsByDate  = [];   // ['Y-m-d' => [ [receipt_id, qty, import_price, note, completed_at], ... ]]
$ordersByDate   = [];   // ['Y-m-d' => [ [order_id, qty, unit_price, recv_name, recv_phone, status], ... ]]

if ($detailPid && $reportFrom && $reportTo) {
    $detailProduct = db_row('SELECT id,name,emoji FROM products WHERE id=?',[$detailPid]);
    if ($detailProduct) {

        // --- Tổng hợp theo ngày (giữ nguyên logic cũ) ---
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

        // --- Chi tiết từng PHIẾU NHẬP theo ngày ---
        $importRows = db_query(
            'SELECT ir.id AS receipt_id,
                    ir.import_date,
                    ir.note,
                    ir.completed_at,
                    ii.quantity,
                    ii.import_price
             FROM import_items ii
             JOIN import_receipts ir ON ir.id = ii.receipt_id
             WHERE ii.product_id = ?
               AND ir.status = "completed"
               AND ir.import_date BETWEEN ? AND ?
             ORDER BY ir.import_date DESC, ir.id ASC',
            [$detailPid, $reportFrom, $reportTo]
        );
        foreach ($importRows as $row) {
            $importsByDate[$row['import_date']][] = $row;
        }

        // --- Chi tiết từng ĐƠN BÁN theo ngày ---
        $orderRows = db_query(
            'SELECT o.id AS order_id,
                    DATE(o.created_at) AS sale_date,
                    o.created_at,
                    o.recv_name,
                    o.recv_phone,
                    o.status,
                    o.payment_method,
                    oi.quantity,
                    oi.unit_price
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             WHERE oi.product_id = ?
               AND o.status <> "cancelled"
               AND DATE(o.created_at) BETWEEN ? AND ?
             ORDER BY DATE(o.created_at) DESC, o.id ASC',
            [$detailPid, $reportFrom, $reportTo]
        );
        foreach ($orderRows as $row) {
            $ordersByDate[$row['sale_date']][] = $row;
        }
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
    <thead><tr><th>Sản phẩm</th><th>Tồn đầu kỳ</th><th>Nhập kho</th><th>Bán ra</th><th>Tồn cuối kỳ</th><th>Chi tiết</th></tr></thead>
    <tbody>
      <?php foreach ($report as $r): ?>
      <tr>
        <td><?= h($r['emoji'].' '.$r['name']) ?></td>
        <td style="color:#1565c0;font-weight:600"><?= $r['opening_stock'] ?></td>
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

    <button type="submit" class="btn btn-primary" onclick="return requireViewDate(this)">Lọc / Xem</button>
    <?php if ($isFiltered): ?>
      <a href="?threshold=10&rfrom=<?= h($reportFrom) ?>&rto=<?= h($reportTo) ?>" class="btn btn-secondary">✕ Bỏ lọc</a>
    <?php endif; ?>
  </form>
  <div id="view-date-error" style="display:none;background:#f8d7da;border-radius:8px;padding:.65rem 1rem;font-size:.84rem;color:#721c24;margin-top:.75rem;border-left:4px solid #e74c3c">
    ❌ <strong>Vui lòng chọn ngày</strong> ở ô <em>"📅 Xem ngày"</em> trước khi lọc.
  </div>
  <script>
  function requireViewDate(btn) {
    var dateInput = btn.closest('form').querySelector('[name="view_date"]');
    var errBox    = document.getElementById('view-date-error');
    if (!dateInput || !dateInput.value) {
      errBox.style.display = 'block';
      dateInput && dateInput.focus();
      return false;
    }
    errBox.style.display = 'none';
    return true;
  }
  </script>

  <?php if ($viewDate): ?>
  <div style="background:#e8f4f8;border-radius:8px;padding:.75rem 1rem;font-size:.84rem;color:#0a4d68;margin-top:1.2rem">
    📌 <?php if ($viewDate === $today): ?>
        Đang xem và lọc theo tồn kho <strong>vào thời điểm này</strong>
    <?php else: ?>
        Đang xem và lọc theo tồn kho tính đến cuối ngày <strong><?= date('d/m/Y', strtotime($viewDate)) ?></strong>
    <?php endif; ?>
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
  <div style="font-size: 2.5rem; margin-bottom: 1rem;">📅</div>
  <h4 style="color:var(--muted); font-weight: 500;">Vui lòng chọn ngày để xem tồn kho</h4>
  <p style="color:var(--muted); font-size: 0.9rem;">Chọn <strong>"📅 Xem ngày"</strong> ở bộ lọc trên rồi bấm <strong>"Lọc / Xem"</strong> để hiển thị danh sách tồn kho.</p>
</div>
<?php endif; ?>

<?php if ($detailProduct && $reportFrom && $reportTo): ?>
<?php
// Build URL trở về (dùng nhiều lần)
$backUrl = ADMIN_URL.'/inventory.php?'.http_build_query(array_filter([
    'rfrom'          => $reportFrom,
    'rto'            => $reportTo,
    'threshold'      => $threshold,
    'view_date'      => $viewDate,
    'search'         => $search,
    'cat'            => $catF ?: null,
    'stock'          => $stockF,
    'filter_applied' => $isFiltered ? '1' : null,
]));
?>
<style>
/* ── Detail modal – expanded layout ── */
.inv-modal-wide { max-width: 820px !important; }
.inv-modal-wide .modal-body { padding: 0; max-height: 72vh; overflow-y: auto; }

/* Ngày header row */
.day-header {
  display: flex; align-items: center; gap: .75rem;
  background: var(--bg); padding: .65rem 1.25rem;
  border-top: 1px solid var(--border);
  font-size: .82rem; font-weight: 700; color: var(--espresso);
  position: sticky; top: 0; z-index: 1;
}
.day-badge-import { background:#e8f5e9; color:#2e7d32; border-radius:20px; padding:.15rem .65rem; font-size:.78rem; }
.day-badge-sale   { background:#fce4ec; color:#c62828; border-radius:20px; padding:.15rem .65rem; font-size:.78rem; }
.day-net-pos { color:#27ae60; font-size:.82rem; font-weight:700; margin-left:auto; }
.day-net-neg { color:#e74c3c; font-size:.82rem; font-weight:700; margin-left:auto; }

/* Sub-section label */
.inv-section-label {
  display: flex; align-items: center; gap: .5rem;
  padding: .5rem 1.25rem .3rem;
  font-size: .72rem; font-weight: 700; text-transform: uppercase;
  letter-spacing: .6px; color: var(--gray);
}

/* Receipt / Order card */
.inv-doc-card {
  margin: 0 1rem .55rem;
  border: 1px solid var(--border);
  border-radius: 8px;
  overflow: hidden;
  font-size: .82rem;
}
.inv-doc-head {
  display: flex; align-items: center; gap: .6rem;
  padding: .5rem .85rem;
  background: #f9f9f9; border-bottom: 1px solid var(--border);
}
.inv-doc-head .doc-id   { font-weight: 700; color: var(--espresso); }
.inv-doc-head .doc-meta { color: var(--gray); font-size: .78rem; }
.inv-doc-body { padding: .5rem .85rem .6rem; }
.inv-doc-row  { display: flex; justify-content: space-between; align-items: center; padding: .22rem 0; border-bottom: 1px solid #f0f0f0; }
.inv-doc-row:last-child { border-bottom: none; }
.inv-doc-total { display: flex; justify-content: flex-end; gap: .5rem; padding: .4rem .85rem; background: #f9f9f9; border-top: 1px solid var(--border); font-weight: 700; font-size: .83rem; }

/* Import accent */
.inv-doc-card.import-card { border-left: 3px solid #43a047; }
.inv-doc-card.import-card .inv-doc-head { background: #f1f8f1; }

/* Sale accent */
.inv-doc-card.sale-card   { border-left: 3px solid #e53935; }
.inv-doc-card.sale-card .inv-doc-head   { background: #fdf4f4; }

/* Summary tfoot */
.inv-summary-bar {
  display: flex; gap: 2rem; justify-content: flex-end; align-items: center;
  padding: .8rem 1.25rem; border-top: 2px solid var(--border);
  background: var(--bg); font-size: .85rem; font-weight: 700;
}
</style>

<div class="modal-overlay active" id="detail-modal"
     onclick="if(event.target===this)location.href='<?= $backUrl ?>'">
  <div class="modal inv-modal-wide">
    <div class="modal-header">
      <h3 style="display:flex;align-items:center;gap:.5rem">
        <span><?= h($detailProduct['emoji']) ?></span>
        <span><?= h($detailProduct['name']) ?></span>
        <span style="font-size:.78rem;font-weight:400;color:var(--gray);margin-left:.25rem">— Hóa đơn chi tiết</span>
      </h3>
      <a href="<?= $backUrl ?>" class="modal-close">✕</a>
    </div>

    <!-- Khoảng thời gian info bar -->
    <div style="display:flex;align-items:center;gap:1.5rem;padding:.65rem 1.25rem;background:#e8f4f8;border-bottom:1px solid #b3d9e8;font-size:.82rem;color:#0a4d68">
      <span>📅 <strong><?= date('d/m/Y',strtotime($reportFrom)) ?> – <?= date('d/m/Y',strtotime($reportTo)) ?></strong></span>
      <span>📥 Tổng nhập: <strong style="color:#2e7d32">+<?= array_sum(array_column($dailyDetail,'imported')) ?></strong></span>
      <span>📤 Tổng bán: <strong style="color:#c62828">−<?= array_sum(array_column($dailyDetail,'sold')) ?></strong></span>
      <?php $grandNet = array_sum(array_column($dailyDetail,'imported')) - array_sum(array_column($dailyDetail,'sold')); ?>
      <span>⚖️ Chênh lệch: <strong style="color:<?= $grandNet>=0?'#2e7d32':'#c62828' ?>"><?= $grandNet>=0?'+'.$grandNet:$grandNet ?></strong></span>
    </div>

    <div class="modal-body">
      <?php if (empty($dailyDetail)): ?>
        <div style="text-align:center;padding:3rem 1rem;color:var(--muted)">
          <div style="font-size:2rem;margin-bottom:.5rem">📭</div>
          Không có hoạt động nào trong khoảng thời gian này.
        </div>
      <?php else: ?>

        <?php foreach ($dailyDetail as $d):
          $date    = $d['activity_date'];
          $net     = (int)$d['imported'] - (int)$d['sold'];
          $imports = $importsByDate[$date] ?? [];
          $orders  = $ordersByDate[$date]  ?? [];
        ?>

        <!-- ═══ NGÀY HEADER ═══ -->
        <div class="day-header">
          <span>📅 <?= date('d/m/Y', strtotime($date)) ?></span>
          <?php if ($d['imported'] > 0): ?>
            <span class="day-badge-import">📥 +<?= $d['imported'] ?> nhập</span>
          <?php endif; ?>
          <?php if ($d['sold'] > 0): ?>
            <span class="day-badge-sale">📤 −<?= $d['sold'] ?> bán</span>
          <?php endif; ?>
          <span class="<?= $net>=0?'day-net-pos':'day-net-neg' ?>">
            <?= $net>=0 ? '⚖️ +' : '⚖️ ' ?><?= $net ?>
          </span>
        </div>

        <!-- ─── PHIẾU NHẬP của ngày này ─── -->
        <?php if ($imports): ?>
        <div class="inv-section-label" style="color:#2e7d32;padding-top:.7rem">
          <span>📥</span> Phiếu nhập hàng (<?= count($imports) ?> phiếu)
        </div>
        <?php foreach ($imports as $imp): ?>
        <div class="inv-doc-card import-card">
          <div class="inv-doc-head">
            <span class="doc-id">Phiếu #<?= $imp['receipt_id'] ?></span>
            <span class="doc-meta">
              · Hoàn thành: <?= $imp['completed_at'] ? date('d/m/Y', strtotime($imp['completed_at'])) : '—' ?>
            </span>
            <?php if ($imp['note']): ?>
              <span class="doc-meta" style="margin-left:auto;max-width:220px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis" title="<?= h($imp['note']) ?>">
                📝 <?= h($imp['note']) ?>
              </span>
            <?php endif; ?>
          </div>
          <div class="inv-doc-body">
            <div class="inv-doc-row">
              <span style="color:var(--gray)">Số lượng nhập</span>
              <strong style="color:#2e7d32">+<?= $imp['quantity'] ?> cái</strong>
            </div>
            <div class="inv-doc-row">
              <span style="color:var(--gray)">Đơn giá nhập</span>
              <span><?= format_currency($imp['import_price']) ?></span>
            </div>
          </div>
          <div class="inv-doc-total">
            <span style="color:var(--gray);font-weight:400">Thành tiền:</span>
            <span style="color:#2e7d32"><?= format_currency($imp['quantity'] * $imp['import_price']) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- ─── ĐƠN BÁN của ngày này ─── -->
        <?php if ($orders): ?>
        <div class="inv-section-label" style="color:#c62828;padding-top:.55rem">
          <span>📤</span> Đơn hàng bán ra (<?= count($orders) ?> đơn)
        </div>
        <?php foreach ($orders as $ord):
          $slLabel = match($ord['status']) {
            'new'        => ['Mới đặt',    '#1565c0'],
            'processing' => ['Đang xử lý', '#e65100'],
            'delivered'  => ['Đã giao',    '#2e7d32'],
            default      => [$ord['status'], '#555'],
          };
          $pmLabel = match($ord['payment_method']) {
            'cash'     => 'Tiền mặt',
            'transfer' => 'Chuyển khoản',
            'online'   => 'Trực tuyến',
            default    => $ord['payment_method'],
          };
        ?>
        <div class="inv-doc-card sale-card">
          <div class="inv-doc-head">
            <span class="doc-id">Đơn #<?= $ord['order_id'] ?></span>
            <span style="display:inline-block;background:<?= $slLabel[1] ?>22;color:<?= $slLabel[1] ?>;border-radius:20px;padding:.1rem .55rem;font-size:.72rem;font-weight:700"><?= $slLabel[0] ?></span>
            <span class="doc-meta">· <?= date('H:i', strtotime($ord['created_at'])) ?></span>
            <span class="doc-meta" style="margin-left:auto">💳 <?= $pmLabel ?></span>
          </div>
          <div class="inv-doc-body">
            <div class="inv-doc-row">
              <span style="color:var(--gray)">Khách hàng</span>
              <span><strong><?= h($ord['recv_name']) ?></strong>
                <span style="color:var(--gray);margin-left:.35rem;font-size:.78rem"><?= h($ord['recv_phone']) ?></span>
              </span>
            </div>
            <div class="inv-doc-row">
              <span style="color:var(--gray)">Số lượng bán</span>
              <strong style="color:#c62828">−<?= $ord['quantity'] ?> cái</strong>
            </div>
            <div class="inv-doc-row">
              <span style="color:var(--gray)">Đơn giá bán</span>
              <span><?= format_currency($ord['unit_price']) ?></span>
            </div>
          </div>
          <div class="inv-doc-total">
            <span style="color:var(--gray);font-weight:400">Thành tiền:</span>
            <span style="color:#c62828"><?= format_currency($ord['quantity'] * $ord['unit_price']) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <?php endforeach; // end foreach $dailyDetail ?>

        <!-- ═══ TỔNG KẾT CUỐI MODAL ═══ -->
        <div class="inv-summary-bar">
          <?php
            $totalImportValue = 0;
            foreach ($importsByDate as $rows) {
              foreach ($rows as $r) $totalImportValue += $r['quantity'] * $r['import_price'];
            }
            $totalSaleValue = 0;
            foreach ($ordersByDate as $rows) {
              foreach ($rows as $r) $totalSaleValue += $r['quantity'] * $r['unit_price'];
            }
          ?>
          <span>📥 Tổng nhập: <span style="color:#2e7d32">+<?= array_sum(array_column($dailyDetail,'imported')) ?> cái</span>
            &nbsp;(<?= format_currency($totalImportValue) ?>)</span>
          <span>📤 Tổng bán: <span style="color:#c62828">−<?= array_sum(array_column($dailyDetail,'sold')) ?> cái</span>
            &nbsp;(<?= format_currency($totalSaleValue) ?>)</span>
        </div>

      <?php endif; // end if dailyDetail ?>
    </div><!-- /.modal-body -->

    <div class="modal-footer">
      <a href="<?= $backUrl ?>" class="btn btn-outline">Đóng</a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php admin_layout_end(); ?>