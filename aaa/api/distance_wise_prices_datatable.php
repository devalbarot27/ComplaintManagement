<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/distance_wise_price_helpers.php';

admin_api_require_system_admin($obconn);
distance_wise_price_ensure_schema($obconn);

$allowedOrderColumns = ['id', 'from_km', 'price', 'status', 'created_at'];
$req = dt_parse_request($allowedOrderColumns, 'from_km');

$baseWhere = 'deleted_at IS NULL';
$filterParams = [];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM distance_wise_prices WHERE {$baseWhere}");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($req['searchValue'] !== '') {
    $searchFilter = distance_wise_price_search_filter($req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM distance_wise_prices WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$orderSqlMap = [
    'id' => 'id',
    'from_km' => 'COALESCE(from_km, to_km)',
    'price' => 'price',
    'status' => 'status',
    'created_at' => 'created_at',
];
$orderSql = $orderSqlMap[$req['orderColumn']] ?? 'COALESCE(from_km, to_km)';

$dataQuery = "
    SELECT id, range_type, from_km, to_km, price, status, created_at
    FROM distance_wise_prices
    WHERE {$filterWhere}
    ORDER BY {$orderSql} {$req['orderDir']}, id ASC
    LIMIT :limit OFFSET :offset
";

$dataStmt = $obconn->prepare($dataQuery);
foreach ($filterParams as $key => $value) {
    $dataStmt->bindValue($key, $value);
}
$dataStmt->bindValue(':limit', $req['length'], PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $req['start'], PDO::PARAM_INT);
$dataStmt->execute();

$data = [];

foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $data[] = [
        'id' => '#' . (int) $row['id'],
        'km_range' => htmlspecialchars(distance_wise_price_range_label($row), ENT_QUOTES, 'UTF-8'),
        'price' => htmlspecialchars(distance_wise_price_format_rupees($row['price']), ENT_QUOTES, 'UTF-8'),
        'status' => rbac_status_badge($row['status']),
        'created_at' => rbac_format_datetime($row['created_at']),
        'actions' => distance_wise_price_entry_actions((int) $row['id']),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);