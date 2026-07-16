<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';
require_once dirname(__DIR__) . '/includes/spare_parts_helpers.php';

header('Content-Type: application/json; charset=utf-8');

$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid record.']);
    exit;
}

if (!after_market_user_can_access_record($obconn, 'spare_parts_consumption', $id)) {
    http_response_code(404);
    echo json_encode(['error' => 'Record not found.']);
    exit;
}

$stmt = $obconn->prepare('
    SELECT sp.*, ib.order_id, ib.customer_name, ib.machine_model
    FROM spare_parts_consumption sp
    LEFT JOIN installed_base ib
        ON ib.id = sp.installed_base_id
       AND ib.deleted_at IS NULL
    WHERE sp.id = :id
      AND sp.deleted_at IS NULL
');
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();

$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Record not found.']);
    exit;
}

$items = spare_parts_items_for_consumption($obconn, $id);
$firstItem = $items[0] ?? null;

$consumption_date = $row['consumption_date'];
$date = new DateTime($consumption_date);
$formatted_consumption_date = $date->format('Y-m-d');

$sparePartsItems = array_map(static function (array $item): array {
    return [
        'id' => (int) $item['id'],
        'spare_kit_number' => $item['spare_kit_number'],
        'reason' => $item['reason'],
        'quantity' => $item['quantity'],
        'order_value' => $item['order_value'],
    ];
}, $items);

echo json_encode([
    'id' => (int) $row['id'],
    'installed_base_id' => (int) $row['installed_base_id'],
    'service_log_id' => $row['service_log_id'] ? (int) $row['service_log_id'] : '',
    'order_id' => $row['order_id'] ?? '',
    'fab_number' => $row['fab_number'] ?? '',
    'serial_number' => $row['serial_number'],
    'consumption_date' => $formatted_consumption_date,
    'warranty_chargeable' => $row['warranty_chargeable'],
    'spare_kit_number' => $firstItem['spare_kit_number'] ?? '',
    'quantity' => $firstItem['quantity'] ?? '',
    'order_value' => $firstItem['order_value'] ?? '',
    'reason' => $firstItem['reason'] ?? '',
    'running_hours' => $row['running_hours'],
    'remarks' => $row['remarks'],
    'spare_parts_items' => $sparePartsItems,
]);
