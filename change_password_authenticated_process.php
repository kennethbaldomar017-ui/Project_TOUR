<?php
require_once 'config.php';
$actor = require_login($conn);
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method');
}

$mustChange = (int)($actor['must_change_password'] ?? 0);
$currentPassword = (string)($_POST['current_password'] ?? '');
$newPassword = (string)($_POST['new_password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');
$questions = [
    trim((string)($_POST['question1'] ?? '')),
    trim((string)($_POST['question2'] ?? '')),
    trim((string)($_POST['question3'] ?? '')),
];
$answers = [
    (string)($_POST['auth_a1'] ?? ''),
    (string)($_POST['auth_a2'] ?? ''),
    (string)($_POST['auth_a3'] ?? ''),
];
$errors = [];

if (!$mustChange && !require_actor_current_password($conn, $actor, $currentPassword)) {
    $errors[] = 'Current password is incorrect.';
}
if (strlen($newPassword) < 8 || strlen($newPassword) > 64) {
    $errors[] = 'New password must be between 8 and 64 characters.';
}
if ($newPassword !== $confirmPassword) {
    $errors[] = 'New passwords do not match.';
}
foreach ($questions as $i => $question) {
    if (!in_array($question, security_question_options(), true)) {
        $errors[] = 'Security question ' . ($i + 1) . ' is invalid.';
    }
    if (strlen(trim($answers[$i])) < 2) {
        $errors[] = 'Security answer ' . ($i + 1) . ' must be at least 2 characters.';
    }
}

if ($errors) {
    $_SESSION['form_error'] = implode(' ', $errors);
    header('Location: change_password_authenticated.php');
    exit;
}

$passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
$hashedAnswers = array_map(static function (string $answer): string {
    return password_hash($answer, PASSWORD_DEFAULT);
}, $answers);
$stmt = $conn->prepare('UPDATE users SET password = ?, must_change_password = 0, auth_q1 = ?, auth_a1 = ?, auth_q2 = ?, auth_a2 = ?, auth_q3 = ?, auth_a3 = ? WHERE id = ?');
$stmt->bind_param('sssssssi', $passwordHash, $questions[0], $hashedAnswers[0], $questions[1], $hashedAnswers[1], $questions[2], $hashedAnswers[2], $actor['id']);
$stmt->execute();
$stmt->close();
log_audit_action($conn, $actor, $actor, 'password_change', null, null, null, null, 'User changed their own password');

unset($_SESSION['force_pwd_change']);
$_SESSION['success'] = $mustChange ? 'Your password has been updated. Now set your security questions.' : 'Your password has been updated.';
header('Location: edit_info.php');
exit;
