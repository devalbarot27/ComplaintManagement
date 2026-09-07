<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/customer_master_helpers.php';

admin_api_require_system_admin($obconn);
customer_master_ensure_schema($obconn);

$allowedOrderColumns = ['id', 'customer_name', 'email', 'mobile', 'city', 'state', 'created_at'];
$req = dt_parse_request($allowedOrderColumns, 'id');

$baseWhere = 'deleted_at IS NULL';
$filterParams = [];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM customer_masters WHERE {$baseWhere}");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($req['searchValue'] !== '') {
    $searchFilter = customer_master_search_filter($req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM customer_masters WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$dataQuery = "
    SELECT
        id,
        customer_name,
        email,
        mobile,
        city,
        state,
        created_at
    FROM customer_masters
    WHERE {$filterWhere}
    ORDER BY {$req['orderColumn']} {$req['orderDir']}
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
        'customer_name' => htmlspecialchars(trim((string) ($row['customer_name'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'email' => htmlspecialchars(trim((string) ($row['email'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'mobile' => htmlspecialchars(trim((string) ($row['mobile'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'city' => htmlspecialchars(trim((string) ($row['city'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'state' => htmlspecialchars(trim((string) ($row['state'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'created_at' => rbac_format_datetime($row['created_at']),
        'actions' => customer_master_entry_actions((int) $row['id']),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
