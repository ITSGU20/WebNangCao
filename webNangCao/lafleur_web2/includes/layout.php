<?php
// Layout helpers - shared header/footer/navbar for customer pages
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/cart_helper.php';
require_once __DIR__ . '/functions.php';

function render_head(string $title = 'La Fleur Pâtisserie'): void { ?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= h($title) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<meta name="base-url" content="<?= BASE_URL ?>">
</head>
<body>
<?php }

function render_navbar(): void {
    $user  = auth_user();
    $count = cart_count();
?>
<nav class="navbar">
  <a href="<?= BASE_URL ?>/index.php" class="logo">La Fleur <span>Pâtisserie</span></a>
  <ul class="nav-links">
    <li><a href="<?= BASE_URL ?>/index.php">Trang chủ</a></li>
    <li><a href="<?= BASE_URL ?>/search.php">Tìm kiếm</a></li>
    <li><a href="<?= BASE_URL ?>/index.php#footer">Liên hệ</a></li>
  </ul>
  <div class="nav-actions">
    <a href="<?= BASE_URL ?>/cart.php" class="cart-btn">
      🛒<span class="cart-badge" <?= $count ? '' : 'style="display:none"' ?>><?= $count ?></span>
    </a>
    <?php if ($user): ?>
    <div class="user-menu">
      <button class="user-btn" onclick="this.parentElement.querySelector('.dropdown-menu').classList.toggle('open')">
        👤 <?= h(mb_substr(mb_strrchr($user['name'], ' ') ?: $user['name'], 1) ?: $user['name']) ?> ▾
      </button>
      <div class="dropdown-menu">
        <a class="dropdown-item" href="<?= BASE_URL ?>/profile.php">👤 Tài khoản</a>
        <a class="dropdown-item" href="<?= BASE_URL ?>/orders.php">📦 Đơn hàng</a>
        <hr class="dropdown-divider">
        <a class="dropdown-item" href="<?= BASE_URL ?>/logout.php">🚪 Đăng xuất</a>
      </div>
    </div>
    <?php else: ?>
    <a href="<?= BASE_URL ?>/login.php" class="btn btn-outline btn-sm">Đăng nhập</a>
    <a href="<?= BASE_URL ?>/register.php" class="btn btn-primary btn-sm">Đăng ký</a>
    <?php endif; ?>
  </div>
</nav>
<script>
document.addEventListener('click', e => {
  if (!e.target.closest('.user-menu'))
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('open'));
});
</script>
<?php }

function render_footer(): void { ?>
<footer class="site-footer" id="footer">
  <div class="footer-grid">
    <div class="footer-brand">
      <a href="<?= BASE_URL ?>/index.php" class="logo">La Fleur <span style="color:var(--caramel-light)">Pâtisserie</span></a>
      <p>Tiệm bánh ngọt phong cách Pháp tại TP.HCM.</p>
    </div>
    <div class="footer-col"><h4>Sản phẩm</h4><ul>
      <li><a href="<?= BASE_URL ?>/search.php">Tất cả sản phẩm</a></li>
      <li><a href="<?= BASE_URL ?>/search.php?cat=1">Bánh kem</a></li>
      <li><a href="<?= BASE_URL ?>/search.php?cat=4">Macaron</a></li>
    </ul></div>
    <div class="footer-col"><h4>Hỗ trợ</h4><ul>
      <li><a href="#">Chính sách giao hàng</a></li>
      <li><a href="#">Đổi trả</a></li>
    </ul></div>
    <div class="footer-col"><h4>Liên hệ</h4><ul>
      <li>📍 123 Lê Lợi, Q1, TP.HCM</li>
      <li>📞 0900 123 456</li>
      <li>🕐 7:00 - 21:00</li>
    </ul></div>
  </div>
  <div class="footer-bottom">© 2025 La Fleur Pâtisserie. Tất cả quyền được bảo lưu.</div>
</footer>
<div id="toast"></div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body></html>
<?php }

// Product card HTML
function render_product_card(array $p, string $catName = ''): string {
    $price    = calc_sell_price($p['cost_price'], $p['profit_rate']);
    $imgHtml  = $p['image_path']
        ? '<img src="' . BASE_URL . '/uploads/products/' . h($p['image_path']) . '" alt="' . h($p['name']) . '" style="width:100%;height:100%;object-fit:cover">'
        : '<span style="font-size:4rem">' . h($p['emoji']) . '</span>';
    $gradients = ['#FFE8D6,#F7C9B5','#E8F5E9,#C8E6C9','#E3F2FD,#BBDEFB','#FCE4EC,#F8BBD0','#FFF8E1,#FFE082'];
    $g = $gradients[$p['id'] % count($gradients)];
    $bgStyle  = $p['image_path'] ? '' : "background:linear-gradient(135deg,{$g})";
    $stockBadge = $p['stock'] <= 0
        ? '<div style="position:absolute;top:.5rem;right:.5rem"><span class="badge badge-danger">Hết hàng</span></div>'
        : ($p['stock'] <= 5 ? '<div style="position:absolute;top:.5rem;right:.5rem"><span class="badge badge-warning">Còn ' . $p['stock'] . '</span></div>' : '');
    $addBtn = $p['stock'] > 0
        ? '<button class="btn btn-caramel btn-sm" onclick="event.stopPropagation();addToCartAjax(' . $p['id'] . ',this)">+ Giỏ hàng</button>'
        : '<button class="btn btn-secondary btn-sm" disabled>Hết hàng</button>';
    return '
      <div class="product-card" onclick="location.href=\'' . BASE_URL . '/product.php?id=' . $p['id'] . '\'">
        <div class="product-thumb" style="' . $bgStyle . ';position:relative">' . $imgHtml . $stockBadge . '</div>
        <div class="product-info">
          <div class="product-cat">' . h($catName) . '</div>
          <div class="product-name">' . h($p['name']) . '</div>
          <div class="product-desc">' . h(mb_substr($p['description'] ?? '', 0, 80)) . '…</div>
          <div class="product-footer">
            <div class="product-price">' . format_currency($price) . '</div>
            ' . $addBtn . '
          </div>
        </div>
      </div>';
}