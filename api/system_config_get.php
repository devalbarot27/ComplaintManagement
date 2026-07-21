<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/system_config_master_helpers.php';

admin_api_require_system_admin($obconn);

header('Content-Type: application/json');

$type = trim((string) ($_GET['type'] ?? ''));
$id = (int) ($_GET['id'] ?? 0);

try {
    scm_config($type);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid master type.']);
    exit;
}

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid record id.']);
    exit;
}

$row = scm_get_by_id($obconn, $type, $id);

if ($row === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Record not found.']);
    exit;
}

echo json_encode([
    'id' => (int) $row['id'],
    'name' => htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'status' => $row['status'],
]);
