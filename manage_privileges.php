<?php
require_once 'config.php';
$actor = require_login($conn);
$canManagePrivileges = $actor['role'] === ROLE_SUPERADMIN || user_has_privilege($conn, $actor, PRIV_MANAGE_PRIVILEGES);
$token = csrf_token();

$targetId = max(0, (int)($_GET['id'] ?? 0));
$target = $canManagePrivileges && $targetId > 0 ? get_user_by_id($conn, $targetId) : null;

if ($canManagePrivileges && $targetId > 0 && (!$target || $target['role'] !== ROLE_ADMIN)) {
    $_SESSION['form_error'] = 'Privileges can only be managed for administrator accounts.';
    header('Location: admin_users.php');
    exit;
}

$administrators = [];
if ($canManagePrivileges && !$target) {
    $adminResult = $conn->query("SELECT id, username, first_name, last_name, email, status FROM users WHERE role = 'admin' ORDER BY first_name, last_name, username");
    $administrators = $adminResult ? $adminResult->fetch_all(MYSQLI_ASSOC) : [];
}

$currentPrivileges = $target ? get_user_privileges($conn, (int)$target['id']) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Privileges | PRIME.</title>
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

    <main class="container auth-page privileges-page">
        <div class="auth-card privilege-card admin-data-page">
            <p class="page-eyebrow">SUPERADMIN CONTROL</p>
            <?php if (!$canManagePrivileges): ?>
                <h2>Privileges unavailable</h2>
                <p class="auth-subtitle">Your account can see this area, but no privilege-management permission has been assigned.</p>
                <div class="empty-state">
                    <strong>No privileges assigned</strong>
                    <span>A superadmin must grant <b>Manage administrator privileges</b> before these controls become available.</span>
                </div>
            <?php elseif (!$target): ?>
                <h2>Administrator Privileges</h2>
                <p class="auth-subtitle">Select an administrator to view or update their permissions. This area is available to superadmins only.</p>
                <?php if ($administrators): ?>
                    <div class="privilege-admin-list">
                        <?php foreach ($administrators as $administrator): ?>
                            <a class="privilege-admin-row" href="manage_privileges.php?id=<?= (int)$administrator['id']; ?>">
                                <span class="admin-avatar small"><?= e(strtoupper(substr(preg_replace('/[^A-Za-z]/', '', trim($administrator['first_name'] . ' ' . $administrator['last_name'])) ?: $administrator['username'], 0, 2))); ?></span>
                                <span><strong><?= e(trim($administrator['first_name'] . ' ' . $administrator['last_name']) ?: $administrator['username']); ?></strong><small><?= e($administrator['username']); ?> · <?= e($administrator['email']); ?></small></span>
                                <span class="privilege-admin-status status-<?= e($administrator['status']); ?>"><?= e(ucfirst($administrator['status'])); ?></span>
                                <b>&rarr;</b>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <strong>Superadmin access is active</strong>
                        <span>No Admin accounts are available to manage yet.</span>
                        <small>Superadmins already have every privilege automatically. Create an Admin account here if you want to assign limited permissions.</small>
                        <a class="btn btn-primary" href="create_admin.php">Create Admin Account</a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <h2>Manage Privileges</h2>
                <p class="auth-subtitle">
                    Grant or remove permissions for
                    <strong><?= e($target['username']); ?></strong>
                    (<?= e(trim($target['first_name'] . ' ' . $target['last_name'])); ?>)
                </p>

                <form action="manage_privileges_process.php" method="post"
                      class="js-confirm"
                      data-confirm-title="Save privilege changes?"
                      data-confirm-message="The selected privileges will be applied to this administrator immediately. Changes are recorded in the audit log.">
                    <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                    <input type="hidden" name="target_id" value="<?= (int)$target['id']; ?>">

                    <fieldset>
                        <legend>Capabilities</legend>
                        <div class="privilege-list">
                            <?php foreach (PRIVILEGE_LABELS as $privilege => $label): ?>
                                <label class="privilege-item">
                                    <input type="checkbox" name="privileges[]" value="<?= e($privilege); ?>" <?= in_array($privilege, $currentPrivileges, true) ? 'checked' : ''; ?>>
                                    <span>
                                        <strong><?= e($label); ?></strong>
                                        <small><?= e($privilege); ?></small>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                        <div class="help-text">Superadmin accounts always hold every privilege. These selections only affect this administrator's account.</div>
                    </fieldset>

                    <div class="step-buttons">
                        <a class="muted-link" href="manage_privileges.php">Back to administrators</a>
                        <button type="submit" class="btn btn-primary btn-block">Save Privileges</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    <?php include 'confirm_modal.php'; ?>
</body>
</html>