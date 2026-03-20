// Admin layout helper - shared across all admin pages
const AdminLayout = {
  sidebar(activePage) {
    const user = DB.Auth.current();
    return `
    <aside class="admin-sidebar">
      <div class="sidebar-brand">
        <a href="admin-dashboard.html" style="text-decoration:none">
          <div class="logo">La Fleur <span>Admin</span></div>
        </a>
      </div>
      <nav class="sidebar-nav">
        <div class="nav-section">Tổng quan</div>
        <a href="admin-dashboard.html" class="nav-item ${activePage==='dashboard'?'active':''}">
          <span class="nav-icon">📊</span> Dashboard
        </a>

        <div class="nav-section">Quản lý</div>
        <a href="admin-users.html" class="nav-item ${activePage==='users'?'active':''}">
          <span class="nav-icon">👥</span> Người dùng
        </a>
        <a href="admin-categories.html" class="nav-item ${activePage==='categories'?'active':''}">
          <span class="nav-icon">🏷️</span> Loại sản phẩm
        </a>
        <a href="admin-products.html" class="nav-item ${activePage==='products'?'active':''}">
          <span class="nav-icon">🎂</span> Sản phẩm
        </a>

        <div class="nav-section">Kho & Giá</div>
        <a href="admin-import.html" class="nav-item ${activePage==='import'?'active':''}">
          <span class="nav-icon">📥</span> Nhập hàng
        </a>
        <a href="admin-pricing.html" class="nav-item ${activePage==='pricing'?'active':''}">
          <span class="nav-icon">💰</span> Quản lý giá
        </a>
        <a href="admin-inventory.html" class="nav-item ${activePage==='inventory'?'active':''}">
          <span class="nav-icon">📦</span> Tồn kho
        </a>

        <div class="nav-section">Bán hàng</div>
        <a href="admin-orders.html" class="nav-item ${activePage==='orders'?'active':''}">
          <span class="nav-icon">🛒</span> Đơn hàng
        </a>

        <div style="padding:1.5rem 1.5rem 1rem;margin-top:auto">
          <div style="font-size:0.78rem;color:rgba(255,255,255,0.4);margin-bottom:0.6rem">Đăng nhập với:</div>
          <div style="font-size:0.83rem;color:rgba(255,255,255,0.75)">${user ? user.name : ''}</div>
          <button onclick="DB.Auth.logout();location.href='admin-login.html'" class="btn btn-danger btn-sm mt-2" style="width:100%;justify-content:center">🚪 Đăng xuất</button>
          <a href="index.html" style="display:block;text-align:center;margin-top:0.5rem;font-size:0.78rem;color:rgba(255,255,255,0.4)">← Trang khách hàng</a>
        </div>
      </nav>
    </aside>`;
  },

  topbar(title) {
    return `
    <div class="admin-topbar">
      <div class="topbar-title">${title}</div>
      <div class="topbar-right">
        <span>👤 ${DB.Auth.current()?.name || 'Admin'}</span>
        <span style="color:var(--border)">|</span>
        <span>${new Date().toLocaleDateString('vi-VN')}</span>
      </div>
    </div>`;
  },

  guard() {
    if (!DB.Auth.requireAdmin('admin-login.html')) return false;
    return true;
  }
};
