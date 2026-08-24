<?php
require_once 'config.php';
$actor = require_superadmin($conn);
$token = csrf_token();
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

    <main class="container auth-page">
        <div class="auth-card admin-create-card">
            <h2>Create Admin</h2>
            <p class="auth-subtitle">Superadmin-only account creation</p>

            <form action="create_admin_process.php" method="post" onsubmit="return confirm('Create this admin account?');">
                <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                <div class="form-grid">
                    <div>
                        <label>ID Number <span class="req">*</span></label>
                        <input type="text" name="id_number" required pattern="[0-9]{4}-[0-9]{4}" placeholder="2026-0001">
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
                    <div>
                        <label>Password <span class="req">*</span></label>
                        <input type="password" name="password" required minlength="8" maxlength="64">
                    </div>
                    <div>
                        <label>Confirm Password <span class="req">*</span></label>
                        <input type="password" name="confirm_password" required minlength="8" maxlength="64">
                    </div>
                    <div class="span-2">
                        <label>Reason or comment</label>
                        <textarea name="reason" rows="2" placeholder="Optional"></textarea>
                    </div>
                </div>
                <div class="step-buttons">
                    <a class="muted-link" href="admin_users.php">Back to users</a>
                    <button type="submit">Create Account</button>
                </div>
            </form>
        </div>
    </main>

    <?php include 'footer.php'; ?>
</body>
</html>
