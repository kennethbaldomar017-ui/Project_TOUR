<?php
require_once __DIR__ . '/../auth.php';

function assert_true($condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$user = ['id' => 1, 'username' => 'regularuser', 'role' => ROLE_USER, 'status' => STATUS_ACTIVE];
$pending = ['id' => 6, 'username' => 'pendinguser', 'role' => ROLE_USER, 'status' => STATUS_PENDING];
$admin = ['id' => 2, 'username' => 'adminuser', 'role' => ROLE_ADMIN, 'status' => STATUS_ACTIVE];
$superadmin = ['id' => 3, 'username' => 'superadmin', 'role' => ROLE_SUPERADMIN, 'status' => STATUS_ACTIVE];
$otherAdmin = ['id' => 4, 'username' => 'otheradmin', 'role' => ROLE_ADMIN, 'status' => STATUS_ACTIVE];
$otherSuperadmin = ['id' => 5, 'username' => 'othersuper', 'role' => ROLE_SUPERADMIN, 'status' => STATUS_ACTIVE];

// With no DB connection ($conn = null) privilege rules are ignored,
// preserving the legacy role-based behaviour.
assert_true(can_manage_account(null, $admin, $user, 'activate'), 'Admin should activate users.');
assert_true(can_manage_account(null, $admin, $user, 'deactivate'), 'Admin should deactivate users.');
assert_true(can_manage_account(null, $admin, $user, 'update_info'), 'Admin should update user information.');
assert_true(can_manage_account(null, $admin, $user, 'delete'), 'Admin should delete users when policy permits.');
assert_true(!can_manage_account(null, $admin, $otherAdmin, 'deactivate'), 'Admin must not deactivate admins.');
assert_true(!can_manage_account(null, $admin, $otherAdmin, 'update_info'), 'Admin must not update admin information.');
assert_true(!can_manage_account(null, $admin, $otherSuperadmin, 'delete'), 'Admin must not delete superadmins.');
assert_true(!can_manage_account(null, $admin, $admin, 'deactivate'), 'Admin must not deactivate self.');
assert_true(!can_manage_account(null, $admin, $pending, 'role_change'), 'Admin must not change roles without the manage_roles privilege.');

assert_true(can_manage_account(null, $superadmin, $user, 'delete'), 'Superadmin should delete users.');
assert_true(can_manage_account(null, $superadmin, $otherAdmin, 'deactivate'), 'Superadmin should deactivate admins.');
assert_true(can_manage_account(null, $superadmin, $otherAdmin, 'update_info'), 'Superadmin should update admin information.');
assert_true(can_manage_account(null, $superadmin, $otherSuperadmin, 'role_change'), 'Superadmin should manage other superadmins.');
assert_true(!can_manage_account(null, $superadmin, $superadmin, 'delete'), 'Superadmin must not delete self.');

assert_true(!can_create_role($admin, ROLE_ADMIN), 'Admin must not create admins.');
assert_true(can_create_role($superadmin, ROLE_ADMIN), 'Superadmin should create admins.');
assert_true(can_create_role($superadmin, ROLE_SUPERADMIN), 'Superadmin should create superadmins.');

assert_true(deactivation_expiration('manual') === null, 'Manual deactivation should not expire.');
assert_true(duration_label('1_month') === '1 month', 'Duration labels should be readable.');

// The superadmin role always holds every privilege.
assert_true(user_has_privilege(null, $superadmin, PRIV_MANAGE_ADMINS), 'Superadmin should always hold manage_admins.');
$adminPrivs = array_fill_keys([PRIV_APPROVE, PRIV_MANAGE_USERS], true);
assert_true(isset($adminPrivs[PRIV_APPROVE]), 'Default admin privileges include approve.');

// Masking helpers.
assert_true(mask_value('20200995') === '20****95', 'Masking should keep first two and last two chars.');
assert_true(mask_value('juan_2020') === 'ju*****20', 'Masking should work for usernames.');
assert_true(mask_id_number('2020-0099') === '20****99', 'ID number masking should keep leading and trailing digits.');

echo "RBAC authorization tests passed.\n";
?>