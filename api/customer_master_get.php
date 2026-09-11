<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/customer_master_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

rbac_require_api_access($obconn);
admin_ensure_session_role($obconn);
customer_master_ensure_schema($obconn);
customer_master_ensure_rbac($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid record id.']);
    exit;
}

$row = customer_master_get_by_id($obconn, $id);

if ($row === null || !customer_master_user_can_access_record($obconn, $row)) {
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
    'dealer_code' => trim((string) ($row['dealer_code'] ?? '')),
    'dealer_name' => trim((string) ($row['dealer_name'] ?? '')),
    'gst_number' => trim((string) ($row['gst_number'] ?? '')),
    'pan_number' => trim((string) ($row['pan_number'] ?? '')),
    'added_by' => trim((string) ($row['added_by'] ?? $row['created_by'] ?? '')),
]);