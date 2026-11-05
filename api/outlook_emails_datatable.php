<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/outlook_email_helpers.php';

admin_api_require_system_admin($obconn);
outlook_email_ensure_schema($obconn);

$allowedOrderColumns = ['id', 'category', 'outlook_email', 'created_at'];
$req = dt_parse_request($allowedOrderColumns, 'created_at');

$groupedFrom = '
    FROM (
        SELECT
            o.group_id AS id,
            MAX(o.category) AS category,
            STRING_AGG(o.outlook_email, \', \' ORDER BY o.id) AS emails,
            MIN(o.created_at) AS created_at
        FROM outlook_emails o
        WHERE o.deleted_at IS NULL
          AND o.user_id IS NULL
          AND o.group_id IS NOT NULL
        GROUP BY o.group_id
    ) configs
';

$recordsTotalStmt = $obconn->query('SELECT COUNT(*) AS total ' . $groupedFrom);
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = '1=1';
$filterParams = [];
if ($req['searchValue'] !== '') {
    $searchFilter = outlook_email_search_filter($req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = $searchFilter['params'];
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total {$groupedFrom} WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$orderSqlMap = [
    'id' => 'configs.id',
    'category' => 'configs.category',
    'outlook_email' => 'configs.emails',
    'created_at' => 'configs.created_at',
];
$orderSql = $orderSqlMap[$req['orderColumn']] ?? 'configs.category';

$dataStmt = $obconn->prepare("
    SELECT configs.id, configs.category, configs.emails, configs.created_at
    {$groupedFrom}
    WHERE {$filterWhere}
    ORDER BY {$orderSql} {$req['orderDir']}, configs.id DESC
    LIMIT :limit OFFSET :offset
");
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
        'category' => htmlspecialchars((string) ($row['category'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'outlook_email' => htmlspecialchars((string) ($row['emails'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'created_at' => rbac_format_datetime($row['created_at']),
        'actions' => outlook_email_entry_actions((int) $row['id']),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
