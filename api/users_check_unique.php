<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/user_helpers.php';

header('Content-Type: application/json; charset=utf-8');

admin_api_require_system_admin($obconn);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode([
        'valid' => false,
        'errors' => [
            'email' => ['Method not allowed. Use POST.'],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$recordId = (int) ($_POST['record_id'] ?? 0);
$email = trim((string) ($_POST['email'] ?? ''));
$mobileNumber = trim((string) ($_POST['mobile_number'] ?? ''));
$customerCode = trim((string) ($_POST['customer_code'] ?? ''));

$errors = [];

if ($email !== '' && user_email_exists($obconn, $email, $recordId)) {
    $errors['email'] = ['Email address already exists'];
}

if ($mobileNumber !== '' && user_mobile_exists($obconn, $mobileNumber, $recordId)) {
    $errors['mobile_number'] = ['Mobile number already exists'];
}

if ($customerCode !== '' && user_customer_code_exists($obconn, $customerCode, $recordId)) {
    $errors['customer_code'] = ['Customer Code already exists. Please choose a different Customer Code.'];
}

echo json_encode([
    'valid' => empty($errors),
    'errors' => $errors,
], JSON_UNESCAPED_UNICODE);
