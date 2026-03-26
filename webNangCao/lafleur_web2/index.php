<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/cart_helper.php';

session_init();

$catId = sanitize_int($_GET['cat'] ?? 0);
$page  = sanitize_int($_GET['page'] ?? 1, 1);
$page  = max(1, $page);

// Active categories
$categories = db_query('SELECT * FROM categories WHERE is_active=1 ORDER BY id');

// Products query
$params = [];
$where  = 'p.is_active=1';
if ($catId) { $where .= ' AND p.category_id=?'; $params[] = $catId; }

$sql = "SELECT p.*, c.name AS cat_name
        FROM products p
        JOIN categories c ON c.id = p.category_id
        WHERE $where
        ORDER BY p.id DESC";

$paged = db_paginate($sql, $params, $page, PAGE_SIZE);

render_head('La Fleur Pâtisserie - Tiệm Bánh Ngọt');
render_navbar();
?>

<!-- HERO -->
<section class="hero">
  <div class="hero-pattern"></div>
  <div class="hero-content">
    <div class="hero-tag">✦ Nghệ thuật bánh ngọt Pháp ✦</div>
    <h1>Ngọt ngào từng<br>khoảnh khắc</h1>
    <p class="hero-sub">Bánh tươi thủ công mỗi ngày — từ macaron Paris đến bánh kem sinh nhật.</p>
    <div class="hero-actions">
      <a href="#products" class="btn btn-caramel btn-lg">🎂 Xem sản phẩm</a>
      <a href="<?= BASE_URL ?>/search.php" class="btn btn-outline btn-lg" style="border-color:rgba(255,255,255,0.6);color:white">🔍 Tìm kiếm</a>
    </div>
  </div>
</section>

<!-- CATEGORY FILTER -->
<div class="category-filter">
  <a href="<?= BASE_URL ?>/index.php" class="cat-pill <?= !$catId ? 'active' : '' ?>">✦ Tất cả</a>
  <?php foreach ($categories as $c): ?>
  <a href="<?= BASE_URL ?>/index.php?cat=<?= $c['id'] ?>" class="cat-pill <?= $catId == $c['id'] ? 'active' : '' ?>">
    <?= h($c['emoji']) ?> <?= h($c['name']) ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- PRODUCTS -->
<section class="products-section" id="products">
  <div class="section-header">
    <div class="section-title">Sản phẩm nổi bật</div>
    <a href="<?= BASE_URL ?>/search.php" style="font-size:.85rem;color:var(--caramel)">Xem tất cả →</a>
  </div>
  <div class="products-grid">
    <?php if (empty($paged['items'])): ?>
      <div class="empty-state" style="grid-column:1/-1">
        <div class="empty-icon">🥐</div><h3>Chưa có sản phẩm</h3><p>Sản phẩm đang được cập nhật.</p>
      </div>
    <?php else: ?>
      <?php foreach ($paged['items'] as $p): ?>
        <?= render_product_card($p, $p['cat_name']) ?>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
  <?php
  $extra = $catId ? ['cat' => $catId] : [];
  echo render_pagination($paged, BASE_URL . '/index.php', $extra);
  ?>
</section>

<?php render_footer(); ?>
