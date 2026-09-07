<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/installed_base_helpers.php';
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';

installed_base_ensure_schema($obconn);

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
$listScope = after_market_list_scope($obconn);
$baseWhere = after_market_scope_where_for_alias($listScope['where'], 'ib');
$filterParams = $listScope['params'];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM installed_base ib WHERE {$baseWhere}");
foreach ($filterParams as $key => $value) {
    $recordsTotalStmt->bindValue($key, $value);
}
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($req['searchValue'] !== '') {
    $filterWhere .= ' AND (
        ib.order_id ILIKE :search
        OR ib.fab_number ILIKE :search
        OR ib.dealer_name ILIKE :search
        OR ib.machine_model ILIKE :search
        OR ib.machine_model_code ILIKE :search
        OR ib.industry_segment ILIKE :search
        OR ib.remarks ILIKE :search
        OR cm.customer_name ILIKE :search
        OR cm.mobile ILIKE :search
        OR cm.email ILIKE :search
        OR cm.city ILIKE :search
        OR CAST(ib.id AS TEXT) ILIKE :search
    )';
    $filterParams[':search'] = '%' . $req['searchValue'] . '%';
}

$countFilteredStmt = $obconn->prepare("
    SELECT COUNT(*) AS total
    FROM installed_base ib
    " . installed_base_customer_join_sql('ib', 'cm') . "
    WHERE {$filterWhere}
");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$orderColumn = $req['orderColumn'];
if ($orderColumn === 'customer_name') {
    $orderColumnSql = 'cm.customer_name';
} elseif (in_array($orderColumn, ['id', 'order_id', 'fab_number', 'dealer_name', 'machine_model', 'industry_segment', 'created_at'], true)) {
    $orderColumnSql = 'ib.' . $orderColumn;
} else {
    $orderColumnSql = 'ib.id';
}

$dataQuery = "
    SELECT
        ib.id,
        ib.order_id,
        ib.fab_number,
        cm.customer_name,
        ib.dealer_name,
        ib.machine_model,
        ib.machine_model_code,
        ib.industry_segment,
        ib.commissioning_date,
        ib.created_at,
        (
            SELECT COUNT(*)
            FROM service_logs sl
            WHERE sl.installed_base_id = ib.id
              AND sl.deleted_at IS NULL
        ) AS service_log_count
    FROM installed_base ib
    " . installed_base_customer_join_sql('ib', 'cm') . "
    WHERE {$filterWhere}
    ORDER BY {$orderColumnSql} {$req['orderDir']}
    LIMIT :limit OFFSET :offset
";

$dataStmt = $obconn->prepare($dataQuery);
foreach ($filterParams as $key => $value) {
    $dataStmt->bindValue($key, $value);
}
$dataStmt->bindValue(':limit', $req['length'], PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $req['start'], PDO::PARAM_INT);
$dataStmt->execute();

if (!isset($_SESSION['role'])) {
    admin_refresh_session_role($obconn);
}

$data = [];
$installedBasePermissions = installed_base_action_permissions($obconn);

foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $hasServiceLog = (int) ($row['service_log_count'] ?? 0) > 0;
    $data[] = [
        'id' => '#' . (int) $row['id'],
        'order_id' => htmlspecialchars((string) $row['order_id'], ENT_QUOTES, 'UTF-8'),
        'fab_number' => htmlspecialchars((string) ($row['fab_number'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'customer_name' => htmlspecialchars(trim((string) ($row['customer_name'] ?? '')) !== '' ? (string) $row['customer_name'] : '-', ENT_QUOTES, 'UTF-8'),
        'dealer_name' => htmlspecialchars((string) ($row['dealer_name'] ?? ''), ENT_QUOTES, 'UTF-8'),
        'machine_model' => htmlspecialchars(installed_base_machine_model_label($row), ENT_QUOTES, 'UTF-8'),
        'commissioning_date' => installed_base_format_date($row['commissioning_date']),
        'created_at' => date('d M Y H:i', strtotime((string) $row['created_at'])),
        'actions' => installed_base_entry_actions((int) $row['id'], $installedBasePermissions, $hasServiceLog),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
