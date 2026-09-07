<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/includes/ln_invoice_helpers.php';
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';
require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';
require_once dirname(__DIR__) . '/includes/customer_master_helpers.php';

header('Content-Type: application/json; charset=utf-8');

installed_base_ensure_schema($obconn);

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid record.']);
    exit;
}

if (!after_market_user_can_access_record($obconn, 'installed_base', $id)) {
    http_response_code(404);
    echo json_encode(['error' => 'Record not found.']);
    exit;
}

$stmt = $obconn->prepare('
    SELECT
        ib.*,
        cm.customer_name,
        cm.email,
        cm.mobile,
        cm.street_1,
        cm.street_2,
        cm.pincode,
        cm.city,
        cm.district,
        cm.state
    FROM installed_base ib
    ' . installed_base_customer_join_sql('ib', 'cm') . '
    WHERE ib.id = :id
      AND ib.deleted_at IS NULL
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
$formatted_commissioning_date = installed_base_format_date_for_input(
    $commissioning_date !== null ? (string) $commissioning_date : ''
);

$customerId = (int) ($row['customer_id'] ?? 0);

echo json_encode([
    'id' => (int) $row['id'],
    'order_ref_id' => trim((string) ($row['order_id'] ?? '')) !== ''
        ? trim((string) $row['order_id'])
        : (string) ($row['order_ref_id'] ?? ''),
    'order_id' => $row['order_id'],
    'fab_number' => $row['fab_number'],
    'customer_id' => $customerId > 0 ? $customerId : '',
    'customer_label' => $customerId > 0 ? customer_master_select2_label($row) : '',
    'customer_name' => $row['customer_name'] ?? '',
    'dealer_name' => $row['dealer_name'],
    'machine_model_code' => $row['machine_model_code'],
    'machine_model' => $row['machine_model'],
    'invoice_date' => $formatted_invoice_date,
    'commissioning_date' => $formatted_commissioning_date,
    'running_hours' => $row['running_hours'],
    'industry_segment' => $row['industry_segment'],
    'remarks' => $row['remarks'],
]);
