<?php
// admin_shell.php - shared dashboard layout (sidebar + topbar) for admin and superadmin pages

function admin_icon(string $name): string {
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 19.5c0-3 2.6-5 5.5-5s5.5 2 5.5 5"/><path d="M16.5 6.5a3 3 0 0 1 0 5.6"/><path d="M17.5 14.8c2 .6 3.5 2.3 3.5 4.7"/>',
        'shield' => '<path d="M12 3l7 3v5.5c0 4.2-2.9 7.9-7 9.5-4.1-1.6-7-5.3-7-9.5V6l7-3z"/><path d="M9.2 12.2l2 2 3.6-3.9"/>',
        'logs' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 8h8M8 12h8M8 16h5"/>',
        'home' => '<path d="M4 10.5L12 4l8 6.5V20a1 1 0 0 1-1 1h-4v-6H9v6H5a1 1 0 0 1-1-1z"/>',
        'logout' => '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="M10 8l-4 4 4 4"/><path d="M6 12h10"/>',
        'eye' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6"/>',
        'ban' => '<circle cx="12" cy="12" r="8"/><path d="M6.5 6.5l11 11"/>',
        'activity' => '<path d="M3 12h4l3 7 4-14 3 7h4"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/>',
        'bell' => '<path d="M6 9a6 6 0 1 1 12 0c0 4 1.5 5.5 1.5 5.5h-15S6 13 6 9z"/><path d="M10 18.5a2 2 0 0 0 4 0"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
    ];
    $path = $paths[$name] ?? $paths['dashboard'];
    return '<svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
}

function admin_nav_items(string $role): array {
    $items = [
        ['href' => 'admin_dashboard.php', 'label' => 'Dashboard', 'icon' => 'dashboard'],
        ['href' => 'admin_users.php', 'label' => 'User Management', 'icon' => 'users'],
    ];
    if ($role === ROLE_SUPERADMIN) {
        $items[] = ['href' => 'create_admin.php', 'label' => 'Create Admin', 'icon' => 'shield'];
        $items[] = ['href' => 'audit_logs.php', 'label' => 'Audit Logs', 'icon' => 'logs'];
    }
    return $items;
}

function admin_initials(array $actor): string {
    $first = trim((string)($actor['first_name'] ?? ''));
    $last = trim((string)($actor['last_name'] ?? ''));
    $initials = strtoupper(substr($first, 0, 1) . substr($last, 0, 1));
    if (trim($initials) === '') {
        $initials = strtoupper(substr((string)$actor['username'], 0, 2));
    }
    return $initials;
}

function admin_display_name(array $actor): string {
    $name = trim(($actor['first_name'] ?? '') . ' ' . ($actor['last_name'] ?? ''));
    return $name !== '' ? $name : (string)$actor['username'];
}

function admin_shell_start(array $actor, string $title, string $subtitle = ''): void {
    $current = basename($_SERVER['PHP_SELF']);
    $items = admin_nav_items($actor['role']);
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title); ?> | PRIME.</title>
    <link rel="stylesheet" href="css/admin.css">
    <script src="js/security.js" defer></script>
</head>
<body class="admin-body">
<div class="shell">
    <aside class="sidebar" id="sidebar">
        <div class="brand">PRIME<span>.</span></div>
        <nav>
            <p class="nav-label">Menu</p>
            <?php foreach ($items as $item): ?>
                <a class="nav-link <?= $current === $item['href'] ? 'active' : ''; ?>" href="<?= e($item['href']); ?>">
                    <?= admin_icon($item['icon']); ?><span><?= e($item['label']); ?></span>
                </a>
            <?php endforeach; ?>
            <p class="nav-label">Account</p>
            <a class="nav-link" href="home.php"><?= admin_icon('home'); ?><span>Back to Site</span></a>
            <a class="nav-link" href="logout.php"><?= admin_icon('logout'); ?><span>Log Out</span></a>
        </nav>
    </aside>

    <div class="main">
        <header class="topbar">
            <button class="menu-toggle" type="button" aria-label="Toggle menu" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <span></span><span></span><span></span>
            </button>
            <form class="topbar-search" method="get" action="admin_users.php">
                <?= admin_icon('search'); ?>
                <input type="search" name="search" placeholder="Search users..." aria-label="Search users">
            </form>
            <div class="topbar-user">
                <span class="badge role-<?= e($actor['role']); ?>"><?= e(ucfirst($actor['role'])); ?></span>
                <div class="avatar"><?= e(admin_initials($actor)); ?></div>
                <div class="topbar-meta">
                    <strong><?= e(admin_display_name($actor)); ?></strong>
                    <span>@<?= e($actor['username']); ?></span>
                </div>
            </div>
        </header>

        <main class="content">
            <div class="page-head">
                <h1><?= e($title); ?></h1>
                <?php if ($subtitle !== ''): ?><p><?= e($subtitle); ?></p><?php endif; ?>
            </div>

            <?php if (isset($_SESSION['form_error'])): ?>
                <div class="alert alert-error" id="alertBox"><?= e($_SESSION['form_error']); unset($_SESSION['form_error']); ?></div>
            <?php endif; ?>
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success" id="alertBox"><?= e($_SESSION['success']); unset($_SESSION['success']); ?></div>
            <?php endif; ?>
    <?php
}

function admin_shell_end(): void {
    ?>
        </main>
        <footer class="admin-footer">&copy; <?= date('Y'); ?> PRIME. All rights reserved.</footer>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const alertBox = document.getElementById('alertBox');
        if (alertBox) {
            setTimeout(function () { alertBox.style.display = 'none'; }, 5000);
        }
    });
</script>
</body>
</html>
    <?php
}
