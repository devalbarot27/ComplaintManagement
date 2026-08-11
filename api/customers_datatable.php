<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/customer_helpers.php';

admin_api_require_system_admin($obconn);

$allowedOrderColumns = ['cuno', 'cuname', 'adr_code', 'status'];
$req = dt_parse_request($allowedOrderColumns, 'cuno');

$baseWhere = '1=1';
$filterParams = [];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM customer_master WHERE {$baseWhere}");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($req['searchValue'] !== '') {
    $searchFilter = customer_search_filter($req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM customer_master WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$dataQuery = "
    SELECT cuno, cuname, adr_code, status
    FROM customer_master
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
$addrCodes = array_column($rows, 'adr_code');
$addrLabels = customer_address_labels($obconn, $addrCodes);

$data = [];

foreach ($rows as $row) {
    $cuno = trim((string) ($row['cuno'] ?? ''));
    $addrCode = trim((string) ($row['adr_code'] ?? ''));
    $addrText = $addrLabels[$addrCode] ?? ($addrCode !== '' ? $addrCode : '-');

    $data[] = [
        'cuno' => htmlspecialchars($cuno, ENT_QUOTES, 'UTF-8'),
        'cuname' => htmlspecialchars(trim((string) ($row['cuname'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'adr_code' => htmlspecialchars($addrText, ENT_QUOTES, 'UTF-8'),
        'status' => htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'actions' => customer_entry_actions($cuno),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
