<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';
require_once dirname(__DIR__) . '/includes/warranty_claims_helpers.php';

$allowedOrderColumns = [
    'id',
    'fab_number',
    'customer_name',
    'commissioning_date',
];

$req = dt_parse_request($allowedOrderColumns, 'id');
$listScope = after_market_list_scope($obconn);
$baseWhere = $listScope['where'];
$filterParams = $listScope['params'];

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
        [
            'fab_number',
            'customer_name',
            'dealer_name',
            'machine_model',
            'machine_model_code',
        ],
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
        fab_number,
        customer_name,
        machine_model,
        machine_model_code,
        commissioning_date
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

$canRequestClaim = rbac_can_access_menu($obconn, 'service_claims.php');

$data = [];
foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $warranty = installed_base_warranty_status($row['commissioning_date']);

    $actionHtml = '<span class="text-muted">Covered under warranty</span>';
    if ($warranty['can_request_approval']) {
        $actionHtml = $canRequestClaim
            ? '<a href="service_claims.php" class="btn btn-sm btn-complaint-primary">Raise Claim for Approval</a>'
            : '<span class="text-muted">Out of warranty</span>';
    }

    $data[] = [
        'id' => '#' . (int) $row['id'],
        'fab_number' => htmlspecialchars((string) $row['fab_number'], ENT_QUOTES, 'UTF-8'),
        'customer_name' => htmlspecialchars((string) $row['customer_name'], ENT_QUOTES, 'UTF-8'),
        'machine_model' => htmlspecialchars(installed_base_machine_model_label($row), ENT_QUOTES, 'UTF-8'),
        'commissioning_date' => installed_base_format_date($row['commissioning_date']),
        'warranty_status' => '<span class="badge ' . $warranty['badge_class'] . '">' . htmlspecialchars($warranty['status'], ENT_QUOTES, 'UTF-8') . '</span>',
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
