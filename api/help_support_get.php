<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/help_support_helpers.php';

help_support_ensure_schema($obconn);
help_support_ensure_rbac($obconn);
rbac_require_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid record id.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$row = help_support_get_by_id($obconn, $id);

if ($row === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Record not found.'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode([
    'id' => (int) ($row['id'] ?? 0),
    'category' => (string) ($row['category'] ?? ''),
    'title' => (string) ($row['title'] ?? ''),
    'description' => (string) ($row['description'] ?? ''),
    'qty' => (int) ($row['qty'] ?? 0),
    'attachment_path' => (string) ($row['attachment_path'] ?? ''),
    'attachment_name' => (string) ($row['attachment_name'] ?? ''),
    'resource_url' => (string) ($row['resource_url'] ?? ''),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
