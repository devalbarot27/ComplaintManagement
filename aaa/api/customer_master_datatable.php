<?php
session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/admin_access_helpers.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/customer_master_helpers.php';
require_once dirname(__DIR__) . '/includes/contact_helpers.php';

rbac_require_api_access($obconn);
admin_ensure_session_role($obconn);
customer_master_ensure_schema($obconn);
customer_master_ensure_rbac($obconn);
contact_ensure_schema($obconn);
contact_ensure_rbac($obconn);

$customerMasterPermissions = customer_master_action_permissions($obconn);
$canAddContact = contact_action_permissions($obconn)['add'];
$isDealerUser = is_dealer_user();

$allowedOrderColumns = ['id', 'customer_name', 'email', 'mobile', 'address', 'dealer_name', 'added_by', 'contact_count', 'created_at'];
$req = dt_parse_request($allowedOrderColumns, 'id');

$scope = customer_master_list_scope_filter($obconn);
$baseWhere = 'deleted_at IS NULL' . $scope['sql'];
$filterParams = $scope['params'];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total FROM customer_masters WHERE {$baseWhere}");
foreach ($filterParams as $key => $value) {
    if ($key === ':cm_scope_dealer_role') {
        $recordsTotalStmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $recordsTotalStmt->bindValue($key, $value);
    }
}
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
    if ($key === ':cm_scope_dealer_role') {
        $countFilteredStmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $countFilteredStmt->bindValue($key, $value);
    }
}
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$contactCountSql = '(
    SELECT COUNT(*)
    FROM contacts ct
    WHERE ct.customer_id = customer_masters.id
      AND ct.deleted_at IS NULL
)';

$addedByNameSql = '(
    SELECT COALESCE(NULLIF(TRIM(um.name), \'\'), \'\')
    FROM user_master um
    WHERE um.deleted_at IS NULL
      AND LOWER(TRIM(um.username)) = LOWER(TRIM(COALESCE(customer_masters.added_by, customer_masters.created_by, \'\')))
    LIMIT 1
)';

$addressOrderSql = "TRIM(CONCAT_WS(', ',
    NULLIF(TRIM(street_1), ''),
    NULLIF(TRIM(street_2), ''),
    NULLIF(TRIM(city), ''),
    NULLIF(TRIM(district), ''),
    NULLIF(TRIM(state), ''),
    NULLIF(TRIM(pincode), '')
))";

$orderColumn = $req['orderColumn'];
if ($orderColumn === 'contact_count') {
    $orderSql = $contactCountSql . ' ' . $req['orderDir'];
} elseif ($orderColumn === 'added_by') {
    $orderSql = 'COALESCE(NULLIF(TRIM(added_by), \'\'), NULLIF(TRIM(created_by), \'\'), \'\') ' . $req['orderDir'];
} elseif ($orderColumn === 'address') {
    $orderSql = $addressOrderSql . ' ' . $req['orderDir'];
} else {
    $orderSql = $orderColumn . ' ' . $req['orderDir'];
}

$dataQuery = "
    SELECT
        id,
        customer_name,
        dealer_code,
        dealer_name,
        email,
        mobile,
        street_1,
        street_2,
        pincode,
        city,
        district,
        state,
        added_by,
        created_by,
        created_at,
        {$contactCountSql} AS contact_count,
        {$addedByNameSql} AS added_by_name
    FROM customer_masters
    WHERE {$filterWhere}
    ORDER BY {$orderSql}
    LIMIT :limit OFFSET :offset
";

$dataStmt = $obconn->prepare($dataQuery);
foreach ($filterParams as $key => $value) {
    if ($key === ':cm_scope_dealer_role') {
        $dataStmt->bindValue($key, $value, PDO::PARAM_INT);
    } else {
        $dataStmt->bindValue($key, $value);
    }
}
$dataStmt->bindValue(':limit', $req['length'], PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $req['start'], PDO::PARAM_INT);
$dataStmt->execute();

$data = [];

foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $rowData = [
        'id' => '#' . (int) $row['id'],
        'customer_name' => htmlspecialchars(trim((string) ($row['customer_name'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'email' => htmlspecialchars(trim((string) ($row['email'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'mobile' => htmlspecialchars(trim((string) ($row['mobile'] ?? '')), ENT_QUOTES, 'UTF-8'),
        'address' => htmlspecialchars(customer_master_full_address_label($row), ENT_QUOTES, 'UTF-8'),
        'contact_count' => (int) ($row['contact_count'] ?? 0),
        'created_at' => rbac_format_datetime($row['created_at']),
        'actions' => customer_master_entry_actions(
            (int) $row['id'],
            $customerMasterPermissions,
            $canAddContact
        ),
    ];

    if (!$isDealerUser) {
        $rowData['dealer_name'] = htmlspecialchars(
            customer_master_dealer_display_label($row),
            ENT_QUOTES,
            'UTF-8'
        );
        $rowData['added_by'] = htmlspecialchars(
            customer_master_created_by_label([
                'created_by_name' => $row['added_by_name'] ?? '',
                'added_by' => $row['added_by'] ?? '',
                'created_by' => $row['created_by'] ?? '',
            ]),
            ENT_QUOTES,
            'UTF-8'
        );
    }

    $data[] = $rowData;
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);