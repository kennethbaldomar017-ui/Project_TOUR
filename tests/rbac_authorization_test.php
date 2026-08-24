<?php
require_once __DIR__ . '/../auth.php';

function assert_true($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$user = ['id' => 1, 'username' => 'regularuser', 'role' => ROLE_USER, 'status' => STATUS_ACTIVE];
$admin = ['id' => 2, 'username' => 'adminuser', 'role' => ROLE_ADMIN, 'status' => STATUS_ACTIVE];
$superadmin = ['id' => 3, 'username' => 'superadmin', 'role' => ROLE_SUPERADMIN, 'status' => STATUS_ACTIVE];
$otherAdmin = ['id' => 4, 'username' => 'otheradmin', 'role' => ROLE_ADMIN, 'status' => STATUS_ACTIVE];
$otherSuperadmin = ['id' => 5, 'username' => 'othersuper', 'role' => ROLE_SUPERADMIN, 'status' => STATUS_ACTIVE];

assert_true(can_manage_account($admin, $user, 'activate'), 'Admin should activate users.');
assert_true(can_manage_account($admin, $user, 'deactivate'), 'Admin should deactivate users.');
assert_true(can_manage_account($admin, $user, 'delete'), 'Admin should delete users when policy permits.');
assert_true(!can_manage_account($admin, $otherAdmin, 'deactivate'), 'Admin must not deactivate admins.');
assert_true(!can_manage_account($admin, $otherSuperadmin, 'delete'), 'Admin must not delete superadmins.');
assert_true(!can_manage_account($admin, $admin, 'deactivate'), 'Admin must not deactivate self.');

assert_true(can_manage_account($superadmin, $user, 'delete'), 'Superadmin should delete users.');
assert_true(can_manage_account($superadmin, $otherAdmin, 'deactivate'), 'Superadmin should deactivate admins.');
assert_true(can_manage_account($superadmin, $otherSuperadmin, 'role_change'), 'Superadmin should manage other superadmins.');
assert_true(!can_manage_account($superadmin, $superadmin, 'delete'), 'Superadmin must not delete self.');

assert_true(!can_create_role($admin, ROLE_ADMIN), 'Admin must not create admins.');
assert_true(can_create_role($superadmin, ROLE_ADMIN), 'Superadmin should create admins.');
assert_true(can_create_role($superadmin, ROLE_SUPERADMIN), 'Superadmin should create superadmins.');

assert_true(deactivation_expiration('manual') === null, 'Manual deactivation should not expire.');
assert_true(duration_label('1_month') === '1 month', 'Duration labels should be readable.');

echo "RBAC authorization tests passed.\n";
?>
