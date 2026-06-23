<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/permission_helpers.php';

admin_api_require_system_admin($obconn);

$allowedOrderColumns = ['id', 'module_name', 'permission_name', 'permission_slug', 'description', 'created_at'];

$req = dt_parse_request($allowedOrderColumns, 'id');
$baseWhere = 'p.deleted_at IS NULL';
$filterParams = [];

$recordsTotalStmt = $obconn->prepare("
    SELECT COUNT(*) AS total
    FROM permissions p
    INNER JOIN modules m ON m.id = p.module_id AND m.deleted_at IS NULL
    WHERE {$baseWhere}
");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($req['searchValue'] !== '') {
    $searchFilter = permission_search_filter($req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("
    SELECT COUNT(*) AS total
    FROM permissions p
    INNER JOIN modules m ON m.id = p.module_id AND m.deleted_at IS NULL
    WHERE {$filterWhere}
");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$orderColumn = $req['orderColumn'];
if ($orderColumn === 'module_name') {
    $orderBy = "m.module_name {$req['orderDir']}";
} else {
    $orderBy = "p.{$orderColumn} {$req['orderDir']}";
}

$dataQuery = "
    SELECT
        p.id,
        p.module_id,
        m.module_name,
        p.permission_name,
        p.permission_slug,
        p.description,
        p.created_at
    FROM permissions p
    INNER JOIN modules m ON m.id = p.module_id AND m.deleted_at IS NULL
    WHERE {$filterWhere}
    ORDER BY {$orderBy}
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
        'module_name' => htmlspecialchars($row['module_name'], ENT_QUOTES, 'UTF-8'),
        'permission_name' => htmlspecialchars($row['permission_name'], ENT_QUOTES, 'UTF-8'),
        'permission_slug' => htmlspecialchars($row['permission_slug'], ENT_QUOTES, 'UTF-8'),
        'description' => htmlspecialchars(rbac_display_value($row['description']), ENT_QUOTES, 'UTF-8'),
        'created_at' => rbac_format_datetime($row['created_at']),
        'actions' => permission_entry_actions((int) $row['id']),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
