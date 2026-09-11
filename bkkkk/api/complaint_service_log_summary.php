<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_service_log_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

rbac_require_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

$complaintId = (int) ($_GET['complaint_id'] ?? 0);

if ($complaintId <= 0) {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid complaint record.']);
    exit;
}

try {
    complaint_service_log_require_assigned_access($obconn, $complaintId);

    $result = complaint_service_log_summary_payload($obconn, $complaintId, current_username());

    if (!$result['success']) {
        $errorMessage = (string) ($result['error'] ?? 'Unable to load service log details.');
        unset($result);
        http_response_code(404);
        api_json_echo(['error' => $errorMessage]);
        exit;
    }

    $response = $result;
    unset($result);
    api_json_echo($response);
} catch (Throwable $e) {
    http_response_code(500);
    api_json_echo(['error' => 'Unable to load service log details.']);
}
