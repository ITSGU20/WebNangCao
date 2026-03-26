<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

function admin_head(string $title): void { ?>
<!DOCTYPE html><html lang="vi"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= h($title) ?> – La Fleur Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head><body class="admin-body">
<?php }

function admin_sidebar(string $active): void {
    $admin = auth_admin();
    $nav = [
        ['dashboard','📊','Dashboard','dashboard.php'],
        ['users','👥','Người dùng','users.php'],
        ['categories','🏷️','Loại sản phẩm','categories.php'],
        ['products','🎂','Sản phẩm','admin_products.php'],
        ['import','📥','Nhập hàng','import.php'],
        ['pricing','💰','Quản lý giá','pricing.php'],
        ['inventory','📦','Tồn kho','inventory.php'],
        ['orders','🛒','Đơn hàng','admin_orders.php'],
    ];
    $sections = [
        'dashboard' => 'Tổng quan',
        'users'     => 'Quản lý',
        'import'    => 'Kho & Giá',
        'orders'    => 'Bán hàng',
    ];
    echo '<aside class="admin-sidebar"><div class="sidebar-brand"><a href="' . ADMIN_URL . '/dashboard.php" style="text-decoration:none"><div class="logo">La Fleur <span>Admin</span></div></a></div><nav class="sidebar-nav">';
    foreach ($nav as [$key, $icon, $label, $file]) {
        if (isset($sections[$key])) {
            echo '<div class="nav-section">' . $sections[$key] . '</div>';
        }
        $cls = $active === $key ? ' active' : '';
        echo "<a href=\"" . ADMIN_URL . "/{$file}\" class=\"nav-item{$cls}\"><span class=\"nav-icon\">{$icon}</span> {$label}</a>";
    }
    echo '<div style="padding:1.5rem 1.5rem 1rem;margin-top:auto">
        <div style="font-size:.78rem;color:rgba(255,255,255,.4);margin-bottom:.4rem">Đăng nhập với:</div>
        <div style="font-size:.83rem;color:rgba(255,255,255,.75)">' . h($admin['name'] ?? '') . '</div>
        <a href="' . ADMIN_URL . '/admin_logout.php" class="btn btn-danger btn-sm mt-2" style="width:100%;justify-content:center">🚪 Đăng xuất</a>
        <a href="' . BASE_URL . '/index.php" style="display:block;text-align:center;margin-top:.5rem;font-size:.78rem;color:rgba(255,255,255,.4)">← Trang khách hàng</a>
    </div></nav></aside>';
}

function admin_topbar(string $title): void {
    $admin = auth_admin();
    echo '<div class="admin-topbar">
        <div class="topbar-title">' . h($title) . '</div>
        <div class="topbar-right">
            <span>👤 ' . h($admin['name'] ?? 'Admin') . '</span>
            <span style="color:var(--border)">|</span>
            <span>' . date('d/m/Y') . '</span>
        </div>
    </div>';
}

function admin_guard(): void { auth_admin_require(); }

function admin_layout_start(string $title, string $active): void {
    admin_guard();
    admin_head($title);
    echo '<div class="admin-layout">';
    admin_sidebar($active);
    echo '<div class="admin-main">';
    admin_topbar($title);
    echo '<div class="admin-content">';
}

function admin_layout_end(): void {
    echo '</div></div></div>';
    echo '<div id="toast"></div>';
    echo '<script src="' . BASE_URL . '/assets/js/main.js"></script>';
    echo '</body></html>';
}

// Pagination for admin (returns HTML)
function admin_pagination(array $paged, string $baseUrl, array $extra = []): string {
    if ($paged['pages'] <= 1) return '';
    $html = '<div class="pagination" style="justify-content:flex-start;margin-top:1rem">';
    for ($i = 1; $i <= $paged['pages']; $i++) {
        $extra['page'] = $i;
        $url = $baseUrl . '?' . http_build_query($extra);
        $cls = $i === $paged['page'] ? ' active' : '';
        $html .= "<a href=\"{$url}\" class=\"pg-btn{$cls}\">{$i}</a>";
    }
    return $html . '</div>';
}
