<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/cart_helper.php';

session_init();

$id = sanitize_int($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . '/index.php');

$product = db_row('SELECT p.*,c.name AS cat_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.id=? AND p.is_active=1', [$id]);
if (!$product) redirect(BASE_URL . '/index.php');

// ── Tính tồn kho động theo ngày client ──────────────────────────────────
// Client gửi ngày máy qua URL param ?_cd=YYYY-MM-DD (do JS redirect).
// Chỉ chấp nhận ngày <= hôm nay (server) để tránh nhận ngày tương lai.
$serverToday = date('Y-m-d'); // ngày thật của server
$clientDate  = '';

if (!empty($_GET['_cd'])) {
    $cd = $_GET['_cd'];
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $cd)
        && strtotime($cd) !== false
        && $cd <= $serverToday) {
        $clientDate = $cd;
        // Lưu vào SESSION để cart/checkout dùng lại
        $_SESSION['_client_date'] = $cd;
    }
} elseif (!empty($_SESSION['_client_date'])) {
    // Không có _cd trên URL → đọc từ SESSION (khi user điều hướng sang trang khác)
    $clientDate = $_SESSION['_client_date'];
}

$stockDate = $clientDate ?: $serverToday; // mốc tính tồn kho

// Cờ: có đang xem ngày khác ngày thật không?
// Nếu true → chỉ xem, không cho mua
$isViewingPastDate = ($stockDate !== $serverToday);

// Tồn kho tại $stockDate = tổng nhập (completed, đến ngày đó) - tổng bán (không hủy, đến ngày đó)
$dynamicStock = (int) db_val(
    'SELECT GREATEST(0,
        COALESCE((SELECT SUM(ii.quantity)
                  FROM import_items ii
                  JOIN import_receipts ir ON ir.id = ii.receipt_id
                  WHERE ii.product_id = ? AND ir.status = "completed"
                    AND ir.import_date <= ?), 0)
        -
        COALESCE((SELECT SUM(oi.quantity)
                  FROM order_items oi
                  JOIN orders o ON o.id = oi.order_id
                  WHERE oi.product_id = ? AND o.status <> "cancelled"
                    AND DATE(o.created_at) <= ?), 0)
    )',
    [$id, $stockDate, $id, $stockDate]
);

// Ghi đè stock bằng giá trị tính theo thời gian
$product['stock'] = $dynamicStock;
// ────────────────────────────────────────────────────────────────────────

$sellPrice = calc_sell_price($product['cost_price'], $product['profit_rate']);
$related   = db_query('SELECT p.*,c.name AS cat_name FROM products p JOIN categories c ON c.id=p.category_id WHERE p.category_id=? AND p.id<>? AND p.is_active=1 LIMIT 4', [$product['category_id'], $id]);

render_head(h($product['name']) . ' - La Fleur');
render_navbar();

$gradients = ['#FFE8D6,#F7C9B5','#E8F5E9,#C8E6C9','#E3F2FD,#BBDEFB','#FCE4EC,#F8BBD0','#FFF8E1,#FFE082'];
$g = $gradients[$product['id'] % count($gradients)];
?>
<div style="max-width:1200px;margin:0 auto;padding:1.5rem 2rem 0">
  <div class="breadcrumb">
    <a href="<?= BASE_URL ?>/index.php" style="color:var(--gray)">Trang chủ</a> ›
    <a href="<?= BASE_URL ?>/search.php?cat=<?= $product['category_id'] ?>" style="color:var(--gray)"><?= h($product['cat_name']) ?></a> ›
    <?= h($product['name']) ?>
  </div>
</div>

<?php if ($isViewingPastDate): ?>
<div style="background:#fff3cd;border-left:4px solid #f39c12;padding:.85rem 1.5rem;max-width:1200px;margin:.75rem auto 0;border-radius:8px;font-size:.88rem;color:#856404">
  📅 Đang xem tồn kho tại ngày <strong><?= date('d/m/Y', strtotime($stockDate)) ?></strong>
  — Chức năng đặt hàng tạm thời bị vô hiệu.
  <a href="<?= BASE_URL ?>/product.php?id=<?= $id ?>" style="margin-left:1rem;color:var(--caramel);font-weight:600">
    Quay về ngày hôm nay →
  </a>
</div>
<?php endif; ?>

