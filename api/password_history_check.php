<?php
session_start();

require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/password_reset_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['valid' => false, 'error' => 'Invalid request method.']);
    exit;
}

$newPassword = (string) ($_POST['new_password'] ?? '');
$token = trim((string) ($_POST['token'] ?? ''));
$username = trim((string) ($_SESSION['usr_name'] ?? ''));

if ($token !== '') {
    $tokenRow = password_reset_find_valid_token($dpconn, $token);
    if ($tokenRow === null) {
        http_response_code(400);
        echo json_encode(['valid' => false, 'error' => 'Invalid or expired reset link. Please request a new one.']);
        exit;
    }
    $username = trim((string) $tokenRow['usr_name']);
}

if ($username === '') {
    http_response_code(401);
    echo json_encode(['valid' => false, 'error' => 'User session expired. Please log in again.']);
    exit;
}

$user = login_fetch_user_auth($obconn, $username);
if ($user === null) {
    http_response_code(404);
    echo json_encode(['valid' => false, 'error' => 'User account not found.']);
    exit;
}

$rulesError = password_reset_rules_error($newPassword);
if ($rulesError !== null) {
    echo json_encode(['valid' => false, 'error' => $rulesError]);
    exit;
}

if (password_history_is_reused($obconn, $username, $newPassword, (string) ($user['password'] ?? ''))) {
    echo json_encode(['valid' => false, 'error' => password_history_reuse_error()]);
    exit;
}

echo json_encode(['valid' => true]);
