<?php
require_once 'config.php';
$actor = require_login($conn);
verify_csrf();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit('Invalid request method'); }
$action = $_POST['action'] ?? '';
if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
try {
    $partId = max(0, (int)($_POST['part_id'] ?? 0));
    if (in_array($action, ['add', 'update'], true)) {
        if ($partId < 1) throw new RuntimeException('Choose a valid part.');
        $stmt = $conn->prepare('SELECT stock FROM tech_parts WHERE id = ? LIMIT 1'); $stmt->bind_param('i', $partId); $stmt->execute(); $part = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$part) throw new RuntimeException('Part not found.');
        $quantity = max(0, (int)($_POST['quantity'] ?? 1));
        if ($quantity > (int)$part['stock']) throw new RuntimeException('That quantity is not available.');
        if ($quantity === 0) unset($_SESSION['cart'][$partId]); else $_SESSION['cart'][$partId] = $action === 'add' ? min((int)$part['stock'], (int)($_SESSION['cart'][$partId] ?? 0) + $quantity) : $quantity;
        $_SESSION['success'] = $action === 'add' ? 'Part added to your cart.' : 'Cart updated.';
        header('Location: ' . ($action === 'add' ? 'shop.php' : 'cart.php')); exit;
    }
    if ($action === 'buy_now') {
        if ($partId < 1) throw new RuntimeException('Choose a valid part.');
        $quantity = max(1, (int)($_POST['quantity'] ?? 1));
        $conn->begin_transaction();
        $stmt = $conn->prepare('SELECT id, name, stock, sale_price FROM tech_parts WHERE id = ? FOR UPDATE');
        $stmt->bind_param('i', $partId); $stmt->execute(); $part = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$part || (int)$part['stock'] < $quantity) throw new RuntimeException('That quantity is no longer available.');
        $total = $quantity * (float)$part['sale_price']; $status = 'processing'; $userId = (int)$actor['id'];
        $order = $conn->prepare('INSERT INTO customer_orders (user_id, status, total) VALUES (?, ?, ?)');
        $order->bind_param('isd', $userId, $status, $total); $order->execute(); $orderId = $order->insert_id; $order->close();
        $item = $conn->prepare('INSERT INTO customer_order_items (order_id, part_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
        $item->bind_param('iiid', $orderId, $partId, $quantity, $part['sale_price']); $item->execute(); $item->close();
        $stock = $conn->prepare('UPDATE tech_parts SET stock = stock - ? WHERE id = ?'); $stock->bind_param('ii', $quantity, $partId); $stock->execute(); $stock->close();
        $conn->commit(); $_SESSION['success'] = 'Purchase placed for ' . $part['name'] . '.'; header('Location: shop.php'); exit;
    }
    if ($action === 'checkout') {
        $cart = $_SESSION['cart']; if (!$cart) throw new RuntimeException('Your cart is empty.');
        $buildName = trim($_POST['build_name'] ?? ''); $notes = trim($_POST['notes'] ?? ''); if ($buildName === '') throw new RuntimeException('Enter a build name.');
        $conn->begin_transaction(); $items = [];
        $select = $conn->prepare('SELECT id, name, stock, sale_price FROM tech_parts WHERE id = ? FOR UPDATE');
        foreach ($cart as $id => $qty) { $id = (int)$id; $qty = (int)$qty; if ($qty < 1) continue; $select->bind_param('i', $id); $select->execute(); $part = $select->get_result()->fetch_assoc(); if (!$part || (int)$part['stock'] < $qty) throw new RuntimeException(($part['name'] ?? 'A selected part') . ' is no longer available in that quantity.'); $items[] = [$id, $qty, (float)$part['sale_price']]; }
        $select->close(); if (!$items) throw new RuntimeException('Your cart is empty.');
        $status = 'quoted'; $customer = trim(($actor['first_name'] ?? '') . ' ' . ($actor['last_name'] ?? '')) ?: $actor['username']; $createdBy = (int)$actor['id'];
        $stmt = $conn->prepare('INSERT INTO pc_builds (build_name, customer_name, status, budget, notes, created_by) VALUES (?, ?, ?, 0, ?, ?)'); $stmt->bind_param('ssssi', $buildName, $customer, $status, $notes, $createdBy); $stmt->execute(); $buildId = $stmt->insert_id; $stmt->close();
        $insert = $conn->prepare('INSERT INTO pc_build_items (build_id, part_id, quantity, sale_price) VALUES (?, ?, ?, ?)'); foreach ($items as [$id, $qty, $price]) { $insert->bind_param('iiid', $buildId, $id, $qty, $price); $insert->execute(); } $insert->close(); $conn->commit(); $_SESSION['cart'] = []; $_SESSION['success'] = 'Your build quote request was submitted.'; header('Location: cart.php'); exit;
    }
    throw new RuntimeException('Unsupported cart action.');
} catch (Throwable $e) { @$conn->rollback(); $_SESSION['form_error'] = $e->getMessage(); header('Location: cart.php'); exit; }
