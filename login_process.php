<?php
require_once 'config.php';
require_once 'auth.php';
verify_csrf();

// Debug: Check if session is working
if (session_status() !== PHP_SESSION_ACTIVE) {
    die('Session not active');
}

$identifier = trim($_POST['identifier'] ?? '');
$password = trim($_POST['password'] ?? '');

// Validation
if (empty($identifier) || empty($password)) {
    $_SESSION['form_error'] = "Please fill in all fields.";
    header('Location: login.php');
    exit();
}

// Length validation (8-16 characters)
if (strlen($identifier) < 8 || strlen($identifier) > 16) {
    $_SESSION['form_error'] = "Username/ID must be 8-16 characters long.";
    header('Location: login.php');
    exit();
}

if (strlen($password) < 8 || strlen($password) > 64) {
    $_SESSION['form_error'] = "Password must be 8-64 characters long.";
    header('Location: login.php');
    exit();
}

// Check lockout
if (!empty($_SESSION['lock_until']) && time() < $_SESSION['lock_until']) {
    $_SESSION['form_error'] = "Too many attempts. Please try again later.";
    header('Location: login.php');
    exit();
}

// Prepare statement to check if user exists and get their account state
$stmt = $conn->prepare("SELECT id, username, email, password, first_name, last_name, role, status, deactivated_until, must_change_password FROM users WHERE id_number = ? OR username = ? LIMIT 1");
if (!$stmt) {
    $_SESSION['form_error'] = "Database error. Please try again.";
    header('Location: login.php');
    exit();
}

$stmt->bind_param("ss", $identifier, $identifier);
$stmt->execute();

// Get result
$result = $stmt->get_result();

