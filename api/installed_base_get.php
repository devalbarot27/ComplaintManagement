<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/includes/ln_invoice_helpers.php';

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
$formatted_invoice_date = ln_invoice_format_date((string) $invoice_date);

if (!empty($row['fab_number'])) {
    $fabInvoiceDate = ln_invoice_resolve_invoice_date_for_fab($dpconn, (string) $row['fab_number']);
    if ($fabInvoiceDate !== null) {
        $formatted_invoice_date = $fabInvoiceDate;
    }
}

$commissioning_date = $row['commissioning_date'];
$date = new DateTime($commissioning_date);
$formatted_commissioning_date = $date->format('Y-m-d'); 
 
echo json_encode([
    'id' => (int) $row['id'],
    'order_ref_id' => (int) ($row['order_ref_id'] ?? 0),
    'order_id' => $row['order_id'],
    'fab_number' => $row['fab_number'],
    'customer_name' => $row['customer_name'],
    'street_1' => $row['street_1'],
    'street_2' => $row['street_2'],
    'pincode' => $row['pincode'],
    'city' => $row['city'],
    'district' => $row['district'],
    'state' => $row['state'],
    'mobile' => $row['mobile'],
    'email' => $row['email'],
    'dealer_name' => $row['dealer_name'],
    'machine_model_code' => $row['machine_model_code'],
    'machine_model' => $row['machine_model'],
    'invoice_date' => $formatted_invoice_date,
    'commissioning_date' => $formatted_commissioning_date,
    'running_hours' => $row['running_hours'],
    'industry_segment' => $row['industry_segment'],
    'remarks' => $row['remarks'],
]);