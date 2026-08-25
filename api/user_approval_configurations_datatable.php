<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/user_approval_configuration_helpers.php';

admin_api_require_system_admin($obconn);
user_approval_config_ensure_schema($obconn);

$allowedOrderColumns = ['id', 'user_name', 'module_slug', 'level_1_approval', 'level_2_approval', 'created_at'];
$req = dt_parse_request($allowedOrderColumns, 'id');

$baseWhere = 'uac.deleted_at IS NULL';
$filterParams = [];

$recordsTotalStmt = $obconn->prepare("
    SELECT COUNT(*) AS total
    FROM user_approval_configurations uac
    WHERE {$baseWhere}
");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($req['searchValue'] !== '') {
    $searchFilter = user_approval_config_search_filter($req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("
    SELECT COUNT(*) AS total
    FROM user_approval_configurations uac
    INNER JOIN user_master um ON um.id = uac.user_id
    WHERE {$filterWhere}
");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$orderSqlMap = [
    'id' => 'uac.id',
    'user_name' => 'LOWER(COALESCE(NULLIF(TRIM(um.name), \'\'), um.username))',
    'module_slug' => 'uac.module_slug',
    'level_1_approval' => 'uac.level_1_approval',
    'level_2_approval' => 'uac.level_2_approval',
    'created_at' => 'uac.created_at',
];
$orderSql = $orderSqlMap[$req['orderColumn']] ?? 'uac.id';

$dataQuery = "
    SELECT
        uac.id,
        uac.user_id,
        uac.module_slug,
        uac.level_1_approval,
        uac.level_2_approval,
        uac.created_at,
        um.username,
        um.name
    FROM user_approval_configurations uac
    INNER JOIN user_master um ON um.id = uac.user_id
    WHERE {$filterWhere}
    ORDER BY {$orderSql} {$req['orderDir']}, uac.id DESC
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
    $moduleSlug = (string) ($row['module_slug'] ?? '');
    $level1 = user_approval_config_bool_from_value($row['level_1_approval'] ?? false);
    $level2 = user_approval_config_bool_from_value($row['level_2_approval'] ?? false);
    $data[] = [
        'id' => '#' . (int) $row['id'],
        'user_name' => htmlspecialchars(user_approval_config_user_label($row), ENT_QUOTES, 'UTF-8'),
        'module_slug' => htmlspecialchars(user_approval_config_module_label($moduleSlug), ENT_QUOTES, 'UTF-8'),
        'level_1_approval' => htmlspecialchars(user_approval_config_yes_no($level1), ENT_QUOTES, 'UTF-8'),
        'level_2_approval' => htmlspecialchars(
            $moduleSlug === user_approval_config_module_service() ? '-' : user_approval_config_yes_no($level2),
            ENT_QUOTES,
            'UTF-8'
        ),
        'created_at' => rbac_format_datetime($row['created_at']),
        'actions' => user_approval_config_entry_actions((int) $row['id']),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
