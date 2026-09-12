<?php
require_once 'config.php';

$actor = require_privilege($conn, require_login($conn), PRIV_RESET_PASSWORDS);
verify_csrf();
require_actor_confirmation($conn, $actor);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method');
}

$targetId = max(1, (int)($_POST['target_id'] ?? 0));
$target = get_user_by_id($conn, $targetId);

if (!$target) {
    $_SESSION['form_error'] = 'Account not found.';
    header('Location: admin_users.php');
    exit;
}

$canReset = ($actor['role'] === ROLE_SUPERADMIN)
    || ($actor['role'] === ROLE_ADMIN && $target['role'] === ROLE_USER);

if (!$canReset) {
    log_audit_action($conn, $actor, $target, 'failed_authorization', $target['status'], $target['status'], null, null, 'Password reset denied');
    $_SESSION['form_error'] = 'You are not allowed to reset that account.';
    header('Location: admin_users.php');
    exit;
}

$defaultPassword = generate_default_password();

try {
    $conn->begin_transaction();

    $stmt = $conn->prepare('UPDATE users SET password = ?, must_change_password = 1 WHERE id = ?');
    $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);
    $stmt->bind_param('si', $hashedPassword, $targetId);
    $stmt->execute();
    $stmt->close();

    if (!log_audit_action($conn, $actor, $target, 'password_reset', $target['status'], $target['status'], null, null, 'Superadmin/privileged reset with temporary password')) {
        throw new RuntimeException('Could not write audit log.');
    }
    $conn->commit();

    $credentialsEmailSent = $actor['role'] === ROLE_SUPERADMIN
        ? send_account_credentials_email($target, $defaultPassword)
        : false;
    $_SESSION['generated_password'] = $defaultPassword;
    $_SESSION['credentials_email_sent'] = $credentialsEmailSent;
    $_SESSION['generated_account'] = [
        'username' => $target['username'],
        'email' => $target['email'],
        'id_number' => $target['id_number'],
        'role' => $target['role'],
    ];
    header('Location: reset_password_result.php');
    exit;
} catch (Throwable $e) {
    @$conn->rollback();
    $_SESSION['form_error'] = 'Password could not be reset: ' . $e->getMessage();
    header('Location: reset_password.php?id=' . $targetId);
    exit;
}