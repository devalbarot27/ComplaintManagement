<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/permission_helpers.php';

admin_api_require_system_admin($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
$row = permission_get_by_id($obconn, $id);

if ($row === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Permission not found.']);
    exit;
}

echo json_encode([
    'id' => (int) $row['id'],
    'module_id' => (int) $row['module_id'],
    'permission_name' => $row['permission_name'],
    'permission_slug' => $row['permission_slug'],
    'description' => $row['description'] ?? '',
    'status' => $row['status'],
], JSON_UNESCAPED_UNICODE);
