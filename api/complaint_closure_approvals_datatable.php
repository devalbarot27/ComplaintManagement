<?php

session_start();
require_once dirname(__DIR__) . '/pdo_obconn.php';
require_once dirname(__DIR__) . '/includes/rbac_access_helpers.php';
rbac_require_api_access($obconn);
require_once dirname(__DIR__) . '/includes/complaint_datatable_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_closure_approval_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_address_helpers.php';
require_once dirname(__DIR__) . '/includes/complaint_status.php';

complaint_closure_approval_ensure_schema($obconn);

$allowedOrderColumns = [
    'id',
    'fab_number',
    'customer_name',
    'status',
    'approval_status',
    'approver_name',
    'created_at',
];
$req = dt_parse_request($allowedOrderColumns, 'created_at');

$approverNameSql = "COALESCE(NULLIF(TRIM(approver.name), ''), NULLIF(TRIM(approver.username), ''), '-')";
$requestedOnSql = "(
    TO_CHAR(a.created_at, 'DD') || ' ' ||
    (ARRAY['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'])[EXTRACT(MONTH FROM a.created_at)::int] || ' ' ||
    TO_CHAR(a.created_at, 'YYYY HH12:MI AM')
)";

$scope = complaint_closure_approval_list_scope($obconn);
$fromJoin = '
    FROM complaint_closure_approvals a
    INNER JOIN complaints c ON c.id = a.complaint_id
    ' . complaint_customer_join_sql('c', 'cm') . '
    LEFT JOIN user_master approver
        ON approver.id = a.approver_user_id
       AND approver.deleted_at IS NULL
';
$baseWhere = 'a.id = (
        SELECT MAX(latest.id)
        FROM complaint_closure_approvals latest
        WHERE latest.complaint_id = a.complaint_id
    )
    AND LOWER(TRIM(a.status)) = :approval_status_pending
    AND ' . $scope['where'];
$scope['params'][':approval_status_pending'] = 'pending';
$baseParams = $scope['params'];

$recordsTotalStmt = $obconn->prepare("SELECT COUNT(*) AS total {$fromJoin} WHERE {$baseWhere}");
complaint_closure_approval_dt_bind($recordsTotalStmt, $baseParams);
$recordsTotalStmt->execute();
$recordsTotal = (int) $recordsTotalStmt->fetch(PDO::FETCH_ASSOC)['total'];

$filterWhere = $baseWhere;
$filterParams = $baseParams;

if ($req['searchValue'] !== '') {
    $searchParts = [];
    $searchValue = '%' . $req['searchValue'] . '%';
    foreach ([
        'CAST(c.id AS TEXT)' => ':search_id',
        "COALESCE(c.fab_number, '')" => ':search_fab',
        "COALESCE(cm.customer_name, '')" => ':search_customer',
        $approverNameSql => ':search_engineer',
        $requestedOnSql => ':search_requested',
    ] as $expression => $param) {
        $searchParts[] = "{$expression} ILIKE {$param}";
        $filterParams[$param] = $searchValue;
    }

    $statusIds = dt_match_status_ids($req['searchValue']);
    foreach ($statusIds as $index => $statusId) {
        $param = ':search_status_' . $index;
        $searchParts[] = 'c.status = ' . $param;
        $filterParams[$param] = (int) $statusId;
    }

    $needle = strtolower($req['searchValue']);
    $approvalIndex = 0;
    foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $status => $label) {
        if (!str_contains(strtolower($label), $needle) && !str_contains($needle, strtolower($label))) {
            continue;
        }
        $param = ':search_approval_' . $approvalIndex;
        $approvalIndex++;
        $searchParts[] = 'LOWER(TRIM(a.status)) = ' . $param;
        $filterParams[$param] = $status;
    }

    if ($searchParts !== []) {
        $filterWhere .= ' AND (' . implode(' OR ', $searchParts) . ')';
    }
}

$countFilteredStmt = $obconn->prepare("SELECT COUNT(*) AS total {$fromJoin} WHERE {$filterWhere}");
complaint_closure_approval_dt_bind($countFilteredStmt, $filterParams);
$countFilteredStmt->execute();
$recordsFiltered = (int) $countFilteredStmt->fetch(PDO::FETCH_ASSOC)['total'];

$orderColumn = $req['orderColumn'];
$orderDir = $req['orderDir'];
$orderSql = "{$orderColumn} {$orderDir}, id DESC";

$dataQuery = "
    SELECT *
    FROM (
        SELECT
            c.id,
            c.fab_number,
            COALESCE(NULLIF(TRIM(cm.customer_name), ''), '-') AS customer_name,
            c.status,
            LOWER(TRIM(a.status)) AS approval_status,
            {$approverNameSql} AS approver_name,
            a.created_at
        {$fromJoin}
        WHERE {$filterWhere}
    ) approvals
    ORDER BY {$orderSql}
    LIMIT :limit OFFSET :offset
";
$dataStmt = $obconn->prepare($dataQuery);
complaint_closure_approval_dt_bind($dataStmt, $filterParams);
$dataStmt->bindValue(':limit', $req['length'], PDO::PARAM_INT);
$dataStmt->bindValue(':offset', $req['start'], PDO::PARAM_INT);
$dataStmt->execute();

$data = [];
foreach ($dataStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $complaintId = (int) $row['id'];
    $encodedId = rawurlencode(base64_encode((string) $complaintId));
    $requestedAt = trim((string) ($row['created_at'] ?? ''));
    $data[] = [
        'id' => '#' . $complaintId,
        'fab_number' => htmlspecialchars((string) ($row['fab_number'] ?? '-'), ENT_QUOTES, 'UTF-8'),
        'customer_name' => htmlspecialchars((string) ($row['customer_name'] ?? '-'), ENT_QUOTES, 'UTF-8'),
        'status' => complaint_status_badge((int) ($row['status'] ?? 0)),
        'approval_status' => htmlspecialchars(
            complaint_closure_approval_status_label((string) ($row['approval_status'] ?? '')),
            ENT_QUOTES,
            'UTF-8'
        ),
        'approver_name' => htmlspecialchars((string) ($row['approver_name'] ?? '-'), ENT_QUOTES, 'UTF-8'),
        'created_at' => $requestedAt !== ''
            ? htmlspecialchars(date('d M Y h:i A', strtotime($requestedAt)), ENT_QUOTES, 'UTF-8')
            : '-',
        'actions' => '<a href="complaint_details.php?id=' . $encodedId . '&from=approval" class="btn-complaint-action" title="View"><i class="bi bi-eye"></i></a>',
    ];
}

dt_json_response($req['draw'], $recordsTotal, $recordsFiltered, $data);

function complaint_closure_approval_dt_bind(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue(
            $key,
            $value,
            is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR
        );
    }
}
