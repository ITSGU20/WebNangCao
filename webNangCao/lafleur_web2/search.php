<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/cart_helper.php';

session_init();

$q        = trim($_GET['q'] ?? '');
$catId    = sanitize_int($_GET['cat'] ?? 0);
$minPrice = sanitize_float($_GET['min'] ?? 0);
$maxPrice = sanitize_float($_GET['max'] ?? 0);
$sort     = $_GET['sort'] ?? 'default';
$page     = max(1, sanitize_int($_GET['page'] ?? 1));
$advanced = isset($_GET['adv']);

$categories = db_query('SELECT * FROM categories WHERE is_active=1 ORDER BY id');

// Build query
$where  = ['p.is_active=1'];
$params = [];

if ($q) { $where[] = 'p.name LIKE ?'; $params[] = '%' . $q . '%'; }
if ($catId) { $where[] = 'p.category_id=?'; $params[] = $catId; }
if ($minPrice > 0) { $where[] = '(p.cost_price * (1 + p.profit_rate/100)) >= ?'; $params[] = $minPrice; }
if ($maxPrice > 0) { $where[] = '(p.cost_price * (1 + p.profit_rate/100)) <= ?'; $params[] = $maxPrice; }

$orderBy = match($sort) {
    'price_asc'  => 'ORDER BY (p.cost_price*(1+p.profit_rate/100)) ASC',
    'price_desc' => 'ORDER BY (p.cost_price*(1+p.profit_rate/100)) DESC',
    'name_asc'   => 'ORDER BY p.name ASC',
    default      => 'ORDER BY p.id DESC',
};

$sql = "SELECT p.*, c.name AS cat_name FROM products p JOIN categories c ON c.id=p.category_id
        WHERE " . implode(' AND ', $where) . " $orderBy";

$paged = db_paginate($sql, $params, $page, PAGE_SIZE);

$extraParams = array_filter(compact('q','catId','minPrice','maxPrice','sort','advanced'),fn($v)=>$v!==''&&$v!==0&&$v!==0.0&&$v!==false);
if ($catId) $extraParams['cat'] = $catId;

render_head('Tìm kiếm - La Fleur');
render_navbar();
?>
<div style="background:linear-gradient(135deg,var(--espresso),var(--chocolate));color:white;padding:2.5rem 2rem;text-align:center">
  <h1 style="font-family:var(--font-display);font-size:2rem;font-style:italic;margin-bottom:.5rem">Tìm kiếm sản phẩm</h1>
  <p style="opacity:.75">Khám phá các loại bánh ngọt tuyệt vời</p>
</div>

<div style="max-width:1200px;margin:0 auto;padding:2.5rem 2rem">
  <div class="card mb-4">
    <!-- Basic search -->
    <form method="GET" action="<?= BASE_URL ?>/search.php">
      <div style="display:flex;gap:.8rem;align-items:center;margin-bottom:1rem">
        <div class="search-wrap" style="flex:1">
          <span class="search-icon">🔍</span>
          <input type="text" name="q" value="<?= h($q) ?>" placeholder="Tìm theo tên sản phẩm..." class="form-control" style="padding-left:2.4rem">
        </div>
        <button type="submit" class="btn btn-primary">Tìm</button>
        <button type="button" class="btn btn-secondary" onclick="toggleAdv()" id="advBtn">⚙️ Nâng cao</button>
      </div>
      <!-- Advanced search -->
      <div id="advPanel" style="display:<?= ($advanced||$catId||$minPrice||$maxPrice)?'block':'none' ?>;padding-top:1rem;border-top:1px solid var(--border)">
        <p style="font-size:.83rem;color:var(--gray);margin-bottom:1rem">🔎 Kết hợp nhiều tiêu chí tìm kiếm</p>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:1rem;align-items:end">
          <div class="form-group mb-0">
            <label class="form-label">Loại sản phẩm</label>
            <select name="cat" class="form-control">
              <option value="">-- Tất cả --</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $catId==$c['id']?'selected':'' ?>><?= h($c['emoji'].' '.$c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group mb-0">
            <label class="form-label">Giá từ (đ)</label>
            <input type="number" name="min" value="<?= $minPrice?:'' ?>" class="form-control" placeholder="0" min="0">
          </div>
          <div class="form-group mb-0">
            <label class="form-label">Giá đến (đ)</label>
            <input type="number" name="max" value="<?= $maxPrice?:'' ?>" class="form-control" placeholder="Không giới hạn" min="0">
          </div>
          <div class="form-group mb-0">
            <label class="form-label">Sắp xếp</label>
            <select name="sort" class="form-control">
              <option value="default" <?= $sort=='default'?'selected':'' ?>>Mặc định</option>
              <option value="price_asc" <?= $sort=='price_asc'?'selected':'' ?>>Giá: Thấp → Cao</option>
              <option value="price_desc" <?= $sort=='price_desc'?'selected':'' ?>>Giá: Cao → Thấp</option>
              <option value="name_asc" <?= $sort=='name_asc'?'selected':'' ?>>Tên: A → Z</option>
            </select>
          </div>
        </div>
        <input type="hidden" name="adv" value="1">
        <div style="margin-top:1rem;display:flex;gap:.8rem">
          <button type="submit" class="btn btn-caramel">🔍 Tìm kiếm</button>
          <a href="<?= BASE_URL ?>/search.php" class="btn btn-secondary">↺ Đặt lại</a>
        </div>
      </div>
    </form>
  </div>

  <div style="display:flex;justify-content:space-between;margin-bottom:1rem;font-size:.88rem;color:var(--gray)">
    <span>Tìm thấy <strong><?= $paged['total'] ?></strong> sản phẩm</span>
  </div>

  <div class="products-grid">
    <?php if (empty($paged['items'])): ?>
      <div class="empty-state" style="grid-column:1/-1">
        <div class="empty-icon">🔍</div><h3>Không tìm thấy sản phẩm</h3>
        <p>Thử tìm với từ khóa khác.</p>
        <a href="<?= BASE_URL ?>/search.php" class="btn btn-caramel">Xem tất cả</a>
      </div>
    <?php else: ?>
      <?php foreach ($paged['items'] as $p): echo render_product_card($p, $p['cat_name']); endforeach; ?>
    <?php endif; ?>
  </div>
  <?= render_pagination($paged, BASE_URL . '/search.php', $extraParams) ?>
</div>

<script>function toggleAdv(){const p=document.getElementById('advPanel');const b=document.getElementById('advBtn');const s=p.style.display==='none';p.style.display=s?'block':'none';b.textContent=s?'▲ Thu gọn':'⚙️ Nâng cao'}</script>
<?php render_footer(); ?>
