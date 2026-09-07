<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';
rbac_require_api_access($obconn);

installed_base_ensure_schema($obconn);

header('Content-Type: application/json; charset=utf-8');

$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? ''));

$scope = after_market_list_scope($obconn);
$scopeWhere = after_market_scope_where_for_alias($scope['where'], 'ib');

$sql = "
    SELECT
        ib.id,
        ib.order_ref_id,
        ib.order_id,
        ib.fab_number,
        cm.customer_name,
        ib.machine_model,
        ib.machine_model_code,
        ib.running_hours
    FROM installed_base ib
    " . installed_base_customer_join_sql('ib', 'cm') . "
    WHERE {$scopeWhere}
";

if ($term !== '') {
    $sql .= "
      AND (
            ib.order_id ILIKE :term
         OR ib.fab_number ILIKE :term
         OR cm.customer_name ILIKE :term
         OR ib.machine_model ILIKE :term
         OR ib.machine_model_code ILIKE :term
      )
    ";
}

$sql .= '
    ORDER BY ib.id DESC
    LIMIT 25
';

$stmt = $obconn->prepare($sql);
foreach ($scope['params'] as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
if ($term !== '') {
    $stmt->bindValue(':term', '%' . $term . '%');
}
$stmt->execute();

$results = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $customerName = trim((string) ($row['customer_name'] ?? ''));
    $label = '#' . (int) $row['id'] . ' - ' . $row['fab_number'] . ' - ' . ($customerName !== '' ? $customerName : '-');
    $machineModelLabel = installed_base_machine_model_label($row);

    $results[] = [
        'id' => (int) $row['id'],
        'text' => $label,
        'installed_base_id' => (int) $row['id'],
        'order_id' => '',
        'order_ref_id' => (int) ($row['order_ref_id'] ?? 0),
        'fab_number' => $row['fab_number'],
        'machine_model' => $machineModelLabel,
        'machine_model_code' => $row['machine_model_code'],
        'machine_model_desc' => trim((string) ($row['machine_model'] ?? '')),
        'running_hours' => $row['running_hours'],
    ];
}

echo json_encode(['results' => $results]);
