<?php
// auth.php - shared authentication, authorization, and audit helpers

const ROLE_USER = 'user';
const ROLE_ADMIN = 'admin';
const ROLE_SUPERADMIN = 'superadmin';

const STATUS_ACTIVE = 'active';
const STATUS_PENDING = 'pending';
const STATUS_DEACTIVATED = 'deactivated';
const SESSION_IDLE_TIMEOUT = 1800;

// System privileges granted by a superadmin to administrators.
// The superadmin role implicitly holds every privilege.
const PRIV_APPROVE = 'approve';
const PRIV_MANAGE_USERS = 'manage_users';
const PRIV_DELETE_USERS = 'delete_users';
const PRIV_MANAGE_ROLES = 'manage_roles';
const PRIV_MANAGE_ADMINS = 'manage_admins';
const PRIV_RESET_PASSWORDS = 'reset_passwords';
const PRIV_VIEW_AUDIT_LOGS = 'view_audit_logs';
const PRIV_MANAGE_PRIVILEGES = 'manage_privileges';
const PRIV_UPDATE_INFO = 'update_info';

const PRIVILEGE_LABELS = [
    PRIV_APPROVE => 'Approve registrations',
    PRIV_MANAGE_USERS => 'Manage users (activate / deactivate)',
    PRIV_DELETE_USERS => 'Delete user accounts',
    PRIV_MANAGE_ROLES => 'Change user roles',
    PRIV_MANAGE_ADMINS => 'Manage administrator accounts',
    PRIV_RESET_PASSWORDS => 'Reset account passwords',
    PRIV_VIEW_AUDIT_LOGS => 'View audit logs',
    PRIV_MANAGE_PRIVILEGES => 'Manage administrator privileges',
    PRIV_UPDATE_INFO => 'Update account information',
];

