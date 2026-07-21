<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/service_log_helpers.php';
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';
require_once dirname(__DIR__) . '/includes/api_json_helpers.php';

after_market_require_service_log_add_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? $_GET['installed_base_id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    api_json_echo(['error' => 'Invalid installed base record.']);
    exit;
}

$row = service_log_get_installed_base($obconn, $id, current_username());

if (!$row) {
    http_response_code(404);
    api_json_echo(['error' => 'Installed base record not found.']);
    exit;
}

$installedBaseId = (int) $row['id'];
$fabNumber = (string) ($row['fab_number'] ?? '');
$customerName = (string) ($row['customer_name'] ?? '');
$machineModel = service_log_machine_model_from_installed_base($row);
$machineModelCode = (string) ($row['machine_model_code'] ?? '');
$machineModelDesc = trim((string) ($row['machine_model'] ?? ''));
$runningHours = (string) ($row['running_hours'] ?? '');
$serialNumber = service_log_peek_next_serial_number_safe($obconn);
unset($row);

$label = '#' . $installedBaseId . ' - ' . $fabNumber . ' - ' . $customerName;

api_json_echo([
    'installed_base_id' => $installedBaseId,
    'installed_base_label' => $label,
    'order_id' => '',
    'fab_number' => $fabNumber,
    'machine_model' => $machineModel,
    'machine_model_code' => $machineModelCode,
    'machine_model_desc' => $machineModelDesc,
    'running_hours' => $runningHours,
    'serial_number' => $serialNumber,
]);
