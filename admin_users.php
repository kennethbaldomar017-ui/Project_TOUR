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

$where[] = "must_change_password = 0
    AND TRIM(id_number) <> ''
    AND TRIM(username) <> ''
    AND TRIM(first_name) <> ''
    AND TRIM(last_name) <> ''
    AND birthdate IS NOT NULL
    AND age IS NOT NULL
    AND TRIM(email) <> ''
    AND TRIM(street) <> ''
    AND TRIM(barangay) <> ''
    AND TRIM(city) <> ''
    AND TRIM(province) <> ''
    AND TRIM(country) <> ''
    AND TRIM(zip) <> ''
    AND TRIM(auth_q1) <> ''
    AND TRIM(auth_a1) <> ''
    AND TRIM(auth_q2) <> ''
    AND TRIM(auth_a2) <> ''
    AND TRIM(auth_q3) <> ''
    AND TRIM(auth_a3) <> ''";

if ($actor['role'] !== ROLE_SUPERADMIN) {
    $where[] = 'role <> ?';
    $params[] = ROLE_SUPERADMIN;
    $types .= 's';
}

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
           must_change_password, birthdate, age, street, barangay, city, province, country, zip,
           auth_q1, auth_a1, auth_q2, auth_a2, auth_q3, auth_a3,
         CASE WHEN status = 'active'
                 AND last_seen_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                 AND (role <> 'superadmin' OR id = (SELECT active_user_id FROM superadmin_lock WHERE lock_name = 'superadmin' LIMIT 1))
             THEN 1 ELSE 0 END AS is_online
    FROM users";
