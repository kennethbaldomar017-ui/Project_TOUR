<?php
require_once 'config.php';
$actor = require_login($conn);
$isSuperadminActor = $actor['role'] === ROLE_SUPERADMIN;
$canManagePrivileges = $isSuperadminActor || user_has_privilege($conn, $actor, PRIV_MANAGE_PRIVILEGES);
$token = csrf_token();

$targetId = max(0, (int)($_GET['id'] ?? 0));
$target = $canManagePrivileges && $targetId > 0 ? get_user_by_id($conn, $targetId) : null;

// Fetch administrators for privilege management
$administrators = [];
if ($canManagePrivileges) {
    $roleFilter = "role IN ('admin', 'superadmin')";
    $adminResult = $conn->query("SELECT id, username, first_name, last_name, email, role, status, created_at FROM users WHERE $roleFilter ORDER BY FIELD(role, 'superadmin', 'admin'), first_name, last_name, username");
    $administrators = $adminResult ? $adminResult->fetch_all(MYSQLI_ASSOC) : [];
}

// All privilege options defined in PRIVILEGE_LABELS
$assignablePrivileges = PRIVILEGE_LABELS;

// Short display labels for capability grid cards
$shortLabels = [
    PRIV_APPROVE => 'Approve',
    PRIV_MANAGE_USERS => 'Manage Users',
    PRIV_DELETE_USERS => 'Delete Users',
    PRIV_MANAGE_ROLES => 'User Roles',
    PRIV_RESET_PASSWORDS => 'Reset Passwords',
    PRIV_MANAGE_PRIVILEGES => 'Admin Privileges',
    PRIV_UPDATE_INFO => 'Update Info'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Privileges | PRIME.</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'header.php'; ?>

    <?php if(isset($_SESSION['form_error'])): ?>
        <div class="form-errors" id="alertBox"><?= e($_SESSION['form_error']); unset($_SESSION['form_error']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['success'])): ?>
        <div class="form-success" id="alertBox"><?= e($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <main class="container admin-page tech-page privileges-page">
        <div class="privileges-heading-row">
            <div class="privileges-title-group">
                <h1>PRIVILEGES</h1>
                <p>Access control and permission management for administrator accounts</p>
            </div>
            <?php if ($canManagePrivileges && $administrators): ?>
                <button class="btn btn-primary" type="button" id="openGrantModal"><span aria-hidden="true">+</span> GRANT PRIVILEGE</button>
            <?php endif; ?>
        </div>

        <?php if (!$canManagePrivileges): ?>
            <div class="empty-state">
                <strong>Privileges unavailable</strong>
                <span>A superadmin must grant administrator privilege management before these controls become available.</span>
            </div>
        <?php else: ?>
            <div class="user-privileges-stack">
                <?php foreach ($administrators as $admin): ?>
                    <?php 
                        $adminId = (int)$admin['id'];
                        $fullName = trim($admin['first_name'] . ' ' . $admin['last_name']) ?: $admin['username'];
                        $initials = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $fullName) ?: $admin['username'], 0, 2));
                        $userPrivs = get_user_privileges($conn, $adminId);
                        
                        $isSuperUnconfigured = $admin['role'] === ROLE_SUPERADMIN && !in_array(PRIV_SUPERADMIN_CONFIGURED, $userPrivs, true);
                        if ($isSuperUnconfigured) {
                            $userPrivs = array_keys($assignablePrivileges);
                        }

                        $activeCount = count(array_intersect($userPrivs, array_keys($assignablePrivileges)));
                        $totalCount = count($assignablePrivileges);
                        
                        if ($activeCount === $totalCount || $admin['role'] === ROLE_SUPERADMIN) {
                            $summaryText = 'Full Access';
                            $summaryClass = 'level-fullaccess';
                        } elseif ($activeCount > 0) {
                            $summaryText = $activeCount . ' / ' . $totalCount . ' Granted';
                            $summaryClass = 'level-readwrite';
                        } else {
                            $summaryText = 'No Access';
                            $summaryClass = 'level-noaccess';
                        }

                        $grantedDate = date('Y-m-d', strtotime($admin['created_at'] ?? '2024-01-01'));
                        $noteText = ($admin['role'] === ROLE_SUPERADMIN) ? 'Founding administrator account' : 'Secondary administrator — privileges managed by Superadmin';
                    ?>
                    <div class="user-privilege-card" data-admin-id="<?= $adminId; ?>">
                        <div class="user-privilege-card-header">
                            <div class="user-info-left">
                                <span class="user-avatar-circle"><?= e($initials); ?></span>
                                <div>
                                    <div class="user-name-row">
                                        <strong class="user-name-title"><?= e($fullName); ?></strong>
                                        <span class="user-role-badge role-<?= strtolower($admin['role']); ?>"><?= e(strtoupper($admin['role'])); ?></span>
                                    </div>
                                    <span class="user-email-text"><?= e($admin['email']); ?></span>
                                </div>
                            </div>
                            <div class="user-info-right">
                                <div class="user-access-summary">
                                    <strong class="access-level-title <?= e($summaryClass); ?>"><?= e($summaryText); ?></strong>
                                    <span class="access-meta-text">Granted by System &middot; <?= e($grantedDate); ?></span>
                                </div>
                                <?php if ($canManagePrivileges && ($admin['role'] !== ROLE_SUPERADMIN || ($isSuperadminActor && $adminId === (int)$actor['id']))): ?>
                                    <button class="btn btn-ghost edit-privilege-btn" type="button" 
                                            data-admin-id="<?= $adminId; ?>" 
                                            data-admin-name="<?= e($fullName); ?>"
                                            data-admin-privs='<?= json_encode(array_values($userPrivs)); ?>'>
                                        &#9998; EDIT
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Grid of assignable privileges for this user -->
                        <div class="module-permissions-grid">
                            <?php foreach ($assignablePrivileges as $privKey => $privLabel): ?>
                                <?php 
                                    $isGranted = in_array($privKey, $userPrivs, true) || ($admin['role'] === ROLE_SUPERADMIN && $isSuperUnconfigured);
                                    $statusText = $isGranted ? 'Granted' : 'No Access';
                                    $statusClass = $isGranted ? 'level-full' : 'level-none';
                                    $shortName = $shortLabels[$privKey] ?? $privLabel;
                                ?>
                                <div class="module-card <?= $statusClass; ?>" title="<?= e($privLabel); ?>">
                                    <span class="module-status"><?= e($statusText); ?></span>
                                    <span class="module-name"><?= e($shortName); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="user-card-note">
                            <strong>NOTE &rsaquo;</strong> <span><?= e($noteText); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (!$administrators): ?>
                    <div class="empty-state">
                        <strong>No administrator accounts found</strong>
                        <span>Create an Admin account first to assign specific permissions.</span>
                        <a class="btn btn-primary margin-top-12" href="create_admin.php">Create Admin Account</a>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <!-- GRANT / EDIT PRIVILEGE MODAL -->
    <div class="modal-backdrop" id="grantPrivilegeModal" hidden>
        <div class="modal privilege-modal" role="dialog" aria-modal="true" aria-labelledby="grantModalTitle">
            <div class="modal-header">
                <h3 id="grantModalTitle">EDIT PRIVILEGES</h3>
                <button type="button" class="modal-close" id="closeGrantModal" aria-label="Close">&times;</button>
            </div>
            <form action="manage_privileges_process.php" method="post" id="grantPrivilegeForm"
                  class="js-confirm"
                  data-confirm-title="Save privilege changes?"
                  data-confirm-message="The selected privileges will be updated immediately. Action will be logged in audit trail.">
                <input type="hidden" name="csrf_token" value="<?= e($token); ?>">
                <input type="hidden" name="target_id" id="modalTargetId" value="">

                <div class="modal-body privilege-modal-body">
                    <div class="form-group margin-bottom-14">
                        <label for="modalAdminSelect">Administrator Account</label>
                        <select id="modalAdminSelect" required>
                            <option value="">&mdash; Select administrator &mdash;</option>
                            <?php foreach ($administrators as $ad): ?>
                                <?php $adName = trim($ad['first_name'] . ' ' . $ad['last_name']) ?: $ad['username']; ?>
                                <option value="<?= (int)$ad['id']; ?>"><?= e($adName); ?> (<?= e($ad['email']); ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="module-permissions-header">
                        <strong>ASSIGNABLE PRIVILEGES</strong> <small>(click cards to toggle permissions)</small>
                    </div>

                    <div class="interactive-modules-grid">
                        <?php foreach ($assignablePrivileges as $privKey => $privLabel): ?>
                            <?php $shortName = $shortLabels[$privKey] ?? $privLabel; ?>
                            <label class="cycle-module-card level-none capability-card" data-priv="<?= e($privKey); ?>">
                                <input type="checkbox" name="privileges[]" value="<?= e($privKey); ?>" class="priv-checkbox" hidden>
                                <span class="cycle-name"><?= e($shortName); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="help-text margin-top-12">Select the individual capabilities granted to this administrator account.</div>
                </div>

                <div class="modal-footer privilege-modal-footer">
                    <button type="button" class="btn btn-ghost" id="cancelGrantModal">CANCEL</button>
                    <button type="submit" class="btn btn-primary">SAVE PRIVILEGES</button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    <?php include 'confirm_modal.php'; ?>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('grantPrivilegeModal');
            const openBtn = document.getElementById('openGrantModal');
            const closeBtn = document.getElementById('closeGrantModal');
            const cancelBtn = document.getElementById('cancelGrantModal');
            const titleEl = document.getElementById('grantModalTitle');
            const adminSelect = document.getElementById('modalAdminSelect');
            const targetIdInput = document.getElementById('modalTargetId');
            const capCards = document.querySelectorAll('.capability-card');

            function syncCardState(card) {
                const chk = card.querySelector('.priv-checkbox');
                const isChecked = chk && chk.checked;
                card.className = 'cycle-module-card capability-card level-' + (isChecked ? 'full' : 'none');
            }

            // Sync card visuals when a privilege checkbox changes (label click toggles natively)
            capCards.forEach(card => {
                const chk = card.querySelector('.priv-checkbox');
                if (!chk) return;
                chk.addEventListener('change', function() {
                    syncCardState(card);
                });
            });

            function showModal() { modal.hidden = false; }
            function hideModal() { modal.hidden = true; }

            if (openBtn) {
                openBtn.addEventListener('click', function() {
                    titleEl.textContent = 'GRANT PRIVILEGES';
                    adminSelect.value = '';
                    adminSelect.disabled = false;
                    targetIdInput.value = '';
                    capCards.forEach(c => {
                        c.querySelector('.priv-checkbox').checked = false;
                        syncCardState(c);
                    });
                    showModal();
                });
            }

            if (closeBtn) closeBtn.addEventListener('click', hideModal);
            if (cancelBtn) cancelBtn.addEventListener('click', hideModal);
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) hideModal();
                });
            }

            adminSelect.addEventListener('change', function() {
                targetIdInput.value = this.value;
            });

            const grantForm = document.getElementById('grantPrivilegeForm');
            if (grantForm) {
                grantForm.addEventListener('submit', function(e) {
                    if (!targetIdInput.value) {
                        e.preventDefault();
                        e.stopImmediatePropagation();
                        adminSelect.classList.add('input-error-field');
                        adminSelect.focus();
                        return;
                    }
                    adminSelect.classList.remove('input-error-field');
                }, true);
            }

            // Edit button handler for each admin card
            document.querySelectorAll('.edit-privilege-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const adminId = this.getAttribute('data-admin-id');
                    const adminName = this.getAttribute('data-admin-name');
                    const privsJson = this.getAttribute('data-admin-privs');
                    let activePrivs = [];
                    try { activePrivs = JSON.parse(privsJson) || []; } catch(e) {}

                    titleEl.textContent = 'EDIT PRIVILEGES FOR ' + adminName.toUpperCase();
                    adminSelect.value = adminId;
                    adminSelect.disabled = true;
                    targetIdInput.value = adminId;

                    capCards.forEach(card => {
                        const privKey = card.getAttribute('data-priv');
                        const chk = card.querySelector('.priv-checkbox');
                        chk.checked = activePrivs.includes(privKey);
                        syncCardState(card);
                    });

                    showModal();
                });
            });
        });
    </script>
</body>
</html>