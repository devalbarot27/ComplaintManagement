<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/service_log_helpers.php';
require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';

rbac_require_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? $_GET['installed_base_id'] ?? 0);
$username = current_username();

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid installed base record.']);
    exit;
}

if ($username === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

$installedBase = service_log_get_installed_base($obconn, $id, $username);

if (!$installedBase) {
    http_response_code(404);
    echo json_encode(['error' => 'Installed base record not found.']);
    exit;
}

$installedBaseId = (int) $installedBase['id'];
$installedBaseLabel = '#' . $installedBaseId
    . ' - ' . ($installedBase['order_id'] ?? '')
    . ' - ' . ($installedBase['fab_number'] ?? '')
    . ' - ' . ($installedBase['customer_name'] ?? '');

$serviceLogStmt = $obconn->prepare('
    SELECT
        sl.id,
        sl.serial_number,
        sl.warranty_chargeable,
        sl.complaint_date,
        sl.issue_description,
        sl.engineer_name,
        sl.visit_date,
        sl.action_taken,
        sl.closure_date,
        sl.remarks
    FROM service_logs sl
    WHERE sl.installed_base_id = :installed_base_id
      AND sl.deleted_at IS NULL
      AND TRIM(sl.username) = :username
    ORDER BY sl.id DESC
    LIMIT 1
');
$serviceLogStmt->bindValue(':installed_base_id', $installedBaseId, PDO::PARAM_INT);
$serviceLogStmt->bindValue(':username', $username);
$serviceLogStmt->execute();
$serviceLog = $serviceLogStmt->fetch(PDO::FETCH_ASSOC);

$dealerStmt = $obconn->prepare('
    SELECT dealer_name
    FROM installed_base
    WHERE id = :id
      AND deleted_at IS NULL
');
$dealerStmt->bindValue(':id', $installedBaseId, PDO::PARAM_INT);
$dealerStmt->execute();
$dealerRow = $dealerStmt->fetch(PDO::FETCH_ASSOC);

$serviceLogId = $serviceLog ? (int) $serviceLog['id'] : 0;
$serviceLogLabel = $serviceLogId > 0
    ? '#' . $serviceLogId
        . ' - ' . ($installedBase['order_id'] ?? '')
        . ' - ' . ($serviceLog['serial_number'] ?? '')
    : '';

$serialNumber = $serviceLog
    ? trim((string) ($serviceLog['serial_number'] ?? ''))
    : trim((string) ($installedBase['fab_number'] ?? ''));

echo json_encode([
    'service_log_id' => $serviceLogId > 0 ? $serviceLogId : '',
    'service_log_label' => $serviceLogLabel,
    'installed_base_id' => $installedBaseId,
    'installed_base_label' => $installedBaseLabel,
    'customer_name' => $installedBase['customer_name'] ?? '',
    'dealer_name' => $dealerRow['dealer_name'] ?? '',
    'order_id' => $installedBase['order_id'] ?? '',
    'fab_number' => $installedBase['fab_number'] ?? '',
    'serial_number' => $serialNumber,
    'machine_model' => service_log_machine_model_from_installed_base($installedBase),
    'warranty_chargeable' => $serviceLog['warranty_chargeable'] ?? '',
    'complaint_date' => service_log_format_input_date($serviceLog['complaint_date'] ?? null),
    'issue_description' => $serviceLog['issue_description'] ?? '',
    'engineer_name' => $serviceLog['engineer_name'] ?? '',
    'visit_date' => service_log_format_input_date($serviceLog['visit_date'] ?? null),
    'action_taken' => $serviceLog['action_taken'] ?? '',
    'closure_date' => service_log_format_input_date($serviceLog['closure_date'] ?? null),
    'running_hours' => $installedBase['running_hours'] ?? '',
    'service_remarks' => $serviceLog['remarks'] ?? '',
]);
