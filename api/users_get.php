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
unset($row);

api_json_echo([
    'id' => $safeId,
    'role' => $safeRole,
    'username' => $safeUsername,
    'name' => $safeName,
    'email' => $safeEmail,
    'mobile_number' => $safeMobile,
    'sales_coordinator_id' => $safeSalesCoordinatorId,
]);
