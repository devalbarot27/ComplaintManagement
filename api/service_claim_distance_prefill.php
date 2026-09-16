<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/warranty_claims_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    api_json_echo(['success' => false, 'found' => false, 'error' => 'Method not allowed.']);
    exit;
}

rbac_require_api_access($obconn);

if (!rbac_user_can($obconn, 'service-claims', 'view')
    && !rbac_user_can($obconn, 'service-claims', 'create-service-claims')
) {
    http_response_code(403);
    api_json_echo(['success' => false, 'found' => false, 'error' => 'Access denied.']);
    exit;
}

$complaintId = (int) ($_REQUEST['complaint_id'] ?? 0);
if ($complaintId <= 0) {
    api_json_echo(['success' => true, 'found' => false, 'km_travelled' => '']);
    exit;
}

$payload = service_claim_distance_from_service_log($obconn, $complaintId);
$payload['success'] = true;

api_json_echo($payload);