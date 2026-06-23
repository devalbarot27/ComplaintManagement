<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/user_helpers.php';

admin_api_require_system_admin($obconn);

header('Content-Type: application/json; charset=utf-8');

$recordId = (int) ($_GET['record_id'] ?? $_POST['record_id'] ?? 0);
$email = trim((string) ($_GET['email'] ?? $_POST['email'] ?? ''));
$mobileNumber = trim((string) ($_GET['mobile_number'] ?? $_POST['mobile_number'] ?? ''));

$errors = [];

if ($email !== '' && user_email_exists($obconn, $email, $recordId)) {
    $errors['email'] = ['Email address already exists'];
}

if ($mobileNumber !== '' && user_mobile_exists($obconn, $mobileNumber, $recordId)) {
    $errors['mobile_number'] = ['Mobile number already exists'];
}

echo json_encode([
    'valid' => empty($errors),
    'errors' => $errors,
], JSON_UNESCAPED_UNICODE);