<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/system_config_master_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

admin_api_require_system_admin($obconn);

header('Content-Type: application/json; charset=utf-8');

$type = trim((string) ($_GET['type'] ?? ''));
$id = (int) ($_GET['id'] ?? 0);

try {
    scm_config($type);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid master type.']);
    exit;
}

if ($id <= 0) {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid record id.']);
    exit;
}

$row = scm_get_by_id($obconn, $type, $id);

if ($row === null) {
    http_response_code(404);
    api_json_echo(['error' => 'Record not found.']);
    exit;
}

$safeId = (int) ($row['id'] ?? 0);
$safeName = (string) ($row['name'] ?? '');
$safeStatus = (string) ($row['status'] ?? '');
unset($row);

api_json_echo([
    'id' => $safeId,
    'name' => $safeName,
    'status' => $safeStatus,
]);
