<?php
require_once 'config.php';

$actor = require_admin($conn);
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method');
}

$action = $_POST['action'] ?? '';
$targetId = (int)($_POST['target_id'] ?? 0);
$duration = $_POST['duration'] ?? null;
$reason = trim($_POST['reason'] ?? '');
$newRole = $_POST['new_role'] ?? '';

$target = get_user_by_id($conn, $targetId);
if (!$target) {
    $_SESSION['form_error'] = 'Account not found.';
    header('Location: admin_users.php');
    exit;
}

$allowedDurations = ['1_month', '3_months', '6_months', 'manual'];

try {
    if ($action === 'activate') {
        if (!can_manage_account($actor, $target, 'activate')) {
            log_audit_action($conn, $actor, $target, 'failed_authorization', $target['status'], $target['status'], null, null, 'Activation denied');
            throw new RuntimeException('You are not allowed to activate this account.');
        }

        $conn->begin_transaction();
        $stmt = $conn->prepare("UPDATE users SET status = 'active', deactivation_duration = NULL, deactivated_until = NULL, status_reason = NULL, status_changed_at = NOW() WHERE id = ?");
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $stmt->close();
        if (!log_audit_action($conn, $actor, $target, 'activate', $target['status'], STATUS_ACTIVE, null, null, $reason ?: null)) {
            throw new RuntimeException('Could not write audit log.');
        }
        $conn->commit();
        $_SESSION['success'] = 'Account activated.';
    } elseif ($action === 'deactivate') {
        if (!in_array($duration, $allowedDurations, true)) {
            throw new RuntimeException('Choose a valid deactivation duration.');
        }
        if (!can_manage_account($actor, $target, 'deactivate')) {
            log_audit_action($conn, $actor, $target, 'failed_authorization', $target['status'], $target['status'], $duration, null, 'Deactivation denied');
            throw new RuntimeException('You are not allowed to deactivate this account.');
        }

        $expiresAt = deactivation_expiration($duration);
        $conn->begin_transaction();
        $stmt = $conn->prepare("UPDATE users SET status = 'deactivated', deactivation_duration = ?, deactivated_until = ?, status_reason = ?, status_changed_at = NOW() WHERE id = ?");
        $storedReason = $reason ?: null;
        $stmt->bind_param('sssi', $duration, $expiresAt, $storedReason, $targetId);
        $stmt->execute();
        $stmt->close();
        if (!log_audit_action($conn, $actor, $target, 'deactivate', $target['status'], STATUS_DEACTIVATED, $duration, $expiresAt, $storedReason)) {
            throw new RuntimeException('Could not write audit log.');
        }
        $conn->commit();
        $_SESSION['success'] = 'Account deactivated for ' . duration_label($duration) . '.';
    } elseif ($action === 'delete') {
        if (!can_manage_account($actor, $target, 'delete')) {
            log_audit_action($conn, $actor, $target, 'failed_authorization', $target['status'], $target['status'], null, null, 'Deletion denied');
            throw new RuntimeException('You are not allowed to delete this account.');
        }

        $conn->begin_transaction();
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param('i', $targetId);
        $stmt->execute();
        $stmt->close();
        if (!log_audit_action($conn, $actor, $target, 'delete', $target['status'], 'deleted', null, null, $reason ?: null)) {
            throw new RuntimeException('Could not write audit log.');
        }
        $conn->commit();
        $_SESSION['success'] = 'Account deleted.';
    } elseif ($action === 'role_change') {
        if ($actor['role'] !== ROLE_SUPERADMIN || !can_manage_account($actor, $target, 'role_change') || !in_array($newRole, [ROLE_USER, ROLE_ADMIN, ROLE_SUPERADMIN], true)) {
            log_audit_action($conn, $actor, $target, 'failed_authorization', $target['status'], $target['status'], null, null, 'Role change denied');
            throw new RuntimeException('You are not allowed to change this role.');
        }

        $conn->begin_transaction();
        $stmt = $conn->prepare("UPDATE users SET role = ?, status_changed_at = NOW() WHERE id = ?");
        $stmt->bind_param('si', $newRole, $targetId);
        $stmt->execute();
        $stmt->close();
        $updatedTarget = $target;
        $updatedTarget['role'] = $newRole;
        $roleReason = trim('Role changed from ' . $target['role'] . ' to ' . $newRole . '. ' . $reason);
        if (!log_audit_action($conn, $actor, $updatedTarget, 'role_change', $target['status'], $target['status'], null, null, $roleReason)) {
            throw new RuntimeException('Could not write audit log.');
        }
        $conn->commit();
        $_SESSION['success'] = 'Account role updated.';
    } else {
        throw new RuntimeException('Unsupported action.');
    }
} catch (Throwable $e) {
    if ($conn->errno === 0) {
        // no-op: transaction state is not directly exposed by mysqli
    }
    @$conn->rollback();
    $_SESSION['form_error'] = $e->getMessage();
}

header('Location: admin_users.php');
exit;
?>
