<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_assignment_helpers.php';
require_once dirname(__DIR__) . '/includes/cq_helpers.php';
require_once dirname(__DIR__) . '/includes/warranty_claims_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    api_json_echo(['error' => 'Method not allowed.']);
    exit;
}

if (empty($_SESSION['usr_name'])) {
    http_response_code(401);
    api_json_echo(['error' => 'Unauthorized.']);
    exit;
}

if (!complaint_assigned_action_permissions($obconn)['service_update']) {
    http_response_code(403);
    api_json_echo(['error' => 'Access denied.']);
    exit;
}

$complaintId = (int) ($_REQUEST['complaint_id'] ?? 0);
if ($complaintId <= 0) {
    http_response_code(422);
    api_json_echo(['error' => 'Complaint ID is required.']);
    exit;
}

if (!complaint_user_can_access_assigned_complaint($obconn, $complaintId)) {
    http_response_code(403);
    api_json_echo(['error' => 'Access denied.']);
    exit;
}

$stmt = $obconn->prepare('SELECT complaint_category_name FROM complaints WHERE id = :id AND deleted_at IS NULL');
$stmt->bindValue(':id', $complaintId, PDO::PARAM_INT);
$stmt->execute();
$categoryName = (string) ($stmt->fetchColumn() ?: '');

$categoryQualifies = cq_complaint_category_qualifies($categoryName);
$warrantyStatus = $categoryQualifies
    ? cq_resolve_warranty_status_for_complaint($obconn, $complaintId)['status']
    : '';
$serviceType = $categoryQualifies ? cq_resolve_service_type_for_complaint($obconn, $complaintId) : '';
$serviceTypeQualifies = $categoryQualifies && cq_service_type_qualifies($warrantyStatus, $serviceType);
$parts = $serviceTypeQualifies ? warranty_claims_existing_items_for_complaint($obconn, $complaintId) : [];

api_json_echo([
    'success' => true,
    'category_qualifies' => $categoryQualifies,
    'warranty_status' => $warrantyStatus,
    'warranty_badge_class' => $warrantyStatus !== '' ? cq_warranty_status_badge_class($warrantyStatus) : 'bg-secondary',
    'service_type' => $serviceType,
    'qualifies' => $serviceTypeQualifies,
    'parts' => $parts,
]);

