<?php
require_once 'config.php';
$currentUser = require_admin($conn);
$token = csrf_token();

$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$stockFilter = $_GET['stock'] ?? '';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(sku LIKE ? OR name LIKE ? OR brand LIKE ? OR model LIKE ? OR supplier LIKE ?)';
    $like = '%' . $search . '%';
    for ($i = 0; $i < 5; $i++) {
        $params[] = $like;
        $types .= 's';
    }
}
if (in_array($categoryFilter, PART_CATEGORIES, true)) {
    $where[] = 'category = ?';
    $params[] = $categoryFilter;
    $types .= 's';
}
if ($stockFilter === 'low') {
    $where[] = 'stock <= reorder_level';
} elseif ($stockFilter === 'available') {
    $where[] = 'stock > 0';
}

$sql = 'SELECT * FROM tech_parts';
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY category, brand, name';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$parts = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$lowStockCount = 0;
$availableCount = 0;
$inventoryValue = 0.0;
$categoryCount = count(array_unique(array_filter(array_column($parts, 'category'))));
foreach ($parts as $part) {
    if ((int)$part['stock'] <= (int)$part['reorder_level']) {
        $lowStockCount++;
    }
    if ((int)$part['stock'] > 0) {
        $availableCount++;
    }
    $inventoryValue += (int)$part['stock'] * (float)$part['unit_cost'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory | PRIME TechBuild</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <?php if(isset($_SESSION['form_error'])): ?>
        <div class="form-errors" id="alertBox"><?= e($_SESSION['form_error']); unset($_SESSION['form_error']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['success'])): ?>
        <div class="form-success" id="alertBox"><?= e($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <main class="container admin-page tech-page inventory-page">
        <section class="admin-panel tech-panel">
            <div class="admin-heading">
                <div>
                    <p class="page-eyebrow">STOCK CONTROL</p>
                    <h2>Computer Parts Inventory</h2>
                    <p>Track stock, cost, selling price, supplier, and build compatibility notes.</p>
                </div>
                <button class="btn btn-primary tech-heading-action" type="button" id="openPartModal"><span aria-hidden="true">+</span> Add Part</button>
            </div>

            <div class="tech-kpis" aria-label="Inventory summary">
                <div><span>Total parts</span><strong><?= number_format(count($parts)); ?></strong><small><?= $categoryCount; ?> categories</small></div>
                <div><span>Available</span><strong><?= number_format($availableCount); ?></strong><small>with stock on hand</small></div>
                <div class="is-warning"><span>Low stock</span><strong><?= number_format($lowStockCount); ?></strong><small>at or below reorder level</small></div>
                <div><span>Cost value</span><strong><?= e(peso($inventoryValue)); ?></strong><small>current stock at unit cost</small></div>
            </div>

            <div class="inventory-modal-backdrop" id="partModal" hidden>
                <div class="inventory-modal" role="dialog" aria-modal="true" aria-labelledby="partModalTitle">
                    <div class="modal-header"><h3 id="partModalTitle">Add computer part</h3><button class="modal-close" type="button" id="closePartModal" aria-label="Close">&times;</button></div>
                    <form class="tech-form-grid" action="inventory_action.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                <input type="hidden" name="action" value="create_part">
                <label>SKU<span class="req"> *</span><input type="text" name="sku" placeholder="CPU-R5-5600" required></label>
                <label>Part Name<span class="req"> *</span><input type="text" name="name" placeholder="Ryzen 5 5600" required></label>
                <label>Category<span class="req"> *</span>
                    <select name="category" required>
                        <?php foreach (PART_CATEGORIES as $category): ?>
                            <option value="<?= e($category); ?>"><?= e($category); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Brand<input type="text" name="brand" placeholder="AMD"></label>
                <label>Model<input type="text" name="model" placeholder="100-100000927BOX"></label>
                <label>Socket<input type="text" name="socket" placeholder="AM4, LGA1700"></label>
                <label>Form Factor<input type="text" name="form_factor" placeholder="ATX, mATX, M.2"></label>
                <label>Wattage<input type="number" name="wattage" min="0" placeholder="65"></label>
                <label>Initial Stock<input type="number" name="stock" min="0" value="0"></label>
                <label>Reorder Level<input type="number" name="reorder_level" min="0" value="3"></label>
                <label>Unit Cost<input type="number" name="unit_cost" min="0" step="0.01" value="0.00"></label>
                <label>Sale Price<input type="number" name="sale_price" min="0" step="0.01" value="0.00"></label>
                <label>Supplier<input type="text" name="supplier" placeholder="Main supplier"></label>
                <label class="span-2">Notes<textarea name="notes" rows="2" placeholder="Compatibility, warranty, or bundle notes"></textarea></label>
                        <button class="btn btn-primary span-2" type="submit">Add Part</button>
                    </form>
                </div>
            </div>

            <form class="filter-bar tech-filter" method="get">
                <span class="filter-label">Find a part</span>
                <input type="search" name="search" value="<?= e($search); ?>" placeholder="Search SKU, part, brand, model, supplier">
                <select name="category">
                    <option value="">All categories</option>
                    <?php foreach (PART_CATEGORIES as $category): ?>
                        <option value="<?= e($category); ?>" <?= $categoryFilter === $category ? 'selected' : ''; ?>><?= e($category); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="stock">
                    <option value="">All stock</option>
                    <option value="available" <?= $stockFilter === 'available' ? 'selected' : ''; ?>>Available</option>
                    <option value="low" <?= $stockFilter === 'low' ? 'selected' : ''; ?>>Low stock</option>
                </select>
                <button class="btn" type="submit">Filter</button>
            </form>

            <div class="table-wrap">
                <table class="admin-table inventory-table">
                    <thead>
                        <tr>
                            <th>Part</th>
                            <th>Specs</th>
                            <th>Stock</th>
                            <th>Cost</th>
                            <th>Sale</th>
                            <th>Adjust</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($parts as $part): ?>
                        <tr>
                            <td>
                                <strong><?= e($part['name']); ?></strong>
                                <span><?= e($part['sku']); ?> · <?= e($part['category']); ?> · <?= e($part['brand']); ?></span>
                            </td>
                            <td>
                                <span><?= e($part['model'] ?: 'No model'); ?> · <?= e($part['socket'] ?: 'Any socket'); ?> · <?= e($part['form_factor'] ?: 'Any size'); ?></span>
                                <span><?= (int)$part['wattage']; ?>W · Supplier: <?= e($part['supplier'] ?: 'Not set'); ?></span>
                            </td>
                            <td>
                                <span class="badge <?= (int)$part['stock'] <= (int)$part['reorder_level'] ? 'status-pending' : 'status-active'; ?>">
                                    <?= (int)$part['stock']; ?> on hand
                                </span>
                            </td>
                            <td><?= e(peso((float)$part['unit_cost'])); ?></td>
                            <td><?= e(peso((float)$part['sale_price'])); ?></td>
                            <td>
                                <form class="inline-stock-form" action="inventory_action.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                    <input type="hidden" name="action" value="adjust_stock">
                                    <input type="hidden" name="part_id" value="<?= (int)$part['id']; ?>">
                                    <input type="number" name="adjustment" placeholder="+/- qty" required>
                                    <button class="btn" type="submit">Update</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$parts): ?>
                        <tr><td class="empty-state" colspan="6"><strong>No parts found</strong><span>Add your first computer part above.</span></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modal = document.getElementById('partModal');
            const open = document.getElementById('openPartModal');
            const close = document.getElementById('closePartModal');
            function hide() { modal.hidden = true; }
            open.addEventListener('click', function () { modal.hidden = false; });
            close.addEventListener('click', hide);
            modal.addEventListener('click', function (event) { if (event.target === modal) hide(); });
            document.addEventListener('keydown', function (event) { if (event.key === 'Escape') hide(); });
        });
    </script>
</body>
</html>
