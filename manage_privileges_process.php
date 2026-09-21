<?php
require_once 'config.php';

$actor = require_login($conn);
if ($actor['role'] !== ROLE_SUPERADMIN && !user_has_privilege($conn, $actor, PRIV_MANAGE_PRIVILEGES)) {
    http_response_code(403);
    exit('Forbidden');
}
verify_csrf();
require_actor_confirmation($conn, $actor);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method');
}

$targetId = (int)($_POST['target_id'] ?? 0);
if ($targetId <= 0) {
    $_SESSION['form_error'] = 'Select an administrator account before saving privileges.';
    header('Location: manage_privileges.php');
    exit;
}

$target = get_user_by_id($conn, $targetId);

if (!$target || !in_array($target['role'], [ROLE_ADMIN, ROLE_SUPERADMIN], true) || ($target['role'] === ROLE_SUPERADMIN && $actor['role'] !== ROLE_SUPERADMIN)) {
    $_SESSION['form_error'] = 'Privileges can only be managed for administrator accounts.';
    header('Location: manage_privileges.php');
    exit;
}

$previous = get_user_privileges($conn, (int)$target['id']);
$selected = (array)($_POST['privileges'] ?? []);
$assignablePrivileges = PRIVILEGE_LABELS;
$selected = array_values(array_intersect($selected, array_keys($assignablePrivileges)));
sort($selected);

try {
    $conn->begin_transaction();
    set_user_privileges($conn, (int)$target['id'], $selected, $actor, 'Privilege update', $target['role'] === ROLE_SUPERADMIN);

    $removed = array_diff($previous, $selected);
    $added = array_diff($selected, $previous);
    $detail = '';
    if ($added) {
        $detail .= 'Added: ' . implode(', ', $added) . '. ';
    }
    if ($removed) {
        $detail .= 'Removed: ' . implode(', ', $removed) . '. ';
    }
    if ($detail === '') {
        $detail = 'No privilege changes.';
    }

    if (!log_audit_action($conn, $actor, $target, 'privileges_update', null, null, null, null, trim($detail))) {
        throw new RuntimeException('Could not write audit log.');
    }
    $conn->commit();

    $_SESSION['success'] = 'Privileges updated for ' . $target['username'] . '.';
    header('Location: manage_privileges.php');
    exit;
} catch (Throwable $e) {
    @$conn->rollback();
    $_SESSION['form_error'] = 'Privileges could not be updated: ' . $e->getMessage();
    header('Location: manage_privileges.php?id=' . $targetId);
    exit;
}