<?php
require_once 'config.php';
require_once 'admin_shell.php';
$actor = require_superadmin($conn);

$search = trim($_GET['search'] ?? '');
$actionFilter = trim($_GET['action'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');

$where = [];
$params = [];
$types = '';

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

$sql = "SELECT actor_id, actor_username, actor_role, target_id, target_username, target_role,
        action, previous_status, new_status, deactivation_duration, deactivated_until,
        reason, ip_address, user_agent, created_at
        FROM audit_logs";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= ' ORDER BY created_at DESC, id DESC LIMIT 200';

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

admin_shell_start($actor, 'Audit Logs', 'Superadmin review of the 200 most recent entries');
?>

<section class="card">
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
        <button type="submit">Filter</button>
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
                    <td><?= e($log['created_at']); ?></td>
                    <td>
                        <strong><?= e($log['actor_username'] ?? 'system'); ?></strong>
                        <span>#<?= e($log['actor_id'] ?? ''); ?> <?= e($log['actor_role'] ?? ''); ?></span>
                    </td>
                    <td>
                        <strong><?= e($log['target_username'] ?? 'N/A'); ?></strong>
                        <span>#<?= e($log['target_id'] ?? ''); ?> <?= e($log['target_role'] ?? ''); ?></span>
                    </td>
                    <td><?= e(str_replace('_', ' ', $log['action'])); ?></td>
                    <td><?= e($log['previous_status'] ?? ''); ?> → <?= e($log['new_status'] ?? ''); ?></td>
                    <td>
                        <?= e(duration_label($log['deactivation_duration'])); ?>
                        <?php if ($log['deactivated_until']): ?>
                            <span>until <?= e($log['deactivated_until']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span><?= e($log['reason'] ?? ''); ?></span>
                        <span><?= e($log['ip_address'] ?? ''); ?></span>
                        <span><?= e($log['user_agent'] ?? ''); ?></span>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?>
                <tr><td colspan="7">No audit entries found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php admin_shell_end(); ?>
