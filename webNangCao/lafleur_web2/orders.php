<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/cart_helper.php';

session_init();
$user = auth_require();

$statusFilter = $_GET['status'] ?? '';
$page = max(1, sanitize_int($_GET['page'] ?? 1));

$where  = ['o.user_id = ?'];
$params = [$user['id']];
if ($statusFilter && in_array($statusFilter, ['new','processing','delivered','cancelled'])) {
    $where[]  = 'o.status = ?';
    $params[] = $statusFilter;
}

$sql = 'SELECT o.* FROM orders o WHERE ' . implode(' AND ', $where) . ' ORDER BY o.created_at DESC';
$paged = db_paginate($sql, $params, $page, 8);

// Detail view
$detailId = sanitize_int($_GET['detail'] ?? 0);
$detail   = null;
if ($detailId) {
    $detail = db_row('SELECT * FROM orders WHERE id=? AND user_id=?', [$detailId, $user['id']]);
    if ($detail) {
        $detail['items'] = db_query('SELECT * FROM order_items WHERE order_id=?', [$detailId]);
    }
}

render_head('Đơn hàng của tôi - La Fleur');
render_navbar();
?>

<div style="max-width:900px;margin:3rem auto;padding:0 2rem">
  <div style="display:flex;gap:2rem;flex-wrap:wrap">
    <!-- Sidebar -->
    <div style="width:200px;flex-shrink:0">
      <div class="card card-sm" style="padding:1.5rem">
        <div style="text-align:center;margin-bottom:1.2rem">
          <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,var(--caramel-light),var(--rose-light));margin:0 auto .6rem;display:flex;align-items:center;justify-content:center;font-size:1.8rem">👤</div>
          <div style="font-weight:600;font-size:.88rem;color:var(--espresso)"><?= h($user['name']) ?></div>
        </div>
        <nav style="display:flex;flex-direction:column;gap:.3rem">
          <a href="<?= BASE_URL ?>/profile.php" style="padding:.55rem .8rem;border-radius:var(--radius-sm);color:var(--gray);font-size:.85rem">👤 Hồ sơ</a>
          <a href="<?= BASE_URL ?>/orders.php" style="padding:.55rem .8rem;border-radius:var(--radius-sm);background:var(--gray-light);color:var(--chocolate);font-size:.85rem;font-weight:600">📦 Đơn hàng</a>
          <a href="<?= BASE_URL ?>/cart.php" style="padding:.55rem .8rem;border-radius:var(--radius-sm);color:var(--gray);font-size:.85rem">🛒 Giỏ hàng</a>
        </nav>
      </div>
    </div>

    <!-- Orders -->
    <div style="flex:1;min-width:0">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:.8rem">
        <h2 style="font-family:var(--font-display);font-size:1.6rem;color:var(--chocolate)">Đơn hàng của tôi</h2>
        <select class="form-control" onchange="location.href='<?= BASE_URL ?>/orders.php?status='+this.value" style="width:auto;font-size:.83rem">
          <option value="" <?= !$statusFilter?'selected':'' ?>>Tất cả</option>
          <?php foreach (['new'=>'Mới đặt','processing'=>'Đang xử lý','delivered'=>'Đã giao','cancelled'=>'Đã hủy'] as $k=>$v): ?>
            <option value="<?= $k ?>" <?= $statusFilter===$k?'selected':'' ?>><?= $v ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <?php if (empty($paged['items'])): ?>
      <div class="empty-state">
        <div class="empty-icon">📦</div><h3>Chưa có đơn hàng</h3>
        <p>Hãy mua sắm ngay!</p>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-caramel">🎂 Xem sản phẩm</a>
      </div>
      <?php else: ?>
        <?php foreach ($paged['items'] as $o):
          $sl = order_status_label($o['status']);
          $oItems = db_query('SELECT * FROM order_items WHERE order_id=?', [$o['id']]);
          $names  = implode(', ', array_map(fn($i) => $i['product_name'].' × '.$i['quantity'], $oItems));
          $totalQty = array_sum(array_column($oItems, 'quantity'));
        ?>
        <div class="card card-sm mb-3" style="cursor:pointer" onclick="location.href='<?= BASE_URL ?>/orders.php?detail=<?= $o['id'] ?>'">
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem">
            <span style="font-weight:700;color:var(--chocolate)">Đơn #<?= $o['id'] ?></span>
            <span class="badge <?= $sl['cls'] ?>"><?= $sl['text'] ?></span>
          </div>
          <div style="font-size:.82rem;color:var(--gray);margin-bottom:.5rem">
            📅 <?= format_datetime($o['created_at']) ?> &nbsp;|&nbsp;
            💳 <?= payment_label($o['payment_method']) ?> &nbsp;|&nbsp;
            📍 <?= h($o['recv_district'].', '.$o['recv_city']) ?>
          </div>
          <div style="font-size:.82rem;color:var(--espresso);margin-bottom:.7rem"><?= h(mb_substr($names, 0, 80)) ?></div>
          <div style="display:flex;justify-content:space-between">
            <span style="font-size:.82rem;color:var(--gray)"><?= $totalQty ?> sản phẩm</span>
            <span style="font-weight:700;color:var(--chocolate)"><?= format_currency($o['total_amount']) ?></span>
          </div>
        </div>
        <?php endforeach; ?>
        <?= render_pagination($paged, BASE_URL . '/orders.php', $statusFilter ? ['status' => $statusFilter] : []) ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Detail Modal -->
