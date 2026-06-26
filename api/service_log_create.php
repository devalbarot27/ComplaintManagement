<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/service_log_helpers.php';

rbac_require_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$createdBy = current_user_id($obconn);
if ($createdBy === null || $createdBy <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Unable to resolve logged-in user.']);
    exit;
}

$result = service_log_create_record($obconn, $_POST, current_username(), $createdBy);

if (!$result['success']) {
    http_response_code(422);
    echo json_encode(['error' => $result['message']]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $result['message'],
    'service_log_id' => (int) ($result['service_log_id'] ?? 0),
    'installed_base_id' => (int) ($result['installed_base_id'] ?? 0),
]);
