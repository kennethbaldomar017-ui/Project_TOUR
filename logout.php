<?php
session_start();
require_once 'config.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['logged_in'])) {
	$user = current_app_user($conn);
	if ($user) {
		log_audit_action($conn, $user, $user, 'logout', STATUS_ACTIVE, STATUS_ACTIVE, null, null, 'User logout');
	}
}

session_unset();
session_destroy();

header('location:login.php');
exit();
?>