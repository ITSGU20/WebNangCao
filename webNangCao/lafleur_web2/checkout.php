<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/cart_helper.php';

session_init();
$user = auth_require(BASE_URL . '/login.php?redirect=' . urlencode(BASE_URL . '/checkout.php'));

// Đọc giỏ hàng sớm để kiểm tra trống không
$items = cart_get();
if (empty($items)) redirect(BASE_URL . '/cart.php');

$error   = '';
$success = false;
$orderId = null;

$wards = [
    // Quận 1
    'Phường Bến Nghé','Phường Bến Thành','Phường Cầu Kho','Phường Cầu Ông Lãnh',
    'Phường Cô Giang','Phường Đa Kao','Phường Nguyễn Cư Trinh','Phường Nguyễn Thái Bình',
    'Phường Phạm Ngũ Lão','Phường Tân Định',
    // Quận 3
    'Phường 1 (Q3)','Phường 2 (Q3)','Phường 3 (Q3)','Phường 4 (Q3)','Phường 5 (Q3)',
    'Phường 6 (Q3)','Phường 7 (Q3)','Phường 8 (Q3)','Phường 9 (Q3)','Phường 10 (Q3)',
    'Phường 11 (Q3)','Phường 12 (Q3)','Phường 13 (Q3)','Phường 14 (Q3)',
    // Quận 4
    'Phường 1 (Q4)','Phường 2 (Q4)','Phường 3 (Q4)','Phường 4 (Q4)','Phường 5 (Q4)',
    'Phường 6 (Q4)','Phường 7 (Q4)','Phường 8 (Q4)','Phường 9 (Q4)','Phường 10 (Q4)',
    'Phường 13 (Q4)','Phường 14 (Q4)','Phường 15 (Q4)','Phường 16 (Q4)','Phường 18 (Q4)',
    // Quận 5
    'Phường 1 (Q5)','Phường 2 (Q5)','Phường 3 (Q5)','Phường 4 (Q5)','Phường 5 (Q5)',
    'Phường 6 (Q5)','Phường 7 (Q5)','Phường 8 (Q5)','Phường 9 (Q5)','Phường 10 (Q5)',
    'Phường 11 (Q5)','Phường 12 (Q5)','Phường 13 (Q5)','Phường 14 (Q5)','Phường 15 (Q5)',
    // Quận 6
    'Phường 1 (Q6)','Phường 2 (Q6)','Phường 3 (Q6)','Phường 4 (Q6)','Phường 5 (Q6)',
    'Phường 6 (Q6)','Phường 7 (Q6)','Phường 8 (Q6)','Phường 9 (Q6)','Phường 10 (Q6)',
    'Phường 11 (Q6)','Phường 12 (Q6)','Phường 13 (Q6)','Phường 14 (Q6)',
    // Quận 7
    'Phường Bình Thuận','Phường Phú Mỹ','Phường Phú Thuận','Phường Tân Hưng',
    'Phường Tân Kiểng','Phường Tân Phong','Phường Tân Phú (Q7)','Phường Tân Quy',
    'Phường Tân Thuận Đông','Phường Tân Thuận Tây',
    // Quận 8
    'Phường 1 (Q8)','Phường 2 (Q8)','Phường 3 (Q8)','Phường 4 (Q8)','Phường 5 (Q8)',
    'Phường 6 (Q8)','Phường 7 (Q8)','Phường 8 (Q8)','Phường 9 (Q8)','Phường 10 (Q8)',
    'Phường 11 (Q8)','Phường 12 (Q8)','Phường 13 (Q8)','Phường 14 (Q8)','Phường 15 (Q8)','Phường 16 (Q8)',
    // Quận 10
    'Phường 1 (Q10)','Phường 2 (Q10)','Phường 3 (Q10)','Phường 4 (Q10)','Phường 5 (Q10)',
    'Phường 6 (Q10)','Phường 7 (Q10)','Phường 8 (Q10)','Phường 9 (Q10)','Phường 10 (Q10)',
    'Phường 11 (Q10)','Phường 12 (Q10)','Phường 13 (Q10)','Phường 14 (Q10)','Phường 15 (Q10)',
    // Quận 11
    'Phường 1 (Q11)','Phường 2 (Q11)','Phường 3 (Q11)','Phường 4 (Q11)','Phường 5 (Q11)',
    'Phường 6 (Q11)','Phường 7 (Q11)','Phường 8 (Q11)','Phường 9 (Q11)','Phường 10 (Q11)',
    'Phường 11 (Q11)','Phường 12 (Q11)','Phường 13 (Q11)','Phường 14 (Q11)','Phường 15 (Q11)','Phường 16 (Q11)',
    // Quận 12
    'Phường An Phú Đông','Phường Đông Hưng Thuận','Phường Hiệp Thành',
    'Phường Tân Chánh Hiệp','Phường Tân Hưng Thuận','Phường Tân Thới Hiệp',
    'Phường Tân Thới Nhất','Phường Thạnh Lộc','Phường Thạnh Xuân','Phường Thới An','Phường Trung Mỹ Tây',
    // Bình Thạnh
    'Phường 1 (BT)','Phường 2 (BT)','Phường 3 (BT)','Phường 5 (BT)','Phường 6 (BT)',
    'Phường 7 (BT)','Phường 11 (BT)','Phường 12 (BT)','Phường 13 (BT)','Phường 14 (BT)',
    'Phường 15 (BT)','Phường 17 (BT)','Phường 19 (BT)','Phường 21 (BT)',
    'Phường 22 (BT)','Phường 24 (BT)','Phường 25 (BT)','Phường 26 (BT)','Phường 27 (BT)','Phường 28 (BT)',
    // Gò Vấp
    'Phường 1 (GV)','Phường 3 (GV)','Phường 4 (GV)','Phường 5 (GV)','Phường 6 (GV)',
    'Phường 7 (GV)','Phường 8 (GV)','Phường 9 (GV)','Phường 10 (GV)','Phường 11 (GV)',
    'Phường 12 (GV)','Phường 13 (GV)','Phường 14 (GV)','Phường 15 (GV)','Phường 16 (GV)','Phường 17 (GV)',
    // Tân Bình
    'Phường 1 (TB)','Phường 2 (TB)','Phường 3 (TB)','Phường 4 (TB)','Phường 5 (TB)',
    'Phường 6 (TB)','Phường 7 (TB)','Phường 8 (TB)','Phường 9 (TB)','Phường 10 (TB)',
    'Phường 11 (TB)','Phường 12 (TB)','Phường 13 (TB)','Phường 14 (TB)','Phường 15 (TB)',
    // Tân Phú
    'Phường Hiệp Tân','Phường Hoà Thạnh','Phường Phú Thạnh','Phường Phú Thọ Hoà',
    'Phường Tân Quý','Phường Tân Sơn Nhì','Phường Tân Thành','Phường Tân Thới Hoà',
    'Phường Tân Thới Nhứt (TP)','Phường Tây Thạnh','Phường Sơn Kỳ',
    // Phú Nhuận
    'Phường 1 (PN)','Phường 2 (PN)','Phường 3 (PN)','Phường 4 (PN)','Phường 5 (PN)',
    'Phường 7 (PN)','Phường 8 (PN)','Phường 9 (PN)','Phường 10 (PN)','Phường 11 (PN)',
    'Phường 12 (PN)','Phường 13 (PN)','Phường 14 (PN)','Phường 15 (PN)','Phường 17 (PN)',
    // TP. Thủ Đức
    'Phường An Khánh','Phường An Lợi Đông','Phường An Phú','Phường Bình An',
    'Phường Bình Khánh','Phường Bình Trưng Đông','Phường Bình Trưng Tây',
    'Phường Cát Lái','Phường Thảo Điền','Phường Thủ Thiêm','Phường Thạnh Mỹ Lợi',
    'Phường Hiệp Phú','Phường Long Bình','Phường Long Phước','Phường Long Thạnh Mỹ',
    'Phường Long Trường','Phường Phú Hữu','Phường Tân Phú (TĐ)','Phường Tăng Nhơn Phú A','Phường Tăng Nhơn Phú B','Phường Trường Thạnh',
    'Phường Bình Chiểu','Phường Bình Thọ','Phường Hiệp Bình Chánh','Phường Hiệp Bình Phước',
    'Phường Linh Chiểu','Phường Linh Đông','Phường Linh Tây','Phường Linh Trung',
    'Phường Linh Xuân','Phường Tam Bình','Phường Tam Phú','Phường Trường Thọ',
    // Bình Chánh
    'Thị trấn Tân Túc','Xã An Phú Tây','Xã Bình Chánh','Xã Bình Hưng','Xã Bình Lợi',
    'Xã Đa Phước','Xã Hưng Long','Xã Lê Minh Xuân','Xã Phạm Văn Hai','Xã Phong Phú',
    'Xã Quy Đức','Xã Tân Kiên','Xã Tân Nhựt','Xã Tân Quý Tây','Xã Vĩnh Lộc A','Xã Vĩnh Lộc B',
    // Nhà Bè
    'Thị trấn Nhà Bè','Xã Hiệp Phước','Xã Long Thới','Xã Nhơn Đức','Xã Phú Xuân','Xã Phước Kiển','Xã Phước Lộc',
    // Củ Chi
    'Thị trấn Củ Chi','Xã An Nhơn Tây','Xã An Phú (CC)','Xã Bình Mỹ','Xã Hòa Phú',
    'Xã Nhuận Đức','Xã Phạm Văn Cội','Xã Phú Hòa Đông','Xã Phú Mỹ Hưng (CC)',
    'Xã Tân An Hội','Xã Tân Phú Trung','Xã Tân Thạnh Đông','Xã Tân Thạnh Tây',
    'Xã Tân Thông Hội','Xã Thái Mỹ','Xã Trung An','Xã Trung Lập Hạ','Xã Trung Lập Thượng',
    // Hóc Môn
    'Thị trấn Hóc Môn','Xã Bà Điểm','Xã Đông Thạnh','Xã Nhị Bình','Xã Tân Hiệp (HM)',
    'Xã Tân Thới Nhì','Xã Tân Xuân','Xã Thới Tam Thôn','Xã Trung Chánh',
    'Xã Xuân Thới Đông','Xã Xuân Thới Sơn','Xã Xuân Thới Thượng',
    // Cần Giờ
    'Thị trấn Cần Thạnh','Xã An Thới Đông','Xã Bình Khánh (CG)','Xã Long Hòa','Xã Lý Nhơn','Xã Tam Thôn Hiệp','Xã Thạnh An',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $addrType = $_POST['addr_type'] ?? 'account';
    $payment  = $_POST['payment'] ?? 'cash';
    if (!in_array($payment, ['cash','transfer','online'])) $payment = 'cash';

    if ($addrType === 'account') {
        $recvName     = $user['name'];
        $recvPhone    = $user['phone'];
        $recvAddress  = $user['address'];
        $recvDistrict = $user['ward'];
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
        elseif (!$recvDistrict)            $error = 'Vui lòng chọn xã/phường.';
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
            // Xóa ngày client trong SESSION sau khi đặt hàng thành công
            unset($_SESSION['_client_date']);
            $success = true;
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Có lỗi xảy ra khi đặt hàng. Vui lòng thử lại.';
            error_log('Order error: ' . $e->getMessage());
        }
    }
}

// Đọc giỏ hàng 1 lần duy nhất để hiển thị
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
                <label class="form-label">Xã/Phường *</label>
                <select name="recv_district" class="form-control">
                  <option value="">-- Chọn --</option>
                  <?php foreach ($wards as $d): ?>
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
