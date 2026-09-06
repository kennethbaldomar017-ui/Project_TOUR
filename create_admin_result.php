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
        <div class="auth-card">
            <div class="result-icon result-icon-success">&#10003;</div>
            <h2 class="result-heading">Account Created</h2>
            <p class="auth-subtitle">
                <?= e(ucfirst($account['role'])); ?> account
                <strong><?= e($account['username']); ?></strong> (#<?= e($account['id_number']); ?>)
                has been created.
            </p>

            <div class="generated-password-card">
                <div class="verification-header">Temporary Password — shown once</div>
                <p class="modal-hint">Share this password with the account owner privately. It is required on their first login, then they must set a new password and create their own security questions.</p>
                <div class="password-wrapper generated-password-value">
                    <input type="password" id="generatedPasswordValue" value="<?= e($generatedPassword); ?>" readonly>
                    <span class="toggle-pwd-icon" data-reveal-pwd> &#128065;</span>
                    <button type="button" class="copy-btn" id="copyPassword" title="Copy to clipboard">Copy</button>
                </div>
                <div class="help-text generated-help">This password is hidden by default. Use the eye to reveal it, and the copy button to copy it.</div>
            </div>

            <form action="clear_generated_password.php" method="post">
                <button type="submit" class="btn btn-primary btn-block">I've saved the password — Done</button>
            </form>

            <div class="auth-links">
                <p><a class="muted-link" href="admin_users.php">Back to user management</a></p>
            </div>
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
                        reveal.innerHTML = '&#128066;';
                    } else {
                        input.type = 'password';
                        reveal.innerHTML = '&#128065;';
                    }
                });
            }

            if (copyBtn && input) {
                copyBtn.addEventListener('click', function () {
                    input.type = 'text';
                    input.select();
                    input.setSelectionRange(0, 99999);
                    document.execCommand('copy');
                    copyBtn.textContent = 'Copied!';
                    setTimeout(function () { copyBtn.textContent = 'Copy'; }, 1500);
                });
            }
        });
    </script>
</body>
</html>