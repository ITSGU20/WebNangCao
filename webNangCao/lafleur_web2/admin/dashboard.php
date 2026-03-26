<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/layout.php';

session_init();

admin_layout_start('Dashboard', 'dashboard');

$revenue    = db_val("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status<>'cancelled'");
$totalOrders= db_val("SELECT COUNT(*) FROM orders");
$newOrders  = db_val("SELECT COUNT(*) FROM orders WHERE status='new'");
$customers  = db_val("SELECT COUNT(*) FROM users WHERE role='customer'");
$products   = db_val("SELECT COUNT(*) FROM products WHERE is_active=1");
$recentOrders = db_query("SELECT o.*,u.name AS uname FROM orders o JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC LIMIT 5");
$lowStock   = db_query("SELECT * FROM products WHERE is_active=1 AND stock <= 10 ORDER BY stock ASC LIMIT 6");

function short(float $n): string {
    if ($n >= 1e9) return round($n/1e9,1).'tỷ';
    if ($n >= 1e6) return round($n/1e6,1).'tr';
    if ($n >= 1e3) return round($n/1e3).'k';
    return number_format($n,0,',','.');
}
?>

<div class="stats-grid">
  <?php foreach ([
    ['💰','si-caramel', short($revenue).' đ', 'Tổng doanh thu', format_currency($revenue)],
    ['🛒','si-brown',   $totalOrders,           'Tổng đơn hàng', ''],
    ['⚡','si-purple',  $newOrders,             'Đơn hàng mới',  ''],
    ['👥','si-blue',    $customers,             'Khách hàng',    ''],
    ['🎂','si-green',   $products,              'SP đang bán',   ''],
  ] as [$icon,$cls,$val,$label,$title]): ?>
  <div class="stat-card">
    <div class="stat-icon <?= $cls ?>"><?= $icon ?></div>
    <div class="stat-info">
      <h3 <?= $title ? "title=\"{$title}\"" : '' ?> style="font-size:1.3rem;max-width:140px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= h($val) ?></h3>
      <p><?= h($label) ?></p>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-top:1.5rem">
  <!-- Recent orders -->
  <div class="card">
    <div class="flex-between mb-3">
      <h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--chocolate)">Đơn hàng gần đây</h3>
      <a href="<?= ADMIN_URL ?>/orders.php" style="font-size:.82rem;color:var(--caramel)">Xem tất cả →</a>
    </div>
    <table class="admin-table">
      <thead><tr><th>Mã</th><th>Khách</th><th>Tổng</th><th>TT</th></tr></thead>
      <tbody>
        <?php if (empty($recentOrders)): ?>
        <tr><td colspan="4" style="text-align:center;color:var(--muted);padding:1.5rem">Chưa có đơn hàng</td></tr>
        <?php else: foreach ($recentOrders as $o):
          $sl = order_status_label($o['status']); ?>
        <tr>
          <td><a href="<?= ADMIN_URL ?>/orders.php?id=<?= $o['id'] ?>" style="color:var(--caramel)">#<?= $o['id'] ?></a></td>
          <td><?= h($o['uname']) ?></td>
          <td style="white-space:nowrap"><?= format_currency($o['total_amount']) ?></td>
          <td><span class="badge <?= $sl['cls'] ?>"><?= $sl['text'] ?></span></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Low stock -->
  <div class="card">
    <div class="flex-between mb-3">
      <h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--chocolate)">⚠️ Sắp hết hàng</h3>
      <a href="<?= ADMIN_URL ?>/inventory.php" style="font-size:.82rem;color:var(--caramel)">Xem kho →</a>
    </div>
    <?php if (empty($lowStock)): ?>
      <p class="text-muted">Tất cả sản phẩm còn đủ hàng ✅</p>
    <?php else: foreach ($lowStock as $p): ?>
      <div style="display:flex;justify-content:space-between;align-items:center;padding:.6rem 0;border-bottom:1px solid var(--border);font-size:.85rem">
        <span><?= h($p['emoji'].' '.$p['name']) ?></span>
        <span class="badge <?= $p['stock']<=0?'badge-danger':($p['stock']<=5?'badge-warning':'badge-info') ?>"><?= $p['stock'] ?> còn lại</span>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<!-- Quick links -->
<div class="card mt-3">
  <h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--chocolate);margin-bottom:1.2rem">Thao tác nhanh</h3>
  <div style="display:flex;gap:1rem;flex-wrap:wrap">
    <a href="<?= ADMIN_URL ?>/products.php" class="btn btn-caramel">🎂 Thêm sản phẩm</a>
    <a href="<?= ADMIN_URL ?>/import.php" class="btn btn-primary">📥 Nhập hàng mới</a>
    <a href="<?= ADMIN_URL ?>/orders.php" class="btn btn-outline">🛒 Quản lý đơn hàng</a>
    <a href="<?= ADMIN_URL ?>/users.php" class="btn btn-secondary">👥 Xem khách hàng</a>
    <a href="<?= BASE_URL ?>/index.php" target="_blank" class="btn btn-secondary">🌐 Xem website</a>
  </div>
</div>

<?php admin_layout_end(); ?>
