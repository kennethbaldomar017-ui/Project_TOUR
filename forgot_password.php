<?php
require_once 'config.php';

function clean($v) {
    return trim(htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'));
}

$errors = [];
$success = '';
$id_value = '';
$username = '';
$questions = [];
$otp_sent = false;
$account = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['lookup'])) {
        $id = clean($_POST['id_number'] ?? '');
        $id_value = $id;

        if ($id === '') {
            $errors[] = 'ID Number or Username is required.';
        } else {
            $stmt = $conn->prepare('SELECT id, username, id_number, auth_q1, auth_q2, auth_q3 FROM users WHERE id_number = ? OR username = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('ss', $id, $id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows === 1) {
                    $row = $result->fetch_assoc();
                    $account = $row;
                    $username = $row['username'];
                    $id_value = $row['id_number'];
                    $questions = [
                        'a1' => $row['auth_q1'],
                        'a2' => $row['auth_q2'],
                        'a3' => $row['auth_q3'],
                    ];
                } else {
                    $errors[] = 'No account found with that ID Number or Username.';
                }
                $stmt->close();
            } else {
                $errors[] = 'Database error.';
            }
        }
    }

    if (isset($_POST['verify'])) {
        // Verify security answers only, then email an OTP.
        $id = clean($_POST['id_number'] ?? '');
        $id_value = $id;
        $a1 = trim($_POST['a1'] ?? '');
        $a2 = trim($_POST['a2'] ?? '');
        $a3 = trim($_POST['a3'] ?? '');

        if ($id === '') $errors[] = 'ID Number or Username is required.';
        if ($a1 === '' || $a2 === '' || $a3 === '') $errors[] = 'All authentication answers are required.';

        if (empty($errors)) {
            $stmt = $conn->prepare('SELECT id, id_number, username, auth_a1, auth_a2, auth_a3 FROM users WHERE id_number = ? OR username = ? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('ss', $id, $id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result->num_rows !== 1) {
                    $errors[] = 'Account not found.';
                } else {
                    $row = $result->fetch_assoc();
                    $uid = (int)$row['id'];
                    $ipAddress = substr((string)($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
                    $attemptStmt = $conn->prepare('SELECT attempts, locked_until FROM recovery_attempts WHERE user_id = ? AND ip_address = ? LIMIT 1');
                    $attemptStmt->bind_param('is', $uid, $ipAddress);
                    $attemptStmt->execute();
                    $attemptState = $attemptStmt->get_result()->fetch_assoc();
                    $attemptStmt->close();
                    if ($attemptState && !empty($attemptState['locked_until']) && strtotime($attemptState['locked_until']) > time()) {
                        $errors[] = 'Too many verification attempts. Please try again later.';
                    } else {
                        $correctAnswers = 0;
                    $correctAnswers += password_verify($a1, $row['auth_a1'] ?? '') ? 1 : 0;
                    $correctAnswers += password_verify($a2, $row['auth_a2'] ?? '') ? 1 : 0;
                    $correctAnswers += password_verify($a3, $row['auth_a3'] ?? '') ? 1 : 0;
                        if ($correctAnswers < 2) {
                        $recordAttempt = $conn->prepare("INSERT INTO recovery_attempts (user_id, ip_address, attempts, locked_until) VALUES (?, ?, 1, NULL) ON DUPLICATE KEY UPDATE attempts = attempts + 1, locked_until = IF(attempts + 1 >= 5, DATE_ADD(NOW(), INTERVAL 15 MINUTE), locked_until)");
                        $recordAttempt->bind_param('is', $uid, $ipAddress);
                        $recordAttempt->execute();
                        $recordAttempt->close();
                        $errors[] = 'At least two of the three authentication answers must match.';
                        } else {
                        $clearAttempts = $conn->prepare('DELETE FROM recovery_attempts WHERE user_id = ? AND ip_address = ?');
                        $clearAttempts->bind_param('is', $uid, $ipAddress);
                        $clearAttempts->execute();
                        $clearAttempts->close();
                        $otp = (string)random_int(100000, 999999);
                        $tokenHash = password_hash($otp, PASSWORD_DEFAULT);
                        $emailStmt = $conn->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
                        $emailStmt->bind_param('i', $uid);
                        $emailStmt->execute();
                        $emailValue = $emailStmt->get_result()->fetch_assoc()['email'] ?? '';
                        $emailStmt->close();

                        $conn->query('DELETE FROM otp_tokens WHERE user_id = ' . (int)$uid . ' OR expires_at < NOW()');
                        $otpStmt = $conn->prepare("INSERT INTO otp_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
                        $otpStmt->bind_param('is', $uid, $tokenHash);
                        $otpStmt->execute();
                        $otpStmt->close();

                        $sent = $emailValue !== '' && @mail(
                            $emailValue,
                            'PRIME. password reset code',
                            "Your PRIME. verification code is {$otp}. It expires in 10 minutes."
                        );
                        if (!$sent) {
                            $conn->query('DELETE FROM otp_tokens WHERE user_id = ' . (int)$uid);
                            $errors[] = 'The verification email could not be sent. Configure SMTP in XAMPP/PHP and try again.';
                        } else {
                            $otp_sent = true;
                            $id_value = $row['id_number'];
                            $username = $row['username'];
                            $questions = [];
                        }
                        }
                    }
                }
                $stmt->close();
            } else {
                $errors[] = 'Database error.';
            }
        }
    }

    if (isset($_POST['verify_otp'])) {
        $id = clean($_POST['id_number'] ?? '');
        $otp = trim($_POST['otp'] ?? '');
        if (!preg_match('/^[0-9]{6}$/', $otp)) {
            $errors[] = 'Enter the six-digit verification code.';
        } else {
            $stmt = $conn->prepare('SELECT otp_tokens.id, user_id, token_hash, attempts FROM otp_tokens JOIN users ON users.id = otp_tokens.user_id WHERE (users.id_number = ? OR users.username = ?) AND used_at IS NULL AND attempts < 5 AND expires_at > NOW() ORDER BY otp_tokens.id DESC LIMIT 1');
            $stmt->bind_param('ss', $id, $id);
            $stmt->execute();
            $token = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$token || !password_verify($otp, $token['token_hash'])) {
                if ($token) {
                    $attemptStmt = $conn->prepare('UPDATE otp_tokens SET attempts = attempts + 1, used_at = IF(attempts + 1 >= 5, NOW(), used_at) WHERE id = ?');
                    $attemptStmt->bind_param('i', $token['id']);
                    $attemptStmt->execute();
                    $attemptStmt->close();
                }
                $errors[] = 'Invalid or expired verification code.';
            } else {
                $usedStmt = $conn->prepare('UPDATE otp_tokens SET used_at = NOW() WHERE id = ?');
                $usedStmt->bind_param('i', $token['id']);
                $usedStmt->execute();
                $usedStmt->close();
                $_SESSION['pwd_reset_user'] = (int)$token['user_id'];
                $_SESSION['pwd_reset_started'] = time();
                header('Location: change_password.php');
                exit;
            }
        }
        $otp_sent = true;
        $id_value = $id;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prime. - Forgot Password</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>

<?php include 'header.php'; ?>

<?php if (!empty($errors)): ?>
    <div class="form-errors" id="alertBox">
        <?php foreach ($errors as $e): ?>
            <?php echo htmlspecialchars($e, ENT_QUOTES, 'UTF-8'); ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="form-success" id="alertBox">
        <?php echo $success; ?>
    </div>
<?php endif; ?>

<main class="container auth-page">
    <div class="auth-card">
        <h2>Forgot Password</h2>
        <p class="auth-subtitle">Enter your ID Number or Username and answer your security questions.</p>

        <form method="post" action="forgot_password.php" class="auth-form">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($otp_sent): ?>
                <input type="hidden" name="id_number" value="<?php echo htmlspecialchars($id_value, ENT_QUOTES, 'UTF-8'); ?>">
                <div>
                    <label for="otp">Email verification code <span class="req">*</span></label>
                    <div class="password-wrapper">
                        <input id="otp" type="password" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="Enter 6-digit code" required>
                        <span class="toggle-pwd-icon">&#128065;</span>
                    </div>
                    <div class="help-text">The code was sent to your registered email and expires after 10 minutes.</div>
                </div>
                <button type="submit" name="verify_otp" class="btn btn-primary btn-block">Verify code</button>
            <?php elseif (empty($questions)): ?>
                <div>
                    <label for="id_number">ID Number or Username <span class="req">*</span></label>
                    <input id="id_number" type="text" name="id_number" autocomplete="username" placeholder="e.g. 2020-0099 or juan_2020" required value="<?php echo htmlspecialchars($id_value, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <button type="submit" name="lookup" class="btn btn-primary btn-block">Continue</button>
            <?php else: ?>
                <!-- Display masked ID Number and Username -->
                <div class="verification-info">
                    <div class="verification-header">Your Account Information</div>
                    <div class="verification-grid">
                        <div class="verification-field">
                            <div class="verification-label">ID Number</div>
                            <div class="verification-value"><?php echo htmlspecialchars(mask_id_number($id_value), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <div class="verification-field">
                            <div class="verification-label">Username</div>
                            <div class="verification-value"><?php echo htmlspecialchars(mask_value($username), ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="id_number" value="<?php echo htmlspecialchars($id_value, ENT_QUOTES, 'UTF-8'); ?>">

                <div style="margin-top: 16px;">
                    <label><?php echo htmlspecialchars($questions['a1'] ?? '', ENT_QUOTES, 'UTF-8'); ?> <span class="req">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" name="a1" placeholder="Your answer" autocomplete="off" required>
                        <span class="toggle-pwd-icon">&#128065;</span>
                    </div>
                </div>

                <div>
                    <label><?php echo htmlspecialchars($questions['a2'] ?? '', ENT_QUOTES, 'UTF-8'); ?> <span class="req">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" name="a2" placeholder="Your answer" autocomplete="off" required>
                        <span class="toggle-pwd-icon">&#128065;</span>
                    </div>
                </div>

                <div>
                    <label><?php echo htmlspecialchars($questions['a3'] ?? '', ENT_QUOTES, 'UTF-8'); ?> <span class="req">*</span></label>
                    <div class="password-wrapper">
                        <input type="password" name="a3" placeholder="Your answer" autocomplete="off" required>
                        <span class="toggle-pwd-icon">&#128065;</span>
                    </div>
                </div>

                <button type="submit" name="verify" class="btn btn-primary btn-block">Verify Answers</button>
            <?php endif; ?>
        </form>

        <div class="auth-links">
            <p>Remembered it? <a class="muted-link" href="login.php">Back to login</a></p>
        </div>
    </div>
</main>

<?php include 'footer.php'; ?>
<?php include 'confirm_modal.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const alertBox = document.getElementById('alertBox');
    if (alertBox) {
        setTimeout(function () {
            alertBox.classList.add('dismiss');
            setTimeout(function () {
                alertBox.style.display = 'none';
            }, 400);
        }, 5000);
    }
});
</script>

</body>
</html>