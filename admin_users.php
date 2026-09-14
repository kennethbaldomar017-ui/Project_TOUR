<?php
require_once 'config.php';
$actor = require_admin($conn);

$employeeId = trim($_GET['employee_id'] ?? '');
$roleFilter = $_GET['role'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$where = [];
$params = [];
$types = '';

if ($employeeId !== '') {
    $where[] = 'id_number LIKE ?';
    $params[] = '%' . $employeeId . '%';
    $types .= 's';
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

$countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM users' . ($where ? ' WHERE ' . implode(' AND ', $where) : ''));
if ($params) {
    $countStmt->bind_param($types, ...$params);
}
$countStmt->execute();
$totalUsers = (int)$countStmt->get_result()->fetch_assoc()['total'];
$countStmt->close();
$totalPages = max(1, (int)ceil($totalUsers / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$sql = "SELECT id, id_number, username, first_name, last_name, email, role, status,
           CASE WHEN status = 'active' AND last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END AS is_online
    FROM users";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$sql .= " ORDER BY FIELD(role, 'superadmin', 'admin', 'user'), username LIMIT ? OFFSET ?";
$params[] = $perPage;
$params[] = $offset;
$types .= 'ii';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

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
            <form class="filter-bar" method="get">
                <input type="search" name="employee_id" value="<?= e($employeeId); ?>" placeholder="Employee ID">
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
                            <th>Employee ID</th>
                            <th>User</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Account Controls</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                            $canActivate = can_manage_account($conn, $actor, $user, 'activate') && $user['status'] !== STATUS_ACTIVE;
                            $canDeactivate = can_manage_account($conn, $actor, $user, 'deactivate') && $user['status'] !== STATUS_DEACTIVATED;
                            $canDelete = can_manage_account($conn, $actor, $user, 'delete');
                            $canRoleChange = can_manage_account($conn, $actor, $user, 'role_change');
                            $canUpdateInfo = can_manage_account($conn, $actor, $user, 'update_info');
                            $canReset = $canResetPasswords && (
                                $isSuperadmin || ($actor['role'] === ROLE_ADMIN && $user['role'] === ROLE_USER)
                            ) && (int)$user['id'] !== (int)$actor['id'];
                        ?>
                        <tr>
                            <td><?= e($user['id_number']); ?></td>
                            <td>
                                <strong><?= e($user['username']); ?></strong>
                                <span><?= e(trim($user['first_name'] . ' ' . $user['last_name'])); ?> · <?= e($user['email']); ?></span>
                            </td>
                            <td><span class="badge role-<?= e($user['role']); ?>"><?= e(ucfirst($user['role'])); ?></span></td>
                            <?php
                                if ($user['status'] === STATUS_DEACTIVATED) {
                                    $presenceLabel = 'Blocked';
                                    $presenceClass = 'blocked';
                                } elseif ($user['status'] !== STATUS_ACTIVE) {
                                    $presenceLabel = ucfirst($user['status']);
                                    $presenceClass = $user['status'];
                                } elseif ((int)$user['is_online'] === 1) {
                                    $presenceLabel = 'Active';
                                    $presenceClass = 'active';
                                } else {
                                    $presenceLabel = 'Offline';
                                    $presenceClass = 'offline';
                                }
                            ?>
                            <td><span class="badge status-<?= e($presenceClass); ?>"><?= e($presenceLabel); ?></span></td>
                            <td>
                                <div class="action-stack">
                                    <?php if ($canActivate): ?>
                                        <form method="post" action="admin_action.php" class="js-confirm"
                                              data-confirm-title="<?= $user['status'] === STATUS_PENDING ? 'Approve this registration?' : 'Activate this account?'; ?>"
                                              data-confirm-message="<?= $user['status'] === STATUS_PENDING ? 'This will approve the registration and let the account sign in.' : 'This will let this account sign in again.'; ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                            <input type="hidden" name="target_id" value="<?= (int)$user['id']; ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" class="btn btn-primary icon-action" title="<?= $user['status'] === STATUS_PENDING ? 'Approve registration' : 'Activate'; ?>" aria-label="<?= $user['status'] === STATUS_PENDING ? 'Approve registration' : 'Activate'; ?>"><span aria-hidden="true">&#10003;</span></button>
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
                                            <button type="submit" class="btn btn-primary icon-action" title="Change role" aria-label="Change role"><span aria-hidden="true">&#8644;</span></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canUpdateInfo): ?>
                                        <a class="btn btn-ghost action-link icon-action" href="account_info.php?id=<?= (int)$user['id']; ?>" title="Edit information" aria-label="Edit information"><span aria-hidden="true">&#9998;</span></a>
                                    <?php endif; ?>
                                    <?php if ($canDeactivate && $user['status'] !== STATUS_PENDING): ?>
                                        <form method="post" action="admin_action.php" class="js-confirm"
                                              data-confirm-title="Deactivate this account?"
                                              data-confirm-message="The account will remain deactivated until an administrator activates it.">
                                            <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                            <input type="hidden" name="target_id" value="<?= (int)$user['id']; ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <button type="submit" class="btn btn-primary icon-action" title="Deactivate" aria-label="Deactivate"><span aria-hidden="true">&#8856;</span></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                        <form method="post" action="admin_action.php" class="js-confirm"
                                              data-confirm-title="<?= $isSuperadmin ? 'Delete this account?' : 'Request account deletion?'; ?>"
                                              data-confirm-message="<?= $isSuperadmin ? 'This permanently removes the account and all its data. This cannot be undone.' : 'A deletion request will be sent to the superadmin for review.'; ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                            <input type="hidden" name="target_id" value="<?= (int)$user['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                                                                        <?php if (!$isSuperadmin): ?>
                                                                                                <input type="text" name="reason" placeholder="Deletion reason" maxlength="255" required data-confirm-required aria-label="Deletion reason">
                                                                                        <?php endif; ?>
                                            <button class="btn btn-danger icon-action" type="submit" title="<?= $isSuperadmin ? 'Delete' : 'Request deletion'; ?>" aria-label="<?= $isSuperadmin ? 'Delete' : 'Request deletion'; ?>"><span aria-hidden="true">&#128465;</span></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canReset): ?>
                                        <a class="btn btn-ghost action-link icon-action" href="reset_password.php?id=<?= (int)$user['id']; ?>" title="Reset password" aria-label="Reset password"><span aria-hidden="true">&#128273;</span></a>
                                    <?php endif; ?>
                                    <?php if (!$canActivate && !$canDeactivate && !$canDelete && !$canRoleChange && !$canUpdateInfo && !$canReset): ?>
                                        <span class="muted-text">No permitted actions for your role</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$users): ?>
                        <tr><td class="empty-state" colspan="5"><strong>No accounts found</strong><span>Try changing the Employee ID or filters.</span></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="pagination" aria-label="User pages">
                    <?php for ($pageNumber = 1; $pageNumber <= $totalPages; $pageNumber++): ?>
                        <a class="<?= $pageNumber === $page ? 'active' : ''; ?>" href="<?= e('?' . http_build_query(array_merge($_GET, ['page' => $pageNumber]))); ?>"><?= $pageNumber; ?></a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        </section>
    </main>

    <?php include 'footer.php'; ?>
    <?php include 'confirm_modal.php'; ?>
    <script src="js/admin.js"></script>
</body>
</html>