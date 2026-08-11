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
            'cust_code' => ['Method not allowed. Use POST.'],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$excludeCuno = trim((string) ($_POST['original_cuno'] ?? ''));
$custCode = trim((string) ($_POST['cust_code'] ?? ''));
$custName = trim((string) ($_POST['cust_name'] ?? ''));

$errors = [];

if ($custCode !== '' && customer_code_exists($obconn, $custCode, $excludeCuno)) {
    $errors['cust_code'] = ['Customer code already exists. Please choose a different code.'];
}

if ($custName !== '' && customer_name_exists($obconn, $custName, $excludeCuno)) {
    $errors['cust_name'] = ['Customer name already exists. Please choose a different name.'];
}

echo json_encode([
    'valid' => empty($errors),
    'errors' => $errors,
], JSON_UNESCAPED_UNICODE);
