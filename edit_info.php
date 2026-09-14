<?php
require_once 'config.php';
$actor = require_login($conn);

$mustChange = (int)($actor['must_change_password'] ?? 0);

// Pull the signed-in user's account details for display.
$qStmt = $conn->prepare('SELECT id_number, username, first_name, middle_name, last_name, extension, birthdate, age, email, street, barangay, city, province, country, zip FROM users WHERE id = ? LIMIT 1');
$qStmt->bind_param('i', $actor['id']);
$qStmt->execute();
$qRow = $qStmt->get_result()->fetch_assoc();
$qStmt->close();
$accountDetails = [
    'id_number' => $qRow['id_number'] ?? '',
    'username' => $qRow['username'] ?? '',
    'first_name' => $qRow['first_name'] ?? '',
    'middle_name' => $qRow['middle_name'] ?? '',
    'last_name' => $qRow['last_name'] ?? '',
    'extension' => $qRow['extension'] ?? '',
    'birthdate' => $qRow['birthdate'] ?? '',
    'age' => $qRow['age'] ?? '',
    'email' => $qRow['email'] ?? '',
    'street' => $qRow['street'] ?? '',
    'barangay' => $qRow['barangay'] ?? '',
    'city' => $qRow['city'] ?? '',
    'province' => $qRow['province'] ?? '',
    'country' => $qRow['country'] ?? '',
    'zip' => $qRow['zip'] ?? '',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Info | PRIME.</title>
    <link rel="stylesheet" href="css/style.css">
    <script src="js/validation.js" defer></script>
    <script src="js/confirm.js" defer></script>
</head>
<body>
    <?php include 'header.php'; ?>

    <?php if(isset($_SESSION['form_error'])): ?>
        <div class="form-errors" id="alertBox"><?= e($_SESSION['form_error']); unset($_SESSION['form_error']); ?></div>
    <?php endif; ?>
    <?php if(isset($_SESSION['success'])): ?>
        <div class="form-success" id="alertBox"><?= e($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <main class="container auth-page">
        <div class="auth-card edit-info-card">
            <div class="account-settings-heading">
                <div>
                    <h2>Account Settings</h2>
                    <p class="auth-subtitle">View your account information</p>
                </div>
                <a class="btn btn-ghost" href="account_info.php?id=<?= (int)$actor['id']; ?>">Edit Information</a>
            </div>

            <?php if ($mustChange): ?>
                <div class="banner banner-warning">
                    Your account uses a temporary password. Please set a new password before continuing.
                </div>
            <?php endif; ?>

            <div class="account-details-panel">
                <div class="account-details-heading">Personal Information</div>
                <div class="account-details-grid">
                    <div><span>First Name</span><strong><?= e($accountDetails['first_name']); ?></strong></div>
                    <div><span>Middle Name</span><strong><?= e($accountDetails['middle_name'] ?: 'Not provided'); ?></strong></div>
                    <div><span>Last Name</span><strong><?= e($accountDetails['last_name']); ?></strong></div>
                    <div><span>Extension</span><strong><?= e($accountDetails['extension'] ?: 'None'); ?></strong></div>
                    <div><span>Birthdate</span><strong><?= e($accountDetails['birthdate']); ?></strong></div>
                    <div><span>Age</span><strong><?= e($accountDetails['age']); ?></strong></div>
                </div>
            </div>

            <div class="account-details-panel">
                <div class="account-details-heading">Address Information</div>
                <div class="account-details-grid">
                    <div class="account-details-wide"><span>Street / Purok</span><strong><?= e($accountDetails['street']); ?></strong></div>
                    <div><span>Barangay</span><strong><?= e($accountDetails['barangay']); ?></strong></div>
                    <div><span>City / Municipality</span><strong><?= e($accountDetails['city']); ?></strong></div>
                    <div><span>Province</span><strong><?= e($accountDetails['province']); ?></strong></div>
                    <div><span>Country</span><strong><?= e($accountDetails['country']); ?></strong></div>
                    <div><span>ZIP Code</span><strong><?= e($accountDetails['zip']); ?></strong></div>
                </div>
            </div>

            <div class="account-details-panel">
                <div class="account-details-heading">Account Information</div>
                <div class="account-details-grid">
                    <div>
                        <span>Employee ID</span>
                        <strong><?= e($accountDetails['id_number']); ?></strong>
                    </div>
                    <div>
                        <span>Username</span>
                        <strong><?= e($accountDetails['username']); ?></strong>
                    </div>
                    <div>
                        <span>First Name</span>
                        <strong><?= e($accountDetails['first_name']); ?></strong>
                    </div>
                    <div>
                        <span>Last Name</span>
                        <strong><?= e($accountDetails['last_name']); ?></strong>
                    </div>
                    <div class="account-details-wide">
                        <span>Email</span>
                        <strong><?= e($accountDetails['email']); ?></strong>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <?php include 'footer.php'; ?>

</body>
</html>