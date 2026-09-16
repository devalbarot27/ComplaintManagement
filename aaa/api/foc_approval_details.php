<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/warranty_claims_helpers.php';
require_once dirname(__DIR__) . '/includes/user_helpers.php';

rbac_require_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

$claimId = (int) ($_GET['claim_id'] ?? $_POST['claim_id'] ?? 0);
if ($claimId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid FOC claim.']);
    exit;
}

$flags = user_current_approval_flags($obconn);
$canOpen = rbac_user_can($obconn, 'approvals', 'view')
    || rbac_user_can($obconn, 'foc-parts', 'view')
    || !empty($flags['l1'])
    || !empty($flags['l2']);

if (!$canOpen) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied.']);
    exit;
}

$record = foc_claim_get_by_id($obconn, $claimId);
if (!$record || !foc_parts_user_can_access_claim($obconn, $record)) {
    http_response_code(404);
    echo json_encode(['error' => 'FOC claim not found.']);
    exit;
}

try {
    echo json_encode([
        'html' => foc_approval_extra_html($obconn, $record),
    ]);
} catch (Throwable $e) {
    error_log('foc_approval_details.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load additional details.']);
}