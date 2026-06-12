<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid record.']);
    exit;
}

$stmt = $obconn->prepare('
    SELECT *
    FROM service_logs
    WHERE id = :id
      AND deleted_at IS NULL
');
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Record not found.']);
    exit;
}

$complaint_date = $row['complaint_date'];
$date = new DateTime($complaint_date);
$formatted_complaint_date = $date->format('Y-m-d');

$visit_date = $row['visit_date'];
$date = new DateTime($visit_date);
$formatted_visit_date = $date->format('Y-m-d'); 

$closure_date = $row['closure_date'];
$date = new DateTime($closure_date);
$formatted_closure_date = $date->format('Y-m-d');

echo json_encode([
    'id' => (int) $row['id'],
    'installed_base_id' => (int) $row['installed_base_id'],
    'order_ref_id' => $row['order_ref_id'] ? (int) $row['order_ref_id'] : '',
    'order_id' => $row['order_id'],
    'serial_number' => $row['serial_number'],
    'machine_model' => $row['machine_model'],
    'warranty_chargeable' => $row['warranty_chargeable'],
    'complaint_date' => $formatted_complaint_date,
    'issue_description' => $row['issue_description'],
    'engineer_name' => $row['engineer_name'],
    'visit_date' => $formatted_visit_date,
    'action_taken' => $row['action_taken'],
    'closure_date' => $formatted_closure_date,
    'part_replaced' => $row['part_replaced'],
    'running_hours' => $row['running_hours'],
    'loaded_hours' => $row['loaded_hours'],
    'customer_feedback' => $row['customer_feedback'],
    'remarks' => $row['remarks'],
]);
