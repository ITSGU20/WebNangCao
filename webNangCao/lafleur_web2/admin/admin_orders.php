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
    $id     = sanitize_int($_POST['id'] ?? 0);
    if ($action === 'advance') {
        $flow = ['new'=>'processing','processing'=>'delivered'];
        $cur  = db_val('SELECT status FROM orders WHERE id=?',[$id]);
        if ($cur && isset($flow[$cur])) {
            db_exec('UPDATE orders SET status=? WHERE id=?',[$flow[$cur],$id]);
            $msg='success:Cập nhật trạng thái đơn hàng thành công.';
        }
    }
    if ($action === 'cancel') {
        $cur = db_val('SELECT status FROM orders WHERE id=?',[$id]);
        if (in_array($cur,['new','processing'])) {
            // Restore stock
            $items = db_query('SELECT * FROM order_items WHERE order_id=?',[$id]);
            foreach ($items as $it) db_exec('UPDATE products SET stock=stock+? WHERE id=?',[$it['quantity'],$it['product_id']]);
            db_exec("UPDATE orders SET status='cancelled' WHERE id=?",[$id]);
            $msg='success:Đã huỷ đơn hàng & hoàn tồn kho.';
        }
    }
}

// Filters
$search  = trim($_GET['search'] ?? '');
$statusF = $_GET['status'] ?? '';
$dateFrom= $_GET['from']   ?? '';
$dateTo  = $_GET['to']     ?? '';
$sortAddr= isset($_GET['sort_addr']);
$page    = max(1, sanitize_int($_GET['page'] ?? 1));

$where = ['1=1']; $params = [];
if ($search)  { $where[]='(o.recv_name LIKE ? OR o.recv_phone LIKE ? OR o.id=?)'; $params=array_merge($params,["%$search%","%$search%",(int)$search]); }
if ($statusF && in_array($statusF,['new','processing','delivered','cancelled'])) { $where[]='o.status=?'; $params[]=$statusF; }
if ($dateFrom){ $where[]='DATE(o.created_at)>=?'; $params[]=$dateFrom; }
if ($dateTo)  { $where[]='DATE(o.created_at)<=?'; $params[]=$dateTo; }

$orderBy = $sortAddr ? 'ORDER BY o.recv_district ASC, o.recv_city ASC' : 'ORDER BY o.created_at DESC';
$sql = "SELECT o.*,u.name AS uname FROM orders o JOIN users u ON u.id=o.user_id WHERE ".implode(' AND ',$where)." $orderBy";
$paged = db_paginate($sql,$params,$page,ADMIN_PAGE_SIZE);

// Stats
$counts = db_row("SELECT SUM(status='new') AS n, SUM(status='processing') AS p, SUM(status='delivered') AS d, SUM(status='cancelled') AS c FROM orders");

$viewId = sanitize_int($_GET['id'] ?? 0);
$viewOrder = $viewId ? db_row('SELECT o.*,u.name AS uname,u.email AS uemail FROM orders o JOIN users u ON u.id=o.user_id WHERE o.id=?',[$viewId]) : null;
if ($viewOrder) $viewOrder['items'] = db_query('SELECT * FROM order_items WHERE order_id=?',[$viewId]);

$flow = ['new'=>['processing','Chuyển sang Xử lý','btn-primary'],'processing'=>['delivered','Đánh dấu Đã giao','btn-success']];

