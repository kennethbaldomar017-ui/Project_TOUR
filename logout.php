<?php
session_start();
require_once 'config.php';
require_once 'auth.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['logged_in'])) {
	$user = current_app_user($conn);
	if ($user) {
		log_audit_action($conn, $user, $user, 'logout', STATUS_ACTIVE, STATUS_ACTIVE, null, null, 'User logout');
		clear_user_presence($conn, (int)$user['id']);
		if ($user['role'] === ROLE_SUPERADMIN) {
			release_superadmin_lock($conn, (int)$user['id'], session_id());
		}
	}
}

session_unset();
session_destroy();

header('location:login.php');
exit();
?>