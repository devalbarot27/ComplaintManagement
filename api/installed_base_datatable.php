<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';

$allowedOrderColumns = [
    'id',
    'order_id',
    'fab_number',
    'customer_name',
    'dealer_name',
    'machine_model',
    'industry_segment',
    'created_at',
];

$req = dt_parse_request($allowedOrderColumns, 'id');
$baseWhere = 'deleted_at IS NULL AND username = :username';

$filterParams = [
    ':username' => current_username(),
];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM installed_base WHERE {$baseWhere}");
foreach ($filterParams as $key => $value) {
    $recordsTotalStmt->bindValue($key, $value);
}
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($req['searchValue'] !== '') {
    $searchFilter = dt_complaint_search_filter(
        $req['searchValue'],
        array_merge(
            [
                'order_id',
                'fab_number',
                'customer_name',
                'mobile',
                'email',
                'dealer_name',
                'machine_model',
                'machine_model_code',
                'industry_segment',
                'remarks',
            ],
            installed_base_address_search_columns()
        ),
        'id'
    );
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM installed_base WHERE {$filterWhere}");
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
        order_id,
        fab_number,
        customer_name,
        dealer_name,
        machine_model,
        machine_model_code,
        industry_segment,
        commissioning_date,
        created_at
    FROM installed_base
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
    $data[] = [
        'id' => '#' . (int) $row['id'],
        'order_id' => htmlspecialchars($row['order_id'], ENT_QUOTES, 'UTF-8'),
        'fab_number' => htmlspecialchars((string) $row['fab_number'], ENT_QUOTES, 'UTF-8'),
        'customer_name' => htmlspecialchars($row['customer_name'], ENT_QUOTES, 'UTF-8'),
        'dealer_name' => htmlspecialchars((string) $row['dealer_name'], ENT_QUOTES, 'UTF-8'),
        'machine_model' => htmlspecialchars(installed_base_machine_model_label($row), ENT_QUOTES, 'UTF-8'),
        'commissioning_date' => installed_base_format_date($row['commissioning_date']),
        'created_at' => date('d M Y H:i', strtotime($row['created_at'])),
        'actions' => installed_base_entry_actions((int) $row['id']),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
