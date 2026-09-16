<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/amc_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

rbac_require_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    api_json_echo(['error' => 'Method not allowed.']);
    exit;
}

if (!amc_action_permissions($obconn)['add']) {
    http_response_code(403);
    api_json_echo(['error' => 'Access denied. You do not have permission to add AMC contracts.']);
    exit;
}

$createdBy = current_user_id($obconn);
if ($createdBy === null || $createdBy <= 0) {
    http_response_code(401);
    api_json_echo(['error' => 'Unable to resolve logged-in user.']);
    exit;
}

amc_ensure_schema($obconn);

$result = amc_create_from_post(
    $obconn,
    $_POST,
    (int) $createdBy,
    current_username(),
    current_assignee_name()
);

if (!$result['success']) {
    http_response_code(422);
    api_json_echo(['error' => $result['message']]);
    exit;
}

$_SESSION['success_message'] = $result['message'];

api_json_echo([
    'success' => true,
    'message' => $result['message'],
    'id' => (int) ($result['id'] ?? 0),
    'installed_base_id' => (int) ($result['data']['installed_base_id'] ?? 0),
]);
