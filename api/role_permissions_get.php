<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/role_helpers.php';
require_once dirname(__DIR__) . '/includes/role_permission_helpers.php';

admin_api_require_system_admin($obconn);

header('Content-Type: application/json; charset=utf-8');

$roleId = (int) ($_GET['role_id'] ?? 0);

if ($roleId <= 0) {
    echo json_encode(['modules' => []]);
    exit;
}

if (role_get_by_id($obconn, $roleId) === null) {
    http_response_code(404);
    echo json_encode([
        'error' => htmlspecialchars('Role not found.', ENT_QUOTES, 'UTF-8'),
    ]);
    exit;
}

$modules = role_permission_matrix($obconn, $roleId);
array_walk_recursive($modules, function (&$val) {
    if (is_string($val)) {
        $val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
    }
});

echo json_encode([
    'role_id' => $roleId,
    'modules' => $modules,
], JSON_UNESCAPED_UNICODE);
