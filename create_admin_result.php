<?php
require_once 'config.php';
$actor = require_superadmin($conn);

// Temporary password is only available right after creation.
if (empty($_SESSION['generated_password'])) {
    header('Location: admin_users.php');
    exit;
}
$generatedPassword = $_SESSION['generated_password'];
$account = $_SESSION['generated_account'];
$credentialsEmailSent = !empty($_SESSION['credentials_email_sent']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Created | PRIME.</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="container auth-page">
        <div class="auth-card generated-password-result-card">
            <div class="result-icon result-icon-success">&#10003;</div>
            <h2 class="result-heading">Account Created</h2>
            <p class="auth-subtitle">
                <?= e(ucfirst($account['role'])); ?> account
                <strong><?= e($account['username']); ?></strong> (#<?= e($account['id_number']); ?>)
                has been created.
            </p>
            <p class="help-text <?= $credentialsEmailSent ? '' : 'form-error'; ?>">
                <?= $credentialsEmailSent
                    ? 'A security notice was emailed to ' . e($account['email']) . '. The username and temporary password are not included in email for security reasons.'
                    : 'The account was created, but the security notice email could not be sent.'; ?>
            </p>

            <div class="generated-password-card">
                <div class="verification-header">Temporary Password — shown once</div>
                <p class="modal-hint">Share this password with the account owner privately. It is required on their first login, then they must set a new password and create their own security questions.</p>
                <div class="generated-password-value">
                    <div class="password-wrapper">
                        <input type="password" id="generatedPasswordValue" value="<?= e($generatedPassword); ?>" readonly>
                        <button type="button" class="toggle-pwd-icon" data-reveal-pwd aria-label="Show password">&#128065;</button>
                    </div>
                    <button type="button" class="copy-btn" id="copyPassword" title="Copy to clipboard">Copy</button>
                </div>
                <div class="help-text generated-help">This password is hidden by default. Use the eye to reveal it, and the copy button to copy it.</div>
            </div>

            <form action="clear_generated_password.php" method="post" class="generated-password-done-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
                <div class="auth-links"><a class="muted-link" href="admin_users.php">Back to user management</a></div>
                <button type="submit" class="btn btn-primary btn-block">I've saved the password — Done</button>
            </form>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const input = document.getElementById('generatedPasswordValue');
            const reveal = document.querySelector('[data-reveal-pwd]');
            const copyBtn = document.getElementById('copyPassword');

            if (reveal && input) {
                reveal.addEventListener('click', function () {
                    if (input.type === 'password') {
                        input.type = 'text';
                        reveal.setAttribute('aria-label', 'Hide password');
                    } else {
                        input.type = 'password';
                        reveal.setAttribute('aria-label', 'Show password');
                    }
                });
            }

            if (copyBtn && input) {
                copyBtn.addEventListener('click', function () {
                    const password = input.value;
                    const showCopied = function () {
                        copyBtn.textContent = 'Copied!';
                        setTimeout(function () { copyBtn.textContent = 'Copy'; }, 1500);
                    };
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(password).then(showCopied);
                        return;
                    }
                    input.type = 'text';
                    input.select();
                    input.setSelectionRange(0, 99999);
                    if (document.execCommand('copy')) showCopied();
                    input.type = 'password';
                });
            }
        });
    </script>
</body>
</html>