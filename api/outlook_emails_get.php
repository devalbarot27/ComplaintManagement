<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/outlook_email_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

admin_api_require_system_admin($obconn);
outlook_email_ensure_schema($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid record id.']);
    exit;
}

$row = outlook_email_get_by_id($obconn, $id);

if ($row === null) {
    http_response_code(404);
    api_json_echo(['error' => 'Record not found.']);
    exit;
}

api_json_echo([
    'id' => (int) ($row['id'] ?? 0),
    'user_id' => (int) ($row['user_id'] ?? 0),
    'user_label' => outlook_email_user_label([
        'name' => $row['user_name'] ?? '',
        'username' => $row['username'] ?? '',
    ]),
    'outlook_email' => (string) ($row['outlook_email'] ?? ''),
]);
