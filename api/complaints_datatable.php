<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_address_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_category_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';

$showAddedBy = complaint_can_view_added_by_column($obconn);

$allowedOrderColumns = [
    'id',
    'fab_number',
    'customer_name',
];
if ($showAddedBy) {
    $allowedOrderColumns[] = 'added_by_name';
}
$allowedOrderColumns = array_merge($allowedOrderColumns, [
    'complaint_category_name',
    'city',
    'status',
    'created_at',
    'id',
]);
 
$req = dt_parse_request($allowedOrderColumns, 'created_at');

$listScope = complaint_entry_list_scope($obconn);
$baseWhere = $listScope['where'];
$filterParams = $listScope['params'];
 
$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM complaints WHERE {$baseWhere}");
foreach ($filterParams as $key => $value) {
    $recordsTotalStmt->bindValue(
        $key,
        $value,
        is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
    );
}
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];
 
$filterWhere = $baseWhere;

$statusFilter = trim((string) ($_POST['status_filter'] ?? ''));
if ($statusFilter !== '') {
    $statusFilterInt = (int) $statusFilter;
    if (!array_key_exists($statusFilterInt, complaint_status_map())) {
        $statusFilter = '';
    } else {
        $filterWhere .= ' AND status = :status_filter';
        $filterParams[':status_filter'] = $statusFilterInt;
    }
}

$complaintIdFilter = (int) ($_POST['complaint_id'] ?? $_GET['complaint_id'] ?? 0);
if ($complaintIdFilter > 0) {
    $filterWhere .= ' AND id = :complaint_id';
    $filterParams[':complaint_id'] = $complaintIdFilter;
}

if ($req['searchValue'] !== '') {
    $searchFilter = dt_complaint_search_filter(
        $req['searchValue'],
        array_merge(['fab_number', 'customer_name', 'complaint_category_name', 'username', 'complaint_description'], complaint_address_search_columns()),
        'status'
    );
    if ($showAddedBy) {
        $searchFilter['sql'] = '(' . $searchFilter['sql'] . ' OR EXISTS (
            SELECT 1
            FROM user_master um_s
            WHERE um_s.id = complaints.added_by
              AND um_s.deleted_at IS NULL
              AND (um_s.name ILIKE :search OR um_s.username ILIKE :search)
        ))';
    }
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}
 
$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM complaints WHERE {$filterWhere}");
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
if ($orderColumn === 'added_by_name') {
    $orderSql = "(
        COALESCE(
            (
                SELECT COALESCE(NULLIF(TRIM(um_ord.name), ''), NULLIF(TRIM(um_ord.username), ''))
                FROM user_master um_ord
                WHERE um_ord.id = complaints.added_by
                  AND um_ord.deleted_at IS NULL
                LIMIT 1
            ),
            NULLIF(TRIM(complaints.username), ''),
            '-'
        )
    ) {$orderDir}, created_at DESC, id DESC";
} else {
    $orderSql = complaint_entry_datatable_order_sql($obconn, $orderColumn, $orderDir);
}

$addedBySelect = $showAddedBy
    ? ",
        COALESCE(
            (
                SELECT COALESCE(NULLIF(TRIM(um_added.name), ''), NULLIF(TRIM(um_added.username), ''))
                FROM user_master um_added
                WHERE um_added.id = complaints.added_by
                  AND um_added.deleted_at IS NULL
                LIMIT 1
            ),
            NULLIF(TRIM(complaints.username), ''),
            '-'
        ) AS added_by_name"
    : '';
 
$dataQuery = "
    SELECT
        id,
        fab_number,
        customer_name,
        street_1,
        street_2,
        pincode,
        city,
        district,
        state,
        customer_address,
        complaint_category_id,
        complaint_category_name,
        username,
        status,
        created_at{$addedBySelect},
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
    ORDER BY {$orderSql}
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
$complaintEntryPermissions = complaint_entry_action_permissions($obconn);

foreach ($rows as $row) {
    $status = (int) $row['status'];
    $flags = dt_parse_closure_row_flags($row);

    $rowData = [
        'id' => '#' . (int) $row['id'],
        'fab_number' => htmlspecialchars($row['fab_number'], ENT_QUOTES, 'UTF-8'),
        'customer_name' => htmlspecialchars($row['customer_name'], ENT_QUOTES, 'UTF-8'),
        'complaint_category' => htmlspecialchars(complaint_category_display_name($row), ENT_QUOTES, 'UTF-8'),
        'customer_address' => htmlspecialchars(complaint_format_address($row), ENT_QUOTES, 'UTF-8'),
        'username' => htmlspecialchars((string) ($row['username'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'status' => complaint_status_badge($status),
        'created_at' => date('d M Y H:i', strtotime($row['created_at'])),
        'actions' => complaint_entry_actions(
            (int) $row['id'],
            $status,
            $flags['needs_reassign'],
            $flags['can_close'],
            $complaintEntryPermissions
        ),
    ];

    if ($showAddedBy) {
        $rowData['added_by'] = htmlspecialchars(
            trim((string) ($row['added_by_name'] ?? '')) !== '' ? (string) $row['added_by_name'] : '-',
            ENT_QUOTES,
            'UTF-8'
        );
    }

    $data[] = $rowData;
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
    'city',
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
        array_merge(['fab_number', 'customer_name', 'complaint_description'], complaint_address_search_columns()),
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
        street_1,
        street_2,
        pincode,
        city,
        district,
        state,
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
$canShowComplaintClosure = complaint_user_can_closure($obconn);

foreach ($rows as $row) {
    $status = (int) $row['status'];
    $data[] = [
        'id' => '#' . (int) $row['id'],
        'fab_number' => htmlspecialchars($row['fab_number'], ENT_QUOTES, 'UTF-8'),
        'customer_name' => htmlspecialchars($row['customer_name'], ENT_QUOTES, 'UTF-8'),
        'customer_address' => htmlspecialchars(complaint_format_address($row), ENT_QUOTES, 'UTF-8'),
        'complaint_description' => htmlspecialchars($row['complaint_description'], ENT_QUOTES, 'UTF-8'),
        'status' => complaint_status_badge($status),
        'created_at' => date('d M Y H:i', strtotime($row['created_at'])),
        'actions' => complaint_entry_actions((int) $row['id'], $status),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
*/