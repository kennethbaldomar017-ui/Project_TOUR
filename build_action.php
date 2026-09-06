<?php
require_once 'config.php';

$actor = require_admin($conn);
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method');
}

try {
    $buildName = trim($_POST['build_name'] ?? '');
    $customerName = trim($_POST['customer_name'] ?? '');
    $status = $_POST['status'] ?? 'quoted';
    $budget = max(0, (float)($_POST['budget'] ?? 0));
    $notes = trim($_POST['notes'] ?? '');
    $partIds = $_POST['part_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];

    if ($buildName === '' || !in_array($status, ['quoted', 'reserved', 'sold'], true)) {
        throw new RuntimeException('Build name and valid status are required.');
    }

    $items = [];
    foreach ($partIds as $index => $partIdRaw) {
        $partId = (int)$partIdRaw;
        $qty = max(0, (int)($quantities[$index] ?? 0));
        if ($partId > 0 && $qty > 0) {
            $items[$partId] = ($items[$partId] ?? 0) + $qty;
        }
    }

    if (!$items) {
        throw new RuntimeException('Add at least one computer part to the build.');
    }

    $conn->begin_transaction();

    $actorId = (int)$actor['id'];
    $stmt = $conn->prepare("INSERT INTO pc_builds (build_name, customer_name, status, budget, notes, created_by) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssdsi', $buildName, $customerName, $status, $budget, $notes, $actorId);
    $stmt->execute();
    $buildId = $stmt->insert_id;
    $stmt->close();

    $select = $conn->prepare('SELECT id, name, stock, sale_price FROM tech_parts WHERE id = ? FOR UPDATE');
    $insert = $conn->prepare('INSERT INTO pc_build_items (build_id, part_id, quantity, sale_price) VALUES (?, ?, ?, ?)');
    $update = $conn->prepare('UPDATE tech_parts SET stock = stock - ? WHERE id = ?');

    foreach ($items as $partId => $qty) {
        $select->bind_param('i', $partId);
        $select->execute();
        $part = $select->get_result()->fetch_assoc();
        if (!$part) {
            throw new RuntimeException('One selected part no longer exists.');
        }
        if ((int)$part['stock'] < $qty) {
            throw new RuntimeException($part['name'] . ' does not have enough stock for this build.');
        }

        $salePrice = (float)$part['sale_price'];
        $insert->bind_param('iiid', $buildId, $partId, $qty, $salePrice);
        $insert->execute();

        if (in_array($status, ['reserved', 'sold'], true)) {
            $update->bind_param('ii', $qty, $partId);
            $update->execute();
        }
    }

    $select->close();
    $insert->close();
    $update->close();

    $conn->commit();
    $_SESSION['success'] = 'PC build created and inventory updated.';
} catch (Throwable $e) {
    @$conn->rollback();
    $_SESSION['form_error'] = $e->getMessage();
}

header('Location: builds.php');
exit;
?>
