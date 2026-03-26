<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';


function cart_get(): array {
    session_init();
    return $_SESSION[SESSION_CART] ?? [];
}

function cart_save(array $cart): void {
    session_init();
    $_SESSION[SESSION_CART] = $cart;
}

function cart_count(): int {
    return array_sum(array_column(cart_get(), 'qty'));
}

function cart_total(): float {
    $total = 0;
    foreach (cart_get() as $item) $total += $item['price'] * $item['qty'];
    return $total;
}

// Thêm sản phẩm vào giỏ - trả về 'ok'|'out'|'limited'|'notfound'
function cart_add(int $productId, int $qty = 1): string {
    $product = db_row('SELECT id, name, emoji, cost_price, profit_rate, stock, is_active FROM products WHERE id=?', [$productId]);
    if (!$product || !$product['is_active']) return 'notfound';

    $sellPrice = calc_sell_price($product['cost_price'], $product['profit_rate']);
    $cart = cart_get();
    $currentQty = 0;
    foreach ($cart as &$item) {
        if ($item['product_id'] == $productId) { $currentQty = $item['qty']; break; }
    }
    unset($item);

    $available = $product['stock'] - $currentQty;
    if ($available <= 0) return 'out';
    $addQty = min($qty, $available);

    $found = false;
    foreach ($cart as &$item) {
        if ($item['product_id'] == $productId) {
            $item['qty'] += $addQty;
            $found = true;
            break;
        }
    }
    unset($item);
    if (!$found) {
        $cart[] = [
            'product_id' => $productId,
            'name'       => $product['name'],
            'emoji'      => $product['emoji'],
            'price'      => $sellPrice,
            'qty'        => $addQty,
        ];
    }
    cart_save($cart);
    return $addQty === $qty ? 'ok' : 'limited';
}

// Cập nhật số lượng
function cart_update(int $productId, int $qty): void {
    $cart = cart_get();
    if ($qty <= 0) {
        $cart = array_values(array_filter($cart, fn($i) => $i['product_id'] != $productId));
    } else {
        $product = db_row('SELECT stock FROM products WHERE id=?', [$productId]);
        $maxQty  = $product ? $product['stock'] : $qty;
        foreach ($cart as &$item) {
            if ($item['product_id'] == $productId) {
                $item['qty'] = min($qty, $maxQty);
                break;
            }
        }
        unset($item);
    }
    cart_save($cart);
}

function cart_remove(int $productId): void {
    cart_save(array_values(array_filter(cart_get(), fn($i) => $i['product_id'] != $productId)));
}

function cart_clear(): void { cart_save([]); }

// Validate stock trước khi đặt hàng — trả về [] nếu OK, hoặc danh sách lỗi
function cart_validate_stock(): array {
    $errors = [];
    foreach (cart_get() as $item) {
        $p = db_row('SELECT stock, name FROM products WHERE id=?', [$item['product_id']]);
        if (!$p || $p['stock'] < $item['qty']) {
            $errors[] = "Sản phẩm \"{$item['name']}\" chỉ còn " . ($p ? $p['stock'] : 0) . " cái.";
        }
    }
    return $errors;
}
