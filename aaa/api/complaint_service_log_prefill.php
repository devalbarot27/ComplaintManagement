<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_service_log_helpers.php';
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$complaintId = (int) ($_GET['complaint_id'] ?? 0);

if ($complaintId <= 0) {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid complaint record.']);
    exit;
}

complaint_service_log_require_assigned_access($obconn, $complaintId);

if (!after_market_user_can_add_service_log($obconn)) {
    http_response_code(403);
    api_json_echo(['error' => 'Access denied. You do not have permission to add service log records.']);
    exit;
}

$result = complaint_service_log_prefill_payload($obconn, $complaintId, current_username());

if (!$result['success']) {
    $errorMessage = (string) ($result['error'] ?? '');
    unset($result);
    http_response_code(422);
    api_json_echo(['error' => $errorMessage]);
    exit;
}

$response = $result;
unset($result);
api_json_echo($response);