admin_layout_start('Quản lý đơn hàng','orders');
[$t,$m]=explode(':',$msg?:':',2); if ($m) echo '<div class="alert alert-'.($t==='error'?'danger':'success').' mb-3">'.h($m).'</div>';
?>
<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem;margin-bottom:1.5rem">
  <?php foreach ([
    ['📥','#fff3cd',$counts['n'],'Đơn mới','new'],
    ['⚙️','#cce5ff',$counts['p'],'Đang xử lý','processing'],
    ['✅','#d4edda',$counts['d'],'Đã giao','delivered'],
    ['❌','#f8d7da',$counts['c'],'Đã huỷ','cancelled'],
  ] as [$icon,$bg,$cnt,$lbl,$st]): ?>
  <a href="?status=<?= $st ?>" style="text-decoration:none">
    <div class="admin-stat-card" style="cursor:pointer">
      <div class="stat-icon" style="background:<?= $bg ?>"><?= $icon ?></div>
      <div class="stat-info"><div class="stat-value"><?= (int)$cnt ?></div><div class="stat-label"><?= h($lbl) ?></div></div>
    </div>
  </a>
  <?php endforeach; ?>
</div>

<!-- Filters -->
<div class="filter-bar card" style="margin-bottom:1.5rem">
  <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center">
    <input type="text" name="search" value="<?= h($search) ?>" class="form-control" placeholder="Tên KH, SĐT, mã đơn…" style="width:220px">
    <select name="status" class="form-control" style="width:170px">
      <option value="">Tất cả trạng thái</option>
      <?php foreach (['new'=>'Mới','processing'=>'Đang xử lý','delivered'=>'Đã giao','cancelled'=>'Đã huỷ'] as $k=>$v): ?>
        <option value="<?= $k ?>" <?= $statusF===$k?'selected':'' ?>><?= $v ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= h($dateFrom) ?>" class="form-control" style="width:155px" title="Từ ngày">
    <input type="date" name="to"   value="<?= h($dateTo) ?>"   class="form-control" style="width:155px" title="Đến ngày">
    <button type="submit" class="btn btn-outline">Lọc</button>
    <a href="?<?= $sortAddr?'':'sort_addr=1' ?>&status=<?= h($statusF) ?>" class="btn btn-outline" style="<?= $sortAddr?'background:var(--chocolate);color:white':'' ?>">📍 Sắp theo địa chỉ</a>
    <a href="<?= ADMIN_URL ?>/admin_orders.php" class="btn btn-secondary">Xoá lọc</a>
  </form>
</div>

<div class="card">
  <table class="admin-table">
    <thead><tr><th>Mã đơn</th><th>Khách hàng</th><th>Ngày đặt</th><th>Địa chỉ</th><th>Tổng tiền</th><th>Thanh toán</th><th>TT</th><th>Thao tác</th></tr></thead>
    <tbody>
      <?php foreach ($paged['items'] as $o):
        $sl = order_status_label($o['status']); ?>
      <tr>
        <td><code style="background:var(--bg);padding:.15rem .4rem;border-radius:4px">#<?= $o['id'] ?></code></td>
        <td><div style="font-weight:600"><?= h($o['uname']) ?></div><div style="font-size:.78rem;color:var(--muted)"><?= h($o['recv_phone']) ?></div></td>
        <td style="font-size:.83rem"><?= format_datetime($o['created_at']) ?></td>
        <td style="font-size:.82rem;color:var(--muted)"><?= h($o['recv_district'].', '.$o['recv_city']) ?></td>
        <td style="font-weight:600;white-space:nowrap"><?= format_currency($o['total_amount']) ?></td>
        <td><?= payment_label($o['payment_method']) ?></td>
        <td><span class="badge <?= $sl['cls'] ?>"><?= $sl['text'] ?></span></td>
        <td>
          <div style="display:flex;gap:.3rem;flex-wrap:wrap">
            <a href="?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline">Chi tiết</a>
            <?php if (isset($flow[$o['status']])): [$next,$label,$cls]=$flow[$o['status']]; ?>
            <form method="POST" style="display:inline"><?= csrf_field() ?>
              <input type="hidden" name="action" value="advance"><input type="hidden" name="id" value="<?= $o['id'] ?>">
              <button class="btn btn-sm <?= $cls ?>"><?= h($label) ?></button>
            </form>
            <?php endif; ?>
            <?php if (in_array($o['status'],['new','processing'])): ?>
            <form method="POST" style="display:inline" onsubmit="return confirm('Huỷ đơn hàng này?')"><?= csrf_field() ?>
              <input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $o['id'] ?>">
              <button class="btn btn-sm btn-danger">Huỷ</button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?= admin_pagination($paged, ADMIN_URL.'/admin_orders.php', array_filter(['search'=>$search,'status'=>$statusF,'from'=>$dateFrom,'to'=>$dateTo])+($sortAddr?['sort_addr'=>1]:[])) ?>
