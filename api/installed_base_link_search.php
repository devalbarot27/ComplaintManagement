<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';
rbac_require_api_access($obconn);


header('Content-Type: application/json; charset=utf-8');

$term = trim((string) ($_GET['q'] ?? $_GET['term'] ?? ''));

$scope = after_market_list_scope($obconn);

$sql = "
    SELECT id, order_ref_id, order_id, fab_number, customer_name, machine_model, machine_model_code, running_hours
    FROM installed_base
    WHERE {$scope['where']}
";

if ($term !== '') {
    $sql .= "
      AND (
            order_id ILIKE :term
         OR fab_number ILIKE :term
         OR customer_name ILIKE :term
         OR machine_model ILIKE :term
         OR machine_model_code ILIKE :term
      )
    ";
}

$sql .= '
    ORDER BY id DESC
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
    $label = '#' . (int) $row['id'] . ' - ' . $row['fab_number'] . ' - ' . $row['customer_name'];
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