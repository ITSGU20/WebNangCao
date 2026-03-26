<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/cart_helper.php';

session_init();
$user = auth_require(BASE_URL . '/login.php?redirect=' . urlencode(BASE_URL . '/checkout.php'));

$items = cart_get();
if (empty($items)) redirect(BASE_URL . '/cart.php');

$error   = '';
$success = false;
$orderId = null;

$districts = ['Quận 1','Quận 2','Quận 3','Quận 4','Quận 5','Quận 6','Quận 7','Quận 8','Quận 9','Quận 10','Quận 11','Quận 12','Bình Thạnh','Gò Vấp','Tân Bình','Tân Phú','Phú Nhuận','Bình Chánh','Nhà Bè','Củ Chi','Hóc Môn'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $addrType = $_POST['addr_type'] ?? 'account';
    $payment  = $_POST['payment'] ?? 'cash';
    if (!in_array($payment, ['cash','transfer','online'])) $payment = 'cash';

    if ($addrType === 'account') {
        $recvName     = $user['name'];
        $recvPhone    = $user['phone'];
        $recvAddress  = $user['address'];
        $recvDistrict = $user['district'];
        $recvCity     = $user['city'] ?: 'TP.HCM';
    } else {
        $recvName     = trim($_POST['recv_name'] ?? '');
        $recvPhone    = trim($_POST['recv_phone'] ?? '');
        $recvAddress  = trim($_POST['recv_street'] ?? '');
        $recvDistrict = trim($_POST['recv_district'] ?? '');
        $recvCity     = trim($_POST['recv_city'] ?? 'TP.HCM');

        if (!$recvName)                    $error = 'Vui lòng nhập họ tên người nhận.';
        elseif (!validate_phone($recvPhone)) $error = 'Số điện thoại không hợp lệ.';
        elseif (!$recvAddress)             $error = 'Vui lòng nhập địa chỉ chi tiết.';
        elseif (!$recvDistrict)            $error = 'Vui lòng chọn quận/huyện.';
    }

    if (!$error) {
        // Validate stock
        $stockErrors = cart_validate_stock();
        if ($stockErrors) { $error = implode(' ', $stockErrors); }
    }

    if (!$error) {
        $total = cart_total();
        $pdo   = db();
        $pdo->beginTransaction();
        try {
            // Insert order
            $orderId = db_insert(
                'INSERT INTO orders (user_id,recv_name,recv_phone,recv_address,recv_district,recv_city,payment_method,total_amount,status)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                [$user['id'], $recvName, $recvPhone, $recvAddress, $recvDistrict, $recvCity, $payment, $total, 'new']
            );
            // Insert order items + reduce stock
            foreach ($items as $item) {
                db_exec(
                    'INSERT INTO order_items (order_id,product_id,product_name,quantity,unit_price) VALUES (?,?,?,?,?)',
                    [$orderId, $item['product_id'], $item['name'], $item['qty'], $item['price']]
                );
                db_exec(
                    'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?',
                    [$item['qty'], $item['product_id'], $item['qty']]
                );
            }
            $pdo->commit();
            cart_clear();
            $success  = true;
            $items    = []; // reset for display
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Có lỗi xảy ra khi đặt hàng. Vui lòng thử lại.';
            error_log('Order error: ' . $e->getMessage());
        }
    }
}

$cartItems = cart_get();
$total     = cart_total();

render_head('Thanh toán - La Fleur');
render_navbar();
?>

