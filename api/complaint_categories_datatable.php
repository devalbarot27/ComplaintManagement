<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/admin_api_guard.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_category_helpers.php';

admin_api_require_system_admin($obconn);

$allowedOrderColumns = ['id', 'name', 'status', 'created_at'];
$req = dt_parse_request($allowedOrderColumns, 'id');

$statusFilter = strtolower(trim((string) ($_POST['status_filter'] ?? '')));
$recordFilter = strtolower(trim((string) ($_POST['record_filter'] ?? 'active')));

if (!in_array($recordFilter, ['active', 'deleted'], true)) {
    $recordFilter = 'active';
}

if ($statusFilter !== '' && !array_key_exists($statusFilter, rbac_status_options())) {
    $statusFilter = '';
}

$baseWhere = $recordFilter === 'deleted' ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL';
$filterParams = [];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM complaint_categories WHERE {$baseWhere}");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($recordFilter === 'active' && $statusFilter !== '') {
    $filterWhere .= ' AND status = :status_filter';
    $filterParams[':status_filter'] = $statusFilter;
}

if ($req['searchValue'] !== '') {
    $searchFilter = complaint_category_search_filter($req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM complaint_categories WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$dataQuery = "
    SELECT id, name, status, created_at
    FROM complaint_categories
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
$isDeletedList = $recordFilter === 'deleted';

foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $data[] = [
        'id' => '#' . (int) $row['id'],
        'name' => htmlspecialchars($row['name'], ENT_QUOTES, 'UTF-8'),
        'status' => rbac_status_badge($row['status']),
        'created_at' => rbac_format_datetime($row['created_at']),
        'actions' => complaint_category_entry_actions((int) $row['id'], $isDeletedList),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
