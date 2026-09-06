<?php
require_once 'config.php';
$actor = require_superadmin($conn);
verify_csrf();

unset($_SESSION['generated_password'], $_SESSION['generated_account']);
header('Location: admin_users.php');
exit;