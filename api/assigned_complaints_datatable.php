<?php
 
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_category_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_assignment_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
 
$allowedOrderColumns = [
    'ca.id',
    'c.fab_number',
    'c.customer_name',
    'c.complaint_category_name',
    'c.username',
    'ca.assign_complaint',
    'ca.assign_complaint_datetime',
    'ca.remarks',
    'c.status',
];
 
$req = dt_parse_request($allowedOrderColumns, 'ca.assign_complaint_datetime');

$baseWhere = complaint_assigned_list_where_sql();
$baseParams = complaint_assigned_list_params();
$fromJoin = '
    FROM complaints c
    ' . complaint_assigned_list_join_sql();

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total {$fromJoin} WHERE {$baseWhere}");
foreach ($baseParams as $key => $value) {
    $recordsTotalStmt->bindValue(
        $key,
        $value,
        is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
    );
}
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;
$filterParams = $baseParams;
 
if ($req['searchValue'] !== '') {
    $searchFilter = dt_complaint_search_filter(
        $req['searchValue'],
        ['c.fab_number', 'c.customer_name', 'c.complaint_category_name', 'c.username', 'c.complaint_description', 'ca.assign_complaint', 'ca.remarks'],
        'c.status'
    );
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}
 
$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total {$fromJoin} WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue(
        $key,
        $value,
        is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
    );
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];
 
$orderColumn = $req['orderColumn'];
$orderDir = $req['orderDir'];
 
$dataQuery = "
    SELECT
        c.id,
        ca.id as c_id,
        c.fab_number,
        c.customer_name,
        c.complaint_category_name,
        c.username,
        c.status,
        ca.assign_complaint,
        ca.assign_complaint_datetime,
        ca.remarks,
        ca.is_service_updated
    {$fromJoin}
    WHERE {$filterWhere}
    ORDER BY {$orderColumn} {$orderDir}
    LIMIT :limit OFFSET :offset
";
 
$dataStmt = $obconn->prepare($dataQuery);
foreach ($filterParams as $key => $value) {
    $dataStmt->bindValue(
        $key,
        $value,
        is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
    );
}
$dataStmt->bindValue(':limit', $req['length'], PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $req['start'], PDO::PARAM_INT);
$dataStmt->execute();
 
$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
$data = [];
 
foreach ($rows as $row) {
    $status = (int) $row['status'];
    $hasServiceUpdate = (int) ($row['is_service_updated'] ?? 0) === 1;


    $data[] = [
        'id' => '#' . (int) $row['c_id'],
        'c_id' => '#' . (int) $row['c_id'],
        'fab_number' => htmlspecialchars($row['fab_number'], ENT_QUOTES, 'UTF-8'),
        'customer_name' => htmlspecialchars($row['customer_name'], ENT_QUOTES, 'UTF-8'),
        'complaint_category' => htmlspecialchars(complaint_category_display_name($row), ENT_QUOTES, 'UTF-8'),
        'username' => htmlspecialchars((string) ($row['username'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'assign_complaint' => htmlspecialchars($row['assign_complaint'], ENT_QUOTES, 'UTF-8'),
        'assign_complaint_datetime' => date('d M Y h:i A', strtotime($row['assign_complaint_datetime'])),
        'remarks' => htmlspecialchars(mb_strimwidth((string) ($row['remarks'] ?? ''), 0, 80, '...'), ENT_QUOTES, 'UTF-8'),
        'status' => complaint_status_badge($status),
        'actions' => complaint_assigned_actions((int) $row['id'], $status, $hasServiceUpdate),
    ];
}
 
dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
 