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
    FROM installed_base
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

$invoice_date = $row['invoice_date'];
$date = new DateTime($invoice_date);
$formatted_invoice_date = $date->format('Y-m-d');

$commissioning_date = $row['commissioning_date'];
$date = new DateTime($commissioning_date);
$formatted_commissioning_date = $date->format('Y-m-d'); 
 
echo json_encode([
    'id' => (int) $row['id'],
    'order_ref_id' => (int) ($row['order_ref_id'] ?? 0),
    'order_id' => $row['order_id'],
    'fab_number' => $row['fab_number'],
    'customer_name' => $row['customer_name'],
    'address' => $row['address'],
    'mobile' => $row['mobile'],
    'email' => $row['email'],
    'dealer_name' => $row['dealer_name'],
    'machine_model' => $row['machine_model'],
    'invoice_date' => $formatted_invoice_date,
    'commissioning_date' => $formatted_commissioning_date,
    'running_hours' => $row['running_hours'],
    'industry_segment' => $row['industry_segment'],
    'remarks' => $row['remarks'],
]);