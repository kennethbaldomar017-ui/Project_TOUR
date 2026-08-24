<?php
// config.php - Handle session management and global includes

// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection
require_once 'db_connect.php';
require_once 'auth.php';

ensure_rbac_schema($conn);
reactivate_expired_accounts($conn);
?>
