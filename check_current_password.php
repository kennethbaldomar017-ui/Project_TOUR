<?php
require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');

$actor = require_login($conn);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Invalid request method']);
    exit;
}

verify_csrf();
$password = (string)($_POST['password'] ?? '');

if ($password === '' || strlen($password) < 8 || strlen($password) > 64) {
    echo json_encode(['ok' => true, 'valid' => false]);
    exit;
}

echo json_encode([
    'ok' => true,
    'valid' => require_actor_current_password($conn, $actor, $password),
]);
