<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/customer_master_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

admin_api_require_system_admin($obconn);
customer_master_ensure_schema($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid record id.']);
    exit;
}

$row = customer_master_get_by_id($obconn, $id);

if ($row === null) {
    http_response_code(404);
    api_json_echo(['error' => 'Record not found.']);
    exit;
}

api_json_echo([
    'id' => (int) ($row['id'] ?? 0),
    'customer_name' => trim((string) ($row['customer_name'] ?? '')),
    'email' => trim((string) ($row['email'] ?? '')),
    'mobile' => trim((string) ($row['mobile'] ?? '')),
    'street_1' => trim((string) ($row['street_1'] ?? '')),
    'street_2' => trim((string) ($row['street_2'] ?? '')),
    'pincode' => trim((string) ($row['pincode'] ?? '')),
    'city' => trim((string) ($row['city'] ?? '')),
    'district' => trim((string) ($row['district'] ?? '')),
    'state' => trim((string) ($row['state'] ?? '')),
]);
