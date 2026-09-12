<?php
require_once 'config.php';

$actor = require_login($conn);
$targetId = max(1, (int)($_GET['id'] ?? 0));
$target = get_user_by_id($conn, $targetId);

if (!$target || !can_manage_account($conn, $actor, $target, 'update_info')) {
    http_response_code(403);
    exit('You are not allowed to update this account.');
}

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Account Information | PRIME.</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <?php if (isset($_SESSION['form_error'])): ?>
        <div class="form-errors" id="alertBox"><?= e($_SESSION['form_error']); unset($_SESSION['form_error']); ?></div>
    <?php endif; ?>

    <main class="container auth-page">
        <div class="auth-card edit-info-card">
            <h2>Edit Account Information</h2>
            <p class="auth-subtitle">Update the profile details for <strong><?= e($target['username']); ?></strong>.</p>

            <form action="account_info_process.php" method="post" class="js-confirm" data-confirm-title="Save account information?" data-confirm-message="These account details will be updated immediately.">
                <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                <input type="hidden" name="target_id" value="<?= (int)$target['id']; ?>">

                <fieldset>
                    <legend>Account Information</legend>
                    <div class="form-grid">
                        <div>
                            <label for="id_number">Employee ID <span class="req">*</span></label>
                            <input id="id_number" type="text" name="id_number" value="<?= e($target['id_number']); ?>" required pattern="[0-9]{4}-[0-9]{4}">
                        </div>
                        <div>
                            <label for="username">Username <span class="req">*</span></label>
                            <input id="username" type="text" name="username" value="<?= e($target['username']); ?>" required pattern="[A-Za-z0-9_.\-]{8,16}" minlength="8" maxlength="16">
                        </div>
                        <div>
                            <label for="first_name">First Name <span class="req">*</span></label>
                            <input id="first_name" type="text" name="first_name" value="<?= e($target['first_name']); ?>" required maxlength="100">
                        </div>
                        <div>
                            <label for="last_name">Last Name <span class="req">*</span></label>
                            <input id="last_name" type="text" name="last_name" value="<?= e($target['last_name']); ?>" required maxlength="100">
                        </div>
                        <div class="span-2">
                            <label for="email">Email <span class="req">*</span></label>
                            <input id="email" type="email" name="email" value="<?= e($target['email']); ?>" required maxlength="150">
                        </div>
                    </div>
                </fieldset>

                <div class="step-buttons">
                    <a class="muted-link" href="admin_users.php">Back to users</a>
                    <button type="submit" class="btn btn-primary">Save Information</button>
                </div>
            </form>
        </div>
    </main>

    <?php include 'footer.php'; ?>
    <?php include 'confirm_modal.php'; ?>
</body>
</html>
