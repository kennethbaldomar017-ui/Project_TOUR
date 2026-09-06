<?php
require_once 'config.php';
require_login($conn);

// Keep old bookmarks working while the system uses one shared dashboard.
header('Location: dashboard.php');
exit;
