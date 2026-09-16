<?php
session_start();

require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/password_reset_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'valid' => false,
        'error' => htmlspecialchars('Invalid request method.', ENT_QUOTES, 'UTF-8'),
    ]);
    exit;
}

$newPassword = (string) ($_POST['new_password'] ?? '');
$token = trim((string) ($_POST['token'] ?? ''));
$username = trim((string) ($_SESSION['usr_name'] ?? ''));

if ($token !== '') {
    $tokenRow = password_reset_find_valid_token($dpconn, $token);
    if ($tokenRow === null) {
        http_response_code(400);
        echo json_encode([
            'valid' => false,
            'error' => htmlspecialchars('Invalid or expired reset link. Please request a new one.', ENT_QUOTES, 'UTF-8'),
        ]);
        exit;
    }
    $username = trim((string) $tokenRow['usr_name']);
}

if ($username === '') {
    http_response_code(401);
    echo json_encode([
        'valid' => false,
        'error' => htmlspecialchars('User session expired. Please log in again.', ENT_QUOTES, 'UTF-8'),
    ]);
    exit;
}

$user = login_fetch_user_auth($obconn, $username);
if ($user === null) {
    http_response_code(404);
    echo json_encode([
        'valid' => false,
        'error' => htmlspecialchars('User account not found.', ENT_QUOTES, 'UTF-8'),
    ]);
    exit;
}

$rulesError = password_reset_rules_error($newPassword);
if ($rulesError !== null) {
    echo json_encode([
        'valid' => false,
        'error' => htmlspecialchars((string) $rulesError, ENT_QUOTES, 'UTF-8'),
    ]);
    exit;
}

if (password_history_is_reused($obconn, $username, $newPassword, (string) ($user['password'] ?? ''))) {
    echo json_encode([
        'valid' => false,
        'error' => htmlspecialchars((string) password_history_reuse_error(), ENT_QUOTES, 'UTF-8'),
    ]);
    exit;
}

echo json_encode(['valid' => true]);
