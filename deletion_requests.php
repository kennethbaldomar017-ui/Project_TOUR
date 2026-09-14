<?php
require_once 'config.php';
$actor = require_superadmin($conn);
$requests = [];
$stmt = $conn->prepare("SELECT id, target_id, target_id_number, target_username, target_name, target_email, target_role, target_status, requested_by, reason, created_at FROM deletion_requests WHERE status = 'pending' ORDER BY created_at ASC");
$stmt->execute();
$requests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
$token = csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deletion Requests | PRIME.</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'header.php'; ?>
<?php if (isset($_SESSION['form_error'])): ?>
    <div class="form-errors" id="alertBox"><?= e($_SESSION['form_error']); unset($_SESSION['form_error']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['success'])): ?>
    <div class="form-success" id="alertBox"><?= e($_SESSION['success']); unset($_SESSION['success']); ?></div>
<?php endif; ?>
<main class="container admin-page deletion-requests-page">
    <section class="admin-panel admin-data-page deletion-requests-panel">
        <div class="admin-heading deletion-requests-heading">
            <div><p class="page-eyebrow">SUPERADMIN CONTROL</p><h2>Deletion Requests</h2><p>Review administrator requests before accounts are permanently removed.</p></div>
            <span class="page-count"><strong><?= count($requests); ?></strong> pending</span>
        </div>
        <div class="table-wrap">
            <table class="admin-table">
                <thead><tr><th>Account</th><th>Requested by</th><th>Reason</th><th>Date</th><th>Decision</th></tr></thead>
                <tbody>
                <?php foreach ($requests as $request): ?>
                    <tr>
                        <td><strong><?= e($request['target_name']); ?></strong><span><?= e($request['target_id_number']); ?> · <?= e($request['target_username']); ?> · <?= e($request['target_email']); ?></span></td>
                        <td>#<?= (int)$request['requested_by']; ?></td>
                        <td><?= e($request['reason']); ?></td>
                        <td><?= e($request['created_at']); ?></td>
                        <td>
                            <div class="action-stack">
                                <form method="post" action="deletion_request_action.php" class="js-confirm" data-confirm-title="Approve deletion?" data-confirm-message="This permanently deletes the requested account and directly related records.">
                                    <input type="hidden" name="csrf_token" value="<?= e($token); ?>"><input type="hidden" name="request_id" value="<?= (int)$request['id']; ?>"><input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn btn-danger">Approve</button>
                                </form>
                                <form method="post" action="deletion_request_action.php" class="js-confirm" data-confirm-title="Reject deletion request?" data-confirm-message="The request will be closed without deleting the account.">
                                    <input type="hidden" name="csrf_token" value="<?= e($token); ?>"><input type="hidden" name="request_id" value="<?= (int)$request['id']; ?>"><input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn btn-ghost">Reject</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$requests): ?><tr><td colspan="5" class="empty-state"><strong>No pending deletion requests</strong><span>All submitted requests have been reviewed.</span></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</main>
<?php include 'footer.php'; ?>
<?php include 'confirm_modal.php'; ?>
</body>
</html>