if ($result->num_rows === 1) {
    // User exists, fetch the row
    $row = $result->fetch_assoc();
    $hashed_password = $row['password'] ?? '';
    $user_id = $row['id'] ?? 0;
    $username = $row['username'] ?? '';
    $first_name = $row['first_name'] ?? '';
    $last_name = $row['last_name'] ?? '';
    $role = $row['role'] ?? ROLE_USER;
    $status = $row['status'] ?? STATUS_ACTIVE;

    if (password_verify($password, $hashed_password)) {
        if ($status !== STATUS_ACTIVE) {
            log_audit_action($conn, $row, $row, 'blocked_login_attempt', $status, $status, null, $row['deactivated_until'] ?? null, 'Deactivated account login attempt');
            $_SESSION['form_error'] = "This account is deactivated. Please contact an administrator.";
            $stmt->close();
            header('Location: login.php');
            exit();
        }

        // Password is correct - clear failed login attempts
        if (isset($_SESSION['failed_logins'][$identifier])) {
            unset($_SESSION['failed_logins'][$identifier]);
        }

        session_regenerate_id(true);

        // Remove any lockout
        if (isset($_SESSION['lock_until'])) {
            unset($_SESSION['lock_until']);
        }
        if (isset($_SESSION['lockout_attempts'])) {
            unset($_SESSION['lockout_attempts']);
        }

        $otp = (string)random_int(100000, 999999);
        $tokenHash = password_hash($otp, PASSWORD_DEFAULT);
        $conn->query('DELETE FROM otp_tokens WHERE user_id = ' . (int)$user_id . ' OR expires_at < NOW()');
        $otpStmt = $conn->prepare("INSERT INTO otp_tokens (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
        if (!$otpStmt) {
            $stmt->close();
            $_SESSION['form_error'] = 'Unable to start email verification. Please try again.';
            header('Location: login.php');
            exit();
        }
        $otpStmt->bind_param('is', $user_id, $tokenHash);
        $otpCreated = $otpStmt->execute();
        $otpStmt->close();

        $email = trim((string)($row['email'] ?? ''));
        $sent = $otpCreated && $email !== '' && @mail(
            $email,
            'PRIME. login verification code',
            "Your PRIME. login verification code is {$otp}. It expires in 10 minutes."
        );
        if (!$sent) {
            $conn->query('DELETE FROM otp_tokens WHERE user_id = ' . (int)$user_id);
            $stmt->close();
            $_SESSION['form_error'] = 'The verification email could not be sent. Please try again.';
            header('Location: login.php');
            exit();
        }

        session_regenerate_id(true);
        $_SESSION['pending_login'] = [
            'user_id' => $user_id,
            'identifier' => $identifier,
            'started_at' => time(),
        ];

        // Clear form error if any
        if (isset($_SESSION['form_error'])) {
            unset($_SESSION['form_error']);
        }

        $stmt->close();
        header('Location: login_otp.php');
        exit();
    } else {
        // Password is incorrect
        $stmt->close();
        
        // Set session variables for lockout tracking
        if (!isset($_SESSION['failed_logins'])) {
            $_SESSION['failed_logins'] = array();
        }
        
        $post_identifier = $identifier;
        
        if (!isset($_SESSION['failed_logins'][$post_identifier])) {
            $_SESSION['failed_logins'][$post_identifier] = 0;
        }
        
        // Increment only if not locked out
        if (empty($_SESSION['lock_until']) || time() > $_SESSION['lock_until']) {
            $_SESSION['failed_logins'][$post_identifier]++;
        }
        
        $failed_count = $_SESSION['failed_logins'][$post_identifier];
        
        // Determine lockout duration based on failed attempt count
        $lockout_duration = 0;
        
        if ($failed_count === 3) {
            // First 3 attempts: 15 second lockout
            $lockout_duration = 15;
        } elseif ($failed_count === 6) {
            // Next 3 attempts: 30 second lockout
            $lockout_duration = 30;
        } elseif ($failed_count >= 9) {
            // 9 or more attempts: 60 second lockout (and stays at 60)
            $lockout_duration = 60;
        }
        
        // Set lockout if we reached 3, 6, or 9+ attempts
        if ($lockout_duration > 0) {
            $_SESSION['lock_until'] = time() + $lockout_duration;
            $_SESSION['lockout_duration'] = $lockout_duration;
        }
        
        $lockout_remaining = isset($_SESSION['lock_until']) ? $_SESSION['lock_until'] - time() : 0;
        
        // Store lockout time in localStorage
        if ($lockout_remaining > 0) {
            echo '<script>localStorage.setItem("lockoutRemaining", ' . intval($lockout_remaining) . ');</script>';
        }
        
        log_audit_action($conn, $row, $row, 'login_failed', $status, $status, null, null, 'Invalid password');
        $_SESSION['show_forgot_for'] = $post_identifier;
        $_SESSION['form_error'] = 'Invalid username or password.';
        header('Location: login.php');
        exit();
    }
} else {
    // User not found
    $stmt->close();
    
    // Set session variables for lockout tracking
    if (!isset($_SESSION['failed_logins'])) {
        $_SESSION['failed_logins'] = array();
    }
    
    $post_identifier = $identifier;
    
    if (!isset($_SESSION['failed_logins'][$post_identifier])) {
        $_SESSION['failed_logins'][$post_identifier] = 0;
    }
    
    // Increment only if not locked out
    if (empty($_SESSION['lock_until']) || time() > $_SESSION['lock_until']) {
        $_SESSION['failed_logins'][$post_identifier]++;
    }
    
    $failed_count = $_SESSION['failed_logins'][$post_identifier];
    
    // Determine lockout duration based on failed attempt count
    $lockout_duration = 0;
    
    if ($failed_count === 3) {
        // First 3 attempts: 15 second lockout
        $lockout_duration = 15;
    } elseif ($failed_count === 6) {
        // Next 3 attempts: 30 second lockout
        $lockout_duration = 30;
    } elseif ($failed_count >= 9) {
        // 9 or more attempts: 60 second lockout (and stays at 60)
        $lockout_duration = 60;
    }
    
    // Set lockout if we reached 3, 6, or 9+ attempts
    if ($lockout_duration > 0) {
        $_SESSION['lock_until'] = time() + $lockout_duration;
        $_SESSION['lockout_duration'] = $lockout_duration;
    }
    
    $lockout_remaining = isset($_SESSION['lock_until']) ? $_SESSION['lock_until'] - time() : 0;
    
    // Store lockout time in localStorage
    if ($lockout_remaining > 0) {
        echo '<script>localStorage.setItem("lockoutRemaining", ' . intval($lockout_remaining) . ');</script>';
    }
    
    log_audit_action($conn, null, null, 'login_failed', null, null, null, null, 'Unknown account: ' . $post_identifier);
    $_SESSION['show_forgot_for'] = $post_identifier;
    $_SESSION['form_error'] = 'Invalid username or password.';
    header('Location: login.php');
    exit();
}

$conn->close();
?>
