<?php
require_once 'config.php';

$actor = require_admin($conn);
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method');
}

try {
    $partId = (int)($_POST['part_id'] ?? 0);
    $quantity = max(0, (int)($_POST['quantity'] ?? 0));
    $unitCost = max(0, (float)($_POST['unit_cost'] ?? 0));
    $supplier = trim($_POST['supplier'] ?? '');
    $referenceNo = trim($_POST['reference_no'] ?? '');
    $purchasedAt = trim($_POST['purchased_at'] ?? date('Y-m-d\TH:i'));

    if ($partId <= 0 || $quantity <= 0) {
        throw new RuntimeException('Choose a part and enter a purchase quantity.');
    }

    $totalCost = $quantity * $unitCost;
    $purchaseDate = date('Y-m-d H:i:s', strtotime($purchasedAt) ?: time());
    $actorId = (int)$actor['id'];

    $conn->begin_transaction();

    $stmt = $conn->prepare("INSERT INTO tech_purchases
        (part_id, quantity, unit_cost, total_cost, supplier, reference_no, purchased_at, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iiddsssi', $partId, $quantity, $unitCost, $totalCost, $supplier, $referenceNo, $purchaseDate, $actorId);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare('UPDATE tech_parts SET stock = stock + ?, unit_cost = ?, supplier = COALESCE(NULLIF(?, \'\'), supplier) WHERE id = ?');
    $stmt->bind_param('idsi', $quantity, $unitCost, $supplier, $partId);
    $stmt->execute();
    $stmt->close();

    $conn->commit();
    $_SESSION['success'] = 'Purchase recorded and stock increased.';
} catch (Throwable $e) {
    @$conn->rollback();
    $_SESSION['form_error'] = $e->getMessage();
}

header('Location: purchases.php');
exit;
?>