<div class="product-detail-layout">
  <div>
    <div class="product-gallery" style="<?= $product['image_path'] ? 'background:none;padding:0' : "background:linear-gradient(135deg,$g)" ?>">
      <?php if ($product['image_path']): ?>
        <img src="<?= BASE_URL ?>/uploads/products/<?= h($product['image_path']) ?>" alt="<?= h($product['name']) ?>" style="width:100%;height:100%;object-fit:cover;border-radius:16px">
      <?php else: ?>
        <span style="font-size:9rem"><?= h($product['emoji']) ?></span>
      <?php endif; ?>
    </div>
    <div style="display:flex;gap:.8rem;margin-top:1rem">
      <div style="flex:1;padding:.8rem;border:1.5px solid var(--border);border-radius:8px;text-align:center;font-size:.78rem;color:var(--gray)">🌿 Nguyên liệu tươi</div>
      <div style="flex:1;padding:.8rem;border:1.5px solid var(--border);border-radius:8px;text-align:center;font-size:.78rem;color:var(--gray)">🎁 Đóng gói đẹp</div>
      <div style="flex:1;padding:.8rem;border:1.5px solid var(--border);border-radius:8px;text-align:center;font-size:.78rem;color:var(--gray)">🚀 Giao nhanh 2h</div>
    </div>
  </div>

  <div>
    <div class="detail-cat"><?= h($product['cat_name']) ?></div>
    <h1 class="detail-name"><?= h($product['name']) ?></h1>
    <div class="detail-price"><?= format_currency($sellPrice) ?></div>
    <div style="display:flex;gap:.5rem;margin-bottom:1.2rem">
      <span style="color:#F39C12">★★★★★</span>
      <span style="font-size:.82rem;color:var(--gray)">(128 đánh giá)</span>
    </div>
    <p class="detail-desc"><?= nl2br(h($product['description'] ?? '')) ?></p>
    <div style="padding:1rem;background:var(--gray-light);border-radius:8px;margin-bottom:1.5rem;font-size:.85rem">
      <div style="display:flex;gap:2rem;flex-wrap:wrap">
        <div><span style="color:var(--gray);display:block;font-size:.75rem">Mã SP</span><strong><?= h($product['code']) ?></strong></div>
        <div><span style="color:var(--gray);display:block;font-size:.75rem">Tồn kho</span>
          <?php if ($product['stock'] <= 0): ?>
            <strong style="color:#e74c3c">❌ Hết hàng</strong>
          <?php elseif ($product['stock'] <= 5): ?>
            <strong>Còn <?= $product['stock'] ?> <span class="badge badge-warning">Sắp hết</span></strong>
          <?php else: ?>
            <strong>Còn <?= $product['stock'] ?> <?= h($product['unit'] ?? 'cái') ?></strong>
          <?php endif; ?>
        </div>
        <div><span style="color:var(--gray);display:block;font-size:.75rem">Đơn vị</span><strong><?= h($product['unit'] ?? 'cái') ?></strong></div>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem" id="qtySection" <?= $product['stock']<=0?'style="opacity:.4"':'' ?>>
      <span style="font-size:.85rem;font-weight:600;color:var(--chocolate)">Số lượng:</span>
      <div class="qty-control">
        <button class="qty-btn" onclick="changeQty(-1)">−</button>
        <span class="qty-num" id="qtyDisplay">1</span>
        <button class="qty-btn" onclick="changeQty(1)">+</button>
      </div>
    </div>

    <?php if ($isViewingPastDate): ?>
    
    <div class="detail-actions">
      <button class="btn btn-secondary btn-lg" disabled>🔒 Chỉ xem — không thể đặt hàng</button>
    </div>
    <?php elseif ($product['stock'] > 0): ?>
    <div class="detail-actions">
      <button class="btn btn-caramel btn-lg" onclick="doAddCart()">🛒 Thêm vào giỏ</button>
      <button class="btn btn-primary btn-lg" onclick="doBuyNow()">⚡ Mua ngay</button>
    </div>
    <?php else: ?>
    <div class="detail-actions">
      <button class="btn btn-secondary btn-lg" disabled>❌ Hết hàng</button>
    </div>
    <?php endif; ?>

    <div style="margin-top:1.5rem;padding:1.2rem;border:1px solid var(--border);border-radius:8px;font-size:.83rem;color:var(--gray)">
      ✅ Bánh tươi làm trong ngày &nbsp;|&nbsp; 🔄 Đổi trả 24h &nbsp;|&nbsp; 🛡️ Thanh toán an toàn
    </div>
  </div>
</div>

<?php if ($related): ?>
<div style="max-width:1300px;margin:3rem auto;padding:0 2rem">
  <div class="section-title mb-4">Sản phẩm liên quan</div>
  <div class="products-grid">
    <?php foreach ($related as $rp): echo render_product_card($rp, $rp['cat_name']); endforeach; ?>
  </div>
</div>
<?php endif; ?>

<script>
// ── Gửi ngày máy client qua URL param _cd ──
(function () {
    const d = new Date();
    const today = d.getFullYear() + '-'
        + String(d.getMonth() + 1).padStart(2, '0') + '-'
        + String(d.getDate()).padStart(2, '0');

    const phpUsedDate = '<?= $stockDate ?>';

    // Chỉ redirect nếu PHP chưa dùng đúng ngày client
    // VÀ _cd trên URL chưa phải ngày client (tránh redirect vô hạn)
    const urlCd = new URLSearchParams(location.search).get('_cd');
    if (today !== phpUsedDate && urlCd !== today) {
        const url = new URL(location.href);
        url.searchParams.set('_cd', today);
        location.replace(url.toString());
    }
})();

const maxStock = <?= $dynamicStock ?>;
let qty = 1;
function changeQty(d){qty=Math.max(1,Math.min(qty+d,maxStock));document.getElementById('qtyDisplay').textContent=qty}
function doAddCart(){
  fetch('<?= BASE_URL ?>/ajax/cart_add.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'product_id=<?= $product['id'] ?>&qty='+qty})
  .then(r=>r.json()).then(data=>{
    if(data.status==='ok'||data.status==='limited'){showToast('Đã thêm '+qty+' cái vào giỏ 🛒','success');updateCartBadge(data.cart_count);}
    else if(data.status==='out'){showToast('Sản phẩm đã hết hàng!','error');}
    else if(data.status==='login'){showToast('Vui lòng đăng nhập','warning');setTimeout(()=>location.href='<?= BASE_URL ?>/login.php',1000);}
  });
}
function doBuyNow(){
  fetch('<?= BASE_URL ?>/ajax/cart_add.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'product_id=<?= $product['id'] ?>&qty='+qty})
  .then(()=>location.href='<?= BASE_URL ?>/cart.php');
}
</script>
<?php render_footer(); ?>
