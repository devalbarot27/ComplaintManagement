<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/customer_helpers.php';

admin_api_require_system_admin($obconn);

$allowedOrderColumns = ['id', 'cust_code', 'cust_name', 'cust_addr', 'created_at'];
$req = dt_parse_request($allowedOrderColumns, 'id');

$baseWhere = 'deleted_at IS NULL';
$filterParams = [];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM customers WHERE {$baseWhere}");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($req['searchValue'] !== '') {
    $searchFilter = customer_search_filter($req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM customers WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$dataQuery = "
    SELECT id, cust_code, cust_name, cust_addr, created_at
    FROM customers
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

$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
$addrCodes = array_column($rows, 'cust_addr');
$addrLabels = customer_address_labels($dpconn, $addrCodes);

$data = [];

foreach ($rows as $row) {
    $addrCode = trim((string) ($row['cust_addr'] ?? ''));
    $addrText = $addrLabels[$addrCode] ?? ($addrCode !== '' ? $addrCode : '-');

    $data[] = [
        'id' => '#' . (int) $row['id'],
        'cust_code' => htmlspecialchars((string) $row['cust_code'], ENT_QUOTES, 'UTF-8'),
        'cust_name' => htmlspecialchars((string) $row['cust_name'], ENT_QUOTES, 'UTF-8'),
        'cust_addr' => htmlspecialchars($addrText, ENT_QUOTES, 'UTF-8'),
        'created_at' => rbac_format_datetime($row['created_at']),
        'actions' => customer_entry_actions((int) $row['id']),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
