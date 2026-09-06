<?php
require_once 'config.php';
$currentUser = require_login($conn);
$cart = $_SESSION['cart'] ?? [];
$items = [];
$total = 0.0;
if ($cart) {
    $ids = array_map('intval', array_keys($cart));
    $result = $conn->query('SELECT id, sku, name, category, stock, sale_price FROM tech_parts WHERE id IN (' . implode(',', $ids) . ')');
    while ($row = $result->fetch_assoc()) {
        $quantity = min((int)($cart[$row['id']] ?? 0), (int)$row['stock']);
        if ($quantity > 0) { $row['quantity'] = $quantity; $row['line_total'] = $quantity * (float)$row['sale_price']; $total += $row['line_total']; $items[] = $row; }
    }
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Your Cart | PRIME TechBuild</title><link rel="stylesheet" href="css/style.css"></head><body>
<?php include 'header.php'; ?>
<?php if (isset($_SESSION['form_error'])): ?><div class="form-errors" id="alertBox"><?= e($_SESSION['form_error']); unset($_SESSION['form_error']); ?></div><?php endif; ?>
<?php if (isset($_SESSION['success'])): ?><div class="form-success" id="alertBox"><?= e($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<main class="container cart-page"><div class="page-title-row"><div><p class="page-eyebrow">YOUR SHOPPING CART</p><h1>Ready to build?</h1><p>Review your parts and submit them as a custom PC quote.</p></div><a class="btn btn-ghost" href="shop.php">Continue shopping</a></div>
<?php if ($items): ?><section class="cart-layout"><div class="cart-items"><?php foreach ($items as $item): ?><article class="cart-item"><div><span class="product-category"><?= e($item['category']); ?></span><h2><?= e($item['name']); ?></h2><small><?= e($item['sku']); ?> · <?= e(peso((float)$item['sale_price'])); ?> each</small></div><form method="post" action="cart_action.php" class="cart-quantity"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="update"><input type="hidden" name="part_id" value="<?= (int)$item['id']; ?>"><input type="number" name="quantity" min="0" max="<?= (int)$item['stock']; ?>" value="<?= (int)$item['quantity']; ?>"><button class="btn btn-ghost" type="submit">Update</button></form><strong class="cart-line-total"><?= e(peso($item['line_total'])); ?></strong></article><?php endforeach; ?></div><aside class="cart-summary"><p class="page-eyebrow">QUOTE SUMMARY</p><h2><?= e(peso($total)); ?></h2><p>Submit these parts for a custom build quote. Stock is confirmed by the operations team before fulfillment.</p><form method="post" action="cart_action.php"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="checkout"><label>Build name<input type="text" name="build_name" required placeholder="My custom PC"></label><label>Notes<textarea name="notes" rows="3" placeholder="Tell us about your use case or preferences"></textarea></label><button class="btn btn-primary btn-block" type="submit">Request build quote</button></form></aside></section><?php else: ?><div class="empty-state cart-empty"><strong>Your cart is empty</strong><span>Browse computer parts and start building your setup.</span><a class="btn btn-primary" href="shop.php">Browse shop</a></div><?php endif; ?></main>
<?php include 'footer.php'; ?></body></html>
