<?php
require_once 'config.php';
$actor = require_privilege($conn, require_login($conn), PRIV_RESET_PASSWORDS);

if (empty($_SESSION['generated_password'])) {
    header('Location: admin_users.php');
    exit;
}
$account = $_SESSION['generated_account'];
$credentialsEmailSent = !empty($_SESSION['credentials_email_sent']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Reset | PRIME.</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container auth-page">
        <div class="auth-card generated-password-result-card">
            <div class="result-icon result-icon-success">&#10003;</div>
            <h2 class="result-heading">Password Reset Complete</h2>
            <p class="auth-subtitle">
                <?= e(ucfirst($account['role'])); ?> account
                <strong><?= e($account['username']); ?></strong> (#<?= e($account['id_number']); ?>)
                now uses a temporary password.
            </p>
            <div class="banner <?= $credentialsEmailSent ? 'banner-success' : 'banner-warning'; ?>" role="status">
                <?= $credentialsEmailSent
                    ? 'The username and temporary password were emailed to ' . e($account['email']) . '.'
                    : 'The password was reset, but the email containing the temporary password could not be sent.'; ?>
            </div>

            <form action="clear_generated_password.php" method="post" class="generated-password-done-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <div class="auth-links"><a class="muted-link" href="admin_users.php">Back to user management</a></div>
                <button type="submit" class="btn btn-primary btn-block">Done</button>
            </form>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script src="js/confirm.js" defer></script>
</body>
</html>