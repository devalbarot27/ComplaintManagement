<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/customer_helpers.php';

header('Content-Type: application/json; charset=utf-8');

admin_api_require_system_admin($obconn);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    echo json_encode([
        'valid' => false,
        'errors' => [
            'customer_code' => ['Method not allowed. Use POST.'],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$recordId = (int) ($_POST['record_id'] ?? 0);
$customerCode = trim((string) ($_POST['customer_code'] ?? ''));

$errors = [];

if ($customerCode !== '' && customer_code_exists($obconn, $customerCode, $recordId)) {
    $errors['customer_code'] = ['Customer code already exists in sync list. Please choose a different code.'];
}

echo json_encode([
    'valid' => empty($errors),
    'errors' => $errors,
], JSON_UNESCAPED_UNICODE);
