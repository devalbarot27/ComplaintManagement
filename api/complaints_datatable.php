<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_address_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_category_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';

complaint_ensure_schema($obconn);

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
$baseWhere = complaint_scope_where_for_alias($listScope['where'], 'c');
$filterParams = $listScope['params'];
$fromJoin = '
    FROM complaints c
    ' . complaint_customer_join_sql('c', 'cm');

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total {$fromJoin} WHERE {$baseWhere}");
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
        $filterWhere .= ' AND c.status = :status_filter';
        $filterParams[':status_filter'] = $statusFilterInt;
    }
}

$complaintIdFilter = (int) ($_POST['complaint_id'] ?? $_GET['complaint_id'] ?? 0);
if ($complaintIdFilter > 0) {
    $filterWhere .= ' AND c.id = :complaint_id';
    $filterParams[':complaint_id'] = $complaintIdFilter;
}

if ($req['searchValue'] !== '') {
    $searchFilter = dt_complaint_search_filter(
        $req['searchValue'],
        [
            'c.fab_number',
            'cm.customer_name',
            'c.complaint_category_name',
            'c.username',
            'c.complaint_description',
            'cm.street_1',
            'cm.street_2',
            'cm.pincode',
            'cm.city',
            'cm.district',
            'cm.state',
            'cm.mobile',
            'cm.email',
        ],
        'c.status'
    );
    if ($showAddedBy) {
        $searchFilter['sql'] = '(' . $searchFilter['sql'] . ' OR EXISTS (
            SELECT 1
            FROM user_master um_s
            WHERE um_s.id = c.added_by
              AND um_s.deleted_at IS NULL
              AND (um_s.name ILIKE :search OR um_s.username ILIKE :search)
        ))';
    }
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
if ($orderColumn === 'added_by_name') {
    $orderSql = "(
        COALESCE(
            (
                SELECT COALESCE(NULLIF(TRIM(um_ord.name), ''), NULLIF(TRIM(um_ord.username), ''))
                FROM user_master um_ord
                WHERE um_ord.id = c.added_by
                  AND um_ord.deleted_at IS NULL
                LIMIT 1
            ),
            NULLIF(TRIM(c.username), ''),
            '-'
        )
    ) {$orderDir}, c.created_at DESC, c.id DESC";
} elseif ($orderColumn === 'customer_name') {
    $orderSql = "cm.customer_name {$orderDir}, c.created_at DESC, c.id DESC";
} elseif ($orderColumn === 'city') {
    $orderSql = "cm.city {$orderDir}, c.created_at DESC, c.id DESC";
} elseif ($orderColumn === 'created_at' || $orderColumn === 'id') {
    $orderSql = "c.created_at {$orderDir}, c.id {$orderDir}";
} elseif (in_array($orderColumn, ['fab_number', 'complaint_category_name', 'status'], true)) {
    $orderSql = "c.{$orderColumn} {$orderDir}, c.created_at DESC, c.id DESC";
} else {
    $orderSql = "c.created_at DESC, c.id DESC";
}

$addedBySelect = $showAddedBy
    ? ",
        COALESCE(
            (
                SELECT COALESCE(NULLIF(TRIM(um_added.name), ''), NULLIF(TRIM(um_added.username), ''))
                FROM user_master um_added
                WHERE um_added.id = c.added_by
                  AND um_added.deleted_at IS NULL
                LIMIT 1
            ),
            NULLIF(TRIM(c.username), ''),
            '-'
        ) AS added_by_name"
    : '';

$dataQuery = "
    SELECT
        c.id,
        c.fab_number,
        cm.customer_name,
        cm.street_1,
        cm.street_2,
        cm.pincode,
        cm.city,
        cm.district,
        cm.state,
        c.complaint_category_id,
        c.complaint_category_name,
        c.username,
        c.status,
        c.created_at{$addedBySelect},
        EXISTS (
            SELECT 1
            FROM complaint_service_updates csu
            WHERE csu.complaint_id = c.id
        ) AS has_service_update,
        (
            SELECT cc.call_closure::text
            FROM complaint_closures cc
            WHERE cc.complaint_id = c.id
            ORDER BY cc.created_at DESC, cc.id DESC
            LIMIT 1
        ) AS latest_closure,
        EXISTS (
            SELECT 1
            FROM complaint_assignments ca2
            WHERE ca2.complaint_id = c.id
            AND ca2.assign_complaint_datetime > (
                SELECT cc2.created_at
                FROM complaint_closures cc2
                WHERE cc2.complaint_id = c.id
                  AND cc2.call_closure::text = 'No'
                ORDER BY cc2.created_at DESC, cc2.id DESC
                LIMIT 1
            )
        ) AS has_reassign_after_closure_no,
        EXISTS (
            SELECT 1
            FROM complaint_service_updates su2
            WHERE su2.complaint_id = c.id
            AND su2.created_at > (
                SELECT cc3.created_at
                FROM complaint_closures cc3
                WHERE cc3.complaint_id = c.id
                  AND cc3.call_closure::text = 'No'
                ORDER BY cc3.created_at DESC, cc3.id DESC
                LIMIT 1
            )
        ) AS has_service_after_closure_no
    {$fromJoin}
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
        'customer_name' => htmlspecialchars((string) ($row['customer_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
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
