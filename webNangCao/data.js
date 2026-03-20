// ============ LA FLEUR PÂTISSERIE - DATA MANAGER ============

const DB = (() => {
  // ---- INIT SAMPLE DATA ----
  function init() {
    if (localStorage.getItem('lf_init')) return;

    const categories = [
      { id: 1, name: 'Bánh kem', emoji: '🎂', desc: 'Bánh kem sinh nhật, tiệc, gia đình', active: true },
      { id: 2, name: 'Bánh mì ngọt', emoji: '🥐', desc: 'Croissant, brioche, bánh mì hoa cúc', active: true },
      { id: 3, name: 'Bánh quy', emoji: '🍪', desc: 'Bánh quy bơ, chocolate chip, hạnh nhân', active: true },
      { id: 4, name: 'Macaron', emoji: '🎨', desc: 'Macaron Pháp đủ màu sắc hương vị', active: true },
      { id: 5, name: 'Bánh tart', emoji: '🥧', desc: 'Tart trái cây, trứng, socola', active: true },
    ];

    const products = [
      { id: 1, catId: 1, code: 'BK001', name: 'Bánh kem dâu tây', emoji: '🍓', price: 150000, costPrice: 90000, desc: 'Bánh kem tươi phủ dâu tây ngọt chua, kem vanilla mềm mịn, lớp biscuit xốp thơm. Thích hợp cho sinh nhật và các dịp đặc biệt.', stock: 15, active: true },
      { id: 2, catId: 1, code: 'BK002', name: 'Bánh kem chocolate', emoji: '🍫', price: 180000, costPrice: 110000, desc: 'Bánh kem chocolate Bỉ đậm đà, lớp mousse mượt mà, trang trí hoa chocolate tinh tế. Dành cho người yêu chocolate.', stock: 12, active: true },
      { id: 3, catId: 1, code: 'BK003', name: 'Bánh kem matcha', emoji: '🍵', price: 160000, costPrice: 100000, desc: 'Bánh kem matcha Nhật Bản thượng hạng, vị trà xanh thanh mát, kết hợp kem tươi và đậu đỏ truyền thống.', stock: 8, active: true },
      { id: 4, catId: 1, code: 'BK004', name: 'Bánh kem tiramisu', emoji: '☕', price: 195000, costPrice: 120000, desc: 'Bánh kem tiramisu Ý với mascarpone béo ngậy, espresso đậm đà và lớp bột cacao phủ trên cùng thơm nức.', stock: 10, active: true },
      { id: 5, catId: 2, code: 'BMN001', name: 'Croissant bơ Pháp', emoji: '🥐', price: 35000, costPrice: 18000, desc: 'Croissant bơ Pháp chính gốc, 72 lớp bột xếp tầng, vỏ giòn rụm, ruột xốp thơm phức. Nướng tươi mỗi ngày.', stock: 50, active: true },
      { id: 6, catId: 2, code: 'BMN002', name: 'Bánh mì hoa cúc', emoji: '🍞', price: 45000, costPrice: 25000, desc: 'Bánh mì hoa cúc kiểu Pháp - Brioche, bơ thơm, ruột mềm mịn như bông, vỏ ngoài vàng óng đẹp mắt.', stock: 30, active: true },
      { id: 7, catId: 3, code: 'BQ001', name: 'Bánh quy bơ hộp', emoji: '🍪', price: 120000, costPrice: 65000, desc: 'Hộp bánh quy bơ Denmark cao cấp 300g, 12 loại hình dạng khác nhau, thơm béo, giòn tan. Quà tặng hoàn hảo.', stock: 40, active: true },
      { id: 8, catId: 3, code: 'BQ002', name: 'Bánh quy chocolate chip', emoji: '🫐', price: 130000, costPrice: 70000, desc: 'Bánh quy chocolate chip Mỹ đặc biệt, chocolate 70% cacao, kết cấu vừa giòn vừa dẻo, thơm hấp dẫn.', stock: 35, active: true },
      { id: 9, catId: 4, code: 'MC001', name: 'Macaron hương dâu', emoji: '🩷', price: 25000, costPrice: 13000, desc: 'Macaron vỏ hồng đậu hương dâu tây, nhân buttercream dâu mịn, vỏ giòn tan trong miệng. Đặc sản Paris.', stock: 60, active: true },
      { id: 10, catId: 4, code: 'MC002', name: 'Macaron vanilla', emoji: '🤍', price: 25000, costPrice: 13000, desc: 'Macaron vanilla Madagascar thuần khiết, nhân kem vanilla Madagascar chuẩn vị Pháp, vỏ trắng ngà tinh tế.', stock: 55, active: true },
      { id: 11, catId: 4, code: 'MC003', name: 'Hộp macaron 12 cái', emoji: '🎁', price: 260000, costPrice: 145000, desc: 'Hộp macaron mix 12 hương vị đặc biệt: dâu, vanilla, matcha, caramel, chocolate, chanh dây... Quà tặng sang trọng.', stock: 20, active: true },
      { id: 12, catId: 5, code: 'BT001', name: 'Tart trái cây tươi', emoji: '🍇', price: 55000, costPrice: 30000, desc: 'Bánh tart vỏ giòn nhân kem patisserie mịn, phủ trái cây tươi theo mùa: dâu, kiwi, blueberry, nho...', stock: 25, active: true },
      { id: 13, catId: 5, code: 'BT002', name: 'Tart trứng Bồ Đào Nha', emoji: '🥚', price: 30000, costPrice: 15000, desc: 'Pastel de nata - Tart trứng Bồ Đào Nha chuẩn gốc, vỏ ngàn lớp giòn, nhân trứng sữa mướt thơm quế.', stock: 45, active: true },
    ];

    const users = [
      { id: 1, name: 'Nguyễn Thị Lan', email: 'lan.nguyen@email.com', phone: '0901234567', password: '123456', address: '123 Lê Lợi, Q1, TP.HCM', role: 'customer', active: true, createdAt: '2024-01-15' },
      { id: 2, name: 'Trần Văn Minh', email: 'minh.tran@email.com', phone: '0912345678', password: '123456', address: '456 Nguyễn Huệ, Q1, TP.HCM', role: 'customer', active: true, createdAt: '2024-02-20' },
      { id: 3, name: 'Phạm Thị Thu', email: 'thu.pham@email.com', phone: '0923456789', password: '123456', address: '789 Điện Biên Phủ, Q3, TP.HCM', role: 'customer', active: false, createdAt: '2024-03-10' },
      { id: 4, name: 'Admin', email: 'admin@lafleur.com', phone: '0900000000', password: 'admin123', address: '', role: 'admin', active: true, createdAt: '2024-01-01' },
    ];

    const orders = [
      { id: 1, userId: 1, userName: 'Nguyễn Thị Lan', phone: '0901234567', address: '123 Lê Lợi, Q1, TP.HCM', items: [{productId:1, name:'Bánh kem dâu tây', qty:1, price:150000}, {productId:9, name:'Macaron hương dâu', qty:4, price:25000}], total: 250000, paymentMethod: 'Tiền mặt', status: 'delivered', createdAt: '2024-11-10' },
      { id: 2, userId: 2, userName: 'Trần Văn Minh', phone: '0912345678', address: '456 Nguyễn Huệ, Q1, TP.HCM', items: [{productId:2, name:'Bánh kem chocolate', qty:1, price:180000}], total: 180000, paymentMethod: 'Chuyển khoản', status: 'processing', createdAt: '2024-11-15' },
      { id: 3, userId: 1, userName: 'Nguyễn Thị Lan', phone: '0901234567', address: '123 Lê Lợi, Q1, TP.HCM', items: [{productId:7, name:'Bánh quy bơ hộp', qty:2, price:120000}], total: 240000, paymentMethod: 'Online', status: 'new', createdAt: '2024-11-20' },
    ];

    const imports = [
      { id: 1, date: '2024-11-01', items: [{productId:1, qty:20, costPrice:90000}, {productId:2, qty:15, costPrice:110000}], status: 'completed', createdAt: '2024-11-01' },
      { id: 2, date: '2024-11-10', items: [{productId:5, qty:100, costPrice:18000}, {productId:6, qty:60, costPrice:25000}], status: 'pending', createdAt: '2024-11-10' },
    ];

    save('lf_categories', categories);
    save('lf_products', products);
    save('lf_users', users);
    save('lf_orders', orders);
    save('lf_imports', imports);
    save('lf_cart', []);
    localStorage.setItem('lf_init', '1');
  }

  function save(key, data) { localStorage.setItem(key, JSON.stringify(data)); }
  function load(key, def = []) {
    try { return JSON.parse(localStorage.getItem(key)) || def; }
    catch { return def; }
  }

  // ---- AUTH ----
  const Auth = {
    login(email, password, isAdmin = false) {
      const users = load('lf_users');
      const user = users.find(u => u.email === email && u.password === password);
      if (!user) return { ok: false, msg: 'Email hoặc mật khẩu không đúng.' };
      if (!user.active) return { ok: false, msg: 'Tài khoản đã bị khóa.' };
      if (isAdmin && user.role !== 'admin') return { ok: false, msg: 'Không có quyền truy cập admin.' };
      if (!isAdmin && user.role === 'admin') return { ok: false, msg: 'Vui lòng dùng trang đăng nhập admin.' };
      save('lf_current_user', user);
      return { ok: true, user };
    },
    logout() { localStorage.removeItem('lf_current_user'); },
    current() { return load('lf_current_user', null); },
    isAdmin() { const u = this.current(); return u && u.role === 'admin'; },
    isLoggedIn() { return !!this.current(); },
    requireLogin(redirect = 'login.html') { if (!this.isLoggedIn()) { location.href = redirect; return false; } return true; },
    requireAdmin(redirect = 'admin-login.html') { if (!this.isAdmin()) { location.href = redirect; return false; } return true; },
    register(data) {
      const users = load('lf_users');
      if (users.find(u => u.email === data.email)) return { ok: false, msg: 'Email đã được sử dụng.' };
      const user = { ...data, id: Date.now(), role: 'customer', active: true, createdAt: today() };
      users.push(user);
      save('lf_users', users);
      return { ok: true, user };
    },
    updateProfile(id, updates) {
      const users = load('lf_users');
      const idx = users.findIndex(u => u.id === id);
      if (idx === -1) return false;
      users[idx] = { ...users[idx], ...updates };
      save('lf_users', users);
      save('lf_current_user', users[idx]);
      return true;
    }
  };

  // ---- CATEGORIES ----
  const Categories = {
    all() { return load('lf_categories'); },
    list() { return this.all(); },
    active() { return this.all().filter(c => c.active !== false); },
    get(id) { return this.all().find(c => c.id == id); },
    save(data) {
      const cats = load('lf_categories');
      if (data.id) {
        const idx = cats.findIndex(c => c.id == data.id);
        if (idx !== -1) { cats[idx] = data; }
      } else {
        data.id = Date.now();
        cats.push(data);
      }
      save('lf_categories', cats);
      return data;
    },
    delete(id) {
      const cats = load('lf_categories').filter(c => c.id != id);
      save('lf_categories', cats);
    },
    toggle(id) {
      const cats = load('lf_categories');
      const idx = cats.findIndex(c => c.id == id);
      if (idx !== -1) { cats[idx].active = !cats[idx].active; save('lf_categories', cats); return cats[idx]; }
    }
  };

  // ---- PRODUCTS ----
  const Products = {
    all() { return load('lf_products'); },
    list() { return this.all(); },
    active() { return this.all().filter(p => p.active !== false); },
    get(id) { return this.all().find(p => p.id == id); },
    byCategory(catId) { return this.active().filter(p => p.catId == catId); },
    search(query, catId = null, minPrice = null, maxPrice = null) {
      let results = this.active();
      if (query) results = results.filter(p => p.name.toLowerCase().includes(query.toLowerCase()));
      if (catId) results = results.filter(p => p.catId == catId);
      if (minPrice) results = results.filter(p => p.price >= minPrice);
      if (maxPrice) results = results.filter(p => p.price <= maxPrice);
      return results;
    },
    save(data) {
      const products = load('lf_products');
      if (data.id) {
        const idx = products.findIndex(p => p.id == data.id);
        if (idx !== -1) { products[idx] = { ...products[idx], ...data }; }
      } else {
        data.id = Date.now();
        data.stock = data.stock || 0;
        products.push(data);
      }
      save('lf_products', products);
      return data;
    },
    delete(id) { save('lf_products', load('lf_products').filter(p => p.id != id)); },
    toggle(id) {
      const products = load('lf_products');
      const idx = products.findIndex(p => p.id == id);
      if (idx !== -1) { products[idx].active = !products[idx].active; save('lf_products', products); return products[idx]; }
    },
    updateStock(id, delta) {
      const products = load('lf_products');
      const idx = products.findIndex(p => p.id == id);
      if (idx !== -1) { products[idx].stock = Math.max(0, (products[idx].stock || 0) + delta); save('lf_products', products); }
    }
  };

  // ---- USERS ----
  const Users = {
    all() { return load('lf_users').filter(u => u.role !== 'admin'); },
    list() { return load('lf_users'); },
    get(id) { return load('lf_users').find(u => u.id == id); },
    resetPassword(id) {
      const users = load('lf_users');
      const idx = users.findIndex(u => u.id == id);
      if (idx !== -1) { users[idx].password = '123456'; save('lf_users', users); }
    },
    toggle(id) {
      const users = load('lf_users');
      const idx = users.findIndex(u => u.id == id);
      if (idx !== -1) { users[idx].active = !users[idx].active; save('lf_users', users); return users[idx]; }
    }
  };

  // ---- ORDERS ----
  const Orders = {
    all() { return load('lf_orders'); },
    list() { return this.all(); },
    get(id) { return this.all().find(o => o.id == id); },
    byUser(userId) { return this.all().filter(o => o.userId == userId); },
    place(orderData) {
      const orders = load('lf_orders');
      const order = { ...orderData, id: Date.now(), status: 'new', createdAt: today() };
      orders.push(order);
      save('lf_orders', orders);
      // Reduce stock
      order.items.forEach(item => Products.updateStock(item.productId, -item.qty));
      // Clear cart
      save('lf_cart', []);
      return order;
    },
    updateStatus(id, status) {
      const orders = load('lf_orders');
      const idx = orders.findIndex(o => o.id == id);
      if (idx !== -1) { orders[idx].status = status; save('lf_orders', orders); return orders[idx]; }
    },
    filter(startDate, endDate, status) {
      let orders = this.all();
      if (startDate) orders = orders.filter(o => o.createdAt >= startDate);
      if (endDate) orders = orders.filter(o => o.createdAt <= endDate);
      if (status) orders = orders.filter(o => o.status === status);
      return orders;
    }
  };

  // ---- CART ----
  const Cart = {
    all() { return load('lf_cart'); },
    count() { return this.all().reduce((s, i) => s + i.qty, 0); },
    total() { return this.all().reduce((s, i) => s + i.price * i.qty, 0); },
    add(productId, qty = 1) {
      const cart = this.all();
      const prod = Products.get(productId);
      if (!prod) return false;
      const idx = cart.findIndex(i => i.productId == productId);
      if (idx !== -1) { cart[idx].qty += qty; }
      else { cart.push({ productId, name: prod.name, emoji: prod.emoji, price: prod.price, qty }); }
      save('lf_cart', cart);
      return true;
    },
    update(productId, qty) {
      const cart = this.all();
      const idx = cart.findIndex(i => i.productId == productId);
      if (idx !== -1) {
        if (qty <= 0) { cart.splice(idx, 1); }
        else { cart[idx].qty = qty; }
        save('lf_cart', cart);
      }
    },
    remove(productId) {
      save('lf_cart', this.all().filter(i => i.productId != productId));
    },
    clear() { save('lf_cart', []); }
  };

  // ---- IMPORTS ----
  const Imports = {
    all() { return load('lf_imports'); },
    list() { return this.all(); },
    get(id) { return this.all().find(i => i.id == id); },
    save(data) {
      const imports = load('lf_imports');
      if (data.id) {
        const idx = imports.findIndex(i => i.id == data.id);
        if (idx !== -1) { imports[idx] = { ...imports[idx], ...data }; }
      } else {
        data.id = Date.now();
        data.status = 'pending';
        data.createdAt = today();
        imports.push(data);
      }
      save('lf_imports', imports);
      return data;
    },
    complete(id) {
      const imports = load('lf_imports');
      const idx = imports.findIndex(i => i.id == id);
      if (idx !== -1 && imports[idx].status === 'pending') {
        imports[idx].status = 'completed';
        imports[idx].doneAt = today();
        save('lf_imports', imports);
        // Update stock & cost prices
        imports[idx].items.forEach(item => {
          Products.updateStock(item.productId, item.qty);
          const products = load('lf_products');
          const pIdx = products.findIndex(p => p.id == item.productId);
          if (pIdx !== -1) { products[pIdx].costPrice = item.costPrice; save('lf_products', products); }
        });
        return imports[idx];
      }
      return null;
    }
  };

  // ---- HELPERS ----
  function today() { return new Date().toISOString().split('T')[0]; }

  function formatCurrency(n) {
    return new Intl.NumberFormat('vi-VN', { style: 'currency', currency: 'VND' }).format(n);
  }

  function formatDate(d) {
    if (!d) return '—';
    try {
      const date = new Date(d);
      if (!isNaN(date)) {
        const day = String(date.getDate()).padStart(2, '0');
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const year = date.getFullYear();
        return `${day}/${month}/${year}`;
      }
    } catch {}
    // Fallback: parse YYYY-MM-DD string manually
    const parts = d.split('T')[0].split('-');
    if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
    return d;
  }

  function statusLabel(status) {
    const map = {
      new:        { text: 'Mới đặt',     label: 'Mới đặt',     cls: 'badge-info' },
      processing: { text: 'Đang xử lý',  label: 'Đang xử lý',  cls: 'badge-warning' },
      delivered:  { text: 'Đã giao',     label: 'Đã giao',     cls: 'badge-success' },
      cancelled:  { text: 'Đã hủy',      label: 'Đã hủy',      cls: 'badge-danger' },
    };
    return map[status] || { text: status, label: status, cls: 'badge-secondary' };
  }

  function showToast(msg, type = 'info') {
    let toast = document.getElementById('toast');
    if (!toast) { toast = document.createElement('div'); toast.id = 'toast'; document.body.appendChild(toast); }
    toast.textContent = msg;
    toast.className = `toast-${type} show`;
    clearTimeout(toast._t);
    toast._t = setTimeout(() => { toast.className = toast.className.replace(' show', ''); }, 2800);
  }

  function updateCartBadge() {
    const badges = document.querySelectorAll('.cart-badge');
    const count = Cart.count();
    badges.forEach(b => { b.textContent = count; b.style.display = count ? 'flex' : 'none'; });
  }

  function paginate(arr, page, perPage = 8) {
    const total = Math.ceil(arr.length / perPage);
    const items = arr.slice((page - 1) * perPage, page * perPage);
    return { items, total, page, perPage, count: arr.length };
  }

  function renderPagination(first, second, third) {
    // Detect call signature:
    // Admin pages:    renderPagination(paged, onPage)       → returns HTML string
    // Customer pages: renderPagination(container, paged, onPage) → writes to DOM
    const isDomElement = first && typeof first === 'object' && first.nodeType === 1;

    let container, paged, onPage;
    if (isDomElement) {
      container = first; paged = second; onPage = third;
    } else {
      paged = first; onPage = second;
    }

    if (paged.total <= 1) {
      if (isDomElement) { container.innerHTML = ''; return; }
      return '';
    }

    let html = '<div class="pagination">';
    html += `<button class="pg-btn" ${paged.page === 1 ? 'disabled' : ''} onclick="(${onPage.toString()})(${paged.page - 1})">‹</button>`;
    for (let i = 1; i <= paged.total; i++) {
      html += `<button class="pg-btn${i === paged.page ? ' active' : ''}" onclick="(${onPage.toString()})(${i})">${i}</button>`;
    }
    html += `<button class="pg-btn" ${paged.page === paged.total ? 'disabled' : ''} onclick="(${onPage.toString()})(${paged.page + 1})">›</button>`;
    html += '</div>';

    if (isDomElement) { container.innerHTML = html; return; }
    return html;
  }

  function productCard(p, catName) {
    const gradients = ['#FFE8D6,#F7C9B5', '#E8F5E9,#C8E6C9', '#E3F2FD,#BBDEFB', '#FCE4EC,#F8BBD0', '#FFF8E1,#FFE082', '#EDE7F6,#D1C4E9'];
    const g = gradients[p.id % gradients.length];
    return `
      <div class="product-card" onclick="location.href='product.html?id=${p.id}'">
        <div class="product-thumb" style="background:linear-gradient(135deg,${g})">${p.emoji}</div>
        <div class="product-info">
          <div class="product-cat">${catName || ''}</div>
          <div class="product-name">${p.name}</div>
          <div class="product-desc">${p.desc}</div>
          <div class="product-footer">
            <div class="product-price">${formatCurrency(p.price)}</div>
            <button class="btn btn-caramel btn-sm" onclick="event.stopPropagation();addToCart(${p.id})">+ Giỏ hàng</button>
          </div>
        </div>
      </div>`;
  }

  function addToCart(productId) {
    if (!Auth.isLoggedIn()) { showToast('Vui lòng đăng nhập để mua hàng', 'warning'); setTimeout(() => location.href = 'login.html', 1000); return; }
    Cart.add(productId);
    showToast('Đã thêm vào giỏ hàng! 🛒', 'success');
    updateCartBadge();
  }

  function navbarUser() {
    const user = Auth.current();
    const actionsEl = document.getElementById('nav-actions');
    if (!actionsEl) return;
    const count = Cart.count();
    if (user) {
      actionsEl.innerHTML = `
        <button class="cart-btn" onclick="location.href='cart.html'">🛒<span class="cart-badge" style="${count ? '' : 'display:none'}">${count}</span></button>
        <div class="user-menu">
          <button class="user-btn" onclick="this.parentElement.querySelector('.dropdown-menu').classList.toggle('open')">👤 ${user.name.split(' ').pop()} ▾</button>
          <div class="dropdown-menu">
            <a class="dropdown-item" href="profile.html">👤 Tài khoản</a>
            <a class="dropdown-item" href="orders.html">📦 Đơn hàng</a>
            <hr class="dropdown-divider">
            <button class="dropdown-item" onclick="DB.Auth.logout();location.href='index.html'">🚪 Đăng xuất</button>
          </div>
        </div>`;
    } else {
      actionsEl.innerHTML = `
        <button class="cart-btn" onclick="location.href='cart.html'">🛒<span class="cart-badge" style="${count ? '' : 'display:none'}">${count}</span></button>
        <a href="login.html" class="btn btn-outline btn-sm">Đăng nhập</a>
        <a href="register.html" class="btn btn-primary btn-sm">Đăng ký</a>`;
    }
    document.addEventListener('click', e => {
      if (!e.target.closest('.user-menu')) {
        document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('open'));
      }
    });
  }

  // Auto init
  init();

  return {
    Auth, Categories, Products, Users, Orders, Cart, Imports,
    save, load, formatCurrency, formatDate, statusLabel,
    showToast, updateCartBadge, paginate, renderPagination,
    productCard, addToCart, navbarUser, today
  };
})();

// Make addToCart global
window.addToCart = DB.addToCart.bind(DB);