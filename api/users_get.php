<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/user_helpers.php';

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
    'username' => htmlspecialchars((string) ($row['username'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'name' => htmlspecialchars((string) ($row['name'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'email' => htmlspecialchars((string) ($row['email'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'mobile_number' => htmlspecialchars((string) ($row['mobile_number'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'sales_coordinator_id' => isset($row['sales_coordinator_id']) ? (int) $row['sales_coordinator_id'] : 0,
]);