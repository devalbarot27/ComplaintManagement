<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/service_log_helpers.php';
require_once dirname(__DIR__) . '/includes/service_log_draft_helpers.php';
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';
require_once dirname(__DIR__) . '/includes/amc_helpers.php';

$allowedOrderColumns = [
    'id',
    'order_id',
    'serial_number',
    'machine_model',
    'warranty_chargeable',
    'engineer_name',
    'visit_date',
    'closure_date',
    'created_at',
];

$req = dt_parse_request($allowedOrderColumns, 'id');
$listScope = after_market_list_scope($obconn);
$baseWhere = $listScope['where'];
$filterParams = $listScope['params'];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM service_logs WHERE {$baseWhere}");
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
            'order_id',
            'serial_number',
            'machine_model',
            'warranty_chargeable',
            'issue_description',
            'engineer_name',
            'action_taken',
            'part_replaced',
            'customer_feedback',
            'remarks',
        ],
        'id'
    );
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM service_logs WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$dataQuery = "
    SELECT
        id,
        order_id,
        serial_number,
        machine_model,
        warranty_chargeable,
        engineer_name,
        visit_date,
        closure_date,
        created_at,
        is_draft,
        installed_base_id,
        fab_number
    FROM service_logs
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
$serviceLogPermissions = service_log_action_permissions($obconn);
$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
$amcLookup = amc_coverage_lookup(
    $obconn,
    array_map(static fn ($row) => (int) ($row['installed_base_id'] ?? 0), $rows),
    array_map(static fn ($row) => (string) ($row['fab_number'] ?? ''), $rows)
);
$commissioningLookup = installed_base_commissioning_lookup(
    $obconn,
    array_map(static fn ($row) => (int) ($row['installed_base_id'] ?? 0), $rows),
    array_map(static fn ($row) => (string) ($row['fab_number'] ?? ''), $rows)
);

foreach ($rows as $row) {
    $serviceLogId = (int) $row['id'];
    $isDraft = service_log_is_draft_value($row['is_draft'] ?? 0);
    $coverage = amc_coverage_resolve(
        $amcLookup,
        (int) ($row['installed_base_id'] ?? 0),
        (string) ($row['fab_number'] ?? '')
    );
    $commissioningDate = installed_base_commissioning_resolve(
        $commissioningLookup,
        (int) ($row['installed_base_id'] ?? 0),
        (string) ($row['fab_number'] ?? '')
    );

    $data[] = [
        'id' => service_log_grid_id_cell_html($serviceLogId, $isDraft),
        'is_draft' => $isDraft ? 1 : 0,
        'order_id' => htmlspecialchars($row['order_id'], ENT_QUOTES, 'UTF-8'),
        'serial_number' => htmlspecialchars((string) $row['serial_number'], ENT_QUOTES, 'UTF-8'),
        'machine_model' => amc_with_coverage_html(
            htmlspecialchars((string) $row['machine_model'], ENT_QUOTES, 'UTF-8'),
            $coverage,
            $commissioningDate
        ),
        'warranty_chargeable' => htmlspecialchars($row['warranty_chargeable'], ENT_QUOTES, 'UTF-8'),
        'engineer_name' => htmlspecialchars($row['engineer_name'], ENT_QUOTES, 'UTF-8'),
        'visit_date' => service_log_format_date($row['visit_date']),
        'closure_date' => service_log_format_date($row['closure_date']),
        'created_at' => date('d M Y H:i', strtotime($row['created_at'])),
        'actions' => service_log_entry_actions($serviceLogId, $serviceLogPermissions),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);