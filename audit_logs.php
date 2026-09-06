<?php
require_once 'config.php';
$actor = require_admin($conn);
$canViewAuditLogs = user_has_privilege($conn, $actor, PRIV_VIEW_AUDIT_LOGS);

$search = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$monthFilter = trim($_GET['month'] ?? '');
$dateFilter = trim($_GET['date'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;

$where = [];
$params = [];
$types = '';

if ($actor['role'] === ROLE_ADMIN) {
    $where[] = "(actor_role IN ('admin', 'user') OR target_role IN ('admin', 'user'))";
} elseif ($actor['role'] === ROLE_USER) {
    $where[] = "(actor_role = 'user' OR target_role = 'user')";
}

if ($search !== '') {
    $where[] = "(actor_username LIKE ? OR target_username LIKE ? OR reason LIKE ? OR ip_address LIKE ?)";
    $like = '%' . $search . '%';
    for ($i = 0; $i < 4; $i++) {
        $params[] = $like;
        $types .= 's';
    }
}
if ($actionFilter !== '') {
    $where[] = "action = ?";
    $params[] = $actionFilter;
    $types .= 's';
}
if (in_array($roleFilter, [ROLE_USER, ROLE_ADMIN, ROLE_SUPERADMIN], true)) {
    $where[] = "(actor_role = ? OR target_role = ?)";
    $params[] = $roleFilter;
    $params[] = $roleFilter;
    $types .= 'ss';
}
if (preg_match('/^\d{4}-\d{2}$/', $monthFilter)) {
    $where[] = "DATE_FORMAT(created_at, '%Y-%m') = ?";
    $params[] = $monthFilter;
    $types .= 's';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFilter)) {
    $where[] = 'DATE(created_at) = ?';
    $params[] = $dateFilter;
    $types .= 's';
}

$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
$countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM audit_logs' . $whereSql);
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalLogs = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$totalPages = max(1, (int)ceil($totalLogs / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT actor_id, actor_username, actor_role, target_id, target_username, target_role,
        action, previous_status, new_status, deactivation_duration, deactivated_until,
        reason, ip_address, user_agent, created_at
        FROM audit_logs";
$sql .= $whereSql . ' ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?';
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$actions = [];
$actionResult = $conn->query('SELECT DISTINCT action FROM audit_logs ORDER BY action');
if ($actionResult) {
    while ($row = $actionResult->fetch_assoc()) {
        $actions[] = $row['action'];
    }
}

$failedLoginAccounts = [];
$failedLoginSql = "SELECT COALESCE(actor_username, target_username) AS account_name,
        COALESCE(actor_role, target_role) AS account_role,
        COUNT(*) AS attempts, MAX(created_at) AS last_attempt
        FROM audit_logs
        WHERE action = 'login_failed'
          AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
          AND (actor_username IS NOT NULL OR target_username IS NOT NULL)";
if ($actor['role'] === ROLE_ADMIN) {
    $failedLoginSql .= " AND COALESCE(actor_role, target_role) IN ('admin', 'user')";
}
$failedLoginSql .= " GROUP BY account_name, account_role HAVING COUNT(*) >= 3 ORDER BY attempts DESC, last_attempt DESC LIMIT 10";
if ($canViewAuditLogs) {
    $failedLoginResult = $conn->query($failedLoginSql);
    if ($failedLoginResult) {
        $failedLoginAccounts = $failedLoginResult->fetch_all(MYSQLI_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs | PRIME.</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <?php if (!$canViewAuditLogs): ?>
        <main class="container admin-page audit-page">
            <section class="admin-panel admin-data-page permission-empty-page">
                <p class="page-eyebrow">SECURITY MONITORING</p>
                <h2>Audit Logs unavailable</h2>
                <p>Your account can see this area, but the <strong>View audit logs</strong> privilege has not been assigned.</p>
                <div class="empty-state"><strong>No audit entries available</strong><span>Ask a superadmin to grant the required privilege.</span></div>
            </section>
        </main>
    <?php else: ?>
    <main class="container admin-page audit-page">
        <section class="admin-panel admin-data-page">
            <div class="admin-heading">
                <div>
                    <p class="page-eyebrow">SECURITY MONITORING</p>
                    <h2>Audit Logs</h2>
                    <p>Trace account changes, approvals, and security events across the system.</p>
                </div>
                <span class="page-count"><strong><?= number_format($totalLogs); ?></strong> events</span>
            </div>

            <section class="failed-login-panel" aria-label="Repeated failed logins">
                <div class="failed-login-heading">
                    <div>
                        <h3>Repeated failed logins</h3>
                        <p>Accounts with three or more failed sign-in attempts in the last 24 hours.</p>
                    </div>
                    <span class="failed-login-window">Last 24 hours</span>
                </div>
                <?php if ($failedLoginAccounts): ?>
                    <div class="failed-login-list">
                        <?php foreach ($failedLoginAccounts as $failedLogin): ?>
                            <div class="failed-login-row">
                                <span class="failed-login-account"><strong><?= e($failedLogin['account_name']); ?></strong><small><?= e(ucfirst($failedLogin['account_role'] ?? 'account')); ?></small></span>
                                <span class="failed-login-count"><?= number_format((int)$failedLogin['attempts']); ?> attempts</span>
                                <time datetime="<?= e($failedLogin['last_attempt']); ?>">Last: <?= e($failedLogin['last_attempt']); ?></time>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="failed-login-empty">No account has reached the repeated-failure threshold.</div>
                <?php endif; ?>
            </section>

            <form class="filter-bar" method="get">
                <input type="search" name="search" value="<?= e($search); ?>" placeholder="Search actor, target, reason, IP">
                <select name="action">
                    <option value="">All actions</option>
                    <?php foreach ($actions as $action): ?>
                        <option value="<?= e($action); ?>" <?= $actionFilter === $action ? 'selected' : ''; ?>><?= e(str_replace('_', ' ', ucfirst($action))); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="role">
                    <option value="">All roles</option>
                    <option value="user" <?= $roleFilter === ROLE_USER ? 'selected' : ''; ?>>User</option>
                    <option value="admin" <?= $roleFilter === ROLE_ADMIN ? 'selected' : ''; ?>>Admin</option>
                    <option value="superadmin" <?= $roleFilter === ROLE_SUPERADMIN ? 'selected' : ''; ?>>Superadmin</option>
                </select>
                <input type="date" name="date" value="<?= e($dateFilter); ?>" aria-label="Filter by date">
                <button type="submit" class="btn">Filter</button>
            </form>

            <div class="table-wrap">
                <table class="admin-table audit-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Actor</th>
                            <th>Target</th>
                            <th>Action</th>
                            <th>Status</th>
                            <th>Duration</th>
                            <th>Context</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="audit-date"><?= e(date('Y-m-d', strtotime($log['created_at']))); ?><span><?= e(date('H:i:s', strtotime($log['created_at']))); ?></span></td>
                            <td>
                                <strong><?= e($log['actor_username'] ?? 'system'); ?></strong>
                                <span>#<?= e($log['actor_id'] ?? ''); ?> <?= e($log['actor_role'] ?? ''); ?></span>
                            </td>
                            <td>
                                <strong><?= e($log['target_username'] ?? 'N/A'); ?></strong>
                                <span>#<?= e($log['target_id'] ?? ''); ?> <?= e($log['target_role'] ?? ''); ?></span>
                            </td>
                            <td><span class="audit-action audit-action-<?= e($log['action']); ?>"><?= e(str_replace('_', ' ', $log['action'])); ?></span></td>
                            <td class="audit-status"><?= e($log['previous_status'] ?? ''); ?> <b>&rarr;</b> <?= e($log['new_status'] ?? ''); ?></td>
                            <td>
                                <?= e(duration_label($log['deactivation_duration'])); ?>
                                <?php if ($log['deactivated_until']): ?>
                                    <span>until <?= e($log['deactivated_until']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="audit-context" title="<?= e($log['user_agent'] ?? ''); ?>">
                                <strong><?= e($log['reason'] ?: 'No reason provided'); ?></strong>
                                <span><?= e($log['ip_address'] ?: 'Unknown IP'); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$logs): ?>
                        <tr><td class="empty-state" colspan="7"><strong>No audit entries found</strong><span>Try changing the filters or check back after more system activity.</span></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="Audit log pages">
                    <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                        <a class="<?= $pageNumber === $page ? 'active' : ''; ?>" href="<?= e('?' . http_build_query(array_merge($_GET, ['page' => $pageNumber]))); ?>"><?= $pageNumber; ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        </section>
    </main>
    <?php endif; ?>

    <?php include 'footer.php'; ?>
</body>
</html>
