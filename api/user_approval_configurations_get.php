<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/user_approval_configuration_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

admin_api_require_system_admin($obconn);
user_approval_config_ensure_schema($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid record id.']);
    exit;
}

$row = user_approval_config_get_by_id($obconn, $id);

if ($row === null) {
    http_response_code(404);
    api_json_echo(['error' => 'Record not found.']);
    exit;
}

api_json_echo([
    'id' => (int) ($row['id'] ?? 0),
    'user_id' => (int) ($row['user_id'] ?? 0),
    'module_slug' => (string) ($row['module_slug'] ?? ''),
    'level_1_approval' => user_approval_config_bool_from_value($row['level_1_approval'] ?? false),
    'level_2_approval' => user_approval_config_bool_from_value($row['level_2_approval'] ?? false),
]);
