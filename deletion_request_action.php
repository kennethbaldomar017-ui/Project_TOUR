<?php
require_once 'config.php';
$actor = require_superadmin($conn);
verify_csrf();
require_actor_confirmation($conn, $actor);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method');
}

$requestId = max(0, (int)($_POST['request_id'] ?? 0));
$action = $_POST['action'] ?? '';
$stmt = $conn->prepare("SELECT * FROM deletion_requests WHERE id = ? AND status = 'pending' LIMIT 1");
$stmt->bind_param('i', $requestId);
$stmt->execute();
$request = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$request) {
    $_SESSION['form_error'] = 'Pending deletion request not found.';
    header('Location: deletion_requests.php');
    exit;
}

$target = [
    'id' => (int)$request['target_id'],
    'id_number' => $request['target_id_number'],
    'username' => $request['target_username'],
    'first_name' => $request['target_name'],
    'role' => $request['target_role'],
    'status' => $request['target_status'],
];

try {
    $conn->begin_transaction();
    if ($action === 'reject') {
        $reason = trim((string)($_POST['review_reason'] ?? 'Rejected by superadmin'));
        $update = $conn->prepare("UPDATE deletion_requests SET status = 'rejected', reviewed_by = ?, review_reason = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'");
        $update->bind_param('isi', $actor['id'], $reason, $requestId);
        $update->execute();
        $update->close();
        log_audit_action($conn, $actor, $target, 'delete_rejected', $request['target_status'], $request['target_status'], null, null, $reason);
        $conn->commit();
        $_SESSION['success'] = 'Deletion request rejected.';
    } elseif ($action === 'approve') {
        $deleteItems = $conn->prepare('DELETE items FROM customer_order_items items INNER JOIN customer_orders orders ON orders.id = items.order_id WHERE orders.user_id = ?');
        $deleteItems->bind_param('i', $target['id']);
        $deleteItems->execute();
        $deleteItems->close();

        $deleteOrders = $conn->prepare('DELETE FROM customer_orders WHERE user_id = ?');
        $deleteOrders->bind_param('i', $target['id']);
        $deleteOrders->execute();
        $deleteOrders->close();

        $deletePrivileges = $conn->prepare('DELETE FROM user_privileges WHERE user_id = ?');
        $deletePrivileges->bind_param('i', $target['id']);
        $deletePrivileges->execute();
        $deletePrivileges->close();

        $deleteOtp = $conn->prepare('DELETE FROM otp_tokens WHERE user_id = ?');
        $deleteOtp->bind_param('i', $target['id']);
        $deleteOtp->execute();
        $deleteOtp->close();

        $deleteUser = $conn->prepare('DELETE FROM users WHERE id = ?');
        $deleteUser->bind_param('i', $target['id']);
        $deleteUser->execute();
        $deleteUser->close();

        $update = $conn->prepare("UPDATE deletion_requests SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE id = ? AND status = 'pending'");
        $update->bind_param('ii', $actor['id'], $requestId);
        $update->execute();
        $update->close();
        log_audit_action($conn, $actor, $target, 'delete_approved', $request['target_status'], 'deleted', null, null, $request['reason']);
        $conn->commit();
        $_SESSION['success'] = 'Deletion request approved and account removed.';
    } else {
        throw new RuntimeException('Unsupported deletion request action.');
    }
} catch (Throwable $e) {
    @$conn->rollback();
    $_SESSION['form_error'] = 'Deletion request could not be processed: ' . $e->getMessage();
}
header('Location: deletion_requests.php');
exit;
