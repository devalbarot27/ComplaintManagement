<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/outlook_email_helpers.php';

admin_api_require_system_admin($obconn);
outlook_email_ensure_schema($obconn);

$allowedOrderColumns = ['id', 'user_name', 'outlook_email', 'created_at'];
$req = dt_parse_request($allowedOrderColumns, 'user_name');

$baseFrom = '
    FROM outlook_emails o
    LEFT JOIN user_master um ON um.id = o.user_id
';
$baseWhere = 'o.deleted_at IS NULL';
$filterParams = [];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total {$baseFrom} WHERE {$baseWhere}");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($req['searchValue'] !== '') {
    $searchFilter = outlook_email_search_filter($req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = $searchFilter['params'];
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total {$baseFrom} WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$orderSqlMap = [
    'id' => 'o.id',
    'user_name' => 'um.name',
    'outlook_email' => 'o.outlook_email',
    'created_at' => 'o.created_at',
];
$orderSql = $orderSqlMap[$req['orderColumn']] ?? 'um.name';

$dataQuery = "
    SELECT
        o.id,
        o.outlook_email,
        o.created_at,
        um.name AS user_name
    {$baseFrom}
    WHERE {$filterWhere}
    ORDER BY {$orderSql} {$req['orderDir']}, o.id ASC
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
    $userName = trim((string) ($row['user_name'] ?? ''));
    $data[] = [
        'id' => '#' . (int) $row['id'],
        'user_name' => htmlspecialchars($userName !== '' ? $userName : '-', ENT_QUOTES, 'UTF-8'),
        'outlook_email' => htmlspecialchars((string) ($row['outlook_email'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'created_at' => rbac_format_datetime($row['created_at']),
        'actions' => outlook_email_entry_actions((int) $row['id']),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
