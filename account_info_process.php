<?php
require_once 'config.php';

$actor = require_login($conn);
verify_csrf();
require_actor_confirmation($conn, $actor);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method');
}

$targetId = max(1, (int)($_POST['target_id'] ?? 0));
$target = get_user_by_id($conn, $targetId);
if (!$target || !can_manage_account($conn, $actor, $target, 'update_info')) {
    http_response_code(403);
    exit('You are not allowed to update this account.');
}

$idNumber = trim((string)($_POST['id_number'] ?? ''));
$username = trim((string)($_POST['username'] ?? ''));
$firstName = trim((string)($_POST['first_name'] ?? ''));
$middleName = trim((string)($_POST['middle_name'] ?? ''));
$lastName = trim((string)($_POST['last_name'] ?? ''));
$extension = trim((string)($_POST['extension'] ?? ''));
$birthdate = trim((string)($_POST['birthdate'] ?? ''));
$age = trim((string)($_POST['age'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$street = trim((string)($_POST['street'] ?? ''));
$barangay = trim((string)($_POST['barangay'] ?? ''));
$city = trim((string)($_POST['city'] ?? ''));
$province = trim((string)($_POST['province'] ?? ''));
$country = trim((string)($_POST['country'] ?? ''));
$zip = trim((string)($_POST['zip'] ?? ''));
$errors = [];

if (!preg_match('/^[0-9]{4}-[0-9]{4}$/', $idNumber)) {
    $errors[] = 'Employee ID must be in format xxxx-xxxx.';
}
if (!preg_match('/^[A-Za-z0-9_.\-]{8,16}$/', $username)) {
    $errors[] = 'Username must be 8-16 valid characters.';
}
if ($firstName === '' || $lastName === '') {
    $errors[] = 'First name and last name are required.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email is required.';
}

if (!$errors) {
    $stmt = $conn->prepare('SELECT id FROM users WHERE (id_number = ? OR username = ? OR email = ?) AND id <> ? LIMIT 1');
    $stmt->bind_param('sssi', $idNumber, $username, $email, $targetId);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = 'Employee ID, username, or email is already registered.';
    }
    $stmt->close();
}

if ($errors) {
    $_SESSION['form_error'] = implode(' ', $errors);
    header('Location: account_info.php?id=' . $targetId);
    exit;
}

try {
    $stmt = $conn->prepare('UPDATE users SET id_number = ?, username = ?, first_name = ?, middle_name = ?, last_name = ?, extension = ?, birthdate = ?, age = ?, street = ?, barangay = ?, city = ?, province = ?, country = ?, zip = ?, email = ? WHERE id = ?');
    $stmt->bind_param('sssssssssssssssi', $idNumber, $username, $firstName, $middleName, $lastName, $extension, $birthdate, $age, $street, $barangay, $city, $province, $country, $zip, $email, $targetId);
    $stmt->execute();
    $stmt->close();

    $updatedTarget = $target;
    $updatedTarget['id_number'] = $idNumber;
    $updatedTarget['username'] = $username;
    $updatedTarget['first_name'] = $firstName;
    $updatedTarget['middle_name'] = $middleName;
    $updatedTarget['last_name'] = $lastName;
    $updatedTarget['extension'] = $extension;
    $updatedTarget['birthdate'] = $birthdate;
    $updatedTarget['age'] = $age;
    $updatedTarget['street'] = $street;
    $updatedTarget['barangay'] = $barangay;
    $updatedTarget['city'] = $city;
    $updatedTarget['province'] = $province;
    $updatedTarget['country'] = $country;
    $updatedTarget['zip'] = $zip;
    $updatedTarget['email'] = $email;
    log_audit_action($conn, $actor, $updatedTarget, 'account_info_update', $target['status'], $target['status'], null, null, 'Account information updated');

    if ((int)$actor['id'] === $targetId) {
        $_SESSION['username'] = $username;
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name'] = $lastName;
    }
    $_SESSION['success'] = 'Account information updated.';
    header('Location: ' . ((int)$actor['id'] === $targetId ? 'edit_info.php' : 'admin_users.php'));
    exit;
} catch (Throwable $e) {
    $_SESSION['form_error'] = 'Could not update account information: ' . $e->getMessage();
    header('Location: account_info.php?id=' . $targetId);
    exit;
}
