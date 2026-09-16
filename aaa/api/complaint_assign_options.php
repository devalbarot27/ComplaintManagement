<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_assignment_helpers.php';
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

if (
    !rbac_user_can($obconn, 'complaint-entry', 'assign-complaint')
    && !rbac_user_can($obconn, 'complaint-entry', 'reassign-complaint')
    && !rbac_user_can($obconn, 'complaint-entry', 'complaint-closure')
) {
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

if (!complaint_user_can_access_entry_complaint($obconn, $complaintId)) {
    http_response_code(403);
    api_json_echo(['error' => 'Access denied.']);
    exit;
}

$options = complaint_assign_options_for_complaint($obconn, $complaintId);
$assignees = [];

foreach ($options['assignees'] as $user) {
    $value = complaint_assignee_option_value($user);
    if ($value === '') {
        continue;
    }

    $assignees[] = [
        'value' => $value,
        'label' => complaint_assignee_option_label($user),
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'restrict_to_creator' => (bool) ($options['restrict_to_creator'] ?? false),
    'preselect' => $options['preselect'],
    'assignees' => $assignees,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);