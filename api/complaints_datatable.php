<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
 
$allowedOrderColumns = [
    'id',
    'fab_number',
    'customer_name',
    'customer_address',
    'complaint_description',
    'status',
    'created_at',
];
 
$req = dt_parse_request($allowedOrderColumns, 'id');
 
$baseWhere = 'deleted_at IS NULL';
 
$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM complaints WHERE {$baseWhere}");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];
 
$filterWhere = $baseWhere;
$filterParams = [];
 
if ($req['searchValue'] !== '') {
    $searchFilter = dt_complaint_search_filter(
        $req['searchValue'],
        ['fab_number', 'customer_name', 'customer_address', 'complaint_description'],
        'status'
    );
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}
 
$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM complaints WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];
 
$orderColumn = $req['orderColumn'];
$orderDir = $req['orderDir'];
 
$dataQuery = "
    SELECT
        id,
        fab_number,
        customer_name,
        customer_address,
        complaint_description,
        status,
        created_at,
        EXISTS (
            SELECT 1
            FROM complaint_service_updates csu
            WHERE csu.complaint_id = complaints.id
        ) AS has_service_update,
        (
            SELECT cc.call_closure::text
            FROM complaint_closures cc
            WHERE cc.complaint_id = complaints.id
            ORDER BY cc.created_at DESC, cc.id DESC
            LIMIT 1
        ) AS latest_closure,
        EXISTS (
            SELECT 1
            FROM complaint_assignments ca2
            WHERE ca2.complaint_id = complaints.id
            AND ca2.assign_complaint_datetime > (
                SELECT cc2.created_at
                FROM complaint_closures cc2
                WHERE cc2.complaint_id = complaints.id
                  AND cc2.call_closure::text = 'No'
                ORDER BY cc2.created_at DESC, cc2.id DESC
                LIMIT 1
            )
        ) AS has_reassign_after_closure_no,
        EXISTS (
            SELECT 1
            FROM complaint_service_updates su2
            WHERE su2.complaint_id = complaints.id
            AND su2.created_at > (
                SELECT cc3.created_at
                FROM complaint_closures cc3
                WHERE cc3.complaint_id = complaints.id
                  AND cc3.call_closure::text = 'No'
                ORDER BY cc3.created_at DESC, cc3.id DESC
                LIMIT 1
            )
        ) AS has_service_after_closure_no
    FROM complaints
    WHERE {$filterWhere}
    ORDER BY {$orderColumn} {$orderDir}
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
$data = [];
 
foreach ($rows as $row) {
    $status = (int) $row['status'];
    $flags = dt_parse_closure_row_flags($row);
 
    $data[] = [
        'id' => '#' . (int) $row['id'],
        'fab_number' => htmlspecialchars($row['fab_number'], ENT_QUOTES, 'UTF-8'),
        'customer_name' => htmlspecialchars($row['customer_name'], ENT_QUOTES, 'UTF-8'),
        'customer_address' => htmlspecialchars($row['customer_address'], ENT_QUOTES, 'UTF-8'),
        'complaint_description' => htmlspecialchars(mb_strimwidth($row['complaint_description'], 0, 80, '...'), ENT_QUOTES, 'UTF-8'),
        'status' => complaint_status_badge($status),
        'created_at' => date('d M Y H:i', strtotime($row['created_at'])),
        'actions' => complaint_entry_actions(
            (int) $row['id'],
            $status,
            $flags['needs_reassign'],
            $flags['can_close']
        ),
    ];
}
 
dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
 


/*
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';

$allowedOrderColumns = [
    'id',
    'fab_number',
    'customer_name',
    'customer_address',
    'complaint_description',
    'status',
    'created_at',
];

$req = dt_parse_request($allowedOrderColumns, 'id');

$baseWhere = 'deleted_at IS NULL';

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM complaints WHERE {$baseWhere}");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;
$filterParams = [];

if ($req['searchValue'] !== '') {
    $searchFilter = dt_complaint_search_filter(
        $req['searchValue'],
        ['fab_number', 'customer_name', 'customer_address', 'complaint_description'],
        'status'
    );
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM complaints WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$orderColumn = $req['orderColumn'];
$orderDir = $req['orderDir'];

$dataQuery = "
    SELECT
        id,
        fab_number,
        customer_name,
        customer_address,
        complaint_description,
        status,
        created_at
    FROM complaints
    WHERE {$filterWhere}
    ORDER BY {$orderColumn} {$orderDir}
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
$data = [];

foreach ($rows as $row) {
    $status = (int) $row['status'];
    $data[] = [
        'id' => '#' . (int) $row['id'],
        'fab_number' => htmlspecialchars($row['fab_number'], ENT_QUOTES, 'UTF-8'),
        'customer_name' => htmlspecialchars($row['customer_name'], ENT_QUOTES, 'UTF-8'),
        'customer_address' => htmlspecialchars($row['customer_address'], ENT_QUOTES, 'UTF-8'),
        'complaint_description' => htmlspecialchars($row['complaint_description'], ENT_QUOTES, 'UTF-8'),
        'status' => complaint_status_badge($status),
        'created_at' => date('d M Y H:i', strtotime($row['created_at'])),
        'actions' => complaint_entry_actions((int) $row['id'], $status),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
*/
