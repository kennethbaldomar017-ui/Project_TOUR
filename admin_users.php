<?php
require_once 'config.php';
$actor = require_admin($conn);

$search = trim($_GET['search'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(username LIKE ? OR email LIKE ? OR id_number LIKE ? OR first_name LIKE ? OR last_name LIKE ?)";
    $like = '%' . $search . '%';
    for ($i = 0; $i < 5; $i++) {
        $params[] = $like;
        $types .= 's';
    }
}
if (in_array($roleFilter, [ROLE_USER, ROLE_ADMIN, ROLE_SUPERADMIN], true)) {
    $where[] = "role = ?";
    $params[] = $roleFilter;
    $types .= 's';
}
if (in_array($statusFilter, [STATUS_ACTIVE, STATUS_DEACTIVATED], true)) {
    $where[] = "status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}

$sql = "SELECT id, id_number, username, first_name, last_name, email, role, status, deactivation_duration, deactivated_until, status_reason FROM users";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY FIELD(role, 'superadmin', 'admin', 'user'), username";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | PRIME.</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <?php if(isset($_SESSION['form_error'])): ?>
        <div class="form-errors" id="alertBox"><?= e($_SESSION['form_error']); unset($_SESSION['form_error']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['success'])): ?>
        <div class="form-success" id="alertBox"><?= e($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <main class="container admin-page">
        <section class="admin-panel">
            <div class="admin-heading">
                <div>
                    <h2>User Management</h2>
                    <p><?= e(ucfirst($actor['role'])); ?> access</p>
                </div>
                <?php if ($actor['role'] === ROLE_SUPERADMIN): ?>
                    <a class="btn compact-link" href="create_admin.php">Create Admin</a>
                <?php endif; ?>
            </div>
            <div class="admin-note">
                Active accounts show a Deactivate control. Deactivated accounts show an Activate control. Admin users can only manage regular User accounts; Superadmins can manage Users and Admins.
            </div>

            <form class="filter-bar" method="get">
                <input type="search" name="search" value="<?= e($search); ?>" placeholder="Search users">
                <select name="role">
                    <option value="">All roles</option>
                    <option value="user" <?= $roleFilter === ROLE_USER ? 'selected' : ''; ?>>User</option>
                    <option value="admin" <?= $roleFilter === ROLE_ADMIN ? 'selected' : ''; ?>>Admin</option>
                    <option value="superadmin" <?= $roleFilter === ROLE_SUPERADMIN ? 'selected' : ''; ?>>Superadmin</option>
                </select>
                <select name="status">
                    <option value="">All statuses</option>
                    <option value="active" <?= $statusFilter === STATUS_ACTIVE ? 'selected' : ''; ?>>Active</option>
                    <option value="deactivated" <?= $statusFilter === STATUS_DEACTIVATED ? 'selected' : ''; ?>>Deactivated</option>
                </select>
                <button type="submit">Filter</button>
            </form>

            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Account Controls</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Duration</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                            $canActivate = can_manage_account($actor, $user, 'activate') && $user['status'] !== STATUS_ACTIVE;
                            $canDeactivate = can_manage_account($actor, $user, 'deactivate') && $user['status'] !== STATUS_DEACTIVATED;
                            $canDelete = can_manage_account($actor, $user, 'delete');
                            $canRoleChange = $actor['role'] === ROLE_SUPERADMIN && can_manage_account($actor, $user, 'role_change');
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($user['username']); ?></strong>
                                <span><?= e(trim($user['first_name'] . ' ' . $user['last_name'])); ?> · <?= e($user['email']); ?></span>
                            </td>
                            <td>
                                <div class="action-stack">
                                    <?php if ($canActivate): ?>
                                        <form method="post" action="admin_action.php" onsubmit="return confirm('Activate this account?');">
                                            <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                            <input type="hidden" name="target_id" value="<?= (int)$user['id']; ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="text" name="reason" placeholder="Reason optional">
                                            <button type="submit">Activate</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canDeactivate): ?>
                                        <form method="post" action="admin_action.php" onsubmit="return confirm('Deactivate this account for the selected duration?');">
                                            <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                            <input type="hidden" name="target_id" value="<?= (int)$user['id']; ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <select name="duration" required aria-label="Deactivation duration">
                                                <option value="1_month">1 month</option>
                                                <option value="3_months">3 months</option>
                                                <option value="6_months">6 months</option>
                                                <option value="manual">Until manually reactivated</option>
                                            </select>
                                            <input type="text" name="reason" placeholder="Reason optional">
                                            <button type="submit">Deactivate</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canRoleChange): ?>
                                        <form method="post" action="admin_action.php" onsubmit="return confirm('Change this account role?');">
                                            <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                            <input type="hidden" name="target_id" value="<?= (int)$user['id']; ?>">
                                            <input type="hidden" name="action" value="role_change">
                                            <select name="new_role" required aria-label="New role">
                                                <option value="user" <?= $user['role'] === ROLE_USER ? 'selected' : ''; ?>>User</option>
                                                <option value="admin" <?= $user['role'] === ROLE_ADMIN ? 'selected' : ''; ?>>Admin</option>
                                                <option value="superadmin" <?= $user['role'] === ROLE_SUPERADMIN ? 'selected' : ''; ?>>Superadmin</option>
                                            </select>
                                            <input type="text" name="reason" placeholder="Reason optional">
                                            <button type="submit">Change Role</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                        <form method="post" action="admin_action.php" onsubmit="return confirm('Delete this account permanently?');">
                                            <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                            <input type="hidden" name="target_id" value="<?= (int)$user['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="text" name="reason" placeholder="Reason optional">
                                            <button class="danger" type="submit">Delete</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if (!$canActivate && !$canDeactivate && !$canDelete && !$canRoleChange): ?>
                                        <span class="muted-text">No permitted actions for your role</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td><span class="badge role-<?= e($user['role']); ?>"><?= e(ucfirst($user['role'])); ?></span></td>
                            <td><span class="badge status-<?= e($user['status']); ?>"><?= e(ucfirst($user['status'])); ?></span></td>
                            <td>
                                <?php if ($user['status'] === STATUS_DEACTIVATED): ?>
                                    <?= e(duration_label($user['deactivation_duration'])); ?>
                                    <?php if ($user['deactivated_until']): ?>
                                        <span>until <?= e($user['deactivated_until']); ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span>None</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>
    <script src="js/admin.js"></script>
</body>
</html>
