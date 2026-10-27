<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/help_support_helpers.php';

help_support_ensure_schema($obconn);
help_support_ensure_rbac($obconn);
rbac_require_api_access($obconn);

$helpSupportPermissions = help_support_action_permissions($obconn);
$canViewAll = help_support_can_view_all();
$scope = help_support_visibility_sql('h');

$allowedOrderColumns = ['id', 'category', 'title', 'qty'];
if ($canViewAll) {
    $allowedOrderColumns[] = 'added_by';
}
$allowedOrderColumns[] = 'created_at';
$req = dt_parse_request($allowedOrderColumns, 'title');

$baseFrom = '
    FROM help_support h
    LEFT JOIN user_master um
        ON TRIM(um.username) = TRIM(h.created_by)
       AND um.deleted_at IS NULL
';
$baseWhere = 'h.deleted_at IS NULL' . $scope['sql'];
$filterParams = $scope['params'];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total {$baseFrom} WHERE {$baseWhere}");
help_support_bind_scope($recordsTotalStmt, $scope);
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($req['searchValue'] !== '') {
    $searchFilter = help_support_search_filter($req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total {$baseFrom} WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$orderSqlMap = [
    'id' => 'h.id',
    'category' => 'h.category',
    'title' => 'h.title',
    'qty' => 'h.qty',
    'added_by' => "COALESCE(NULLIF(TRIM(um.name), ''), h.created_by)",
    'created_at' => 'h.created_at',
];
$orderSql = $orderSqlMap[$req['orderColumn']] ?? 'h.title';

$dataQuery = "
    SELECT
        h.id,
        h.category,
        h.title,
        h.qty,
        h.attachment_path,
        h.attachment_name,
        h.resource_url,
        h.created_by,
        h.created_at,
        um.name AS added_by_name
    {$baseFrom}
    WHERE {$filterWhere}
    ORDER BY {$orderSql} {$req['orderDir']}, h.id ASC
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
        'category' => htmlspecialchars(help_support_category_label((string) ($row['category'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'title' => htmlspecialchars((string) ($row['title'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'qty' => (int) ($row['qty'] ?? 0),
        'added_by' => htmlspecialchars(help_support_added_by_label($row), ENT_QUOTES, 'UTF-8'),
        'created_at' => rbac_format_datetime($row['created_at']),
        'resource' => help_support_resource_html($row),
        'actions' => help_support_entry_actions((int) $row['id'], $helpSupportPermissions),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
