<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/cart_helper.php';

session_init();
render_head('Giỏ hàng - La Fleur');
render_navbar();

$items = cart_get();
$total = cart_total();
?>

<div style="max-width:1100px;margin:3rem auto;padding:0 2rem">
  <h1 style="font-family:var(--font-display);font-size:2rem;color:var(--chocolate);margin-bottom:.3rem">Giỏ hàng của bạn</h1>
  <p class="text-muted mb-4"><?= count($items) ?> loại sản phẩm (<?= cart_count() ?> mục)</p>

  <?php if (empty($items)): ?>
  <div class="empty-state">
    <div class="empty-icon">🛒</div>
    <h3>Giỏ hàng trống</h3>
    <p>Hãy thêm một số bánh ngọt vào giỏ nhé!</p>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-caramel btn-lg">🎂 Xem sản phẩm</a>
  </div>
  <?php else: ?>

  <div style="display:grid;grid-template-columns:1fr 360px;gap:2rem;align-items:start">
    <!-- Cart items -->
    <div>
      <div id="cartItems">
        <?php
        $gradients = ['#FFE8D6,#F7C9B5','#E8F5E9,#C8E6C9','#E3F2FD,#BBDEFB','#FCE4EC,#F8BBD0','#FFF8E1,#FFE082'];
        foreach ($items as $item):
          $g = $gradients[$item['product_id'] % count($gradients)];
          $p = db_row('SELECT image_path FROM products WHERE id=?', [$item['product_id']]);
          $imgHtml = ($p && $p['image_path'])
            ? '<img src="' . BASE_URL . '/uploads/products/' . h($p['image_path']) . '" style="width:100%;height:100%;object-fit:cover;border-radius:8px" alt="">'
            : '<span style="font-size:2.2rem">' . h($item['emoji']) . '</span>';
        ?>
        <div class="cart-item" id="row_<?= $item['product_id'] ?>">
          <div class="cart-thumb" style="background:linear-gradient(135deg,<?= $g ?>)"><?= $imgHtml ?></div>
          <div class="cart-info">
            <div class="cart-name"><?= h($item['name']) ?></div>
            <div class="cart-price"><?= format_currency($item['price']) ?> / <?= h($item['unit'] ?? 'cái') ?></div>
          </div>
          <div class="qty-control">
            <button class="qty-btn" onclick="updateQty(<?= $item['product_id'] ?>, <?= $item['qty']-1 ?>)">−</button>
            <span class="qty-num" id="qty_<?= $item['product_id'] ?>"><?= $item['qty'] ?></span>
            <button class="qty-btn" onclick="updateQty(<?= $item['product_id'] ?>, <?= $item['qty']+1 ?>)">+</button>
          </div>
          <div style="min-width:90px;text-align:right;font-weight:700;color:var(--chocolate)" id="sub_<?= $item['product_id'] ?>">
            <?= format_currency($item['price'] * $item['qty']) ?>
          </div>
          <button onclick="removeItem(<?= $item['product_id'] ?>)" style="background:none;border:none;cursor:pointer;color:var(--gray);font-size:1.2rem;padding:.3rem" title="Xóa">✕</button>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;gap:1rem;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border)">
        <button class="btn btn-secondary" onclick="clearCart()">🗑️ Xóa tất cả</button>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline">← Tiếp tục mua</a>
      </div>
    </div>

    <!-- Summary -->
    <div>
      <div class="cart-summary">
        <h3 style="font-family:var(--font-display);font-size:1.3rem;color:var(--chocolate);margin-bottom:1.2rem">Tóm tắt đơn hàng</h3>
        <div id="summaryItems">
          <?php foreach ($items as $item): ?>
          <div class="summary-row">
            <span style="flex:1;font-size:.83rem"><?= h($item['name']) ?> × <?= $item['qty'] ?></span>
            <span><?= format_currency($item['price'] * $item['qty']) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <div style="margin-top:.8rem;padding-top:.5rem">
          <div class="summary-row"><span>Tạm tính</span><span id="totalDisplay"><?= format_currency($total) ?></span></div>
          <div class="summary-row"><span>Phí vận chuyển</span><span style="color:var(--sage)">Miễn phí</span></div>
          <div class="summary-total"><span>Tổng cộng</span><span id="grandTotal"><?= format_currency($total) ?></span></div>
        </div>
        <a href="<?= BASE_URL ?>/checkout.php" class="btn btn-caramel btn-block btn-lg mt-3">Thanh toán →</a>
      </div>
    </div>
  </div>

  <?php endif; ?>
</div>

<script>
function updateQty(productId, qty) {
  fetch('<?= BASE_URL ?>/ajax/cart_update.php', {
    method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'product_id=' + productId + '&qty=' + qty
  })
  .then(r => r.json()).then(data => {
    if (qty <= 0) {
      document.getElementById('row_' + productId)?.remove();
    } else {
      const price = parseFloat(document.querySelector('#row_' + productId + ' .cart-price').textContent.replace(/\D/g,''));
      document.getElementById('qty_' + productId).textContent = qty;
    }
    updateCartBadge(data.cart_count);
    location.reload(); // Đơn giản nhất để sync lại tổng
  });
}

function removeItem(productId) {
  fetch('<?= BASE_URL ?>/ajax/cart_remove.php', {
    method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'product_id=' + productId
  })
  .then(r => r.json()).then(data => { updateCartBadge(data.cart_count); location.reload(); });
}

function clearCart() {
  if (!confirm('Xóa tất cả sản phẩm trong giỏ hàng?')) return;
  fetch('<?= BASE_URL ?>/ajax/cart_update.php', {
    method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: 'clear=1'
  }).then(() => location.reload());
}
</script>

<?php render_footer(); ?>
