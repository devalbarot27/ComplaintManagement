<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/notification_helpers.php';

header('Content-Type: application/json; charset=utf-8');

rbac_require_api_access($obconn);

$userId = current_user_id($obconn);
if ($userId === null || $userId <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

echo json_encode([
    'ok' => true,
    'unread_count' => notification_unread_count($obconn, $userId),
]);