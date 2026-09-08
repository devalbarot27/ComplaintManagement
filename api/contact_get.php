<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/contact_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

rbac_require_api_access($obconn);
contact_ensure_schema($obconn);
contact_ensure_rbac($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid record id.']);
    exit;
}

$row = contact_get_by_id($obconn, $id);

if ($row === null) {
    http_response_code(404);
    api_json_echo(['error' => 'Record not found.']);
    exit;
}

api_json_echo([
    'id' => (int) ($row['id'] ?? 0),
    'customer_id' => (int) ($row['customer_id'] ?? 0),
    'customer_label' => contact_customer_label($row),
    'customer_name' => trim((string) ($row['customer_name'] ?? '')),
    'first_name' => trim((string) ($row['first_name'] ?? '')),
    'last_name' => trim((string) ($row['last_name'] ?? '')),
    'email' => trim((string) ($row['email'] ?? '')),
    'mobile' => trim((string) ($row['mobile'] ?? '')),
]);
