<?php
require_once 'config.php';
$actor = require_login($conn);

$mustChange = (int)($actor['must_change_password'] ?? 0);

// Pull current security questions for pre-selection.
$qStmt = $conn->prepare('SELECT auth_q1, auth_q2, auth_q3 FROM users WHERE id = ? LIMIT 1');
$qStmt->bind_param('i', $actor['id']);
$qStmt->execute();
$qRow = $qStmt->get_result()->fetch_assoc();
$qStmt->close();
$currentQuestions = [
    $qRow['auth_q1'] ?? null,
    $qRow['auth_q2'] ?? null,
    $qRow['auth_q3'] ?? null,
];

$questionOptions = security_question_options();
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Info | PRIME.</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/validation.js" defer></script>
    <script src="js/confirm.js" defer></script>
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
        <div class="auth-card edit-info-card">
            <h2>Account Settings</h2>
            <p class="auth-subtitle">Update your password and security questions</p>

            <?php if ($mustChange): ?>
                <div class="banner banner-warning">
                    Your account uses a temporary password. Please set a new password before continuing.
                </div>
            <?php endif; ?>

            <form action="edit_info_process.php" method="post" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e($token); ?>">

                <div class="account-step-indicator" aria-label="Account settings steps">
                    <span class="active" data-step-indicator="1"><b>1</b>Password</span>
                    <i></i>
                    <span data-step-indicator="2"><b>2</b>Security Questions</span>
                </div>

                <section class="account-step active" data-account-step="1">
                    <fieldset>
                        <legend>Change Password</legend>
                        <div class="form-grid">
                        <?php if (!$mustChange): ?>
                            <div>
                                <label>Current Password <span class="req">*</span></label>
                                <div class="password-wrapper">
                                    <input type="password" name="current_password" id="current_password" autocomplete="current-password" required minlength="8">
                                    <span class="toggle-pwd-icon">&#128065;</span>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div>
                            <label>New Password <span class="req">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="new_password" id="new_password" autocomplete="new-password" required minlength="8" maxlength="64">
                                <span class="toggle-pwd-icon">&#128065;</span>
                            </div>
                            <meter id="pwdStrength" min="0" max="4" value="0"></meter>
                        </div>
                        <div>
                            <label>Confirm New Password <span class="req">*</span></label>
                            <div class="password-wrapper">
                                <input type="password" name="confirm_password" id="confirm_password" autocomplete="new-password" required minlength="8" maxlength="64">
                                <span class="toggle-pwd-icon">&#128065;</span>
                            </div>
                            <div id="pwdMatch" class="match-indicator"></div>
                        </div>
                        <div class="span-2">
                            <div class="help-text">Leave the password fields unchanged if you only want to update your security questions.</div>
                        </div>
                        </div>
                        <div class="account-step-error" id="passwordStepError" role="alert"></div>
                    </fieldset>

                    <div class="account-step-actions">
                        <a class="muted-link" href="dashboard.php">Back to dashboard</a>
                        <button type="button" class="btn btn-primary" id="nextAccountStep">Next: Security Questions</button>
                    </div>
                </section>

                <section class="account-step" data-account-step="2" hidden>
                    <fieldset>
                        <legend>Security Questions</legend>
                        <p class="help-text">Three questions must be selected from the dropdowns. Answers are hidden and stored securely.</p>
                        <?php for ($i = 1; $i <= 3; $i++): ?>
                            <div class="security-question-group">
                                <label>Security Question <?= $i; ?> <span class="req">*</span></label>
                                <select name="question<?= $i; ?>" required>
                                    <option value="">-- Select a question --</option>
                                    <?php foreach ($questionOptions as $question): ?>
                                        <option value="<?= e($question); ?>" <?= $currentQuestions[$i - 1] === $question ? 'selected' : ''; ?>><?= e($question); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="password-wrapper">
                                    <input type="password" name="auth_a<?= $i; ?>" placeholder="Your answer" autocomplete="off" required>
                                    <span class="toggle-pwd-icon">&#128065;</span>
                                </div>
                                <?php if ($currentQuestions[$i - 1] && $currentQuestions[$i - 1] !== 'Created by superadmin'): ?>
                                    <div class="help-text">Current: <?= e($currentQuestions[$i - 1]); ?></div>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </fieldset>

                    <div class="account-step-actions">
                        <button type="button" class="btn btn-ghost" id="previousAccountStep">Back</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                    <div class="account-step-error" id="securityStepError" role="alert"></div>
                </section>
            </form>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const password = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const matchIndicator = document.getElementById('pwdMatch');
            const meter = document.getElementById('pwdStrength');

            if (password && meter) {
                password.addEventListener('input', function () {
                    const val = password.value;
                    let score = 0;
                    if (val.length >= 8) score++;
                    if (/[a-z]/.test(val) && /[A-Z]/.test(val)) score++;
                    if (/\d/.test(val)) score++;
                    if (/[^a-zA-Z\d]/.test(val)) score++;
                    meter.value = score;
                });
            }

            function checkMatch() {
                if (!password || !confirmPassword || !matchIndicator) return;
                if (confirmPassword.value === '') {
                    matchIndicator.textContent = '';
                    matchIndicator.className = 'match-indicator';
                } else if (password.value === confirmPassword.value) {
                    matchIndicator.textContent = '✓ Passwords match';
                    matchIndicator.className = 'match-indicator ok';
                } else {
                    matchIndicator.textContent = '✗ Passwords do not match';
                    matchIndicator.className = 'match-indicator bad';
                }
            }

            if (password) password.addEventListener('input', checkMatch);
            if (confirmPassword) confirmPassword.addEventListener('input', checkMatch);

            const mustChange = <?= $mustChange ? 'true' : 'false'; ?>;
            if (mustChange) {
                const currentPassword = document.getElementById('current_password');
                if (currentPassword) currentPassword.closest('div') && false;
            }

            const form = document.querySelector('.edit-info-card form');
            const nextStep = document.getElementById('nextAccountStep');
            const previousStep = document.getElementById('previousAccountStep');
            const passwordStep = document.querySelector('[data-account-step="1"]');
            const securityStep = document.querySelector('[data-account-step="2"]');
            const passwordStepError = document.getElementById('passwordStepError');
            const indicators = document.querySelectorAll('[data-step-indicator]');

            function setStep(step) {
                passwordStep.hidden = step !== 1;
                securityStep.hidden = step !== 2;
                indicators.forEach(function (indicator) {
                    indicator.classList.toggle('active', Number(indicator.dataset.stepIndicator) === step);
                    indicator.classList.toggle('complete', Number(indicator.dataset.stepIndicator) < step);
                });
            }

            if (nextStep) {
                nextStep.addEventListener('click', async function () {
                    const newValue = password ? password.value.trim() : '';
                    const confirmValue = confirmPassword ? confirmPassword.value.trim() : '';
                    const currentValue = document.getElementById('current_password');
                    const currentValueText = currentValue ? currentValue.value : '';
                    let error = '';

                    if (!mustChange && currentValueText === '') error = 'Enter your current password before continuing.';
                    else if (newValue === '') error = 'Enter a new password before continuing.';
                    else if (confirmValue === '') error = 'Confirm your new password before continuing.';
                    else if (newValue.length < 8 || newValue.length > 64) error = 'New password must be between 8 and 64 characters.';
                    else if (newValue !== confirmValue) error = 'New passwords do not match.';

                    if (passwordStepError) passwordStepError.textContent = error;
                    if (error) return;

                    if (!mustChange) {
                        nextStep.disabled = true;
                        nextStep.textContent = 'Checking password...';
                        try {
                            const response = await fetch('check_current_password.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                                body: new URLSearchParams({
                                    csrf_token: form.querySelector('[name="csrf_token"]').value,
                                    password: currentValueText
                                })
                            });
                            const result = await response.json();
                            if (!result.valid) {
                                if (passwordStepError) passwordStepError.textContent = 'Current password is incorrect.';
                                nextStep.disabled = false;
                                nextStep.textContent = 'Next: Security Questions';
                                if (currentValue) currentValue.focus();
                                return;
                            }
                        } catch (requestError) {
                            if (passwordStepError) passwordStepError.textContent = 'Could not verify the current password. Please try again.';
                            nextStep.disabled = false;
                            nextStep.textContent = 'Next: Security Questions';
                            return;
                        }
                        nextStep.disabled = false;
                        nextStep.textContent = 'Next: Security Questions';
                    }
                    setStep(2);
                });
            }

            if (previousStep) previousStep.addEventListener('click', function () {
                if (passwordStepError) passwordStepError.textContent = '';
                setStep(1);
            });

            if (form) form.addEventListener('submit', function (event) {
                const questions = form.querySelectorAll('[name^="question"]');
                const answers = form.querySelectorAll('[name^="auth_a"]');
                let error = '';
                questions.forEach(function (field) { if (!field.value && !error) error = 'Please select all security questions.'; });
                answers.forEach(function (field) { if (field.value.trim().length < 2 && !error) error = 'Each security answer must be at least 2 characters.'; });
                if (error) {
                    event.preventDefault();
                    const securityError = document.getElementById('securityStepError');
                    if (securityError) securityError.textContent = error;
                }
            });
        });
    </script>
</body>
</html>