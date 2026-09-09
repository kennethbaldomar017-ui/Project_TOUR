<?php
// Shared navigation and administrator workspace shell.
$current_page = basename($_SERVER['PHP_SELF']);
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$is_admin_shell = $is_logged_in && isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'superadmin'], true);
$display_name = trim(($_SESSION['first_name'] ?? '') . ' ' . ($_SESSION['last_name'] ?? '')) ?: ($_SESSION['username'] ?? $_SESSION['identifier'] ?? 'Account');
$avatar_letters = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $display_name), 0, 2));
?>
<script src="js/security.js" defer></script>
<?php if ($is_admin_shell): ?>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-brand">PRIME<span>.</span></div>
            <p class="admin-sidebar-label">MENU</p>
            <nav class="admin-sidebar-nav" aria-label="Dashboard navigation">
                <a class="<?= $current_page === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php"><span class="sidebar-icon">&#9632;</span>Dashboard</a>
                <a class="<?= $current_page === 'inventory.php' ? 'active' : ''; ?>" href="inventory.php"><span class="sidebar-icon">&#9632;</span>Inventory</a>
                <a class="<?= $current_page === 'purchases.php' ? 'active' : ''; ?>" href="purchases.php"><span class="sidebar-icon">&#9670;</span>Buying</a>
                <a class="<?= $current_page === 'builds.php' ? 'active' : ''; ?>" href="builds.php"><span class="sidebar-icon">&#9881;</span>PC Builds</a>
                <p class="admin-sidebar-label">MANAGEMENT</p>
                <a class="<?= $current_page === 'admin_users.php' ? 'active' : ''; ?>" href="admin_users.php"><span class="sidebar-icon">&#9673;</span>Users</a>
                <a class="<?= $current_page === 'audit_logs.php' ? 'active' : ''; ?>" href="audit_logs.php"><span class="sidebar-icon">&#9776;</span>Audit Logs</a>
                <?php if (in_array($_SESSION['role'], ['admin', 'superadmin'], true)): ?>
                    <a class="<?= $current_page === 'manage_privileges.php' ? 'active' : ''; ?>" href="manage_privileges.php"><span class="sidebar-icon">&#9881;</span>Privileges</a>
                <?php endif; ?>
            </nav>
            <div class="admin-sidebar-footer">
                <a href="edit_info.php" class="admin-account-link">
                    <span class="admin-avatar small"><?= e($avatar_letters); ?></span>
                    <span><strong><?= e($display_name); ?></strong><small><?= e(ucfirst($_SESSION['role'])); ?></small></span>
                </a>
                <a href="logout.php" class="admin-logout">Log out</a>
            </div>
        </aside>
        <div class="admin-workspace">
            <header class="admin-topbar">
                <div class="admin-mobile-brand">PRIME<span>.</span></div>
                <div class="admin-topbar-tools">
                    <label class="admin-search"><span>&#9906;</span><input type="search" placeholder="Search..."></label>
                    <a href="audit_logs.php" class="admin-topbar-icon" aria-label="Notifications">&#128276;</a>
                    <details class="admin-account-menu">
                        <summary class="admin-avatar" aria-label="Open account menu"><?= e($avatar_letters); ?></summary>
                        <div class="admin-account-dropdown">
                            <a href="edit_info.php">Account Settings</a>
                            <a href="logout.php">Log Out</a>
                        </div>
                    </details>
                </div>
            </header>
<?php else: ?>
    <header class="site-header">
        <div class="container">
            <h1 class="prospect-name">PRIME.</h1>
            <nav class="top-nav">
                <?php if ($is_logged_in): ?>
                    <a href="dashboard.php" class="<?= $current_page === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
                    <?php if ($is_admin_shell): ?>
                        <a href="inventory.php" class="<?= $current_page === 'inventory.php' ? 'active' : ''; ?>">Inventory</a>
                        <a href="purchases.php" class="<?= $current_page === 'purchases.php' ? 'active' : ''; ?>">Buying</a>
                        <a href="builds.php" class="<?= $current_page === 'builds.php' ? 'active' : ''; ?>">PC Builds</a>
                    <?php else: ?>
                        <a href="shop.php" class="<?= $current_page === 'shop.php' ? 'active' : ''; ?>">Shop</a>
                        <a href="cart.php" class="<?= $current_page === 'cart.php' ? 'active' : ''; ?>">Cart<?php $headerCartCount = array_sum(array_map('intval', $_SESSION['cart'] ?? [])); ?><?= $headerCartCount ? ' (' . (int)$headerCartCount . ')' : ''; ?></a>
                    <?php endif; ?>
                    <span class="nav-user" title="Signed-in account">
                        <?= e($_SESSION['username'] ?? $_SESSION['identifier'] ?? 'Account'); ?>
                        <small><?= e(ucfirst($_SESSION['role'] ?? 'user')); ?></small>
                    </span>
                    <a href="logout.php">Log Out</a>
                <?php elseif ($current_page === 'login.php'): ?>
                    <a href="home.php">Home</a><a href="sign.php">Register</a>
                <?php elseif ($current_page === 'sign.php'): ?>
                    <a href="home.php">Home</a><a href="login.php">Log In</a>
                <?php else: ?>
                    <a href="login.php">Log In</a><a href="sign.php">Register</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
<?php endif; ?>
