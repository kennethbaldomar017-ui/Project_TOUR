<?php
require_once 'config.php';

$actor = require_login($conn);
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Invalid request method');
}

function clean_value($value): string {
    return trim(htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'));
}

$mustChange = (int)($actor['must_change_password'] ?? 0);

$currentPassword = (string)($_POST['current_password'] ?? '');
$newPassword = (string)($_POST['new_password'] ?? '');
$confirmPassword = (string)($_POST['confirm_password'] ?? '');

$questions = [
    clean_value($_POST['question1'] ?? ''),
    clean_value($_POST['question2'] ?? ''),
    clean_value($_POST['question3'] ?? ''),
];
$answers = [
    (string)($_POST['auth_a1'] ?? ''),
    (string)($_POST['auth_a2'] ?? ''),
    (string)($_POST['auth_a3'] ?? ''),
];

$errors = [];

if (!$mustChange && $currentPassword === '') {
    $errors[] = 'Current password is required.';
}
if ($newPassword === '') {
    $errors[] = 'New password is required.';
}
if ($confirmPassword === '') {
    $errors[] = 'Please confirm your new password.';
}

// Basic question/answer validation.
foreach ([0, 1, 2] as $i) {
    if ($questions[$i] === '') {
        $errors[] = 'Security question ' . ($i + 1) . ' must be selected.';
    } elseif (!in_array($questions[$i], security_question_options(), true)) {
        $errors[] = 'Security question ' . ($i + 1) . ' is not a valid choice.';
    }
    if (strlen($answers[$i]) < 2) {
        $errors[] = 'Answer to security question ' . ($i + 1) . ' must be at least 2 characters.';
    }
    if (preg_match('/^\s*$/', $answers[$i])) {
        $errors[] = 'Answer to security question ' . ($i + 1) . ' is required.';
    }
}

// Password handling.
$passwordChanged = $newPassword !== '';
if ($passwordChanged) {
    if (!$mustChange) {
        if (!require_actor_current_password($conn, $actor, $currentPassword)) {
            $errors[] = 'Current password is incorrect.';
        }
    }
    if (strlen($newPassword) < 8 || strlen($newPassword) > 64) {
        $errors[] = 'New password must be between 8 and 64 characters.';
    }
    if ($newPassword !== $confirmPassword) {
        $errors[] = 'New passwords do not match.';
    }
} elseif ($mustChange) {
    $errors[] = 'You must set a new password before continuing.';
}

if ($errors) {
    $_SESSION['form_error'] = implode(' ', $errors);
    header('Location: edit_info.php');
    exit;
}

// Hash answers.
$hashedAnswers = [];
foreach ($answers as $answer) {
    $hashedAnswers[] = password_hash($answer, PASSWORD_DEFAULT);
}

$passwordHash = null;
if ($passwordChanged) {
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
}

try {
    $conn->begin_transaction();

    if ($passwordChanged) {
        $stmt = $conn->prepare('UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?');
        $stmt->bind_param('si', $passwordHash, $actor['id']);
        $stmt->execute();
        $stmt->close();
        log_audit_action($conn, $actor, $actor, 'password_change', null, null, null, null, 'User changed their own password');
    }

    $stmt = $conn->prepare('UPDATE users SET auth_q1 = ?, auth_a1 = ?, auth_q2 = ?, auth_a2 = ?, auth_q3 = ?, auth_a3 = ? WHERE id = ?');
    $stmt->bind_param(
        'ssssssi',
        $questions[0], $hashedAnswers[0],
        $questions[1], $hashedAnswers[1],
        $questions[2], $hashedAnswers[2],
        $actor['id']
    );
    $stmt->execute();
    $stmt->close();

    log_audit_action($conn, $actor, $actor, 'security_questions_updated', null, null, null, null, 'User updated their security questions');

    $conn->commit();

    if ($passwordChanged) {
        $_SESSION['success'] = $mustChange ? 'Your password has been updated. Welcome!' : 'Your password has been updated.';
    } else {
        $_SESSION['success'] = 'Your security questions have been updated.';
    }
    if ($mustChange) {
        unset($_SESSION['force_pwd_change']);
        header('Location: dashboard.php');
    } else {
        header('Location: edit_info.php');
    }
    exit;
} catch (Throwable $e) {
    @$conn->rollback();
    $_SESSION['form_error'] = 'Could not update your account: ' . $e->getMessage();
    header('Location: edit_info.php');
    exit;
}