<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/customer_master_helpers.php';
require_once dirname(__DIR__) . '/includes/contact_helpers.php';

rbac_require_api_access($obconn);
customer_master_ensure_schema($obconn);
customer_master_ensure_rbac($obconn);
contact_ensure_schema($obconn);
contact_ensure_rbac($obconn);

$customerMasterPermissions = customer_master_action_permissions($obconn);
$canAddContact = contact_action_permissions($obconn)['add'];

$allowedOrderColumns = ['id', 'customer_name', 'email', 'mobile', 'city', 'state', 'contact_count', 'created_at'];
$req = dt_parse_request($allowedOrderColumns, 'id');

$baseWhere = 'deleted_at IS NULL';
$filterParams = [];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM customer_masters WHERE {$baseWhere}");
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;

if ($req['searchValue'] !== '') {
    $searchFilter = customer_master_search_filter($req['searchValue']);
    $filterWhere .= ' AND ' . $searchFilter['sql'];
    $filterParams = array_merge($filterParams, $searchFilter['params']);
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM customer_masters WHERE {$filterWhere}");
foreach ($filterParams as $key => $value) {
    $countFilteredStmt->bindValue($key, $value);
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$contactCountSql = '(
    SELECT COUNT(*)
    FROM contacts ct
    WHERE ct.customer_id = customer_masters.id
      AND ct.deleted_at IS NULL
)';

$orderColumn = $req['orderColumn'];
if ($orderColumn === 'contact_count') {
    $orderSql = $contactCountSql . ' ' . $req['orderDir'];
} else {
    $orderSql = $orderColumn . ' ' . $req['orderDir'];
}

$dataQuery = "
    SELECT
        id,
        customer_name,
        email,
        mobile,
        city,
        state,
        created_at,
        {$contactCountSql} AS contact_count
    FROM customer_masters
    WHERE {$filterWhere}
    ORDER BY {$orderSql}
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
        'customer_name' => htmlspecialchars(trim((string) ($row['customer_name'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'email' => htmlspecialchars(trim((string) ($row['email'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'mobile' => htmlspecialchars(trim((string) ($row['mobile'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'city' => htmlspecialchars(trim((string) ($row['city'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'state' => htmlspecialchars(trim((string) ($row['state'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'contact_count' => (int) ($row['contact_count'] ?? 0),
        'created_at' => rbac_format_datetime($row['created_at']),
        'actions' => customer_master_entry_actions(
            (int) $row['id'],
            $customerMasterPermissions,
            $canAddContact
        ),
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);
