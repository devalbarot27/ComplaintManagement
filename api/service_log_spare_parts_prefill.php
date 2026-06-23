<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/service_log_helpers.php';
require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';

rbac_require_api_access($obconn);

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? $_GET['service_log_id'] ?? 0);
$username = current_username();

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid service log record.']);
    exit;
}

if ($username === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

$stmt = $obconn->prepare('
    SELECT
        sl.id,
        sl.installed_base_id,
        sl.order_id,
        sl.fab_number,
        sl.serial_number,
        sl.machine_model,
        sl.warranty_chargeable,
        sl.complaint_date,
        sl.issue_description,
        sl.engineer_name,
        sl.visit_date,
        sl.action_taken,
        sl.closure_date,
        sl.running_hours,
        sl.remarks,
        ib.customer_name,
        ib.dealer_name
    FROM service_logs sl
    INNER JOIN installed_base ib
        ON ib.id = sl.installed_base_id
       AND ib.deleted_at IS NULL
    WHERE sl.id = :id
      AND sl.deleted_at IS NULL
      AND TRIM(sl.username) = :username
');
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->bindValue(':username', $username);
$stmt->execute();

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Service log record not found.']);
    exit;
}

$serviceLogId = (int) $row['id'];
$installedBaseId = (int) $row['installed_base_id'];
$installedBaseLabel = '#' . $installedBaseId
    . ' - ' . ($row['order_id'] ?? '')
    . ' - ' . ($row['fab_number'] ?? '')
    . ' - ' . ($row['customer_name'] ?? '');
$serviceLogLabel = '#' . $serviceLogId
    . ' - ' . ($row['order_id'] ?? '')
    . ' - ' . ($row['serial_number'] ?? '');

echo json_encode([
    'service_log_id' => $serviceLogId,
    'service_log_label' => $serviceLogLabel,
    'installed_base_id' => $installedBaseId,
    'installed_base_label' => $installedBaseLabel,
    'customer_name' => $row['customer_name'] ?? '',
    'dealer_name' => $row['dealer_name'] ?? '',
    'order_id' => $row['order_id'] ?? '',
    'fab_number' => $row['fab_number'] ?? '',
    'serial_number' => $row['serial_number'] ?? '',
    'machine_model' => $row['machine_model'] ?? '',
    'warranty_chargeable' => $row['warranty_chargeable'] ?? '',
    'complaint_date' => service_log_format_input_date($row['complaint_date'] ?? null),
    'issue_description' => $row['issue_description'] ?? '',
    'engineer_name' => $row['engineer_name'] ?? '',
    'visit_date' => service_log_format_input_date($row['visit_date'] ?? null),
    'action_taken' => $row['action_taken'] ?? '',
    'closure_date' => service_log_format_input_date($row['closure_date'] ?? null),
    'running_hours' => $row['running_hours'] ?? '',
    'service_remarks' => $row['remarks'] ?? '',
]);
