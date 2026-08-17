<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/customer_helpers.php';

admin_api_require_system_admin($obconn);

$allowedOrderColumns = ['id', 'customer_code', 'customer_name', 'created_at'];
$req = dt_parse_request($allowedOrderColumns, 'id');

$baseWhere = 'cms.deleted_at IS NULL';
$filterParams = [];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM customer_master_sync cms WHERE {$baseWhere}");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($req['searchValue'] !== '') {
    $searchFilter = customer_search_filter($req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("
    SELECT COUNT(*) AS total
    FROM customer_master_sync cms
    LEFT JOIN customer_master cm ON TRIM(cm.cuno) = TRIM(cms.customer_code)
    WHERE {$filterWhere}
");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$orderColumn = $req['orderColumn'];
if ($orderColumn === 'customer_name') {
    $orderColumnSql = 'cm.cuname';
} elseif ($orderColumn === 'customer_code') {
    $orderColumnSql = 'cms.customer_code';
} elseif ($orderColumn === 'created_at') {
    $orderColumnSql = 'cms.created_at';
} else {
    $orderColumnSql = 'cms.id';
}

$dataQuery = "
    SELECT
        cms.id,
        cms.customer_code,
        TRIM(cm.cuname) AS customer_name,
        cms.created_at
    FROM customer_master_sync cms
    LEFT JOIN customer_master cm ON TRIM(cm.cuno) = TRIM(cms.customer_code)
    WHERE {$filterWhere}
    ORDER BY {$orderColumnSql} {$req['orderDir']}
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
    $customerName = trim((string) ($row['customer_name'] ?? ''));

    $data[] = [
        'id' => '#' . (int) $row['id'],
        'customer_code' => htmlspecialchars(trim((string) ($row['customer_code'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'customer_name' => htmlspecialchars($customerName !== '' ? $customerName : '-', ENT_QUOTES, 'UTF-8'),
        'created_at' => rbac_format_datetime($row['created_at']),
        'actions' => customer_entry_actions((int) $row['id']),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);