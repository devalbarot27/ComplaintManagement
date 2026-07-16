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

$limit = (int) ($_GET['limit'] ?? 5);
$offset = (int) ($_GET['offset'] ?? 0);
$limit = max(1, min(50, $limit));
$offset = max(0, $offset);

$items = notification_list_for_user($obconn, $userId, $limit, $offset);
$total = notification_count_for_user($obconn, $userId);
$unread = notification_unread_count($obconn, $userId);

echo json_encode([
    'ok' => true,
    'items' => $items,
    'total' => $total,
    'unread_count' => $unread,
    'limit' => $limit,
    'offset' => $offset,
    'has_more' => ($offset + count($items)) < $total,
]);