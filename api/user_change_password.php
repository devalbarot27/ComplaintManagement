<?php

session_start();

require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/user_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';
require_once dirname(__DIR__) . '/includes/login_helpers.php';

header('Content-Type: application/json; charset=utf-8');

admin_api_require_system_admin($obconn);
login_enforce_idle_timeout(true, false);
login_enforce_session_version($obconn, true);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    api_json_echo(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$userId = (int) ($_POST['user_id'] ?? 0);
$password = (string) ($_POST['password'] ?? '');
$confirmPassword = (string) ($_POST['confirm_password'] ?? '');

$error = user_admin_change_password($obconn, $userId, $password, $confirmPassword);
if ($error !== null) {
    http_response_code($error === 'User not found.' ? 404 : 422);
    api_json_echo(['success' => false, 'error' => $error]);
    exit;
}

$_SESSION['success_message'] = 'Password changed successfully.';
api_json_echo(['success' => true]);
