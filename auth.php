<?php
// auth.php - shared authentication, authorization, and audit helpers

const ROLE_USER = 'user';
const ROLE_ADMIN = 'admin';
const ROLE_SUPERADMIN = 'superadmin';

const STATUS_ACTIVE = 'active';
const STATUS_DEACTIVATED = 'deactivated';

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
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
        $alter[] = "ADD COLUMN status ENUM('active','deactivated') NOT NULL DEFAULT 'active' AFTER role";
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

    if ($alter) {
        $conn->query('ALTER TABLE users ' . implode(', ', $alter));
    }

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

    $stmt = $conn->prepare("SELECT id, username, first_name, last_name, role, status FROM users WHERE id = ? LIMIT 1");
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
    return $user;
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
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function require_superadmin(mysqli $conn): array {
    $user = require_login($conn);
    if ($user['role'] !== ROLE_SUPERADMIN) {
        log_audit_action($conn, $user, null, 'failed_authorization', null, null, null, null, 'Superadmin access required');
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function can_manage_account(array $actor, array $target, string $action): bool {
    if ((int)$actor['id'] === (int)$target['id'] && in_array($action, ['delete', 'deactivate', 'role_change'], true)) {
        return false;
    }

    if ($actor['role'] === ROLE_SUPERADMIN) {
        return true;
    }

    if ($actor['role'] !== ROLE_ADMIN) {
        return false;
    }

    if ($target['role'] !== ROLE_USER) {
        return false;
    }

    return in_array($action, ['activate', 'deactivate', 'delete'], true);
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
    $stmt = $conn->prepare("SELECT id, id_number, username, first_name, last_name, email, role, status, deactivation_duration, deactivated_until, status_reason FROM users WHERE id = ? LIMIT 1");
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
?>
