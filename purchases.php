<?php
require_once 'config.php';
$currentUser = require_admin($conn);
$token = csrf_token();

$partsResult = $conn->query("SELECT id, sku, name, stock, unit_cost FROM tech_parts ORDER BY category, name");
$parts = $partsResult ? $partsResult->fetch_all(MYSQLI_ASSOC) : [];

$result = $conn->query("SELECT p.*, tp.sku, tp.name, tp.category
    FROM tech_purchases p
    INNER JOIN tech_parts tp ON tp.id = p.part_id
    ORDER BY p.purchased_at DESC, p.id DESC
    LIMIT 100");
$purchases = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$totalSpend = 0.0;
foreach ($purchases as $purchase) {
    $totalSpend += (float)$purchase['total_cost'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buying | PRIME TechBuild</title>
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

    <main class="container admin-page">
        <section class="admin-panel tech-panel">
            <div class="admin-heading">
                <div>
                    <h2>Buying And Purchase Intake</h2>
                    <p>Record incoming computer parts and automatically update stock.</p>
                </div>
                <span class="dashboard-role"><?= e(peso($totalSpend)); ?> recent spend</span>
            </div>

            <form class="tech-form-grid" action="purchase_action.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                <label class="span-2">Purchased Part<span class="req"> *</span>
                    <select name="part_id" required>
                        <option value="">Choose a part</option>
                        <?php foreach ($parts as $part): ?>
                            <option value="<?= (int)$part['id']; ?>">
                                <?= e($part['sku'] . ' - ' . $part['name'] . ' (' . $part['stock'] . ' in stock)'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Quantity<span class="req"> *</span><input type="number" name="quantity" min="1" required></label>
                <label>Unit Cost<input type="number" name="unit_cost" min="0" step="0.01" value="0.00"></label>
                <label>Supplier<input type="text" name="supplier" placeholder="Supplier or shop"></label>
                <label>Reference No.<input type="text" name="reference_no" placeholder="Invoice / receipt"></label>
                <label>Purchased At<input type="datetime-local" name="purchased_at" value="<?= e(date('Y-m-d\TH:i')); ?>"></label>
                <button class="btn btn-primary span-2" type="submit" <?= !$parts ? 'disabled' : ''; ?>>Record Purchase</button>
            </form>

            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Part</th>
                            <th>Qty</th>
                            <th>Unit Cost</th>
                            <th>Total</th>
                            <th>Supplier</th>
                            <th>Ref</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($purchases as $purchase): ?>
                        <tr>
                            <td><?= e(date('M d, Y H:i', strtotime($purchase['purchased_at']))); ?></td>
                            <td><strong><?= e($purchase['name']); ?></strong><span><?= e($purchase['sku'] . ' · ' . $purchase['category']); ?></span></td>
                            <td><?= (int)$purchase['quantity']; ?></td>
                            <td><?= e(peso((float)$purchase['unit_cost'])); ?></td>
                            <td><?= e(peso((float)$purchase['total_cost'])); ?></td>
                            <td><?= e($purchase['supplier'] ?: 'Not set'); ?></td>
                            <td><?= e($purchase['reference_no'] ?: 'None'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$purchases): ?>
                        <tr><td class="empty-state" colspan="7"><strong>No purchases yet</strong><span>Add inventory parts first, then record incoming stock here.</span></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>