if ($where) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
}
$currentUserId = (int)$actor['id'];
$sql .= " ORDER BY CASE WHEN id = {$currentUserId} THEN 0 ELSE 1 END, FIELD(role, 'superadmin', 'admin', 'user'), username LIMIT ? OFFSET ?";
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
    <link rel="stylesheet" href="css/style.css?v=20260918">
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
                    <?php if ($isSuperadmin): ?>
                        <option value="superadmin" <?= $roleFilter === ROLE_SUPERADMIN ? 'selected' : ''; ?>>Superadmin</option>
                    <?php endif; ?>
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
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Controls</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $user): ?>
                        <?php
                            $canActivate = can_manage_account($conn, $actor, $user, 'activate') && $user['status'] !== STATUS_ACTIVE;
                            $canDeactivate = can_manage_account($conn, $actor, $user, 'deactivate') && $user['status'] !== STATUS_DEACTIVATED;
                            $deactivationBlockedBySetup = $user['status'] === STATUS_ACTIVE && !account_ready_for_deactivation($user);
                            $canDelete = can_manage_account($conn, $actor, $user, 'delete');
                            $canRoleChange = $isSuperadmin
                                && can_manage_account($conn, $actor, $user, 'role_change');
                            $canUpdateInfo = can_manage_account($conn, $actor, $user, 'update_info');
                            $canReset = $canResetPasswords
                                && ($isSuperadmin || ($actor['role'] === ROLE_ADMIN && $user['role'] !== ROLE_SUPERADMIN))
                                && (int)$user['id'] !== (int)$actor['id'];
                        ?>
                        <tr>
                            <td><?= e($user['id_number']); ?></td>
                            <td>
                                <span class="user-name"><?= e(trim($user['first_name'] . ' ' . $user['last_name'])); ?></span>
                            </td>
                            <td><strong><?= e($user['username']); ?></strong></td>
                            <td class="user-email-cell"><?= e($user['email']); ?></td>
                            <td><span class="badge role-<?= e($user['role']); ?>"><?= e(ucfirst($user['role'])); ?></span></td>
                            <?php
                                if ($user['status'] === STATUS_DEACTIVATED) {
                                    $presenceLabel = 'Blocked';
                                    $presenceClass = 'blocked';
                                } elseif ($user['status'] !== STATUS_ACTIVE) {
                                    $presenceLabel = ucfirst($user['status']);
                                    $presenceClass = $user['status'];
                                } elseif ((int)$user['is_online'] === 1) {
                                    $presenceLabel = 'Online';
                                    $presenceClass = 'online';
                                } else {
                                    $presenceLabel = 'Offline';
                                    $presenceClass = 'offline';
                                }
                            ?>
                            <td><span class="badge status-<?= e($presenceClass); ?>"><?= e($presenceLabel); ?></span></td>
                            <td>
                                <details class="account-controls-dropdown">
                                    <summary class="btn btn-primary" title="Controls" aria-label="Controls">&#8942;</summary>
                                <div class="action-stack">
                                    <?php if ($canRoleChange): ?>
                                        <button type="button" class="role-change-trigger" data-role-modal-target="role-change-<?= (int)$user['id']; ?>">Change role</button>
                                        <dialog id="role-change-<?= (int)$user['id']; ?>" class="role-change-modal">
                                            <div class="role-change-modal-header">
                                                <h3>Change role</h3>
                                                <button type="button" class="role-change-close" data-role-modal-close aria-label="Close">&times;</button>
                                            </div>
                                            <p class="role-change-modal-help">Choose the access level for this account.</p>
                                            <form method="post" action="admin_action.php" class="js-confirm"
                                                  data-confirm-title="Change this account role?"
                                                  data-confirm-message="The role determines what this account can access. Changes take effect immediately.">
                                                <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                                <input type="hidden" name="target_id" value="<?= (int)$user['id']; ?>">
                                                <input type="hidden" name="action" value="role_change">
                                                <label for="role-select-<?= (int)$user['id']; ?>">Role</label>
                                                <select id="role-select-<?= (int)$user['id']; ?>" name="new_role" required aria-label="New role">
                                                    <?php if ($user['role'] === ROLE_USER): ?>
                                                        <option value="user" selected hidden>User</option>
                                                    <?php endif; ?>
                                                    <option value="admin" <?= $user['role'] === ROLE_ADMIN ? 'selected' : ''; ?>>Admin</option>
                                                    <option value="superadmin" <?= $user['role'] === ROLE_SUPERADMIN ? 'selected' : ''; ?>>Superadmin</option>
                                                </select>
                                                <div class="role-change-modal-actions">
                                                    <button type="button" class="role-change-cancel" data-role-modal-close>Cancel</button>
                                                    <button type="submit" class="btn btn-primary" title="Save role" aria-label="Save role">Save role</button>
                                                </div>
                                            </form>
                                        </dialog>
                                    <?php endif; ?>
                                    <?php if ($canActivate): ?>
                                        <form method="post" action="admin_action.php" class="js-confirm"
                                              data-confirm-title="<?= $user['status'] === STATUS_PENDING ? 'Approve this registration?' : 'Activate this account?'; ?>"
                                              data-confirm-message="<?= $user['status'] === STATUS_PENDING ? 'This will approve the registration and let the account sign in.' : 'This will let this account sign in again.'; ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                            <input type="hidden" name="target_id" value="<?= (int)$user['id']; ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <button type="submit" class="btn btn-primary" title="<?= $user['status'] === STATUS_PENDING ? 'Approve registration' : 'Activate'; ?>" aria-label="<?= $user['status'] === STATUS_PENDING ? 'Approve registration' : 'Activate'; ?>"><?= $user['status'] === STATUS_PENDING ? 'Approve registration' : 'Activate'; ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canUpdateInfo): ?>
                                        <a class="btn btn-ghost action-link" href="account_info.php?id=<?= (int)$user['id']; ?>" title="Edit information" aria-label="Edit information">Edit information</a>
                                    <?php endif; ?>
                                    <?php if ($canDeactivate && $user['status'] !== STATUS_PENDING): ?>
                                        <form method="post" action="admin_action.php" class="js-confirm"
                                              data-confirm-title="Block this account?"
                                              data-confirm-message="The account will remain blocked until an administrator activates it.">
                                            <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                            <input type="hidden" name="target_id" value="<?= (int)$user['id']; ?>">
                                            <input type="hidden" name="action" value="deactivate">
                                            <button type="submit" class="btn btn-primary" title="Block" aria-label="Block">Block</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($deactivationBlockedBySetup): ?>
                                        <span class="muted-text">Complete password, security questions, and personal details before blocking</span>
                                    <?php endif; ?>
                                    <?php if ($canDelete): ?>
                                        <form method="post" action="admin_action.php" class="js-confirm"
                                              data-confirm-title="<?= $isSuperadmin ? 'Delete this account?' : 'Request account deletion?'; ?>"
                                                data-confirm-message="<?= $isSuperadmin ? 'This permanently removes the account and all its data. This cannot be undone.' : 'A deletion request will be sent to the superadmin for review.'; ?>"
                                                <?= !$isSuperadmin ? 'data-confirm-requires-reason="true"' : ''; ?>>
                                            <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                                            <input type="hidden" name="target_id" value="<?= (int)$user['id']; ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <button class="btn btn-danger" type="submit" title="<?= $isSuperadmin ? 'Delete' : 'Request deletion'; ?>" aria-label="<?= $isSuperadmin ? 'Delete' : 'Request deletion'; ?>"><?= $isSuperadmin ? 'Delete' : 'Request deletion'; ?></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($canReset): ?>
                                        <a class="btn btn-ghost action-link" href="reset_password.php?id=<?= (int)$user['id']; ?>" title="Reset password" aria-label="Reset password">Reset password</a>
                                    <?php endif; ?>
                                    <?php if (!$canActivate && !$canDeactivate && !$canDelete && !$canRoleChange && !$canUpdateInfo && !$canReset): ?>
                                        <span class="muted-text">No permitted actions for your role</span>
                                    <?php endif; ?>
                                </div>
                                </details>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$users): ?>
                        <tr><td class="empty-state" colspan="7"><strong>No accounts found</strong><span>Try changing the Employee ID or filters.</span></td></tr>
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
    <script>
    function positionAccountMenu(menu) {
        if (!menu.open) {
            return;
        }
        var panel = menu._floatingPanel || menu.querySelector('.action-stack');
        var trigger = menu.querySelector('summary');
        if (!panel || !trigger) {
            return;
        }
        menu.classList.remove('menu-above');
        panel.style.position = 'fixed';
        panel.style.top = '0px';
        panel.style.left = '0px';
        panel.style.right = 'auto';
        panel.style.bottom = 'auto';
        var triggerRect = trigger.getBoundingClientRect();
        var panelRect = panel.getBoundingClientRect();
        var viewportMargin = 8;
        var gap = 6;
        var left = Math.min(
            Math.max(viewportMargin, triggerRect.right - panelRect.width),
            window.innerWidth - panelRect.width - viewportMargin
        );
        var top = triggerRect.bottom + gap;
        if (top + panelRect.height > window.innerHeight - viewportMargin) {
            top = triggerRect.top - panelRect.height - gap;
        }
        top = Math.max(viewportMargin, Math.min(top, window.innerHeight - panelRect.height - viewportMargin));
        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
    }

    document.querySelectorAll('.account-controls-dropdown').forEach(function (menu) {
        menu.addEventListener('toggle', function () {
            if (!menu.open) {
                if (menu._floatingPanel) {
                    menu.appendChild(menu._floatingPanel);
                    menu._floatingPanel = null;
                }
                if (menu._floatingHost) {
                    menu._floatingHost.remove();
                    menu._floatingHost = null;
                }
                var closedPanel = menu.querySelector('.action-stack');
                if (closedPanel) {
                    closedPanel.style.position = '';
                    closedPanel.style.top = '';
                    closedPanel.style.left = '';
                    closedPanel.style.right = '';
                    closedPanel.style.bottom = '';
                }
                return;
            }
            document.querySelectorAll('details.account-controls-dropdown[open]').forEach(function (otherMenu) {
                if (otherMenu !== menu) {
                    otherMenu.removeAttribute('open');
                }
            });
            var panel = menu.querySelector('.action-stack');
            var host = document.createElement('div');
            var floatingMenu = document.createElement('div');
            host.className = 'users-page account-menu-portal';
            floatingMenu.className = 'account-controls-dropdown';
            floatingMenu.setAttribute('open', '');
            floatingMenu.appendChild(panel);
            host.appendChild(floatingMenu);
            (document.querySelector('.admin-layout') || document.body).appendChild(host);
            menu._floatingPanel = panel;
            menu._floatingHost = host;
            requestAnimationFrame(function () {
                positionAccountMenu(menu);
            });
        });
    });

    document.querySelectorAll('[data-role-modal-target]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            var modal = document.getElementById(trigger.getAttribute('data-role-modal-target'));
            if (modal && typeof modal.showModal === 'function') {
                modal.showModal();
            }
        });
    });

    document.querySelectorAll('[data-role-modal-close]').forEach(function (closeButton) {
        closeButton.addEventListener('click', function () {
            var modal = closeButton.closest('dialog');
            if (modal) {
                modal.close();
            }
        });
    });

    document.querySelectorAll('.role-change-modal').forEach(function (modal) {
        modal.addEventListener('close', function () {
            var parentMenu = modal.closest('.account-controls-dropdown');
            if (parentMenu) {
                parentMenu.removeAttribute('open');
            }
        });

        modal.addEventListener('click', function (event) {
            if (event.target === modal) {
                modal.close();
            }
        });
    });

    window.addEventListener('resize', function () {
        document.querySelectorAll('details.account-controls-dropdown[open]').forEach(positionAccountMenu);
    });

    document.addEventListener('click', function (event) {
        if (!event.target.closest('.account-controls-dropdown')) {
            document.querySelectorAll('details.account-controls-dropdown[open]').forEach(function (menu) {
                menu.removeAttribute('open');
            });
        }
    });
    </script>
    <script src="js/admin.js"></script>
</body>
</html>