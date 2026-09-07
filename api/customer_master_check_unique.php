<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/customer_master_helpers.php';

header('Content-Type: application/json; charset=utf-8');

admin_ensure_session_role($obconn);
if (!is_system_admin() && !customer_master_user_can_create_from_installed_base($obconn)) {
    http_response_code(403);
    echo json_encode([
        'valid' => false,
        'errors' => [
            'email' => ['Access denied.'],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

customer_master_ensure_schema($obconn);

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
$mobile = trim((string) ($_POST['mobile'] ?? ''));

$errors = [];

if ($email !== '' && customer_master_email_exists($obconn, $email, $recordId)) {
    $errors['email'] = ['Email already exists'];
}

if ($mobile !== '' && customer_master_mobile_exists($obconn, $mobile, $recordId)) {
    $errors['mobile'] = ['Mobile already exists'];
}

echo json_encode([
    'valid' => empty($errors),
    'errors' => $errors,
], JSON_UNESCAPED_UNICODE);
