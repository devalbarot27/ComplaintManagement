<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';
require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

rbac_require_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

$fabNumber = trim((string) ($_GET['fab_number'] ?? $_POST['fab_number'] ?? ''));
$editingRecordId = (int) ($_GET['record_id'] ?? $_POST['record_id'] ?? 0);

if ($fabNumber === '') {
    api_json_echo([
        'available' => false,
        'message' => 'Fab Number is required.',
    ]);
    exit;
}

$error = installed_base_validate_fab_for_current_user(
    $obconn,
    $fabNumber,
    null,
    $editingRecordId
);

$accessibleId = installed_base_find_accessible_id_by_fab($obconn, $fabNumber);

if ($error !== null) {
    api_json_echo([
        'available' => false,
        'existing_id' => $accessibleId !== null ? $accessibleId : (int) (installed_base_find_id_by_fab($obconn, $fabNumber) ?? 0),
        'message' => $error,
    ]);
    exit;
}

api_json_echo([
    'available' => true,
    'existing_id' => $accessibleId !== null ? $accessibleId : 0,
    'message' => '',
]);