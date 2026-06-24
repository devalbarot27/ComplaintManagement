<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/complaint_category_helpers.php';

admin_api_require_system_admin($obconn);

header('Content-Type: application/json');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid record id.']);
    exit;
}

$row = complaint_category_get_by_id($obconn, $id);

if ($row === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Record not found.']);
    exit;
}

echo json_encode([
    'id' => (int) $row['id'],
    'name' => $row['name'],
    'status' => $row['status'],
]);
