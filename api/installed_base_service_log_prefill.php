<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/service_log_helpers.php';
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';

after_market_require_service_log_add_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? $_GET['installed_base_id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid installed base record.']);
    exit;
}

$row = service_log_get_installed_base($obconn, $id, current_username());

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Installed base record not found.']);
    exit;
}

$installedBaseId = (int) $row['id'];
$label = '#' . $installedBaseId
    . ' - ' . ($row['order_id'] ?? '')
    . ' - ' . ($row['fab_number'] ?? '')
    . ' - ' . ($row['customer_name'] ?? '');

echo json_encode([
    'installed_base_id' => $installedBaseId,
    'installed_base_label' => $label,
    'order_id' => $row['order_id'] ?? '',
    'fab_number' => $row['fab_number'] ?? '',
    'machine_model' => service_log_machine_model_from_installed_base($row),
    'machine_model_code' => (string) ($row['machine_model_code'] ?? ''),
    'machine_model_desc' => trim((string) ($row['machine_model'] ?? '')),
    'running_hours' => $row['running_hours'] ?? '',
    'serial_number' => service_log_peek_next_serial_number_safe($obconn),
]);