// Privileges automatically granted to newly created administrators.
const DEFAULT_ADMIN_PRIVILEGES = [
    PRIV_APPROVE,
    PRIV_MANAGE_USERS,
    PRIV_DELETE_USERS,
    PRIV_UPDATE_INFO,
    PRIV_VIEW_AUDIT_LOGS,
];

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function send_account_credentials_email(array $account, string $temporaryPassword): bool {
    $email = trim((string)($account['email'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $firstName = trim((string)($account['first_name'] ?? ''));
    $greeting = $firstName !== '' ? 'Hello ' . $firstName . ',' : 'Hello,';
    $message = $greeting . "\n\n"
        . "Your PRIME. account has been created successfully.\n\n"
        . "Your username and temporary password were provided through a secure, private channel and were not included in this email for security reasons.\n\n"
        . "Please sign in using the credentials issued to you privately and change the temporary password immediately after your first login.\n\n"
        . "If you did not expect this email, contact your administrator.\n";
    $headers = "From: PRIME. <primetechcompany@gmail.com>\r\n"
        . "Reply-To: primetechcompany@gmail.com\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n";

    return @mail($email, 'PRIME. account created', $message, $headers);
}

function ensure_rbac_schema(mysqli $conn): void {
    static $checked = false;
    if ($checked) {
        return;
    }
    $checked = true;

    $columns = [];
    $result = $conn->query("SHOW COLUMNS FROM users");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = true;
        }
    }

    $alter = [];
    if (!isset($columns['role'])) {
        $alter[] = "ADD COLUMN role ENUM('user','admin','superadmin') NOT NULL DEFAULT 'user' AFTER password";
    }
    if (!isset($columns['status'])) {
        $alter[] = "ADD COLUMN status ENUM('pending','active','deactivated') NOT NULL DEFAULT 'pending' AFTER role";
    }
    if (!isset($columns['deactivation_duration'])) {
        $alter[] = "ADD COLUMN deactivation_duration ENUM('1_month','3_months','6_months','manual') DEFAULT NULL AFTER status";
    }
    if (!isset($columns['deactivated_until'])) {
        $alter[] = "ADD COLUMN deactivated_until DATETIME DEFAULT NULL AFTER deactivation_duration";
    }
    if (!isset($columns['status_reason'])) {
        $alter[] = "ADD COLUMN status_reason VARCHAR(255) DEFAULT NULL AFTER deactivated_until";
    }
    if (!isset($columns['status_changed_at'])) {
        $alter[] = "ADD COLUMN status_changed_at DATETIME DEFAULT NULL AFTER status_reason";
    }
    if (!isset($columns['must_change_password'])) {
        $alter[] = "ADD COLUMN must_change_password TINYINT(1) NOT NULL DEFAULT 0 AFTER status_changed_at";
    }
    if (!isset($columns['last_seen_at'])) {
        $alter[] = "ADD COLUMN last_seen_at DATETIME DEFAULT NULL AFTER must_change_password";
    }

    if ($alter) {
        $conn->query('ALTER TABLE users ' . implode(', ', $alter));
    }

    $conn->query("ALTER TABLE users MODIFY COLUMN status ENUM('pending','active','deactivated') NOT NULL DEFAULT 'pending'");

    $conn->query("CREATE TABLE IF NOT EXISTS user_privileges (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        privilege VARCHAR(60) NOT NULL,
        granted_by INT(11) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_user_privilege (user_id, privilege),
        KEY idx_priv_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS audit_logs (
        id INT(11) NOT NULL AUTO_INCREMENT,
        actor_id INT(11) DEFAULT NULL,
        actor_username VARCHAR(100) DEFAULT NULL,
        actor_role VARCHAR(20) DEFAULT NULL,
        target_id INT(11) DEFAULT NULL,
        target_username VARCHAR(100) DEFAULT NULL,
        target_role VARCHAR(20) DEFAULT NULL,
        action VARCHAR(60) NOT NULL,
        previous_status VARCHAR(30) DEFAULT NULL,
        new_status VARCHAR(30) DEFAULT NULL,
        deactivation_duration VARCHAR(30) DEFAULT NULL,
        deactivated_until DATETIME DEFAULT NULL,
        reason VARCHAR(255) DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        user_agent VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_audit_actor (actor_id),
        KEY idx_audit_target (target_id),
        KEY idx_audit_action (action),
        KEY idx_audit_created (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS superadmin_lock (
        lock_name VARCHAR(30) NOT NULL,
        active_user_id INT(11) DEFAULT NULL,
        session_id VARCHAR(255) DEFAULT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (lock_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    $conn->query("INSERT IGNORE INTO superadmin_lock (lock_name) VALUES ('superadmin')");

    $conn->query("CREATE TABLE IF NOT EXISTS otp_tokens (
        id INT(11) NOT NULL AUTO_INCREMENT,
        user_id INT(11) NOT NULL,
        token_hash VARCHAR(255) NOT NULL,
        expires_at DATETIME NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        used_at DATETIME DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_otp_user (user_id),
        KEY idx_otp_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS deletion_requests (
        id INT(11) NOT NULL AUTO_INCREMENT,
        target_id INT(11) NOT NULL,
        target_id_number VARCHAR(20) NOT NULL,
        target_username VARCHAR(100) NOT NULL,
        target_name VARCHAR(255) NOT NULL,
        target_email VARCHAR(150) NOT NULL,
        target_role VARCHAR(20) NOT NULL,
        target_status VARCHAR(30) NOT NULL,
        requested_by INT(11) NOT NULL,
        reason VARCHAR(255) NOT NULL,
        status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
        reviewed_by INT(11) DEFAULT NULL,
        review_reason VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        reviewed_at DATETIME DEFAULT NULL,
        PRIMARY KEY (id),
        KEY idx_deletion_status (status),
        KEY idx_deletion_target (target_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $conn->query("CREATE TABLE IF NOT EXISTS recovery_attempts (
        user_id INT(11) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
        locked_until DATETIME DEFAULT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id, ip_address),
        KEY idx_recovery_locked (locked_until)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");

    $superadminCount = 0;
    $countResult = $conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'superadmin'");
    if ($countResult) {
        $superadminCount = (int)($countResult->fetch_assoc()['total'] ?? 0);
    }

    if ($superadminCount === 0) {
        $firstUserResult = $conn->query("SELECT id, username, role, status FROM users ORDER BY id ASC LIMIT 1");
        $firstUser = $firstUserResult ? $firstUserResult->fetch_assoc() : null;
        if ($firstUser) {
            $targetId = (int)$firstUser['id'];
            $conn->query("UPDATE users SET role = 'superadmin', status = 'active', status_changed_at = NOW() WHERE id = " . $targetId);
            $firstUser['role'] = ROLE_SUPERADMIN;
            $firstUser['status'] = STATUS_ACTIVE;
            log_audit_action($conn, null, $firstUser, 'role_change', STATUS_ACTIVE, STATUS_ACTIVE, null, null, 'Initial Superadmin bootstrap');
        }
    }
}

function reactivate_expired_accounts(mysqli $conn): void {
    $stmt = $conn->prepare("UPDATE users
        SET status = 'active',
            deactivation_duration = NULL,
            deactivated_until = NULL,
            status_reason = NULL,
            status_changed_at = NOW()
        WHERE status = 'deactivated'
          AND deactivation_duration <> 'manual'
          AND deactivated_until IS NOT NULL
          AND deactivated_until <= NOW()");
    if ($stmt) {
        $stmt->execute();
        $stmt->close();
    }
}

function current_app_user(mysqli $conn): ?array {
    if (empty($_SESSION['user_id']) || empty($_SESSION['logged_in'])) {
        return null;
    }

    if (!empty($_SESSION['last_activity']) && time() - (int)$_SESSION['last_activity'] > SESSION_IDLE_TIMEOUT) {
        $expiredUserId = (int)$_SESSION['user_id'];
        if (($_SESSION['role'] ?? '') === ROLE_SUPERADMIN) {
            release_superadmin_lock($conn, $expiredUserId, session_id());
        }
        clear_user_presence($conn, $expiredUserId);
        session_unset();
        session_destroy();
        return null;
    }

    $stmt = $conn->prepare("SELECT id, username, first_name, last_name, role, status, must_change_password FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || $user['status'] !== STATUS_ACTIVE) {
        session_unset();
        session_destroy();
        return null;
    }

    $_SESSION['role'] = $user['role'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['last_activity'] = time();
    $presenceStmt = $conn->prepare('UPDATE users SET last_seen_at = NOW() WHERE id = ?');
    if ($presenceStmt) {
        $presenceStmt->bind_param('i', $user['id']);
        $presenceStmt->execute();
        $presenceStmt->close();
    }
    return $user;
}

function clear_user_presence(mysqli $conn, int $userId): void {
    $stmt = $conn->prepare('UPDATE users SET last_seen_at = NULL WHERE id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }
}

function require_login(mysqli $conn): array {
    $user = current_app_user($conn);
    if (!$user) {
        $_SESSION['form_error'] = 'Please log in to continue.';
        header('Location: login.php');
        exit;
    }
    return $user;
}

function is_admin_role(string $role): bool {
    return in_array($role, [ROLE_ADMIN, ROLE_SUPERADMIN], true);
}

function require_admin(mysqli $conn): array {
    $user = require_login($conn);
    if (!is_admin_role($user['role'])) {
        log_audit_action($conn, $user, null, 'failed_authorization', null, null, null, null, 'Admin access required');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $_SESSION['form_error'] = 'Administrator access is required for that page.';
            header('Location: dashboard.php');
            exit;
        }
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function require_superadmin(mysqli $conn): array {
    $user = require_login($conn);
    if ($user['role'] !== ROLE_SUPERADMIN) {
        log_audit_action($conn, $user, null, 'failed_authorization', null, null, null, null, 'Superadmin access required');
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $_SESSION['form_error'] = 'Superadmin access is required for that page.';
            header('Location: dashboard.php');
            exit;
        }
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function can_manage_account(?mysqli $conn, array $actor, array $target, string $action): bool {
    if ((int)$actor['id'] === (int)$target['id'] && in_array($action, ['delete', 'deactivate', 'role_change'], true)) {
        return false;
    }

    if ((int)$actor['id'] === (int)$target['id'] && $action === 'update_info') {
        return true;
    }

    if ($actor['role'] === ROLE_SUPERADMIN) {
        return true;
    }

    if ($actor['role'] !== ROLE_ADMIN) {
        return false;
    }

    // Administrators can only manage regular user accounts.
    if ($target['role'] !== ROLE_USER) {
        return false;
    }

    // Privilege-gated actions for administrators. When no DB connection is
    // available (e.g. unit tests), fall back to the default admin privileges.
    $privilegeForAction = [
        'activate'    => $target['status'] === STATUS_PENDING ? PRIV_APPROVE : PRIV_MANAGE_USERS,
        'deactivate'  => PRIV_MANAGE_USERS,
        'delete'      => PRIV_DELETE_USERS,
        'role_change' => PRIV_MANAGE_ROLES,
        'update_info' => PRIV_UPDATE_INFO,
    ];
    $allowedPrivileges = $conn !== null ? get_user_privileges($conn, (int)$actor['id']) : DEFAULT_ADMIN_PRIVILEGES;
    if (isset($privilegeForAction[$action]) && !in_array($privilegeForAction[$action], $allowedPrivileges, true)) {
        return false;
    }

    return in_array($action, ['activate', 'deactivate', 'delete', 'role_change', 'update_info'], true);
}

function can_create_role(array $actor, string $role): bool {
    if ($role === ROLE_USER) {
        return is_admin_role($actor['role']);
    }
    if ($role === ROLE_ADMIN) {
        return $actor['role'] === ROLE_SUPERADMIN;
    }
    if ($role === ROLE_SUPERADMIN) {
        return $actor['role'] === ROLE_SUPERADMIN;
    }
    return false;
}

function get_user_by_id(mysqli $conn, int $id): ?array {
    $stmt = $conn->prepare("SELECT id, id_number, username, first_name, middle_name, last_name, extension, birthdate, age, street, barangay, city, province, country, zip, email, role, status, deactivation_duration, deactivated_until, status_reason FROM users WHERE id = ? LIMIT 1");
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $user ?: null;
}

function deactivation_expiration(?string $duration): ?string {
    if ($duration === '1_month') {
        return date('Y-m-d H:i:s', strtotime('+1 month'));
    }
    if ($duration === '3_months') {
        return date('Y-m-d H:i:s', strtotime('+3 months'));
    }
    if ($duration === '6_months') {
        return date('Y-m-d H:i:s', strtotime('+6 months'));
    }
    return null;
}

function duration_label(?string $duration): string {
    $labels = [
        '1_month' => '1 month',
        '3_months' => '3 months',
        '6_months' => '6 months',
        'manual' => 'Until manually reactivated',
    ];
    return $labels[$duration] ?? '';
}

function acquire_superadmin_lock(mysqli $conn, int $userId, string $sessionId): ?bool {
    if (!$conn->begin_transaction()) {
        return null;
    }

    $result = $conn->query("SELECT active_user_id FROM superadmin_lock WHERE lock_name = 'superadmin' FOR UPDATE");
    if (!$result) {
        $conn->rollback();
        return null;
    }

    $lock = $result->fetch_assoc();
    $activeUserId = $lock['active_user_id'] ?? null;
    if ($activeUserId !== null && (int)$activeUserId !== $userId) {
        $conn->rollback();
        return false;
    }

    $stmt = $conn->prepare("UPDATE superadmin_lock SET active_user_id = ?, session_id = ? WHERE lock_name = 'superadmin'");
    if (!$stmt) {
        $conn->rollback();
        return null;
    }

    $stmt->bind_param('is', $userId, $sessionId);
    $ok = $stmt->execute() && $conn->commit();
    $stmt->close();
    if (!$ok) {
        $conn->rollback();
        return null;
    }
    return true;
}

function release_superadmin_lock(mysqli $conn, int $userId, string $sessionId): void {
    $stmt = $conn->prepare("UPDATE superadmin_lock
        SET active_user_id = NULL, session_id = NULL
        WHERE lock_name = 'superadmin' AND active_user_id = ? AND session_id = ?");
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('is', $userId, $sessionId);
    $stmt->execute();
    $stmt->close();
}

function log_audit_action(
    mysqli $conn,
    ?array $actor,
    ?array $target,
    string $action,
    ?string $previousStatus,
    ?string $newStatus,
    ?string $duration,
    ?string $expiresAt,
    ?string $reason = null
): bool {
    $actorId = $actor['id'] ?? null;
    $actorUsername = $actor['username'] ?? 'system';
    $actorRole = $actor['role'] ?? 'system';
    $targetId = $target['id'] ?? null;
    $targetUsername = $target['username'] ?? null;
    $targetRole = $target['role'] ?? null;
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null;

    $stmt = $conn->prepare("INSERT INTO audit_logs (
        actor_id, actor_username, actor_role,
        target_id, target_username, target_role,
        action, previous_status, new_status,
        deactivation_duration, deactivated_until,
        reason, ip_address, user_agent
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$stmt) {
        return false;
    }

    $stmt->bind_param(
        'ississssssssss',
        $actorId,
        $actorUsername,
        $actorRole,
        $targetId,
        $targetUsername,
        $targetRole,
        $action,
        $previousStatus,
        $newStatus,
        $duration,
        $expiresAt,
        $reason,
        $ip,
        $agent
    );
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(400);
        exit('Invalid request token');
    }
}

function get_user_privileges(mysqli $conn, int $userId): array {
    $privileges = [];
    $stmt = $conn->prepare('SELECT privilege FROM user_privileges WHERE user_id = ?');
    if (!$stmt) {
        return $privileges;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $privileges[] = $row['privilege'];
    }
    $stmt->close();
    return $privileges;
}

function user_has_privilege(?mysqli $conn, array $user, string $privilege): bool {
    if (($user['role'] ?? '') === ROLE_SUPERADMIN) {
        return true;
    }
    if (($user['role'] ?? '') !== ROLE_ADMIN) {
        return false;
    }
    return in_array($privilege, get_user_privileges($conn, (int)$user['id']), true);
}

function require_privilege(mysqli $conn, array $user, string $privilege): array {
    if (!user_has_privilege($conn, $user, $privilege)) {
        log_audit_action($conn, $user, null, 'failed_authorization', null, null, null, null, 'Missing privilege: ' . $privilege);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            $_SESSION['form_error'] = 'You do not have permission to view that page.';
            header('Location: dashboard.php');
            exit;
        }
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function set_user_privileges(mysqli $conn, int $userId, array $privileges, ?array $grantedBy = null, ?string $reason = null): void {
    $valid = array_intersect($privileges, array_keys(PRIVILEGE_LABELS));
    $grantedById = $grantedBy['id'] ?? null;
    $del = $conn->prepare('DELETE FROM user_privileges WHERE user_id = ?');
    $del->bind_param('i', $userId);
    $del->execute();
    $del->close();
    $stmt = $conn->prepare('INSERT INTO user_privileges (user_id, privilege, granted_by) VALUES (?, ?, ?)');
    foreach ($valid as $privilege) {
        $stmt->bind_param('isi', $userId, $privilege, $grantedById);
        $stmt->execute();
    }
    $stmt->close();
}

function grant_default_privileges(mysqli $conn, int $userId, string $role): void {
    if ($role !== ROLE_ADMIN) {
        return;
    }
    set_user_privileges($conn, $userId, DEFAULT_ADMIN_PRIVILEGES);
}

function verify_actor_password(mysqli $conn, array $actor, string $submitted): bool {
    if ($submitted === '') {
        return false;
    }
    $stmt = $conn->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return false;
    }
    $stmt->bind_param('i', $actor['id']);
    $stmt->execute();
    $hash = $stmt->get_result()->fetch_assoc()['password'] ?? '';
    $stmt->close();
    return $hash !== '' && password_verify($submitted, $hash);
}

// Verify the session user's own password (typed-confirmation).
function require_actor_confirmation(mysqli $conn, array $actor): void {
    $submitted = $_POST['confirm_password'] ?? '';
    if (!verify_actor_password($conn, $actor, $submitted)) {
        log_audit_action($conn, $actor, null, 'failed_authorization', null, null, null, null, 'Password confirmation failed');
        $_SESSION['form_error'] = 'Your password did not match. Action cancelled.';
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if ($referer !== '' && str_starts_with($referer, 'http')) {
            header('Location: ' . $referer);
        } else {
            header('Location: admin_users.php');
        }
        exit;
    }
}

// Mask a sensitive identifier so only the first two and last two characters
// are visible, e.g. 20200995 -> 20****95.
function mask_value(string $value): string {
    $len = strlen($value);
    if ($len <= 4) {
        return str_repeat('*', $len);
    }
    return substr($value, 0, 2) . str_repeat('*', $len - 4) . substr($value, -2);
}

function mask_id_number(string $value): string {
    $plain = preg_replace('/[^0-9]/', '', $value);
    $masked = mask_value($plain);
    if (strlen($plain) === 8) {
        return substr($masked, 0, 2) . '****' . substr($masked, -2);
    }
    return $masked;
}

function next_static_id_number(mysqli $conn): string {
    $year = (int) date('Y');
    $pattern = $year . '-%';
    $stmt = $conn->prepare('SELECT id_number FROM users WHERE id_number LIKE ? ORDER BY id_number DESC LIMIT 1');
    $stmt->bind_param('s', $pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    $nextNumber = 1;
    if ($row && !empty($row['id_number'])) {
        $digits = (int) substr($row['id_number'], -4);
        if ($digits > 0) {
            $nextNumber = $digits + 1;
        }
    }

    return sprintf('%d-%04d', $year, $nextNumber);
}

function generate_default_password(): string {
    $prefix = 'TMP';
    $year = (int) date('Y');
    $rand = strtoupper(bin2hex(random_bytes(3)));
    return sprintf('%s-%d-%s', $prefix, $year, $rand);
}

function security_question_options(): array {
    return [
        'Who is your best friend in Elementary?',
        'What is your mother\'s maiden name?',
        'What was the name of your first pet?',
        'What is the name of your favorite pet?',
        'What is your favorite book?',
        'What city were you born in?',
        'Who is your favorite teacher in high school?',
        'What is your favorite food?',
        'What was your childhood nickname?',
        'What was the name of your elementary school?',
        'What is the nickname of your first best friend?',
        'What was your favorite childhood subject?',
    ];
}

function require_actor_current_password(mysqli $conn, array $actor, string $submitted): bool {
    return verify_actor_password($conn, $actor, $submitted);
}
?>
