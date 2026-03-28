<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/admin_layout.php';

admin_guard();
$admin = auth_admin();
$msg = $_GET['flash'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Save phiếu nhập (tạo mới hoặc cập nhật nháp)
    if ($action === 'save' || $action === 'complete') {
        $id   = sanitize_int($_POST['receipt_id'] ?? 0);
        $date = $_POST['import_date'] ?? date('Y-m-d');
        $note = trim($_POST['note'] ?? '');
        $pids = $_POST['product_id'] ?? [];
        $qtys = $_POST['quantity']   ?? [];
        $costs= $_POST['import_price']?? [];

        // Build items
        $items = [];
        foreach ($pids as $i => $pid) {
            $pid  = sanitize_int($pid);
            $qty  = sanitize_int($qtys[$i] ?? 0);
            $cost = sanitize_float($costs[$i] ?? 0);
            if ($pid && $qty > 0 && $cost > 0) $items[] = compact('pid','qty','cost');
        }
        if (empty($items)) { $msg='error:Vui lòng thêm ít nhất 1 sản phẩm hợp lệ.'; }
        else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                if ($id) {
                    // Only update if still pending
                    $existing = db_row('SELECT status FROM import_receipts WHERE id=?',[$id]);
                    if (!$existing || $existing['status'] === 'completed') throw new Exception('Phiếu đã hoàn thành, không thể sửa.');
                    db_exec('UPDATE import_receipts SET import_date=?,note=? WHERE id=?',[$date,$note,$id]);
                    db_exec('DELETE FROM import_items WHERE receipt_id=?',[$id]);
                } else {
                    $id = db_insert('INSERT INTO import_receipts (import_date,note,status,created_by) VALUES (?,?,?,?)',
                        [$date,$note,'pending',$admin['id']]);
                }
                foreach ($items as $it) {
                    db_exec('INSERT INTO import_items (receipt_id,product_id,quantity,import_price) VALUES (?,?,?,?)',
                        [$id,$it['pid'],$it['qty'],$it['cost']]);
                }
                if ($action === 'complete') {
                    // Weighted avg cost + update stock
                    $products = db_query('SELECT * FROM products WHERE id IN ('.implode(',',array_column($items,'pid')).')',);
                    $pMap = array_column($products,null,'id');
                    foreach ($items as $it) {
                        $p = $pMap[$it['pid']] ?? null;
                        if (!$p) continue;
                        $newCost  = weighted_avg_cost($p['stock'], $p['cost_price'], $it['qty'], $it['cost']);
                        $newStock = $p['stock'] + $it['qty'];
                        db_exec('UPDATE products SET stock=?,cost_price=? WHERE id=?',[$newStock,$newCost,$it['pid']]);
                    }
                    db_exec("UPDATE import_receipts SET status='completed',completed_at=NOW() WHERE id=?",[$id]);
                    $msg='success:Hoàn thành phiếu nhập! Tồn kho & giá vốn đã cập nhật.';
                } else {
                    $msg='success:Đã lưu phiếu nhập.';
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
                $msg='error:'.$e->getMessage();
            }
        }
    }
}

// PRG: redirect after successful POST so modal closes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && str_starts_with($msg, 'success:')) {
    redirect(ADMIN_URL . '/import.php?flash=' . urlencode($msg));
}

// Filters
$statusF = $_GET['status'] ?? '';
$dateFrom= $_GET['from'] ?? '';
$dateTo  = $_GET['to']   ?? '';
$page    = max(1,sanitize_int($_GET['page']??1));
$where=['1=1']; $params=[];
if ($statusF) { $where[]='r.status=?'; $params[]=$statusF; }
if ($dateFrom){ $where[]='r.import_date>=?'; $params[]=$dateFrom; }
if ($dateTo)  { $where[]='r.import_date<=?'; $params[]=$dateTo; }
$sql='SELECT r.*,u.name AS created_by_name FROM import_receipts r LEFT JOIN users u ON u.id=r.created_by WHERE '.implode(' AND ',$where).' ORDER BY r.created_at DESC';
$paged=db_paginate($sql,$params,$page,ADMIN_PAGE_SIZE);

