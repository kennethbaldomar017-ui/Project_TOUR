<?php
require_once 'config.php';
$currentUser = require_admin($conn);
$token = csrf_token();

$partsResult = $conn->query("SELECT id, sku, name, category, stock, sale_price, socket, form_factor, wattage FROM tech_parts WHERE stock > 0 ORDER BY category, name");
$parts = $partsResult ? $partsResult->fetch_all(MYSQLI_ASSOC) : [];

$result = $conn->query("SELECT b.*,
        COALESCE(SUM(bi.quantity * bi.sale_price), 0) AS build_total,
        COALESCE(SUM(bi.quantity), 0) AS item_count
    FROM pc_builds b
    LEFT JOIN pc_build_items bi ON bi.build_id = b.id
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT 50");
$builds = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

$itemsByBuild = [];
$itemsResult = $conn->query("SELECT bi.build_id, bi.quantity, bi.sale_price, tp.name, tp.sku, tp.category
    FROM pc_build_items bi
    INNER JOIN tech_parts tp ON tp.id = bi.part_id
    ORDER BY tp.category, tp.name");
if ($itemsResult) {
    while ($item = $itemsResult->fetch_assoc()) {
        $itemsByBuild[(int)$item['build_id']][] = $item;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PC Builds | PRIME TechBuild</title>
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
                    <h2>PC Build Planner</h2>
                    <p>Quote, reserve, or sell computer builds from available stock.</p>
                </div>
            </div>

            <form class="tech-form-grid" action="build_action.php" method="post">
                <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                <label>Build Name<span class="req"> *</span><input type="text" name="build_name" placeholder="Gaming Ryzen Build" required></label>
                <label>Customer<input type="text" name="customer_name" placeholder="Walk-in / client name"></label>
                <label>Status
                    <select name="status">
                        <option value="quoted">Quote only</option>
                        <option value="reserved">Reserve stock</option>
                        <option value="sold">Sold</option>
                    </select>
                </label>
                <label>Budget<input type="number" name="budget" min="0" step="0.01" value="0.00"></label>
                <label class="span-2">Notes<textarea name="notes" rows="2" placeholder="Use case, target FPS, compatibility notes, warranty terms"></textarea></label>

                <div class="span-2 build-lines">
                    <?php for ($i = 0; $i < 8; $i++): ?>
                        <div class="build-line">
                            <select name="part_id[]" aria-label="Build part <?= $i + 1; ?>">
                                <option value="">Choose part</option>
                                <?php foreach ($parts as $part): ?>
                                    <option value="<?= (int)$part['id']; ?>">
                                        <?= e($part['category'] . ' - ' . $part['name'] . ' [' . $part['stock'] . ' left] ' . peso((float)$part['sale_price'])); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="quantity[]" min="0" placeholder="Qty">
                        </div>
                    <?php endfor; ?>
                </div>
                <button class="btn btn-primary span-2" type="submit" <?= !$parts ? 'disabled' : ''; ?>>Create Build</button>
            </form>

            <div class="build-list">
                <?php foreach ($builds as $build): ?>
                    <article class="build-card">
                        <div class="build-card-head">
                            <div>
                                <h3><?= e($build['build_name']); ?></h3>
                                <p><?= e($build['customer_name'] ?: 'No customer'); ?> · <?= (int)$build['item_count']; ?> parts</p>
                            </div>
                            <span class="badge status-<?= $build['status'] === 'cancelled' ? 'deactivated' : ($build['status'] === 'quoted' ? 'pending' : 'active'); ?>">
                                <?= e(build_status_label($build['status'])); ?>
                            </span>
                        </div>
                        <div class="build-meta">
                            <span>Total: <strong><?= e(peso((float)$build['build_total'])); ?></strong></span>
                            <span>Budget: <?= e(peso((float)$build['budget'])); ?></span>
                            <span><?= e(date('M d, Y', strtotime($build['created_at']))); ?></span>
                        </div>
                        <?php if (!empty($itemsByBuild[(int)$build['id']])): ?>
                            <ul class="build-items">
                                <?php foreach ($itemsByBuild[(int)$build['id']] as $item): ?>
                                    <li>
                                        <span><?= e($item['category'] . ' - ' . $item['name']); ?></span>
                                        <strong><?= (int)$item['quantity']; ?> x <?= e(peso((float)$item['sale_price'])); ?></strong>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                        <?php if ($build['notes']): ?>
                            <p class="admin-note"><?= e($build['notes']); ?></p>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
                <?php if (!$builds): ?>
                    <div class="empty-state"><strong>No builds yet</strong><span>Create a PC build after adding parts to inventory.</span></div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>