<div style="max-width:1050px;margin:2.5rem auto;padding:0 2rem">
  <!-- Steps -->
  <div class="checkout-steps mb-4">
    <div class="step done"><div class="step-num">✓</div> Giỏ hàng</div>
    <div class="step-line"></div>
    <div class="step <?= !$success?'active':'' ?>"><div class="step-num">2</div> Thanh toán</div>
    <div class="step-line"></div>
    <div class="step <?= $success?'active':'' ?>"><div class="step-num"><?= $success?'✓':'3' ?></div> Xác nhận</div>
  </div>

  <?php if ($success): ?>
  <!-- SUCCESS -->
  <div style="max-width:560px;margin:0 auto">
    <div class="card" style="text-align:center;padding:3rem 2rem">
      <div style="font-size:4rem;margin-bottom:1rem">🎉</div>
      <h2 style="font-family:var(--font-display);font-size:1.8rem;color:var(--chocolate);margin-bottom:.5rem">Đặt hàng thành công!</h2>
      <p style="color:var(--gray);margin-bottom:1.5rem">Cảm ơn bạn đã tin tưởng La Fleur. Chúng tôi sẽ liên hệ xác nhận sớm nhất.</p>
      <div style="background:var(--gray-light);border-radius:8px;padding:1.2rem;margin-bottom:2rem;text-align:left">
        <div class="summary-row"><span>Mã đơn hàng</span><strong>#<?= $orderId ?></strong></div>
        <div class="summary-row"><span>Thanh toán</span><span><?= payment_label($payment ?? 'cash') ?></span></div>
      </div>
      <div style="display:flex;gap:1rem;justify-content:center">
        <a href="<?= BASE_URL ?>/orders.php" class="btn btn-primary">📦 Xem đơn hàng</a>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline">Tiếp tục mua sắm</a>
      </div>
    </div>
  </div>

  <?php else: ?>
  <!-- FORM -->
  <?php if ($error): ?><div class="alert alert-danger mb-3"><?= h($error) ?></div><?php endif; ?>

  <form method="POST">
    <?= csrf_field() ?>
    <div style="display:grid;grid-template-columns:1fr 380px;gap:2rem;align-items:start">
      <div>
        <!-- Address -->
        <div class="card mb-3">
          <h3 style="font-family:var(--font-display);font-size:1.3rem;color:var(--chocolate);margin-bottom:1.2rem">📍 Địa chỉ giao hàng</h3>
          <div class="radio-group" style="margin-bottom:1rem">
            <?php if ($user['address']): ?>
            <label class="radio-item selected" onclick="setAddrType('account',this)">
              <input type="radio" name="addr_type" value="account" <?= ($_POST['addr_type']??'account')==='account'?'checked':'' ?>>
              <div>
                <div style="font-weight:600;font-size:.85rem">Địa chỉ tài khoản</div>
                <div style="font-size:.78rem;color:var(--gray)"><?= h($user['address']) ?></div>
              </div>
            </label>
            <?php endif; ?>
            <label class="radio-item <?= !$user['address']?'selected':'' ?>" onclick="setAddrType('new',this)">
              <input type="radio" name="addr_type" value="new" <?= (!$user['address']||($_POST['addr_type']??'')==='new')?'checked':'' ?>>
              <div style="font-size:.85rem;font-weight:600">➕ Nhập địa chỉ giao hàng mới</div>
            </label>
          </div>
          <div id="newAddrForm" style="display:<?= (!$user['address']||($_POST['addr_type']??'')==='new')?'block':'none' ?>;padding-top:1rem;border-top:1px solid var(--border)">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Họ tên người nhận *</label>
                <input type="text" name="recv_name" class="form-control" value="<?= h($_POST['recv_name']??$user['name']) ?>">
              </div>
              <div class="form-group">
                <label class="form-label">Số điện thoại *</label>
                <input type="tel" name="recv_phone" class="form-control" value="<?= h($_POST['recv_phone']??$user['phone']) ?>" maxlength="11">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Địa chỉ chi tiết *</label>
              <input type="text" name="recv_street" class="form-control" value="<?= h($_POST['recv_street']??'') ?>" placeholder="Số nhà, tên đường">
            </div>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Quận/Huyện *</label>
                <select name="recv_district" class="form-control">
                  <option value="">-- Chọn --</option>
                  <?php foreach ($districts as $d): ?>
                    <option value="<?= h($d) ?>" <?= ($_POST['recv_district']??'')===$d?'selected':'' ?>><?= h($d) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Tỉnh/Thành</label>
                <select name="recv_city" class="form-control">
                  <?php foreach (['TP.HCM','Hà Nội','Đà Nẵng','Cần Thơ','Hải Phòng'] as $c): ?>
                    <option value="<?= h($c) ?>" <?= ($_POST['recv_city']??'TP.HCM')===$c?'selected':'' ?>><?= h($c) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <!-- Payment -->
        <div class="card">
          <h3 style="font-family:var(--font-display);font-size:1.3rem;color:var(--chocolate);margin-bottom:1.2rem">💳 Phương thức thanh toán</h3>
          <div class="radio-group">
            <?php
            $methods = [
              'cash'     => ['💵','Tiền mặt khi nhận hàng','Thanh toán trực tiếp cho nhân viên giao hàng'],
              'transfer' => ['🏦','Chuyển khoản ngân hàng','Vietcombank • MB Bank • Techcombank'],
              'online'   => ['💻','Thanh toán trực tuyến','MoMo / ZaloPay / VNPay / Thẻ tín dụng'],
            ];
            $selPay = $_POST['payment'] ?? 'cash';
            foreach ($methods as $key => [$icon, $label, $sub]):
            ?>
            <label class="radio-item <?= $selPay===$key?'selected':'' ?>" onclick="selectPay(this,'<?= $key ?>')">
              <input type="radio" name="payment" value="<?= $key ?>" <?= $selPay===$key?'checked':'' ?>>
              <span style="font-size:1.3rem"><?= $icon ?></span>
              <div>
                <div style="font-weight:600;font-size:.9rem"><?= h($label) ?></div>
                <div style="font-size:.75rem;color:var(--gray)"><?= h($sub) ?></div>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
          <!-- Bank transfer info -->
          <div id="transferInfo" style="display:<?= ($selPay==='transfer')?'block':'none' ?>;margin-top:1rem;background:var(--gray-light);border-radius:var(--radius-sm);padding:1rem;font-size:.85rem">
            <div style="font-weight:600;margin-bottom:.5rem;color:var(--chocolate)">🏦 Thông tin chuyển khoản</div>
            <div>Ngân hàng: <strong>Vietcombank</strong></div>
            <div>Số TK: <strong>1234567890</strong></div>
            <div>Chủ TK: <strong>CONG TY TNHH LA FLEUR</strong></div>
            <div style="margin-top:.4rem;color:var(--rose)">Nội dung: <strong>LAFLEUR + Tên + SĐT</strong></div>
          </div>
        </div>
      </div>

      <!-- Order summary -->
      <div>
        <div class="cart-summary">
          <h3 style="font-family:var(--font-display);font-size:1.2rem;color:var(--chocolate);margin-bottom:1.2rem">📋 Đơn hàng</h3>
          <div style="max-height:260px;overflow-y:auto">
            <?php foreach ($cartItems as $item): ?>
            <div class="summary-row">
              <span style="flex:1;font-size:.82rem"><?= h($item['emoji'].' '.$item['name']) ?> × <?= $item['qty'] ?></span>
              <span style="white-space:nowrap"><?= format_currency($item['price'] * $item['qty']) ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <div style="padding-top:.8rem;margin-top:.5rem;border-top:1px solid var(--border)">
            <div class="summary-row"><span>Tạm tính</span><span><?= format_currency($total) ?></span></div>
            <div class="summary-row"><span>Phí vận chuyển</span><span style="color:var(--sage)">Miễn phí</span></div>
            <div class="summary-total"><span>Tổng cộng</span><span><?= format_currency($total) ?></span></div>
          </div>
          <button type="submit" class="btn btn-caramel btn-block btn-lg mt-3">Đặt hàng →</button>
          <a href="<?= BASE_URL ?>/cart.php" class="btn btn-secondary btn-block mt-1" style="justify-content:center">← Quay lại giỏ hàng</a>
        </div>
      </div>
    </div>
  </form>
  <?php endif; ?>
</div>

<script>
function setAddrType(type, el) {
  document.querySelectorAll('.radio-group .radio-item').forEach(r => r.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('newAddrForm').style.display = type === 'new' ? 'block' : 'none';
}
function selectPay(el, key) {
  document.querySelectorAll('.card .radio-group .radio-item').forEach(r => r.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('transferInfo').style.display = key === 'transfer' ? 'block' : 'none';
}
</script>
<?php render_footer(); ?>
