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
    echo json_encode([
        'error' => htmlspecialchars('Module not found.', ENT_QUOTES, 'UTF-8'),
    ]);
    exit;
}

echo json_encode([
    'id' => (int) $row['id'],
    'module_name' => htmlspecialchars((string) ($row['module_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'module_slug' => htmlspecialchars((string) ($row['module_slug'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'description' => htmlspecialchars((string) ($row['description'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'status' => $row['status'],
], JSON_UNESCAPED_UNICODE);
