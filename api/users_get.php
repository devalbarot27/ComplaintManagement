<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/user_helpers.php';

admin_api_require_system_admin($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user.']);
    exit;
}

$row = user_get_by_id($obconn, $id);

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found.']);
    exit;
}

echo json_encode([
    'id' => (int) $row['id'],
    'role' => (int) $row['role'],
    'username' => $row['username'],
    'name' => $row['name'],
    'email' => $row['email'],
    'mobile_number' => $row['mobile_number'],
]);