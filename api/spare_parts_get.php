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
        'spare_kit_number' => htmlspecialchars((string) ($item['spare_kit_number'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'reason' => htmlspecialchars((string) ($item['reason'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'quantity' => $item['quantity'],
        'order_value' => $item['order_value'],
    ];
}, $items);

echo json_encode([
    'id' => (int) $row['id'],
    'installed_base_id' => (int) $row['installed_base_id'],
    'service_log_id' => $row['service_log_id'] ? (int) $row['service_log_id'] : '',
    'order_id' => htmlspecialchars((string) ($row['order_id'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'fab_number' => htmlspecialchars((string) ($row['fab_number'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'serial_number' => htmlspecialchars((string) ($row['serial_number'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'consumption_date' => $formatted_consumption_date,
    'warranty_chargeable' => htmlspecialchars((string) ($row['warranty_chargeable'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'spare_kit_number' => htmlspecialchars((string) ($firstItem['spare_kit_number'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'quantity' => $firstItem['quantity'] ?? '',
    'order_value' => $firstItem['order_value'] ?? '',
    'reason' => htmlspecialchars((string) ($firstItem['reason'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'running_hours' => $row['running_hours'],
    'remarks' => htmlspecialchars((string) ($row['remarks'] ?? ''), ENT_QUOTES, 'UTF-8'),
    'spare_parts_items' => $sparePartsItems,
]);
