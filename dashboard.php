<?php
require_once 'config.php';
$currentUser = require_login($conn);

$stats = [
    'users' => 0,
    'admins' => 0,
    'superadmins' => 0,
    'pending' => 0,
    'active' => 0,
    'deactivated' => 0,
    'parts' => 0,
    'low_stock' => 0,
    'builds' => 0,
    'inventory_value' => 0,
];
$statsResult = $conn->query("SELECT role, status, COUNT(*) AS total FROM users GROUP BY role, status");
if ($statsResult) {
    while ($stat = $statsResult->fetch_assoc()) {
        $roleKey = $stat['role'] === 'user' ? 'users' : $stat['role'] . 's';
        $stats[$roleKey] += (int)$stat['total'];
        $stats[$stat['status']] += (int)$stat['total'];
    }
}
$partStats = $conn->query("SELECT COUNT(*) AS parts, SUM(stock <= reorder_level) AS low_stock, COALESCE(SUM(stock * unit_cost), 0) AS inventory_value FROM tech_parts");
if ($partStats) {
    $row = $partStats->fetch_assoc();
    $stats['parts'] = (int)($row['parts'] ?? 0);
    $stats['low_stock'] = (int)($row['low_stock'] ?? 0);
    $stats['inventory_value'] = (float)($row['inventory_value'] ?? 0);
}
$buildStats = $conn->query("SELECT COUNT(*) AS builds FROM pc_builds WHERE status IN ('quoted', 'reserved')");
if ($buildStats) {
    $stats['builds'] = (int)($buildStats->fetch_assoc()['builds'] ?? 0);
}
$lowStockResult = $conn->query("SELECT sku, name, stock, reorder_level FROM tech_parts WHERE stock <= reorder_level ORDER BY stock ASC, name ASC LIMIT 5");
$lowStockParts = $lowStockResult ? $lowStockResult->fetch_all(MYSQLI_ASSOC) : [];
$isAdminDashboard = in_array($currentUser['role'], ['admin', 'superadmin'], true);
$displayName = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?: ($currentUser['username'] ?? 'Account');
$avatarLetters = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $displayName), 0, 2));
$totalAccounts = $stats['users'] + $stats['admins'] + $stats['superadmins'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | PRIME TechBuild</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <?php if(isset($_SESSION['form_error'])): ?>
        <div class="form-errors" id="alertBox"><?= htmlspecialchars($_SESSION['form_error']); unset($_SESSION['form_error']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['success'])): ?>
        <div class="form-success" id="alertBox"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <main class="container dashboard-page <?= $isAdminDashboard ? 'admin-dashboard-content' : ''; ?>">
        <div class="dashboard-intro">
            <div>
                <h2><?= $isAdminDashboard ? 'Dashboard' : 'Good to see you, ' . e($displayName) . '.'; ?></h2>
                <?php if (!$isAdminDashboard): ?>
                    <p class="dashboard-summary">Manage computer parts inventory, supplier buying, and PC build jobs from one workspace.</p>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($isAdminDashboard): ?>
            <section class="admin-profile-card">
                <div class="admin-profile-main">
                    <span class="admin-avatar large"><?= e($avatarLetters); ?></span>
                    <div>
                        <h1><?= e($displayName); ?></h1>
                        <p><?= e($currentUser['email'] ?? $currentUser['username']); ?></p>
                        <div class="admin-profile-tags"><span>Tech Operations</span><span>Full Access</span></div>
                    </div>
                </div>
                <div class="admin-profile-meta"><span><strong><?= number_format($totalAccounts); ?></strong> accounts</span><span><strong><?= number_format($stats['active']); ?></strong> active</span><span><strong><?= number_format($stats['pending']); ?></strong> pending</span></div>
            </section>
        <?php endif; ?>

        <?php if ($isAdminDashboard): ?>
        <section class="dashboard-stats" aria-label="Account summary">
            <?php if (in_array($currentUser['role'], ['admin', 'superadmin'], true)): ?>
            <article class="stat-card stat-users">
                <span class="stat-label">Total users</span>
                <strong><?= number_format($stats['users'] + $stats['admins'] + $stats['superadmins']); ?></strong>
                <span class="stat-detail">All registered accounts</span>
            </article>
            <article class="stat-card stat-active">
                <span class="stat-label">Active accounts</span>
                <strong><?= number_format($stats['active']); ?></strong>
                <span class="stat-detail">Currently enabled</span>
            </article>
            <article class="stat-card stat-admins">
                <span class="stat-label">Pending approvals</span>
                <strong><?= number_format($stats['pending']); ?></strong>
                <span class="stat-detail">Need review</span>
            </article>
            <article class="stat-card stat-superadmins">
                <span class="stat-label">Administrators</span>
                <strong><?= number_format($stats['admins'] + $stats['superadmins']); ?></strong>
                <span class="stat-detail">Admin and superadmin accounts</span>
            </article>
            <?php else: ?>
            <article class="stat-card stat-users">
                <span class="stat-label">Parts in catalog</span>
                <strong><?= $stats['parts']; ?></strong>
                <span class="stat-detail">Inventory items</span>
            </article>
            <article class="stat-card stat-admins">
                <span class="stat-label">Low stock</span>
                <strong><?= $stats['low_stock']; ?></strong>
                <span class="stat-detail">Need reorder</span>
            </article>
            <article class="stat-card stat-superadmins">
                <span class="stat-label">Open builds</span>
                <strong><?= $stats['builds']; ?></strong>
                <span class="stat-detail">Quoted or reserved</span>
            </article>
            <article class="stat-card stat-active">
                <span class="stat-label">Inventory value</span>
                <strong><?= e(peso($stats['inventory_value'])); ?></strong>
                <span class="stat-detail">At unit cost</span>
            </article>
            <?php endif; ?>
        </section>
        <?php else: ?>
        <section class="user-welcome-panel">
            <p class="eyebrow">ACCOUNT OVERVIEW</p>
            <h2>Your workspace is ready.</h2>
            <p>Browse computer parts, build your cart, and request a custom PC quote from the PRIME Tech Shop.</p>
            <a class="btn btn-primary" href="shop.php">Browse the shop</a>
        </section>
        <?php endif; ?>

        <?php if ($isAdminDashboard): ?>
            <section class="admin-chart-grid" aria-label="Dashboard overview">
                <article class="admin-chart-card">
                    <div class="admin-card-heading"><h3>Account status</h3><span>Current</span></div>
                    <div class="status-chart"><div class="status-donut"><strong><?= number_format($totalAccounts); ?></strong><small>accounts</small></div><div class="status-legend"><span><i class="active-dot"></i>Active <b><?= number_format($stats['active']); ?></b></span><span><i class="pending-dot"></i>Pending <b><?= number_format($stats['pending']); ?></b></span><span><i class="disabled-dot"></i>Deactivated <b><?= number_format($stats['deactivated']); ?></b></span></div></div>
                </article>
                <article class="admin-chart-card">
                    <div class="admin-card-heading"><h3>Users by role</h3><span>All accounts</span></div>
                    <div class="role-bars"><span><label>Users <b><?= number_format($stats['users']); ?></b></label><i style="width: <?= $totalAccounts ? round(($stats['users'] / $totalAccounts) * 100) : 0; ?>%"></i></span><span><label>Admins <b><?= number_format($stats['admins']); ?></b></label><i class="admin-bar" style="width: <?= $totalAccounts ? round(($stats['admins'] / $totalAccounts) * 100) : 0; ?>%"></i></span><span><label>Superadmins <b><?= number_format($stats['superadmins']); ?></b></label><i class="superadmin-bar" style="width: <?= $totalAccounts ? round(($stats['superadmins'] / $totalAccounts) * 100) : 0; ?>%"></i></span></div>
                </article>
                <article class="admin-chart-card admin-quick-card">
                    <div class="admin-card-heading"><h3>Quick actions</h3><span>Manage</span></div>
                    <a href="admin_users.php"><span class="quick-icon">&#9673;</span><span><strong>Review users</strong><small>Accounts and approvals</small></span><b>&rarr;</b></a>
                    <a href="audit_logs.php"><span class="quick-icon">&#9776;</span><span><strong>View activity</strong><small>Recent audit events</small></span><b>&rarr;</b></a>
                </article>
            </section>
        <?php endif; ?>

        <?php if ($isAdminDashboard): ?>
        <section class="dashboard-actions">
            <?php if (in_array($currentUser['role'], ['admin', 'superadmin'], true)): ?>
            <a class="tech-action" href="admin_users.php">
                <strong>Manage Users</strong>
                <span>Review accounts, approvals, roles, and account status.</span>
            </a>
            <a class="tech-action" href="audit_logs.php">
                <strong>Audit Logs</strong>
                <span>Review important account and system activity.</span>
            </a>
            <?php if ($currentUser['role'] === 'superadmin'): ?>
            <a class="tech-action" href="manage_privileges.php">
                <strong>Privileges</strong>
                <span>Manage permissions for administrative accounts.</span>
            </a>
            <?php endif; ?>
            <?php endif; ?>
            <a class="tech-action" href="inventory.php">
                <strong>Inventory</strong>
                <span>Add CPUs, GPUs, boards, RAM, storage, PSUs, cases, and peripherals.</span>
            </a>
            <a class="tech-action" href="purchases.php">
                <strong>Buying</strong>
                <span>Record supplier purchases and receive stock into inventory.</span>
            </a>
            <a class="tech-action" href="builds.php">
                <strong>PC Builds</strong>
                <span>Create quotes, reserve parts, and track sold computer builds.</span>
            </a>
        </section>
        <?php endif; ?>

        <?php if ($isAdminDashboard): ?>
        <section class="admin-panel dashboard-low-stock">
            <div class="admin-heading">
                <div>
                    <h2>Reorder Watch</h2>
                    <p>Parts at or below their reorder level.</p>
                </div>
            </div>
            <?php if ($lowStockParts): ?>
                <div class="table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>SKU</th><th>Part</th><th>Stock</th><th>Reorder Level</th></tr></thead>
                        <tbody>
                        <?php foreach ($lowStockParts as $part): ?>
                            <tr>
                                <td><?= e($part['sku']); ?></td>
                                <td><strong><?= e($part['name']); ?></strong></td>
                                <td><?= (int)$part['stock']; ?></td>
                                <td><?= (int)$part['reorder_level']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state"><strong>Stock levels look good</strong><span>No parts need reorder right now.</span></div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

    </main>

    <?php include 'footer.php'; ?>
    
    <script>
        // Disable back button
        history.pushState(null, null, location.href);
        window.addEventListener('popstate', function () {
            history.pushState(null, null, location.href);
        });

        // Auto-dismiss alert after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alertBox = document.getElementById('alertBox');
            if (alertBox) {
                setTimeout(function() {
                    alertBox.classList.add('dismiss');
                    setTimeout(function() {
                        alertBox.style.display = 'none';
                    }, 400);
                }, 5000);
            }
        });
    </script>
</body>
</html>
