<?php
require_once 'config.php';
$actor = require_login($conn);
$mustChange = (int)($actor['must_change_password'] ?? 0);
$token = csrf_token();
$questionStmt = $conn->prepare('SELECT auth_q1, auth_q2, auth_q3 FROM users WHERE id = ? LIMIT 1');
$questionStmt->bind_param('i', $actor['id']);
$questionStmt->execute();
$questionRow = $questionStmt->get_result()->fetch_assoc() ?: [];
$questionStmt->close();
$currentQuestions = [$questionRow['auth_q1'] ?? '', $questionRow['auth_q2'] ?? '', $questionRow['auth_q3'] ?? ''];
$questionOptions = security_question_options();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password | PRIME.</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'header.php'; ?>
<?php if (isset($_SESSION['form_error'])): ?>
    <div class="form-errors" id="alertBox"><?= e($_SESSION['form_error']); unset($_SESSION['form_error']); ?></div>
<?php endif; ?>
<main class="container auth-page">
    <div class="auth-card edit-info-card first-login-card">
        <h2>Change Password</h2>
        <p class="auth-subtitle">Create a new secure password</p>
        <?php if ($mustChange): ?>
            <div class="banner banner-warning">Your temporary password must be changed before you can continue.</div>
        <?php endif; ?>
        <form id="changePasswordForm" action="change_password_authenticated_process.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
            <div class="account-step-indicator" aria-label="Change password steps">
                <span class="active" data-change-step-indicator="1"><b>1</b>Password</span>
                <i></i>
                <span data-change-step-indicator="2"><b>2</b>Security Questions</span>
            </div>

            <section data-change-step="1">
                <div class="change-password-fields">
                    <?php if (!$mustChange): ?>
                        <div>
                            <label for="current_password">Current Password <span class="req">*</span></label>
                            <div class="password-wrapper">
                                <input id="current_password" type="password" name="current_password" autocomplete="current-password" required minlength="8">
                                <span class="toggle-pwd-icon">&#128065;</span>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div>
                        <label for="new_password">New Password <span class="req">*</span></label>
                        <div class="password-wrapper">
                            <input id="new_password" type="password" name="new_password" autocomplete="new-password" required minlength="8" maxlength="64">
                            <span class="toggle-pwd-icon">&#128065;</span>
                        </div>
                    </div>
                    <div>
                        <label for="confirm_password">Confirm New Password <span class="req">*</span></label>
                        <div class="password-wrapper">
                            <input id="confirm_password" type="password" name="confirm_password" autocomplete="new-password" required minlength="8" maxlength="64">
                            <span class="toggle-pwd-icon">&#128065;</span>
                        </div>
                    </div>
                </div>
                <div class="account-step-actions">
                    <a class="muted-link" href="edit_info.php">Back to account settings</a>
                    <button type="button" class="btn btn-primary" id="nextChangeStep">Next: Security Questions</button>
                </div>
                <div class="account-step-error" id="passwordStepError" role="alert"></div>
            </section>

            <section data-change-step="2" hidden>
                <fieldset class="change-password-security-fieldset">
                    <legend>Security Questions</legend>
                    <p class="help-text">Select three questions and provide answers for account recovery.</p>
                    <?php for ($i = 1; $i <= 3; $i++): ?>
                        <div class="security-question-group">
                            <label>Security Question <?= $i; ?> <span class="req">*</span></label>
                            <select name="question<?= $i; ?>" required disabled>
                                <option value="">-- Select a question --</option>
                                <?php foreach ($questionOptions as $question): ?>
                                    <option value="<?= e($question); ?>" <?= $currentQuestions[$i - 1] === $question ? 'selected' : ''; ?>><?= e($question); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="password-wrapper">
                                <input type="password" name="auth_a<?= $i; ?>" placeholder="Your answer" autocomplete="off" required disabled>
                                <span class="toggle-pwd-icon">&#128065;</span>
                            </div>
                        </div>
                    <?php endfor; ?>
                </fieldset>
                <div class="account-step-actions">
                    <button type="button" class="btn btn-ghost" id="previousChangeStep">Back</button>
                    <button type="submit" class="btn btn-primary">Save Password</button>
                </div>
                <div class="account-step-error" id="securityStepError" role="alert"></div>
            </section>
        </form>
    </div>
</main>
<?php include 'footer.php'; ?>
<script src="js/confirm.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('changePasswordForm');
    const passwordStep = document.querySelector('[data-change-step="1"]');
    const securityStep = document.querySelector('[data-change-step="2"]');
    const nextButton = document.getElementById('nextChangeStep');
    const previousButton = document.getElementById('previousChangeStep');
    const indicators = document.querySelectorAll('[data-change-step-indicator]');
    const securityFields = securityStep.querySelectorAll('select, input');
    const mustChange = <?= $mustChange ? 'true' : 'false'; ?>;

    function setStep(step) {
        passwordStep.hidden = step !== 1;
        securityStep.hidden = step !== 2;
        securityFields.forEach(function (field) { field.disabled = step !== 2; });
        indicators.forEach(function (indicator) {
            const indicatorStep = Number(indicator.dataset.changeStepIndicator);
            indicator.classList.toggle('active', indicatorStep === step);
            indicator.classList.toggle('complete', indicatorStep < step);
        });
    }

    document.querySelectorAll('.toggle-pwd-icon').forEach(function (button) {
        button.addEventListener('click', function () {
            const wrapper = button.closest('.password-wrapper');
            const field = wrapper ? wrapper.querySelector('input') : null;
            if (!field) return;
            const isPassword = field.type === 'password';
            field.type = isPassword ? 'text' : 'password';
            button.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
        });
    });

    nextButton.addEventListener('click', function () {
        const current = document.getElementById('current_password');
        const password = document.getElementById('new_password').value;
        const confirmation = document.getElementById('confirm_password').value;
        let error = '';
        if (!mustChange && current && current.value === '') error = 'Enter your current password.';
        else if (password.length < 8 || password.length > 64) error = 'New password must be between 8 and 64 characters.';
        else if (password !== confirmation) error = 'New passwords do not match.';
        document.getElementById('passwordStepError').textContent = error;
        if (!error) setStep(2);
    });

    previousButton.addEventListener('click', function () { setStep(1); });
    form.addEventListener('submit', function (event) {
        const questions = form.querySelectorAll('[name^="question"]');
        const answers = form.querySelectorAll('[name^="auth_a"]');
        let error = '';
        questions.forEach(function (field) { if (!field.value && !error) error = 'Please select all security questions.'; });
        answers.forEach(function (field) { if (field.value.trim().length < 2 && !error) error = 'Each security answer must be at least 2 characters.'; });
        document.getElementById('securityStepError').textContent = error;
        if (error) event.preventDefault();
    });
});
</script>
</body>
</html>
