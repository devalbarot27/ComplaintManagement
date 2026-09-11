<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/contact_helpers.php';

rbac_require_api_access($obconn);
contact_ensure_schema($obconn);
contact_ensure_rbac($obconn);

$contactPermissions = contact_action_permissions($obconn);

$allowedOrderColumns = [
    'ct.id',
    'cm.customer_name',
    'ct.first_name',
    'ct.last_name',
    'ct.email',
    'ct.mobile',
    'ct.created_at',
];
$req = dt_parse_request($allowedOrderColumns, 'ct.id');

$baseWhere = 'ct.deleted_at IS NULL';
$filterParams = [];
$fromJoin = '
    FROM contacts ct
    ' . contact_customer_join_sql('ct', 'cm');

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total {$fromJoin} WHERE {$baseWhere}");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($req['searchValue'] !== '') {
    $searchFilter = contact_search_filter($req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total {$fromJoin} WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$dataQuery = "
    SELECT
        ct.id,
        cm.customer_name,
        ct.first_name,
        ct.last_name,
        ct.email,
        ct.mobile,
        ct.created_at
    {$fromJoin}
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
        'customer_name' => htmlspecialchars(trim((string) ($row['customer_name'] ?? '')) !== '' ? (string) $row['customer_name'] : '-', ENT_QUOTES, 'UTF-8'),
        'first_name' => htmlspecialchars(trim((string) ($row['first_name'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'last_name' => htmlspecialchars(trim((string) ($row['last_name'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'email' => htmlspecialchars(trim((string) ($row['email'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'mobile' => htmlspecialchars(trim((string) ($row['mobile'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'created_at' => rbac_format_datetime($row['created_at']),
        'actions' => contact_entry_actions((int) $row['id'], $contactPermissions),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
