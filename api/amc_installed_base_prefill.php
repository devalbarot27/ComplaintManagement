<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/amc_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

rbac_require_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

if (!amc_action_permissions($obconn)['add']) {
    http_response_code(403);
    api_json_echo(['error' => 'Access denied. You do not have permission to add AMC contracts.']);
    exit;
}

$id = (int) ($_GET['id'] ?? $_GET['installed_base_id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid installed base record.']);
    exit;
}

$snapshot = amc_installed_base_snapshot($obconn, $id);
if ($snapshot === null) {
    http_response_code(404);
    api_json_echo(['error' => 'Installed base record not found.']);
    exit;
}

echo json_encode($snapshot);
