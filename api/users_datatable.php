<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/user_helpers.php';

admin_api_require_system_admin($obconn);

$allowedOrderColumns = [
    'id',
    'role',
    'username',
    'name',
    'email',
    'mobile_number',
    'last_login_at',
    'created_at',
];

$req = dt_parse_request($allowedOrderColumns, 'id');
$baseWhere = 'deleted_at IS NULL';
$filterParams = [];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM user_master WHERE {$baseWhere}");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($req['searchValue'] !== '') {
    $searchFilter = user_search_filter($obconn, $req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM user_master WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$dataQuery = "
    SELECT
        id,
        role,
        username,
        name,
        email,
        mobile_number,
        last_login_at,
        created_at
    FROM user_master
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
        'role' => htmlspecialchars(user_role_label($obconn, $row['role']), ENT_QUOTES, 'UTF-8'),
        'username' => htmlspecialchars($row['username'], ENT_QUOTES, 'UTF-8'),
        'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
        'email' => htmlspecialchars($row['email'], ENT_QUOTES, 'UTF-8'),
        'mobile_number' => htmlspecialchars((string) $row['mobile_number'], ENT_QUOTES, 'UTF-8'),
        'last_login_at' => user_format_datetime($row['last_login_at']),
        'created_at' => user_format_datetime($row['created_at']),
        'actions' => user_entry_actions((int) $row['id']),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);