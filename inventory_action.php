<?php
require_once 'config.php';

$actor = require_admin($conn);
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method');
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'create_part') {
        $sku = strtoupper(trim($_POST['sku'] ?? ''));
        $name = trim($_POST['name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $brand = trim($_POST['brand'] ?? '');
        $model = trim($_POST['model'] ?? '');
        $socket = trim($_POST['socket'] ?? '');
        $formFactor = trim($_POST['form_factor'] ?? '');
        $wattage = max(0, (int)($_POST['wattage'] ?? 0));
        $stock = max(0, (int)($_POST['stock'] ?? 0));
        $reorderLevel = max(0, (int)($_POST['reorder_level'] ?? 0));
        $unitCost = max(0, (float)($_POST['unit_cost'] ?? 0));
        $salePrice = max(0, (float)($_POST['sale_price'] ?? 0));
        $supplier = trim($_POST['supplier'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if ($sku === '' || $name === '' || !in_array($category, PART_CATEGORIES, true)) {
            throw new RuntimeException('SKU, part name, and category are required.');
        }

        $stmt = $conn->prepare("INSERT INTO tech_parts
            (sku, name, category, brand, model, socket, form_factor, wattage, stock, reorder_level, unit_cost, sale_price, supplier, notes, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $actorId = (int)$actor['id'];
        $stmt->bind_param(
            'sssssssiiiddssi',
            $sku,
            $name,
            $category,
            $brand,
            $model,
            $socket,
            $formFactor,
            $wattage,
            $stock,
            $reorderLevel,
            $unitCost,
            $salePrice,
            $supplier,
            $notes,
            $actorId
        );
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = 'Part added to inventory.';
    } elseif ($action === 'adjust_stock') {
        $partId = (int)($_POST['part_id'] ?? 0);
        $adjustment = (int)($_POST['adjustment'] ?? 0);
        if ($partId <= 0 || $adjustment === 0) {
            throw new RuntimeException('Choose a part and enter a stock adjustment.');
        }

        $stmt = $conn->prepare('UPDATE tech_parts SET stock = GREATEST(0, stock + ?) WHERE id = ?');
        $stmt->bind_param('ii', $adjustment, $partId);
        $stmt->execute();
        $stmt->close();
        $_SESSION['success'] = 'Inventory stock updated.';
    } else {
        throw new RuntimeException('Unsupported inventory action.');
    }
} catch (Throwable $e) {
    $_SESSION['form_error'] = $e->getMessage();
}

header('Location: inventory.php');
exit;
?>
