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

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    $input = $_POST;
}

$notificationId = (int) ($input['id'] ?? $input['notification_id'] ?? 0);
if ($notificationId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid notification.']);
    exit;
}

$notification = notification_get_for_user($obconn, $userId, $notificationId);
if ($notification === null) {
    http_response_code(404);
    echo json_encode(['error' => 'Notification not found.']);
    exit;
}

notification_mark_read($obconn, $userId, $notificationId);
$updated = notification_get_for_user($obconn, $userId, $notificationId);

echo json_encode([
    'ok' => true,
    'notification' => $updated,
    'unread_count' => notification_unread_count($obconn, $userId),
]);