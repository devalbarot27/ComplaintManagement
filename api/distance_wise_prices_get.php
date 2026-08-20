<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/distance_wise_price_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

admin_api_require_system_admin($obconn);
distance_wise_price_ensure_schema($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid record id.']);
    exit;
}

$row = distance_wise_price_get_by_id($obconn, $id);

if ($row === null) {
    http_response_code(404);
    api_json_echo(['error' => 'Record not found.']);
    exit;
}

api_json_echo([
    'id' => (int) ($row['id'] ?? 0),
    'range_type' => (string) ($row['range_type'] ?? 'between'),
    'from_km' => $row['from_km'] === null || $row['from_km'] === ''
        ? ''
        : distance_wise_price_format_number($row['from_km']),
    'to_km' => $row['to_km'] === null || $row['to_km'] === ''
        ? ''
        : distance_wise_price_format_number($row['to_km']),
    'price' => distance_wise_price_format_number($row['price'] ?? ''),
    'status' => (string) ($row['status'] ?? 'active'),
]);
