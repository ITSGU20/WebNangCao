const BASE_URL = document.querySelector('meta[name="base-url"]')?.content || '';

function showToast(msg, type = 'info') {
  let t = document.getElementById('toast');
  if (!t) { t = document.createElement('div'); t.id = 'toast'; document.body.appendChild(t); }
  t.textContent = msg;
  t.className = 'toast-' + type + ' show';
  clearTimeout(t._tm);
  t._tm = setTimeout(() => t.className = t.className.replace(' show', ''), 2800);
}

function updateCartBadge(count) {
  document.querySelectorAll('.cart-badge').forEach(b => {
    b.textContent = count;
    b.style.display = count > 0 ? 'flex' : 'none';
  });
}

function addToCartAjax(productId, btn) {
  if (btn) { btn.disabled = true; btn.textContent = '...'; }
  fetch(BASE_URL + '/ajax/cart_add.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'product_id=' + productId + '&qty=1'
  })
  .then(r => r.json())
  .then(data => {
    if (data.status === 'ok' || data.status === 'limited') {
      showToast(data.status === 'limited' ? 'Đã thêm (giới hạn tồn kho)!' : 'Đã thêm vào giỏ! 🛒', 'success');
      updateCartBadge(data.cart_count);
    } else if (data.status === 'out') {
      showToast('Sản phẩm đã hết hàng!', 'error');
    } else if (data.status === 'login') {
      showToast('Vui lòng đăng nhập để mua hàng', 'warning');
      setTimeout(() => location.href = BASE_URL + '/login.php?redirect=' + encodeURIComponent(location.pathname), 1000);
    } else {
      showToast(data.message || 'Có lỗi xảy ra', 'error');
    }
  })
  .catch(() => showToast('Lỗi kết nối', 'error'))
  .finally(() => { if (btn) { btn.disabled = false; btn.textContent = '+ Giỏ hàng'; } });
}

document.addEventListener('click', e => {
  if (!e.target.closest('.user-menu'))
    document.querySelectorAll('.dropdown-menu').forEach(m => m.classList.remove('open'));
});
