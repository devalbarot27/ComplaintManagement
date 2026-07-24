<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/product_helpers.php';

admin_api_require_system_admin($obconn);

$allowedOrderColumns = [
    'id',
    'dpst',
    'product_group',
    'tplcode',
    'tpldesc',
    'cos',
    'valid',
    'company',
    'warehouse',
    'created_at',
    'id',
];

$req = dt_parse_request($allowedOrderColumns, 'id');

$validFilter = product_normalize_yn((string) ($_POST['valid_filter'] ?? ''), '');
if ($validFilter !== '' && !array_key_exists($validFilter, product_yn_options())) {
    $validFilter = '';
}

$baseWhere = 'deleted_at IS NULL';
$filterParams = [];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM products WHERE {$baseWhere}");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($validFilter !== '') {
    $filterWhere .= ' AND UPPER(TRIM(valid)) = :valid_filter';
    $filterParams[':valid_filter'] = $validFilter;
}

if ($req['searchValue'] !== '') {
    $searchFilter = product_search_filter($req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM products WHERE {$filterWhere}");
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
        dpst,
        product_group,
        tplcode,
        tpldesc,
        cos,
        valid,
        company,
        warehouse,
        created_at
    FROM products
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

$data = [];

foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $id = (int) $row['id'];

    $data[] = [
        'id' => '#' . $id,
        'dpst' => htmlspecialchars(product_display_value($row['dpst'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'product_group' => htmlspecialchars(product_display_value($row['product_group'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'tplcode' => htmlspecialchars(product_display_value($row['tplcode'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'tpldesc' => htmlspecialchars(product_display_value($row['tpldesc'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'cos' => htmlspecialchars(product_format_cos($row['cos'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'valid' => product_yn_badge((string) ($row['valid'] ?? '')),
        'company' => htmlspecialchars(product_display_value($row['company'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'warehouse' => htmlspecialchars(product_display_value($row['warehouse'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'created_at' => rbac_format_datetime($row['created_at'] ?? null),
        'actions' => product_entry_actions($id),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
