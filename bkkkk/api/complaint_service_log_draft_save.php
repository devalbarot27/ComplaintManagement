<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_service_log_draft_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    api_json_echo(['error' => 'Method not allowed.']);
    exit;
}

$complaintId = (int) ($_POST['complaint_id'] ?? 0);
complaint_service_log_require_assigned_access($obconn, $complaintId);

$createdBy = current_user_id($obconn);
if ($createdBy === null || $createdBy <= 0) {
    http_response_code(401);
    api_json_echo(['error' => 'Unable to resolve logged-in user.']);
    exit;
}

if (empty($_POST['from_complaint_modal'])) {
    http_response_code(422);
    api_json_echo(['error' => 'Invalid complaint service log draft request.']);
    exit;
}

$result = complaint_service_log_save_draft_record(
    $obconn,
    $_POST,
    current_username(),
    (int) $createdBy
);

if (!$result['success']) {
    $errorMessage = (string) ($result['message'] ?? '');
    unset($result);
    http_response_code(422);
    api_json_echo(['error' => $errorMessage]);
    exit;
}

$safeMessage = (string) ($result['message'] ?? '');
$safeServiceLogId = (int) ($result['service_log_id'] ?? 0);
$safeComplaintId = (int) ($result['complaint_id'] ?? $complaintId);
unset($result);

api_json_echo([
    'success' => true,
    'message' => $safeMessage,
    'service_log_id' => $safeServiceLogId,
    'complaint_id' => $safeComplaintId,
    'is_draft' => 1,
]);