<?php if ($detail): ?>
<div class="modal-overlay active" id="detailModal" onclick="if(event.target.id==='detailModal')closeDetail()">
  <div class="modal" style="max-width:620px">
    <div class="modal-header">
      <h3>Chi tiết đơn #<?= $detail['id'] ?></h3>
      <button class="modal-close" onclick="closeDetail()">✕</button>
    </div>
    <div class="modal-body">
      <?php $sl = order_status_label($detail['status']); ?>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.2rem;font-size:.87rem">
        <div><span style="color:var(--muted)">Trạng thái:</span> <span class="badge <?= $sl['cls'] ?>"><?= $sl['text'] ?></span></div>
        <div><span style="color:var(--muted)">Ngày đặt:</span> <strong><?= format_datetime($detail['created_at']) ?></strong></div>
        <div><span style="color:var(--muted)">Thanh toán:</span> <strong><?= payment_label($detail['payment_method']) ?></strong></div>
        <div><span style="color:var(--muted)">Điện thoại:</span> <strong><?= h($detail['recv_phone']) ?></strong></div>
        <div style="grid-column:1/-1"><span style="color:var(--muted)">Địa chỉ:</span> <strong><?= h($detail['recv_address']) ?></strong></div>
      </div>
      <table class="data-table" style="font-size:.86rem">
        <thead><tr><th>Sản phẩm</th><th>SL</th><th>Đơn giá</th><th>Thành tiền</th></tr></thead>
        <tbody>
          <?php foreach ($detail['items'] as $it): ?>
          <tr>
            <td><?= h($it['product_name']) ?></td>
            <td><?= $it['quantity'] ?></td>
            <td><?= format_currency($it['unit_price']) ?></td>
            <td style="font-weight:600"><?= format_currency($it['unit_price'] * $it['quantity']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr style="background:var(--bg)">
            <td colspan="3" style="text-align:right;font-weight:600">Tổng:</td>
            <td style="font-weight:700;color:var(--primary)"><?= format_currency($detail['total_amount']) ?></td>
          </tr>
        </tfoot>
      </table>
    </div>
    <div class="modal-footer">
      <a href="<?= BASE_URL ?>/orders.php<?= $statusFilter?'?status='.$statusFilter:'' ?>" class="btn btn-outline">Đóng</a>
    </div>
  </div>
</div>
<?php endif; ?>
<script>function closeDetail(){history.back()}</script>
<?php render_footer(); ?>
