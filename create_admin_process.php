<?php
require_once 'config.php';

$actor = require_superadmin($conn);
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method');
}

function clean_admin_value($value): string {
    return trim(htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
}

$idNumber = clean_admin_value($_POST['id_number'] ?? '');
$firstName = clean_admin_value($_POST['first_name'] ?? '');
$lastName = clean_admin_value($_POST['last_name'] ?? '');
$email = clean_admin_value($_POST['email'] ?? '');
$username = clean_admin_value($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$role = $_POST['role'] ?? ROLE_ADMIN;
$reason = clean_admin_value($_POST['reason'] ?? '');

$errors = [];
if (!can_create_role($actor, $role) || !in_array($role, [ROLE_ADMIN, ROLE_SUPERADMIN], true)) {
    $errors[] = 'You are not allowed to create that role.';
}
if (!preg_match('/^[0-9]{4}-[0-9]{4}$/', $idNumber)) {
    $errors[] = 'ID Number must be in format xxxx-xxxx.';
}
if (!preg_match('/^[A-Za-z0-9_.\-]{8,16}$/', $username)) {
    $errors[] = 'Username must be 8-16 valid characters.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Invalid email format.';
}
if ($password !== $confirmPassword) {
    $errors[] = 'Passwords do not match.';
}
if (strlen($password) < 8 || strlen($password) > 64) {
    $errors[] = 'Password must be between 8 and 64 characters.';
}
if ($firstName === '' || $lastName === '') {
    $errors[] = 'First and last name are required.';
}

if (!$errors) {
    $stmt = $conn->prepare('SELECT id FROM users WHERE id_number = ? OR email = ? OR username = ? LIMIT 1');
    $stmt->bind_param('sss', $idNumber, $email, $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $errors[] = 'ID Number, Email or Username already registered.';
    }
    $stmt->close();
}

if ($errors) {
    log_audit_action($conn, $actor, null, 'failed_authorization', null, null, null, null, implode(' ', $errors));
    $_SESSION['form_error'] = implode(' ', $errors);
    header('Location: create_admin.php');
    exit;
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$placeholderHash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
$birthdate = date('Y-m-d', strtotime('-18 years'));
$age = 18;
$middleName = '';
$extension = '';
$street = 'Admin Office';
$barangay = 'Admin';
$city = 'Admin';
$province = 'Admin';
$country = 'Philippines';
$zip = '0000';
$question = 'Created by superadmin';

try {
    $conn->begin_transaction();
    $stmt = $conn->prepare('INSERT INTO users (
        id_number, first_name, middle_name, last_name, extension, birthdate, age,
        street, barangay, city, province, country, zip,
        email, username, password, role, status,
        auth_q1, auth_a1, auth_q2, auth_a2, auth_q3, auth_a3
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $status = STATUS_ACTIVE;
    $stmt->bind_param(
        'ssssssisssssssssssssssss',
        $idNumber,
        $firstName,
        $middleName,
        $lastName,
        $extension,
        $birthdate,
        $age,
        $street,
        $barangay,
        $city,
        $province,
        $country,
        $zip,
        $email,
        $username,
        $hashedPassword,
        $role,
        $status,
        $question,
        $placeholderHash,
        $question,
        $placeholderHash,
        $question,
        $placeholderHash
    );
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    $target = [
        'id' => $newId,
        'username' => $username,
        'role' => $role,
        'status' => STATUS_ACTIVE,
    ];
    if (!log_audit_action($conn, $actor, $target, 'account_creation', null, STATUS_ACTIVE, null, null, $reason ?: null)) {
        throw new RuntimeException('Could not write audit log.');
    }
    $conn->commit();
    $_SESSION['success'] = ucfirst($role) . ' account created.';
    header('Location: admin_users.php');
    exit;
} catch (Throwable $e) {
    @$conn->rollback();
    $_SESSION['form_error'] = 'Account could not be created: ' . $e->getMessage();
    header('Location: create_admin.php');
    exit;
}
?>
