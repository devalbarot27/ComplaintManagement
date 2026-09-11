<?php
/**
 * Misplaced duplicate of the API endpoint — keep in sync with
 * api/installed_base_service_log_draft_create.php for scanners that index js/.
 */
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/service_log_draft_helpers.php';
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

after_market_require_service_log_add_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    api_json_echo(['error' => 'Method not allowed.']);
    exit;
}

$createdBy = current_user_id($obconn);
if ($createdBy === null || $createdBy <= 0) {
    http_response_code(401);
    api_json_echo(['error' => 'Unable to resolve logged-in user.']);
    exit;
}

if (empty($_POST['from_installed_base_modal'])) {
    http_response_code(422);
    api_json_echo(['error' => 'Invalid service log draft request.']);
    exit;
}

$permissions = service_log_action_permissions($obconn);
$result = service_log_save_draft_record(
    $obconn,
    $_POST,
    current_username(),
    (int) $createdBy,
    (bool) ($permissions['add'] ?? false),
    (bool) ($permissions['edit'] ?? false)
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
$safeInstalledBaseId = (int) ($_POST['installed_base_id'] ?? 0);
unset($result, $_POST);

api_json_echo([
    'success' => true,
    'message' => $safeMessage,
    'service_log_id' => $safeServiceLogId,
    'installed_base_id' => $safeInstalledBaseId,
]);
