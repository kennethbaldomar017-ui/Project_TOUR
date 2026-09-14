<?php
require_once 'config.php';

$pending = $_SESSION['pending_login'] ?? null;
if (!is_array($pending) || empty($pending['user_id']) || empty($pending['started_at'])) {
    $_SESSION['form_error'] = 'Please log in again to continue.';
    header('Location: login.php');
    exit;
}

if (time() - (int)$pending['started_at'] > 600) {
    unset($_SESSION['pending_login']);
    $_SESSION['form_error'] = 'Your verification code expired. Please log in again.';
    header('Location: login.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $otp = trim($_POST['otp'] ?? '');

    if (!preg_match('/^[0-9]{6}$/', $otp)) {
        $error = 'Enter the six-digit verification code.';
    } else {
        $userId = (int)$pending['user_id'];
        $stmt = $conn->prepare('SELECT id, username, first_name, last_name, role, status, must_change_password FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $tokenStmt = $conn->prepare('SELECT id, token_hash, attempts FROM otp_tokens WHERE user_id = ? AND used_at IS NULL AND attempts < 5 AND expires_at > NOW() ORDER BY id DESC LIMIT 1');
        $tokenStmt->bind_param('i', $userId);
        $tokenStmt->execute();
        $token = $tokenStmt->get_result()->fetch_assoc();
        $tokenStmt->close();

        if (!$user || $user['status'] !== STATUS_ACTIVE || !$token || !password_verify($otp, $token['token_hash'])) {
            if ($token) {
                $attemptStmt = $conn->prepare('UPDATE otp_tokens SET attempts = attempts + 1, used_at = IF(attempts + 1 >= 5, NOW(), used_at) WHERE id = ?');
                $attemptStmt->bind_param('i', $token['id']);
                $attemptStmt->execute();
                $attemptStmt->close();
            }
            $error = 'Invalid or expired verification code.';
        } else {
            $usedStmt = $conn->prepare('UPDATE otp_tokens SET used_at = NOW() WHERE id = ?');
            $usedStmt->bind_param('i', $token['id']);
            $usedStmt->execute();
            $usedStmt->close();

            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['identifier'] = $pending['identifier'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            $_SESSION['last_activity'] = time();
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name'] = $user['last_name'];
            unset($_SESSION['pending_login']);

            if ($user['role'] === ROLE_SUPERADMIN) {
                $superadminLock = acquire_superadmin_lock($conn, (int)$user['id'], session_id());
                if ($superadminLock !== true) {
                    unset($_SESSION['user_id'], $_SESSION['identifier'], $_SESSION['username'], $_SESSION['role'], $_SESSION['logged_in'], $_SESSION['first_name'], $_SESSION['last_name']);
                    if ($superadminLock === false) {
                        log_audit_action($conn, $user, $user, 'blocked_superadmin_login', null, STATUS_ACTIVE, null, null, 'Another superadmin is already logged in');
                        $_SESSION['form_error'] = 'Another superadmin is already logged in. Please wait for that superadmin to log out.';
                    } else {
                        $_SESSION['form_error'] = 'Unable to verify superadmin availability. Please try again.';
                    }
                    header('Location: login.php');
                    exit;
                }
            }

            $presenceStmt = $conn->prepare('UPDATE users SET last_seen_at = NOW() WHERE id = ?');
            if ($presenceStmt) {
                $presenceStmt->bind_param('i', $user['id']);
                $presenceStmt->execute();
                $presenceStmt->close();
            }
            log_audit_action($conn, $user, $user, 'login_success', null, STATUS_ACTIVE, null, null, 'Password and OTP verified');
            $_SESSION['success'] = 'Login successful! Welcome back.';

            if (!empty($user['must_change_password'])) {
                $_SESSION['force_pwd_change'] = true;
                header('Location: change_password_authenticated.php');
                exit;
            }
            header('Location: dashboard.php');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Login | PRIME.</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'header.php'; ?>
<main class="container auth-page">
    <div class="auth-card">
        <h2>Verify your login</h2>
        <p class="auth-subtitle">Enter the six-digit code sent to your registered email address.</p>
        <?php if ($error !== ''): ?>
            <div class="form-errors" id="alertBox"><?= e($error); ?></div>
        <?php endif; ?>
        <form method="post" action="login_otp.php">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()); ?>">
            <label for="otp">Verification code <span class="req">*</span></label>
            <input id="otp" type="password" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required autofocus>
            <div class="otp-actions">
                <div class="auth-links"><a class="muted-link" href="logout.php">Cancel and return to login</a></div>
                <button type="submit" class="btn btn-primary btn-block">Verify and continue</button>
            </div>
        </form>
    </div>
</main>
<?php include 'footer.php'; ?>
</body>
</html>
