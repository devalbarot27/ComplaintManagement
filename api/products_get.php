<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/product_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

admin_api_require_system_admin($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid record id.']);
    exit;
}

$row = product_get_by_id($obconn, $id);

if ($row === null) {
    http_response_code(404);
    api_json_echo(['error' => 'Record not found.']);
    exit;
}

api_json_echo([
    'id' => (int) ($row['id'] ?? 0),
    'dpst' => (string) ($row['dpst'] ?? ''),
    'product_group' => (string) ($row['product_group'] ?? ''),
    'tplcode' => (string) ($row['tplcode'] ?? ''),
    'tpldesc' => (string) ($row['tpldesc'] ?? ''),
    'dealer_price' => (string) ($row['dealer_price'] ?? ''),
    'tod_flag' => product_normalize_yn((string) ($row['tod_flag'] ?? ''), 'N'),
    'excisable' => product_normalize_yn((string) ($row['excisable'] ?? ''), 'N'),
    'mc' => (string) ($row['mc'] ?? ''),
    'vc' => (string) ($row['vc'] ?? ''),
    'fc' => (string) ($row['fc'] ?? ''),
    'cos' => (string) ($row['cos'] ?? ''),
    'valid' => product_normalize_yn((string) ($row['valid'] ?? ''), ''),
    'warehouse' => trim((string) ($row['warehouse'] ?? '')),
    'otcode' => trim((string) ($row['otcode'] ?? '')),
    'company' => (string) ($row['company'] ?? ''),
    'order_type' => (string) ($row['order_type'] ?? ''),
]);
