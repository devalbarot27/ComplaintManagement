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
    FROM spare_parts_consumption
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

$consumption_date = $row['consumption_date'];
$date = new DateTime($consumption_date);
$formatted_consumption_date = $date->format('Y-m-d');

echo json_encode([
    'id' => (int) $row['id'],
    'installed_base_id' => (int) $row['installed_base_id'],
    'service_log_id' => $row['service_log_id'] ? (int) $row['service_log_id'] : '',
    'serial_number' => $row['serial_number'],
    'consumption_date' => $formatted_consumption_date,
    'warranty_chargeable' => $row['warranty_chargeable'],
    'spare_kit_number' => $row['spare_kit_number'],
    'quantity' => $row['quantity'],
    'order_value' => $row['order_value'],
    'reason' => $row['reason'],
    'running_hours' => $row['running_hours'],
    'remarks' => $row['remarks'],
]);
