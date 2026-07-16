<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_service_log_draft_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed.']);
    exit;
}

$complaintId = (int) ($_POST['complaint_id'] ?? 0);
complaint_service_log_require_assigned_access($obconn, $complaintId);

$createdBy = current_user_id($obconn);
if ($createdBy === null || $createdBy <= 0) {
    http_response_code(401);
    echo json_encode(['error' => 'Unable to resolve logged-in user.']);
    exit;
}

if (empty($_POST['from_complaint_modal'])) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid complaint service log draft request.']);
    exit;
}

$result = complaint_service_log_save_draft_record(
    $obconn,
    $_POST,
    current_username(),
    (int) $createdBy
);

if (!$result['success']) {
    http_response_code(422);
    echo json_encode(['error' => $result['message']]);
    exit;
}

echo json_encode([
    'success' => true,
    'message' => $result['message'],
    'service_log_id' => (int) ($result['service_log_id'] ?? 0),
    'complaint_id' => (int) ($result['complaint_id'] ?? $complaintId),
    'is_draft' => 1,
]);