$editId    = sanitize_int($_GET['edit']??0);
$viewId    = sanitize_int($_GET['view']??0);
$editReceipt = $editId ? db_row('SELECT * FROM import_receipts WHERE id=? AND status=?',[$editId,'pending']) : null;
$viewReceipt = $viewId ? db_row('SELECT * FROM import_receipts WHERE id=?',[$viewId]) : null;
if ($viewReceipt) $viewReceipt['items'] = db_query('SELECT ii.*,p.name AS pname,p.emoji FROM import_items ii JOIN products p ON p.id=ii.product_id WHERE ii.receipt_id=?',[$viewId]);
$editItems = $editId ? db_query('SELECT * FROM import_items WHERE receipt_id=?',[$editId]) : [];

$allProducts = db_query('SELECT id,name,emoji,stock,cost_price FROM products WHERE is_active=1 ORDER BY name');
$showModal = isset($_GET['add']) || $editReceipt;

admin_layout_start('Quản lý nhập hàng','import');
[$t,$m]=explode(':',$msg?:':',2); if ($m) echo '<div class="alert alert-'.($t==='error'?'danger':'success').' mb-3">'.h($m).'</div>';
?>
<div class="filter-bar card" style="margin-bottom:1.5rem">
  <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;align-items:center">
    <select name="status" class="form-control" style="width:180px">
      <option value="">Tất cả trạng thái</option>
      <option value="pending" <?= $statusF==='pending'?'selected':'' ?>>Chờ xử lý</option>
      <option value="completed" <?= $statusF==='completed'?'selected':'' ?>>Hoàn thành</option>
    </select>
    <input type="date" name="from" value="<?= h($dateFrom) ?>" class="form-control" style="width:160px" title="Từ ngày">
    <input type="date" name="to" value="<?= h($dateTo) ?>" class="form-control" style="width:160px" title="Đến ngày">
    <button type="submit" class="btn btn-outline">Lọc</button>
    <a href="?add=1" class="btn btn-primary" style="margin-left:auto">+ Tạo phiếu nhập</a>
  </form>
</div>

<div class="card">
  <table class="admin-table">
    <thead><tr><th>Mã phiếu</th><th>Ngày nhập</th><th>Số SP</th><th>Tổng SL</th><th>Tổng giá trị</th><th>Trạng thái</th><th>Thao tác</th></tr></thead>
    <tbody>
      <?php foreach ($paged['items'] as $r):
        $rItems = db_query('SELECT * FROM import_items WHERE receipt_id=?',[$r['id']]);
        $totalQty = array_sum(array_column($rItems,'quantity'));
        $totalVal = array_sum(array_map(fn($i)=>$i['quantity']*$i['import_price'],$rItems));
        $isPending= $r['status']==='pending';
      ?>
      <tr>
        <td><code style="background:var(--bg);padding:.15rem .4rem;border-radius:4px">#<?= $r['id'] ?></code></td>
        <td><?= format_date($r['import_date']) ?></td>
        <td><?= count($rItems) ?></td>
        <td><?= $totalQty ?></td>
        <td style="font-weight:600"><?= format_currency($totalVal) ?></td>
        <td><span class="badge <?= $isPending?'badge-warning':'badge-success' ?>"><?= $isPending?'Chờ xử lý':'Hoàn thành' ?></span></td>
        <td>
          <div style="display:flex;gap:.4rem;flex-wrap:wrap">
            <a href="?view=<?= $r['id'] ?>" class="btn btn-sm btn-outline">Xem</a>
            <?php if ($isPending): ?>
              <a href="?edit=<?= $r['id'] ?>" class="btn btn-sm btn-outline">Sửa</a>
              <form method="POST" style="display:inline" onsubmit="return confirm('Hoàn thành phiếu nhập? Tồn kho sẽ được cập nhật.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="complete">
                <input type="hidden" name="receipt_id" value="<?= $r['id'] ?>">
                <input type="hidden" name="import_date" value="<?= $r['import_date'] ?>">
                <?php foreach ($rItems as $ri): ?>
                  <input type="hidden" name="product_id[]" value="<?= $ri['product_id'] ?>">
                  <input type="hidden" name="quantity[]" value="<?= $ri['quantity'] ?>">
                  <input type="hidden" name="import_price[]" value="<?= $ri['import_price'] ?>">
                <?php endforeach; ?>
                <button class="btn btn-sm btn-primary">✅ Hoàn thành</button>
              </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?= admin_pagination($paged, ADMIN_URL.'/import.php', array_filter(['status'=>$statusF,'from'=>$dateFrom,'to'=>$dateTo])) ?>
