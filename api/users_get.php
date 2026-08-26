<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/user_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid user.']);
    exit;
}

$row = user_get_by_id($obconn, $id);

if (!$row) {
    http_response_code(404);
    api_json_echo(['error' => 'User not found.']);
    exit;
}

$safeId = (int) ($row['id'] ?? 0);
$safeRole = (int) ($row['role'] ?? 0);
$safeUsername = (string) ($row['username'] ?? '');
$safeName = (string) ($row['name'] ?? '');
$safeEmail = (string) ($row['email'] ?? '');
$safeMobile = (string) ($row['mobile_number'] ?? '');
$safeSalesCoordinatorId = isset($row['sales_coordinator_id']) ? (int) $row['sales_coordinator_id'] : 0;
$safeCustomerCode = trim((string) ($row['customer_code'] ?? ''));
$safeCustomerLabel = $safeCustomerCode !== ''
    ? user_customer_code_label($obconn, $safeCustomerCode)
    : '';
$safeLevel1Approval = user_bool_from_value($row['level_1_approval'] ?? false);
$safeLevel2Approval = user_bool_from_value($row['level_2_approval'] ?? false);
unset($row);

api_json_echo([
    'id' => $safeId,
    'role' => $safeRole,
    'username' => $safeUsername,
    'name' => $safeName,
    'email' => $safeEmail,
    'mobile_number' => $safeMobile,
    'sales_coordinator_id' => $safeSalesCoordinatorId,
    'customer_code' => $safeCustomerCode,
    'customer_code_text' => $safeCustomerLabel,
    'level_1_approval' => $safeLevel1Approval,
    'level_2_approval' => $safeLevel2Approval,
]);
