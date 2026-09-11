<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_assignment_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_closure_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    api_json_echo(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

if (empty($_SESSION['usr_name'])) {
    http_response_code(401);
    api_json_echo(['success' => false, 'error' => 'Unauthorized.']);
    exit;
}

if (!complaint_user_can_closure($obconn)) {
    http_response_code(403);
    api_json_echo(['success' => false, 'error' => 'Access denied.']);
    exit;
}

$complaintId = (int) ($_REQUEST['complaint_id'] ?? 0);
if ($complaintId <= 0) {
    http_response_code(422);
    api_json_echo(['success' => false, 'error' => 'Complaint ID is required.']);
    exit;
}

if (!complaint_user_can_access_entry_complaint($obconn, $complaintId)) {
    http_response_code(403);
    api_json_echo(['success' => false, 'error' => 'Access denied.']);
    exit;
}

$payload = complaint_closure_distance_from_service_claim($obconn, $complaintId);
$payload['success'] = true;

api_json_echo($payload);
