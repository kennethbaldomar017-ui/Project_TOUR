<?php
require_once 'config.php';
$actor = require_login($conn);
$canCreateAdmin = $actor['role'] === ROLE_SUPERADMIN;
$token = csrf_token();
$nextIdNumber = next_static_id_number($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin | PRIME.</title>
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

    <main class="container auth-page">
        <div class="auth-card admin-create-card">
            <h2>Create Admin</h2>
            <p class="auth-subtitle">Superadmin-only account creation</p>

            <?php if (!$canCreateAdmin): ?>
                <div class="permission-empty-page">
                    <p>Your account can see this area, but only a superadmin can create administrator accounts.</p>
                    <div class="empty-state"><strong>Account creation unavailable</strong><span>Ask a superadmin to create an administrator account.</span></div>
                </div>
            <?php else: ?>
            <form action="create_admin_process.php" method="post"
                  class="js-confirm"
                  data-confirm-title="Create this account?"
                  data-confirm-message="A temporary password will be generated, shown once, and handed to the new administrator. They must change it and set their own security questions on first login.">
                <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                <div class="form-grid">
                    <div>
                        <label>ID Number <span class="req">*</span></label>
                        <input type="text" name="id_number" value="<?= e($nextIdNumber); ?>" readonly required pattern="[0-9]{4}-[0-9]{4}" title="Format: 4 digits - 4 digits" placeholder="2026-0001">
                    </div>
                    <div>
                        <label>Role <span class="req">*</span></label>
                        <select name="role" required>
                            <option value="admin">Admin</option>
                            <option value="superadmin">Superadmin</option>
                        </select>
                    </div>
                    <div>
                        <label>First Name <span class="req">*</span></label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div>
                        <label>Last Name <span class="req">*</span></label>
                        <input type="text" name="last_name" required>
                    </div>
                    <div>
                        <label>Email <span class="req">*</span></label>
                        <input type="email" name="email" required>
                    </div>
                    <div>
                        <label>Username <span class="req">*</span></label>
                        <input type="text" name="username" required minlength="8" maxlength="16" pattern="[A-Za-z0-9_.\-]{8,16}">
                    </div>
                </div>
                <div class="admin-note">
                    No password or security questions are required here. A secure temporary password is generated automatically, shown once, and the new administrator must change it and create their own security questions when they next log in.
                </div>
                <div class="step-buttons">
                    <a class="muted-link" href="admin_users.php">Back to users</a>
                    <button type="submit" class="btn btn-primary btn-block">Create Account</button>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    <?php include 'confirm_modal.php'; ?>
</body>
</html>