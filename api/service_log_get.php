<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/includes/service_log_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);
$username = current_username();

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid record.']);
    exit;
}

if ($username === '') {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

$stmt = $obconn->prepare('
    SELECT *
    FROM service_logs
    WHERE id = :id
      AND deleted_at IS NULL
      AND TRIM(username) = :username
');
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->bindValue(':username', $username);
$stmt->execute();

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Record not found.']);
    exit;
}

$response = [
    'id' => (int) $row['id'],
    'installed_base_id' => (int) $row['installed_base_id'],
    'order_ref_id' => $row['order_ref_id'] ? (int) $row['order_ref_id'] : '',
    'order_id' => $row['order_id'],
    'fab_number' => $row['fab_number'],
    'serial_number' => $row['serial_number'],
    'machine_model' => $row['machine_model'],
    'warranty_chargeable' => $row['warranty_chargeable'],
    'complaint_date' => service_log_format_input_date($row['complaint_date'] ?? null),
    'issue_description' => $row['issue_description'],
    'engineer_name' => $row['engineer_name'],
    'visit_date' => service_log_format_input_date($row['visit_date'] ?? null),
    'action_taken' => $row['action_taken'],
    'closure_date' => service_log_format_input_date($row['closure_date'] ?? null),
    'part_replaced' => $row['part_replaced'],
    'running_hours' => $row['running_hours'],
    'loaded_hours' => $row['loaded_hours'],
    'customer_feedback' => $row['customer_feedback'],
    'remarks' => $row['remarks'],
];

foreach (service_log_remaining_consumable_column_names() as $field) {
    if (substr($field, -15) === '_remaining_date') {
        $response[$field] = service_log_format_input_date($row[$field] ?? null);
        continue;
    }

    $response[$field] = $row[$field] ?? '';
}

echo json_encode($response);
