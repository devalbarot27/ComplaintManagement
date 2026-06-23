<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/module_helpers.php';

admin_api_require_system_admin($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
$row = module_get_by_id($obconn, $id);

if ($row === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Module not found.']);
    exit;
}

echo json_encode([
    'id' => (int) $row['id'],
    'module_name' => $row['module_name'],
    'module_slug' => $row['module_slug'],
    'description' => $row['description'] ?? '',
    'status' => $row['status'],
], JSON_UNESCAPED_UNICODE);