</div>

<!-- Order detail modal -->
<?php if ($viewOrder): ?>
<div class="modal-overlay active" onclick="if(event.target===this)location.href='<?= ADMIN_URL ?>/admin_orders.php'">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <h3>Đơn hàng #<?= $viewOrder['id'] ?></h3>
      <a href="<?= ADMIN_URL ?>/admin_orders.php" class="modal-close">✕</a>
    </div>
    <div class="modal-body">
      <?php $sl = order_status_label($viewOrder['status']); ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;font-size:.87rem">
        <div><span style="color:var(--muted)">Khách hàng:</span><br><strong><?= h($viewOrder['uname']) ?></strong><br><?= h($viewOrder['uemail']) ?></div>
        <div><span style="color:var(--muted)">Địa chỉ giao:</span><br><strong><?= h($viewOrder['recv_address'].', '.$viewOrder['recv_district'].', '.$viewOrder['recv_city']) ?></strong></div>
        <div><span style="color:var(--muted)">Điện thoại:</span> <strong><?= h($viewOrder['recv_phone']) ?></strong></div>
        <div><span style="color:var(--muted)">Thanh toán:</span> <strong><?= payment_label($viewOrder['payment_method']) ?></strong></div>
        <div><span style="color:var(--muted)">Ngày đặt:</span> <strong><?= format_datetime($viewOrder['created_at']) ?></strong></div>
        <div><span style="color:var(--muted)">Trạng thái:</span> <span class="badge <?= $sl['cls'] ?>"><?= $sl['text'] ?></span></div>
      </div>
      <table class="admin-table">
        <thead><tr><th>Sản phẩm</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead>
        <tbody>
          <?php foreach ($viewOrder['items'] as $it): ?>
          <tr>
            <td><?= h($it['product_name']) ?></td>
            <td><?= $it['quantity'] ?></td>
            <td><?= format_currency($it['unit_price']) ?></td>
            <td style="font-weight:600"><?= format_currency($it['unit_price']*$it['quantity']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot><tr style="background:var(--bg)">
          <td colspan="3" style="text-align:right;font-weight:600">Tổng:</td>
          <td style="font-weight:700;color:var(--primary)"><?= format_currency($viewOrder['total_amount']) ?></td>
        </tr></tfoot>
      </table>
    </div>
    <div class="modal-footer" style="flex-wrap:wrap;gap:.5rem">
      <a href="<?= ADMIN_URL ?>/admin_orders.php" class="btn btn-outline">Đóng</a>
      <?php if (isset($flow[$viewOrder['status']])): [$next,$label,$cls]=$flow[$viewOrder['status']]; ?>
      <form method="POST" style="display:inline"><?= csrf_field() ?>
        <input type="hidden" name="action" value="advance"><input type="hidden" name="id" value="<?= $viewOrder['id'] ?>">
        <button class="btn <?= $cls ?>"><?= h($label) ?></button>
      </form>
      <?php endif; ?>
      <?php if (in_array($viewOrder['status'],['new','processing'])): ?>
      <form method="POST" style="display:inline" onsubmit="return confirm('Huỷ đơn hàng?')"><?= csrf_field() ?>
        <input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= $viewOrder['id'] ?>">
        <button class="btn btn-danger">Huỷ đơn</button>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php endif;
admin_layout_end(); ?>
