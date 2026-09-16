<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/role_helpers.php';
require_once dirname(__DIR__) . '/includes/role_permission_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

admin_api_require_system_admin($obconn);

header('Content-Type: application/json; charset=utf-8');

$roleId = (int) ($_GET['role_id'] ?? 0);

if ($roleId <= 0) {
    api_json_echo(['modules' => []]);
    exit;
}

if (role_get_by_id($obconn, $roleId) === null) {
    http_response_code(404);
    api_json_echo(['error' => 'Role not found.']);
    exit;
}

$modules = role_permission_matrix($obconn, $roleId);
$response = [
    'role_id' => $roleId,
    'modules' => $modules,
];
unset($modules);
api_json_echo($response);
