<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/spare_parts_helpers.php';

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

$req = dt_parse_request($allowedOrderColumns, 'id');
$baseWhere = 'deleted_at IS NULL';

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM spare_parts_consumption WHERE {$baseWhere}");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;
$filterParams = [];

if ($req['searchValue'] !== '') {
    $searchFilter = dt_complaint_search_filter(
        $req['searchValue'],
        [
            'serial_number',
            'warranty_chargeable',
            'spare_kit_number',
            'reason',
            'remarks',
        ],
        'id'
    );
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM spare_parts_consumption WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$dataQuery = "
    SELECT
        id,
        serial_number,
        consumption_date,
        warranty_chargeable,
        spare_kit_number,
        quantity,
        order_value,
        reason,
        service_log_id,
        created_at
    FROM spare_parts_consumption
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
        'serial_number' => htmlspecialchars($row['serial_number'], ENT_QUOTES, 'UTF-8'),
        'consumption_date' => spare_parts_format_date($row['consumption_date']),
        'warranty_chargeable' => htmlspecialchars($row['warranty_chargeable'], ENT_QUOTES, 'UTF-8'),
        'spare_kit_number' => htmlspecialchars($row['spare_kit_number'], ENT_QUOTES, 'UTF-8'),
        'quantity' => htmlspecialchars((string) $row['quantity'], ENT_QUOTES, 'UTF-8'),
        'order_value' => spare_parts_format_currency($row['order_value']),
        'reason' => htmlspecialchars($row['reason'], ENT_QUOTES, 'UTF-8'),
        'service_log_id' => $row['service_log_id']
            ? '#' . (int) $row['service_log_id']
            : '-',
        'created_at' => date('d M Y H:i', strtotime($row['created_at'])),
        'actions' => spare_parts_entry_actions((int) $row['id']),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
