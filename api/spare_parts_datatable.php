<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/spare_parts_helpers.php';
require_once dirname(__DIR__) . '/includes/after_market_access_helpers.php';
require_once dirname(__DIR__) . '/includes/current_username_helpers.php';

$allowedOrderColumns = [
    'id',
    'serial_number',
    'consumption_date',
    'warranty_chargeable',
    'spare_kit_number',
    'quantity',
    'order_value',
    'reason',
    'created_at',
];

$orderColumnMap = [
    'id' => 'sp.id',
    'serial_number' => 'sp.serial_number',
    'consumption_date' => 'sp.consumption_date',
    'warranty_chargeable' => 'sp.warranty_chargeable',
    'spare_kit_number' => 'spi_first.spare_kit_number',
    'quantity' => 'spi_agg.total_qty',
    'order_value' => 'spi_agg.total_order_value',
    'reason' => 'spi_agg.reasons',
    'created_at' => 'sp.created_at',
];

$req = dt_parse_request($allowedOrderColumns, 'id');
$listScope = after_market_list_scope($obconn);
$baseWhere = preg_replace('/\b(deleted_at|username)\b/', 'sp.$1', $listScope['where']);
$filterParams = $listScope['params'];

$recordsTotalStmt = $obconn->prepare("
    SELECT COUNT(*) AS total
    FROM spare_parts_consumption sp
    WHERE {$baseWhere}
");
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
            'sp.serial_number',
            'sp.warranty_chargeable',
            'sp.remarks',
        ],
        'sp.id'
    );
    $filterWhere .= ' AND (' . $searchFilter['sql'] . ' OR EXISTS (
        SELECT 1
        FROM spare_parts_consumption_items spi_search
        WHERE spi_search.spare_parts_consumption_id = sp.id
          AND spi_search.deleted_at IS NULL
          AND (
              spi_search.spare_kit_number ILIKE :search
              OR spi_search.reason ILIKE :search
          )
    ))';
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("
    SELECT COUNT(*) AS total
    FROM spare_parts_consumption sp
    WHERE {$filterWhere}
");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$orderBy = $orderColumnMap[$req['orderColumn']] ?? 'sp.id';

$dataQuery = "
    SELECT
        sp.id,
        sp.serial_number,
        sp.consumption_date,
        sp.warranty_chargeable,
        sp.service_log_id,
        sp.created_at,
        COALESCE(spi_agg.item_count, 0) AS item_count,
        spi_first.spare_kit_number,
        COALESCE(spi_agg.total_qty, 0) AS quantity,
        COALESCE(spi_agg.total_order_value, 0) AS order_value,
        COALESCE(spi_agg.reasons, '') AS reason
    FROM spare_parts_consumption sp
    LEFT JOIN (
        SELECT
            spare_parts_consumption_id,
            COUNT(*)::int AS item_count,
            MIN(id) AS first_item_id,
            SUM(quantity) AS total_qty,
            SUM(order_value) AS total_order_value,
            string_agg(DISTINCT reason, ', ' ORDER BY reason) AS reasons
        FROM spare_parts_consumption_items
        WHERE deleted_at IS NULL
        GROUP BY spare_parts_consumption_id
    ) spi_agg ON spi_agg.spare_parts_consumption_id = sp.id
    LEFT JOIN spare_parts_consumption_items spi_first
        ON spi_first.id = spi_agg.first_item_id
       AND spi_first.deleted_at IS NULL
    WHERE {$filterWhere}
    ORDER BY {$orderBy} {$req['orderDir']}
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
$sparePartsPermissions = spare_parts_action_permissions($obconn);

foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $itemCount = (int) ($row['item_count'] ?? 0);
    $firstKit = trim((string) ($row['spare_kit_number'] ?? ''));
    $kitDisplay = $firstKit !== ''
        ? spare_parts_format_kit_summary($firstKit, $itemCount)
        : '-';

    $data[] = [
        'id' => '#' . (int) $row['id'],
        'serial_number' => htmlspecialchars($row['serial_number'], ENT_QUOTES, 'UTF-8'),
        'consumption_date' => spare_parts_format_date($row['consumption_date']),
        'warranty_chargeable' => htmlspecialchars($row['warranty_chargeable'], ENT_QUOTES, 'UTF-8'),
        'spare_kit_number' => htmlspecialchars($kitDisplay, ENT_QUOTES, 'UTF-8'),
        'quantity' => spare_parts_format_quantity($row['quantity']),
        'order_value' => spare_parts_format_currency($row['order_value']),
        'reason' => htmlspecialchars($row['reason'] !== '' ? $row['reason'] : '-', ENT_QUOTES, 'UTF-8'),
        'service_log_id' => $row['service_log_id']
            ? '#' . (int) $row['service_log_id']
            : '-',
        'created_at' => date('d M Y H:i', strtotime($row['created_at'])),
        'actions' => spare_parts_entry_actions((int) $row['id'], $sparePartsPermissions),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
