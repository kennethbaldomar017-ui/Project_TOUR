<?php
require_once 'config.php';
$actor = require_privilege($conn, require_login($conn), PRIV_RESET_PASSWORDS);
$token = csrf_token();

$targetId = max(1, (int)($_GET['id'] ?? 0));
$target = get_user_by_id($conn, $targetId);

if (!$target) {
    $_SESSION['form_error'] = 'Account not found.';
    header('Location: admin_users.php');
    exit;
}

if ((int)$target['id'] === (int)$actor['id']) {
    $_SESSION['form_error'] = 'You cannot reset your own password here. Use Account Settings.';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | PRIME.</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <?php if(isset($_SESSION['form_error'])): ?>
        <div class="form-errors" id="alertBox"><?= e($_SESSION['form_error']); unset($_SESSION['form_error']); ?></div>
    <?php endif; ?>

    <main class="container auth-page">
        <div class="auth-card">
            <h2>Reset Password</h2>
            <p class="auth-subtitle">Generate a fresh temporary password for this account</p>

            <div class="verification-info">
                <div class="verification-header">Target Account</div>
                <div class="verification-grid">
                    <div class="verification-field">
                        <div class="verification-label">Username</div>
                        <div class="verification-value"><?= e($target['username']); ?></div>
                    </div>
                    <div class="verification-field">
                        <div class="verification-label">Role</div>
                        <div class="verification-value"><?= e(ucfirst($target['role'])); ?></div>
                    </div>
                    <div class="verification-field">
                        <div class="verification-label">ID Number</div>
                        <div class="verification-value"><?= e(mask_id_number($target['id_number'])); ?></div>
                    </div>
                    <div class="verification-field">
                        <div class="verification-label">Name</div>
                        <div class="verification-value"><?= e(trim($target['first_name'] . ' ' . $target['last_name'])); ?></div>
                    </div>
                </div>
            </div>

            <form action="reset_password_process.php" method="post"
                  class="js-confirm"
                  data-confirm-title="Reset this password?"
                  data-confirm-message="A new temporary password will be generated, shown once, and must be changed by the account owner on next login. The current password will stop working immediately.">
                <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                <input type="hidden" name="target_id" value="<?= (int)$target['id']; ?>">

                <p class="modal-hint">The temporary password is hidden. Reveal it only when sharing with the account owner.</p>

                <div class="step-buttons">
                    <a class="muted-link" href="admin_users.php">Back to users</a>
                    <button type="submit" class="btn btn-primary btn-block">Generate Temporary Password</button>
                </div>
            </form>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    <?php include 'confirm_modal.php'; ?>
</body>
</html>