</div>

<!-- View Modal -->
<?php if ($viewReceipt): ?>
<div class="modal-overlay active" onclick="if(event.target===this)location.href='<?= ADMIN_URL ?>/import.php'">
  <div class="modal" style="max-width:620px">
    <div class="modal-header">
      <h3>Phiếu nhập #<?= $viewReceipt['id'] ?></h3>
      <a href="<?= ADMIN_URL ?>/import.php" class="modal-close">✕</a>
    </div>
    <div class="modal-body">
        <?php if (str_starts_with($msg, 'error:')): ?><div class="alert alert-danger mb-3"><?= h(substr($msg,6)) ?></div><?php endif; ?>
        
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1rem;font-size:.87rem">
        <div><span style="color:var(--muted)">Ngày nhập:</span> <strong><?= format_date($viewReceipt['import_date']) ?></strong></div>
        <div><span style="color:var(--muted)">Trạng thái:</span>
          <span class="badge <?= $viewReceipt['status']==='pending'?'badge-warning':'badge-success' ?>"><?= $viewReceipt['status']==='pending'?'Chờ xử lý':'Hoàn thành' ?></span>
        </div>
        <?php if ($viewReceipt['note']): ?>
        <div style="grid-column:1/-1"><span style="color:var(--muted)">Ghi chú:</span> <?= h($viewReceipt['note']) ?></div>
        <?php endif; ?>
      </div>
      <table class="admin-table">
        <thead><tr><th>Sản phẩm</th><th>SL</th><th>Giá nhập</th><th>Thành tiền</th></tr></thead>
        <tbody>
          <?php foreach ($viewReceipt['items'] as $it): ?>
          <tr>
            <td><?= h($it['emoji'].' '.$it['pname']) ?></td>
            <td><?= $it['quantity'] ?></td>
            <td><?= format_currency($it['import_price']) ?></td>
            <td style="font-weight:600"><?= format_currency($it['quantity']*$it['import_price']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot><tr style="background:var(--bg)">
          <td colspan="3" style="text-align:right;font-weight:600">Tổng:</td>
          <td style="font-weight:700;color:var(--primary)"><?= format_currency(array_sum(array_map(fn($i)=>$i['quantity']*$i['import_price'],$viewReceipt['items']))) ?></td>
        </tr></tfoot>
      </table>
    </div>
    <div class="modal-footer"><a href="<?= ADMIN_URL ?>/import.php" class="btn btn-outline">Đóng</a></div>
  </div>
</div>
<?php endif; ?>

<!-- Add/Edit Modal -->
<?php if ($showModal): ?>
<div class="modal-overlay active" onclick="if(event.target===this)location.href='<?= ADMIN_URL ?>/import.php'">
  <div class="modal" style="max-width:740px">
    <div class="modal-header">
      <h3><?= $editReceipt?'Sửa phiếu nhập #'.$editReceipt['id']:'Tạo phiếu nhập hàng' ?></h3>
      <a href="<?= ADMIN_URL ?>/import.php" class="modal-close">✕</a>
    </div>
    <form method="POST" id="importForm">
      <?= csrf_field() ?>
      <input type="hidden" name="action" id="importAction" value="save">
      <input type="hidden" name="receipt_id" value="<?= $editReceipt['id']??0 ?>">
      <div class="modal-body">
        <div class="form-group"><label class="form-label">Ngày nhập *</label>
          <input type="date" name="import_date" class="form-control" style="width:200px" value="<?= h($editReceipt['import_date']??date('Y-m-d')) ?>" required></div>
        <div class="form-group"><label class="form-label">Ghi chú</label>
          <input type="text" name="note" class="form-control" value="<?= h($editReceipt['note']??'') ?>" placeholder="Ghi chú phiếu nhập…"></div>

        <div style="background:#e8f4f8;border-radius:8px;padding:.75rem 1rem;font-size:.82rem;color:#0a4d68;margin-bottom:1rem">
          ℹ️ Khi hoàn thành, giá vốn sẽ tính theo <strong>bình quân gia quyền</strong>:
          <code>(Tồn cũ × Giá cũ + SL nhập × Giá nhập) / (Tồn cũ + SL nhập)</code>
        </div>

        <div style="display:grid;grid-template-columns:1fr 100px 140px 36px;gap:.5rem;font-size:.75rem;font-weight:600;color:var(--gray);text-transform:uppercase;letter-spacing:.4px;padding:.5rem 0;border-bottom:1px solid var(--border);margin-bottom:.5rem">
          <div>Sản phẩm</div><div style="text-align:center">Số lượng</div><div style="text-align:center">Giá nhập (đ)</div><div></div>
        </div>
        <div id="importRows">
          <?php
          $prefill = $editItems ?: [['product_id'=>'','quantity'=>1,'import_price'=>'']];
          foreach ($prefill as $ri): ?>
          <div style="display:grid;grid-template-columns:1fr 100px 140px 36px;gap:.5rem;margin-bottom:.5rem" class="import-row">
            <select name="product_id[]" class="form-control">
              <option value="">-- Chọn SP --</option>
              <?php foreach ($allProducts as $ap): ?>
                <option value="<?= $ap['id'] ?>" <?= ($ri['product_id']==$ap['id'])?'selected':'' ?>><?= h($ap['emoji'].' '.$ap['name']) ?></option>
              <?php endforeach; ?>
            </select>
            <input type="number" name="quantity[]" class="form-control" value="<?= $ri['quantity'] ?>" min="1" style="text-align:center">
            <input type="number" name="import_price[]" class="form-control" value="<?= $ri['import_price']?:'' ?>" min="0" step="100">
            <button type="button" onclick="this.closest('.import-row').remove()" style="background:#fdf0ef;border:1px solid #e74c3c33;border-radius:6px;cursor:pointer;color:#c0392b;font-size:1rem">✕</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" onclick="addRow()" style="border:2px dashed var(--border);background:none;cursor:pointer;width:100%;padding:.6rem;border-radius:var(--radius-sm);font-size:.85rem;color:var(--caramel);font-family:var(--font-body);margin-top:.4rem;transition:all .2s">+ Thêm sản phẩm</button>
      </div>
      <div class="modal-footer">
        <a href="<?= ADMIN_URL ?>/import.php" class="btn btn-outline">Huỷ</a>
        <button type="button" onclick="submitForm('save')" class="btn btn-secondary">💾 Lưu nháp</button>
        <button type="button" onclick="if(confirm('Hoàn thành phiếu? Tồn kho sẽ được cập nhật.'))submitForm('complete')" class="btn btn-primary">✅ Hoàn thành</button>
      </div>
    </form>
  </div>
</div>
<script>
const productOpts = `<?php echo implode('', array_map(fn($p)=>'<option value="'.$p['id'].'">'.$p['emoji'].' '.htmlspecialchars($p['name']).'</option>', $allProducts)); ?>`;
function addRow(){
  const row=document.createElement('div');
  row.className='import-row';
  row.style.cssText='display:grid;grid-template-columns:1fr 100px 140px 36px;gap:.5rem;margin-bottom:.5rem';
  row.innerHTML=`<select name="product_id[]" class="form-control"><option value="">-- Chọn SP --</option>${productOpts}</select>
    <input type="number" name="quantity[]" class="form-control" value="1" min="1" style="text-align:center">
    <input type="number" name="import_price[]" class="form-control" min="0" step="100">
    <button type="button" onclick="this.closest('.import-row').remove()" style="background:#fdf0ef;border:1px solid #e74c3c33;border-radius:6px;cursor:pointer;color:#c0392b;font-size:1rem">✕</button>`;
  document.getElementById('importRows').appendChild(row);
}
function submitForm(action){document.getElementById('importAction').value=action;document.getElementById('importForm').submit();}
</script>
<?php endif;
admin_layout_end(); ?>
