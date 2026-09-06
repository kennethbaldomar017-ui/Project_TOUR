<?php
require_once 'config.php';
$currentUser = require_login($conn);
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$where = ['stock > 0'];
$params = [];
$types = '';
if ($search !== '') {
    $where[] = '(name LIKE ? OR brand LIKE ? OR model LIKE ? OR category LIKE ?)';
    $like = '%' . $search . '%';
    $params = [$like, $like, $like, $like];
    $types = 'ssss';
}
if (in_array($category, PART_CATEGORIES, true)) {
    $where[] = 'category = ?';
    $params[] = $category;
    $types .= 's';
}
$sql = 'SELECT id, sku, name, category, brand, model, stock, sale_price, socket, form_factor, wattage FROM tech_parts WHERE ' . implode(' AND ', $where) . ' ORDER BY category, name';
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$parts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$cart = $_SESSION['cart'] ?? [];
$cartCount = array_sum(array_map('intval', $cart));
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Shop | PRIME TechBuild</title><link rel="stylesheet" href="css/style.css"></head>
<body>
<?php include 'header.php'; ?>
<?php if (isset($_SESSION['form_error'])): ?><div class="form-errors" id="alertBox"><?= e($_SESSION['form_error']); unset($_SESSION['form_error']); ?></div><?php endif; ?>
<?php if (isset($_SESSION['success'])): ?><div class="form-success" id="alertBox"><?= e($_SESSION['success']); unset($_SESSION['success']); ?></div><?php endif; ?>
<main class="container shop-page">
    <section class="shop-hero"><div><p class="page-eyebrow">PRIME TECH SHOP</p><h1>Build your next computer.</h1><p>Choose available parts, add them to your cart, and send your custom build for a quote.</p></div><a class="btn btn-primary" href="cart.php">Cart <span class="cart-count"><?= (int)$cartCount; ?></span></a></section>
    <form class="shop-filters" method="get"><input type="search" name="search" value="<?= e($search); ?>" placeholder="Search parts, brands, or categories"><select name="category"><option value="">All categories</option><?php foreach (PART_CATEGORIES as $item): ?><option value="<?= e($item); ?>" <?= $category === $item ? 'selected' : ''; ?>><?= e($item); ?></option><?php endforeach; ?></select><button class="btn btn-primary" type="submit">Find parts</button></form>
    <?php if ($parts): ?><section class="product-grid" aria-label="Available computer parts"><?php foreach ($parts as $part): ?><article class="product-card"><div class="product-category"><?= e($part['category']); ?></div><h2><?= e($part['name']); ?></h2><p class="product-meta"><?= e(trim(($part['brand'] ?? '') . ' ' . ($part['model'] ?? '')) ?: 'PRIME component'); ?></p><div class="product-specs"><?php if ($part['socket']): ?><span><?= e($part['socket']); ?></span><?php endif; ?><?php if ($part['form_factor']): ?><span><?= e($part['form_factor']); ?></span><?php endif; ?><?php if ($part['wattage']): ?><span><?= (int)$part['wattage']; ?>W</span><?php endif; ?></div><div class="product-buy"><strong><?= e(peso((float)$part['sale_price'])); ?></strong><small><?= (int)$part['stock']; ?> available</small></div><form method="post" action="cart_action.php" class="product-cart-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>"><input type="hidden" name="action" value="add"><input type="hidden" name="part_id" value="<?= (int)$part['id']; ?>"><input type="number" name="quantity" min="1" max="<?= (int)$part['stock']; ?>" value="1"><button class="btn btn-primary" type="submit">Add to cart</button></form></article><?php endforeach; ?></section><?php else: ?><div class="empty-state shop-empty"><strong>No parts found</strong><span>Try another search or category.</span></div><?php endif; ?>
</main>
<?php include 'footer.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.product-cart-form').forEach(function (cartForm) {
        const buyForm = cartForm.cloneNode(true);
        buyForm.classList.add('product-buy-form');
        buyForm.querySelector('input[name="action"]').value = 'buy_now';
        buyForm.querySelector('button').textContent = 'Buy now';
        cartForm.parentElement.appendChild(buyForm);
    });
});
</script>
</body></html>
