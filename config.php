<?php
// config.php - Handle session management and global includes

// Start session only if not already active
if (session_status() === PHP_SESSION_NONE) {
    $isSecureRequest = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecureRequest,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Include database connection
require_once 'db_connect.php';
require_once 'auth.php';
require_once 'tech_system.php';

ensure_rbac_schema($conn);
ensure_tech_schema($conn);
reactivate_expired_accounts($conn);
?>
