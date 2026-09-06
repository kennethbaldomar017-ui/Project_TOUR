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
if (in_array($statusFilter, [STATUS_PENDING, STATUS_ACTIVE, STATUS_DEACTIVATED], true)) {
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

$userCounts = ['total' => count($users), 'active' => 0, 'pending' => 0, 'deactivated' => 0];
foreach ($users as $listedUser) {
    if (isset($userCounts[$listedUser['status']])) {
        $userCounts[$listedUser['status']]++;
    }
}

$token = csrf_token();

$canResetPasswords = user_has_privilege($conn, $actor, PRIV_RESET_PASSWORDS);
$isSuperadmin = $actor['role'] === ROLE_SUPERADMIN;
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

    <main class="container admin-page users-page">
        <section class="admin-panel admin-data-page">
            <div class="admin-heading">
                <div>
                    <p class="page-eyebrow">ACCOUNT DIRECTORY</p>
                    <h2>User Management</h2>
                    <p>Review accounts, approvals, roles, and account status in one place.</p>
                </div>
                <?php if ($isSuperadmin): ?>
                    <a class="btn compact-link" href="create_admin.php">Create Admin</a>
                <?php endif; ?>
            </div>
            <div class="admin-stat-strip">
                <span><strong><?= number_format($userCounts['total']); ?></strong><small>Visible accounts</small></span>
                <span><strong><?= number_format($userCounts['active']); ?></strong><small>Active</small></span>
                <span><strong><?= number_format($userCounts['pending']); ?></strong><small>Pending</small></span>
                <span><strong><?= number_format($userCounts['deactivated']); ?></strong><small>Deactivated</small></span>
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
                    <option value="pending" <?= $statusFilter === STATUS_PENDING ? 'selected' : ''; ?>>Pending approval</option>
                    <option value="active" <?= $statusFilter === STATUS_ACTIVE ? 'selected' : ''; ?>>Active</option>
                    <option value="deactivated" <?= $statusFilter === STATUS_DEACTIVATED ? 'selected' : ''; ?>>Deactivated</option>
                </select>
                <button type="submit" class="btn">Filter</button>
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
                            $canActivate = can_manage_account($conn, $actor, $user, 'activate') && $user['status'] !== STATUS_ACTIVE;
                            $canDeactivate = can_manage_account($conn, $actor, $user, 'deactivate') && $user['status'] !== STATUS_DEACTIVATED;
                            $canDelete = can_manage_account($conn, $actor, $user, 'delete');
                            $canRoleChange = can_manage_account($conn, $actor, $user, 'role_change');
                            $canReset = $canResetPasswords && (
                                $isSuperadmin || ($actor['role'] === ROLE_ADMIN && $user['role'] === ROLE_USER)
                            ) && (int)$user['id'] !== (int)$actor['id'];
                            $canManagePrivileges = $isSuperadmin && $user['role'] === ROLE_ADMIN;
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($user['username']); ?></strong>
                                <span><?= e($user['id_number']); ?> · <?= e(trim($user['first_name'] . ' ' . $user['last_name'])); ?> · <?= e($user['email']); ?></span>
                            </td>
                            <td>
                                <div class="action-stack">
                                    <?php if ($canActivate): ?>
                                        <form method="post" action="admin_action.php" class="js-confirm"
                                              data-confirm-title="<?= $user['status'] === STATUS_PENDING ? 'Approve this registration?' : 'Activate this account?'; ?>"
                                              data-confirm-message="<?= $user['status'] === STATUS_PENDING ? 'This will approve the registration and let the account sign in.' : 'This will let this account sign in again.'; ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                            <input type="hidden" name="target_id" value="<?= (int)$user['id']; ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" class="btn btn-primary"><?= $user['status'] === STATUS_PENDING ? 'Approve registration' : 'Activate'; ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canDeactivate && $user['status'] !== STATUS_PENDING): ?>
                                        <form method="post" action="admin_action.php" class="js-confirm"
                                              data-confirm-title="Deactivate this account?"
                                              data-confirm-message="The selected duration will apply. The account owner will not be able to sign in.">
                                            <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                            <input type="hidden" name="target_id" value="<?= (int)$user['id']; ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <select name="duration" required aria-label="Deactivation duration">
                                                <option value="1_month">1 month</option>
                                                <option value="3_months">3 months</option>
                                                <option value="6_months">6 months</option>
                                                <option value="manual">Until manually reactivated</option>
                                            </select>
                                            <button type="submit" class="btn btn-primary">Deactivate</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canRoleChange): ?>
                                        <form method="post" action="admin_action.php" class="js-confirm"
                                              data-confirm-title="Change this account role?"
                                              data-confirm-message="The role determines what this account can access. Changes take effect immediately.">
                                            <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                            <input type="hidden" name="target_id" value="<?= (int)$user['id']; ?>">
                                            <input type="hidden" name="action" value="role_change">
                                            <select name="new_role" required aria-label="New role">
                                                <option value="user" <?= $user['role'] === ROLE_USER ? 'selected' : ''; ?>>User</option>
                                                <option value="admin" <?= $user['role'] === ROLE_ADMIN ? 'selected' : ''; ?>>Admin</option>
                                                <option value="superadmin" <?= $user['role'] === ROLE_SUPERADMIN ? 'selected' : ''; ?>>Superadmin</option>
                                            </select>
                                            <button type="submit" class="btn btn-primary">Change Role</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                        <form method="post" action="admin_action.php" class="js-confirm"
                                              data-confirm-title="<?= $isSuperadmin ? 'Delete this account?' : 'Request account deletion?'; ?>"
                                              data-confirm-message="<?= $isSuperadmin ? 'This permanently removes the account and all its data. This cannot be undone.' : 'A deletion request will be sent to the superadmin for review.'; ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                            <input type="hidden" name="target_id" value="<?= (int)$user['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button class="btn btn-danger" type="submit"><?= $isSuperadmin ? 'Delete' : 'Request deletion'; ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canReset): ?>
                                        <a class="btn btn-ghost action-link" href="reset_password.php?id=<?= (int)$user['id']; ?>">Reset Password</a>
                                    <?php endif; ?>
                                    <?php if ($canManagePrivileges): ?>
                                        <a class="btn btn-ghost action-link" href="manage_privileges.php?id=<?= (int)$user['id']; ?>">Manage Privileges</a>
                                    <?php endif; ?>
                                    <?php if (!$canActivate && !$canDeactivate && !$canDelete && !$canRoleChange && !$canReset && !$canManagePrivileges): ?>
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
                    <?php if (!$users): ?>
                        <tr><td class="empty-state" colspan="5"><strong>No accounts found</strong><span>Try changing the search or filters.</span></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>
    <?php include 'confirm_modal.php'; ?>
    <script src="js/admin.js"></script>
</body>
</html>