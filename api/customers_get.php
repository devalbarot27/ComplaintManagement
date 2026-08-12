<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/customer_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

admin_api_require_system_admin($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid record id.']);
    exit;
}

$row = customer_get_by_id($obconn, $id);

if ($row === null) {
    http_response_code(404);
    api_json_echo(['error' => 'Record not found.']);
    exit;
}

$customerCode = trim((string) ($row['customer_code'] ?? ''));
$customerName = trim((string) ($row['customer_name'] ?? ''));

api_json_echo([
    'id' => (int) ($row['id'] ?? 0),
    'customer_code' => $customerCode,
    'customer_name' => $customerName,
    'customer_code_text' => $customerCode !== ''
        ? ($customerName !== '' ? ($customerCode . ' - ' . $customerName) : $customerCode)
        : '',
]);
