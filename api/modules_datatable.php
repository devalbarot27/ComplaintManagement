<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/module_helpers.php';

admin_api_require_system_admin($obconn);

$allowedOrderColumns = ['id', 'module_name', 'module_slug', 'description', 'created_at'];

$req = dt_parse_request($allowedOrderColumns, 'id');
$baseWhere = 'deleted_at IS NULL';
$filterParams = [];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM modules WHERE {$baseWhere}");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($req['searchValue'] !== '') {
    $searchFilter = module_search_filter($req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM modules WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$dataQuery = "
    SELECT id, module_name, module_slug, description, created_at
    FROM modules
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
        'module_name' => htmlspecialchars($row['module_name'], ENT_QUOTES, 'UTF-8'),
        'module_slug' => htmlspecialchars($row['module_slug'], ENT_QUOTES, 'UTF-8'),
        'description' => htmlspecialchars(rbac_display_value($row['description']), ENT_QUOTES, 'UTF-8'),
        'created_at' => rbac_format_datetime($row['created_at']),
        'actions' => module_entry_actions((int) $row['id']),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
