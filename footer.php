<?php
$is_admin_shell = isset($_SESSION['logged_in'], $_SESSION['role'])
    && $_SESSION['logged_in'] === true
    && in_array($_SESSION['role'], ['admin', 'superadmin'], true);
?>
<?php if ($is_admin_shell): ?>
        </div>
    </div>
<?php else: ?>
    <footer class="site-footer">
        <div class="container">
            <small>&copy; <?= date('Y'); ?> PRIME TechBuild. Computer parts inventory and build management.</small>
        </div>
    </footer>
<?php endif; ?>
