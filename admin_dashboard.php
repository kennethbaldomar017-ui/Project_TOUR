<?php
require_once 'config.php';
require_once 'admin_shell.php';
$actor = require_admin($conn);

function count_users(mysqli $conn, string $where = ''): int {
    $sql = 'SELECT COUNT(*) AS total FROM users' . ($where !== '' ? ' WHERE ' . $where : '');
    $result = $conn->query($sql);
    return $result ? (int)($result->fetch_assoc()['total'] ?? 0) : 0;
}

$totalUsers = count_users($conn);
$activeUsers = count_users($conn, "status = 'active'");
$deactivatedUsers = count_users($conn, "status = 'deactivated'");
$adminUsers = count_users($conn, "role IN ('admin','superadmin')");

$roleCounts = [ROLE_USER => 0, ROLE_ADMIN => 0, ROLE_SUPERADMIN => 0];
$roleResult = $conn->query('SELECT role, COUNT(*) AS total FROM users GROUP BY role');
if ($roleResult) {
    while ($row = $roleResult->fetch_assoc()) {
        if (array_key_exists($row['role'], $roleCounts)) {
            $roleCounts[$row['role']] = (int)$row['total'];
        }
    }
}

$actionCounts = [];
$actionResult = $conn->query('SELECT action, COUNT(*) AS total FROM audit_logs GROUP BY action ORDER BY total DESC LIMIT 7');
if ($actionResult) {
    while ($row = $actionResult->fetch_assoc()) {
        $actionCounts[$row['action']] = (int)$row['total'];
    }
}

$recentLogs = [];
$recentResult = $conn->query('SELECT actor_username, actor_role, target_username, action, created_at FROM audit_logs ORDER BY created_at DESC, id DESC LIMIT 8');
if ($recentResult) {
    $recentLogs = $recentResult->fetch_all(MYSQLI_ASSOC);
}

$roleColors = [
    ROLE_USER => '#4b6cf6',
    ROLE_ADMIN => '#7c3aed',
    ROLE_SUPERADMIN => '#17c1c8',
];

/**
 * Build the stroke-dasharray segments of a donut chart from a label => count map.
 */
function donut_segments(array $counts, float $circumference): array {
    $total = array_sum($counts);
    $segments = [];
    $offset = 0.0;
    foreach ($counts as $label => $count) {
        if ($total <= 0 || $count <= 0) {
            continue;
        }
        $length = $circumference * ($count / $total);
        $segments[] = ['label' => $label, 'length' => $length, 'offset' => $offset];
        $offset += $length;
    }
    return $segments;
}

$radius = 60.0;
$circumference = 2 * M_PI * $radius;
$segments = donut_segments($roleCounts, $circumference);
$maxAction = $actionCounts ? max($actionCounts) : 0;

admin_shell_start($actor, 'Dashboard', 'Overview of accounts and activity for ' . ucfirst($actor['role']) . ' access');
?>

<section class="stat-grid">
    <div class="card stat">
        <div>
            <p class="stat-label">Total Accounts</p>
            <p class="stat-value"><?= number_format($totalUsers); ?></p>
        </div>
        <div class="stat-icon tone-primary"><?= admin_icon('users'); ?></div>
    </div>
    <div class="card stat">
        <div>
            <p class="stat-label">Active Accounts</p>
            <p class="stat-value"><?= number_format($activeUsers); ?></p>
        </div>
        <div class="stat-icon tone-teal"><?= admin_icon('user'); ?></div>
    </div>
    <div class="card stat">
        <div>
            <p class="stat-label">Deactivated</p>
            <p class="stat-value"><?= number_format($deactivatedUsers); ?></p>
        </div>
        <div class="stat-icon tone-pink"><?= admin_icon('ban'); ?></div>
    </div>
    <div class="card stat">
        <div>
            <p class="stat-label">Admin Accounts</p>
            <p class="stat-value"><?= number_format($adminUsers); ?></p>
        </div>
        <div class="stat-icon tone-amber"><?= admin_icon('shield'); ?></div>
    </div>
</section>

<section class="chart-grid">
    <div class="card">
        <div class="card-head">
            <h2>Accounts by Role</h2>
        </div>
        <div class="donut-wrap">
            <svg class="donut" viewBox="0 0 160 160" role="img" aria-label="Accounts by role">
                <circle cx="80" cy="80" r="<?= $radius; ?>" fill="none" stroke="#f0f1f7" stroke-width="18"></circle>
                <?php foreach ($segments as $segment): ?>
                    <circle cx="80" cy="80" r="<?= $radius; ?>" fill="none"
                            stroke="<?= e($roleColors[$segment['label']]); ?>" stroke-width="18"
                            stroke-dasharray="<?= round($segment['length'], 2); ?> <?= round($circumference - $segment['length'], 2); ?>"
                            stroke-dashoffset="<?= round(-$segment['offset'], 2); ?>"
                            transform="rotate(-90 80 80)"></circle>
                <?php endforeach; ?>
                <text class="donut-center-value" x="80" y="78" text-anchor="middle"><?= number_format($totalUsers); ?></text>
                <text class="donut-center-label" x="80" y="94" text-anchor="middle">ACCOUNTS</text>
            </svg>
            <ul class="legend">
                <?php foreach ($roleCounts as $role => $count): ?>
                    <li>
                        <span class="dot" style="background: <?= e($roleColors[$role]); ?>"></span>
                        <?= e(ucfirst($role)); ?>
                        <span class="legend-value"><?= number_format($count); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="card">
        <div class="card-head">
            <h2>Audit Activity by Action</h2>
            <?php if ($actor['role'] === ROLE_SUPERADMIN): ?>
                <a class="btn btn-ghost" href="audit_logs.php"><?= admin_icon('logs'); ?>View logs</a>
            <?php endif; ?>
        </div>
        <?php if ($actionCounts): ?>
            <div class="bars">
                <?php foreach ($actionCounts as $action => $count): ?>
                    <div class="bar-row">
                        <span><?= e(ucfirst(str_replace('_', ' ', $action))); ?></span>
                        <span class="bar-track">
                            <span class="bar-fill<?= $action === 'failed_authorization' ? ' pink' : ''; ?>" style="width: <?= $maxAction > 0 ? round($count / $maxAction * 100, 1) : 0; ?>%"></span>
                        </span>
                        <span class="bar-count"><?= number_format($count); ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p class="empty-state">No audit activity recorded yet.</p>
        <?php endif; ?>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <h2>Recent Activity</h2>
        <a class="btn" href="admin_users.php"><?= admin_icon('users'); ?>Manage users</a>
    </div>
    <div class="table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Actor</th>
                    <th>Target</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($recentLogs as $log): ?>
                <tr>
                    <td><?= e($log['created_at']); ?></td>
                    <td>
                        <strong><?= e($log['actor_username'] ?? 'system'); ?></strong>
                        <span><?= e($log['actor_role'] ?? ''); ?></span>
                    </td>
                    <td><?= e($log['target_username'] ?? 'N/A'); ?></td>
                    <td><?= e(ucfirst(str_replace('_', ' ', $log['action']))); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recentLogs): ?>
                <tr><td colspan="4" class="muted-text">No recent activity.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php admin_shell_end(); ?>
