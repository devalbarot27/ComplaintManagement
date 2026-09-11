<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/role_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

admin_api_require_system_admin($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
$row = role_get_by_id($obconn, $id);

if ($row === null) {
    http_response_code(404);
    api_json_echo(['error' => 'Role not found.']);
    exit;
}

$safeId = (int) ($row['id'] ?? 0);
$safeName = (string) ($row['role_name'] ?? '');
$safeDescription = (string) ($row['description'] ?? '');
$safeStatus = (string) ($row['status'] ?? '');
unset($row);

api_json_echo([
    'id' => $safeId,
    'role_name' => $safeName,
    'description' => $safeDescription,
    'status' => $safeStatus,
]);
