<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/notification_helpers.php';

header('Content-Type: application/json; charset=utf-8');

rbac_require_api_access($obconn);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$userId = current_user_id($obconn);
if ($userId === null || $userId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

$updated = notification_mark_all_read($obconn, $userId);

echo json_encode([
    'ok' => true,
    'updated' => $updated,
    'unread_count' => 0,
]);