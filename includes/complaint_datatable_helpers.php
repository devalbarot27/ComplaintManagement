<?php

require_once __DIR__ . '/complaint_status.php';
require_once __DIR__ . '/admin_access_helpers.php';
require_once __DIR__ . '/rbac_access_helpers.php';

function dt_parse_request(array $allowedOrderColumns, string $defaultOrderColumn = 'id'): array
{
    $draw = (int) ($_REQUEST['draw'] ?? 1);
    $start = max(0, (int) ($_REQUEST['start'] ?? 0));
    $length = (int) ($_REQUEST['length'] ?? 10);

    if ($length < 1) {
        $length = 10;
    }
    if ($length > 100) {
        $length = 100;
    }

    $searchValue = trim($_REQUEST['search']['value'] ?? '');

    $orderColumnIndex = (int) ($_REQUEST['order'][0]['column'] ?? 0);
    $orderDir = (isset($_REQUEST['order'][0]['dir']) && strtolower($_REQUEST['order'][0]['dir']) === 'asc')
        ? 'ASC'
        : 'DESC';

    $orderColumn = $allowedOrderColumns[$orderColumnIndex] ?? $defaultOrderColumn;
    if (!in_array($orderColumn, $allowedOrderColumns, true)) {
        $orderColumn = $defaultOrderColumn;
    }

    return [
        'draw' => $draw,
        'start' => $start,
        'length' => $length,
        'searchValue' => $searchValue,
        'orderColumn' => $orderColumn,
        'orderDir' => $orderDir,
    ];
}

function dt_json_response(int $draw, int $recordsTotal, int $recordsFiltered, array $data): void
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Build global search SQL including text columns and status label matching.
 */
function dt_complaint_search_filter(string $searchValue, array $textColumns, string $statusColumn): array
{
    $parts = [];
    $params = [':search' => '%' . $searchValue . '%'];

    foreach ($textColumns as $column) {
        $parts[] = "{$column} ILIKE :search";
    }

    $statusIds = dt_match_status_ids($searchValue);

    if (!empty($statusIds)) {
        $statusPlaceholders = [];

        foreach ($statusIds as $index => $statusId) {
            $paramKey = ':status_search_' . $index;
            $statusPlaceholders[] = $paramKey;
            $params[$paramKey] = $statusId;
        }

        $parts[] = $statusColumn . ' IN (' . implode(', ', $statusPlaceholders) . ')';
    }

    return [
        'sql' => '(' . implode(' OR ', $parts) . ')',
        'params' => $params,
    ];
}

function dt_normalize_closure_value(?string $value): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $normalized = trim((string) $value);

    if (strcasecmp($normalized, 'Yes') === 0) {
        return 'Yes';
    }

    if (strcasecmp($normalized, 'No') === 0) {
        return 'No';
    }

    return $normalized;
}

function dt_parse_closure_row_flags(array $row): array
{
    $hasServiceUpdate = !empty($row['has_service_update'])
        && ($row['has_service_update'] === true
            || $row['has_service_update'] === 't'
            || $row['has_service_update'] === '1');

    $hasReassignAfterClosureNo = !empty($row['has_reassign_after_closure_no'])
        && ($row['has_reassign_after_closure_no'] === true
            || $row['has_reassign_after_closure_no'] === 't'
            || $row['has_reassign_after_closure_no'] === '1');

    $latestClosure = dt_normalize_closure_value(isset($row['latest_closure']) ? (string) $row['latest_closure'] : null);

    $hasServiceAfterClosureNo = !empty($row['has_service_after_closure_no'])
        && ($row['has_service_after_closure_no'] === true
            || $row['has_service_after_closure_no'] === 't'
            || $row['has_service_after_closure_no'] === '1');

    $status = (int) ($row['status'] ?? 0);
    $isClosureNo = ($latestClosure === 'No');
    $isClosureYes = ($latestClosure === 'Yes');

    $needsReassign = $isClosureNo && !$hasReassignAfterClosureNo;

    $canClose = $status === COMPLAINT_STATUS_PENDING_HO
        && $hasServiceUpdate
        && !$isClosureYes
        && (
            $latestClosure === null
            || ($isClosureNo && $hasServiceAfterClosureNo)
        );

    return [
        'has_service_update' => $hasServiceUpdate,
        'latest_closure' => $latestClosure,
        'needs_reassign' => $needsReassign,
        'can_close' => $canClose,
    ];
}

function complaint_entry_datatable_order_sql(PDO $conn, string $orderColumn, string $orderDir): string
{
    $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';

    if ($orderColumn === 'id') {
        $orderColumn = 'created_at';
    }

    if ($orderColumn === 'created_at') {
        return "created_at {$orderDir}, id {$orderDir}";
    }

    return "{$orderColumn} {$orderDir}, created_at DESC, id DESC";
}

function complaint_user_can_closure(PDO $conn): bool
{
    return is_ccs_admin_user()
        && rbac_user_can($conn, 'complaint-entry', 'complaint-closure');
}

/**
 * "Added By" column is visible to Management, CCS Admin, and System Admin only.
 */
function complaint_can_view_added_by_column(?PDO $conn = null): bool
{
    if ($conn !== null && (!isset($_SESSION['role']) || current_user_role() <= 0)) {
        admin_ensure_session_role($conn);
    }

    return is_system_admin() || is_management_user() || is_ccs_admin_user();
}

/**
 * Dealer/User display name for the complaint creator (added_by → user_master).
 */
function complaint_added_by_sql_expression(string $complaintAlias = 'complaints', string $userAlias = 'um_added'): string
{
    return "COALESCE(
        NULLIF(TRIM({$userAlias}.name), ''),
        NULLIF(TRIM({$userAlias}.username), ''),
        NULLIF(TRIM({$complaintAlias}.username), ''),
        '-'
    )";
}

function complaint_added_by_join_sql(string $complaintAlias = 'complaints', string $userAlias = 'um_added'): string
{
    return "LEFT JOIN user_master {$userAlias}
        ON {$userAlias}.id = {$complaintAlias}.added_by
       AND {$userAlias}.deleted_at IS NULL";
}

function complaint_entry_require_closure_permission(PDO $conn, string $redirect = 'new_complaint.php'): void
{
    if (!complaint_user_can_closure($conn)) {
        $_SESSION['error_message'] = 'Access denied. Complaint closure is available to CCS Admin users with the required permission only.';
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * @return array{view: bool, add: bool, delete: bool, assign: bool, reassign: bool, closure: bool}
 */
function complaint_entry_action_permissions(PDO $conn): array
{
    return [
        'view' => rbac_user_can($conn, 'complaint-entry', 'view'),
        'add' => rbac_user_can($conn, 'complaint-entry', 'add'),
        'delete' => rbac_user_can($conn, 'complaint-entry', 'delete'),
        'assign' => rbac_user_can($conn, 'complaint-entry', 'assign-complaint'),
        'reassign' => rbac_user_can($conn, 'complaint-entry', 'reassign-complaint'),
        'closure' => complaint_user_can_closure($conn),
    ];
}

function complaint_entry_require_permission(
    PDO $conn,
    string $permissionSlug,
    string $redirect = 'new_complaint.php'
): void {
    if (!rbac_user_can($conn, 'complaint-entry', $permissionSlug)) {
        $_SESSION['error_message'] = 'Access denied. You do not have permission for this action.';
        header('Location: ' . $redirect);
        exit;
    }
}

/**
 * @return array{view: bool, service_update: bool}
 */
function complaint_assigned_action_permissions(PDO $conn): array
{
    return [
        'view' => rbac_user_can($conn, 'assigned-complaint-list', 'view'),
        'service_update' => rbac_user_can($conn, 'assigned-complaint-list', 'service-update'),
    ];
}

function complaint_assigned_require_page_access(PDO $conn): void
{
    if (!rbac_user_can($conn, 'assigned-complaint-list', 'view')) {
        rbac_access_denied_redirect();
    }
}

function complaint_assigned_require_permission(
    PDO $conn,
    string $permissionSlug
): void {
    if (!rbac_user_can($conn, 'assigned-complaint-list', $permissionSlug)) {
        rbac_access_denied_redirect();
    }
}

function complaint_entry_actions(
    int $id,
    int $status,
    bool $needsReassign = false,
    bool $canClose = false,
    array $permissions = []
): string {
    $permissions = array_merge([
        'view' => false,
        'assign' => false,
        'delete' => false,
        'closure' => false,
    ], $permissions);
    $encodedId = base64_encode((string) $id);
    $html = '<div class="complaint-action-cell">';

    if ($permissions['assign'] && $status === COMPLAINT_STATUS_OPEN) {
        $html .= '<button type="button" class="btn-complaint-action manual-assign-btn" '
            . 'data-id="' . $id . '" title="Assign Complaint" data-bs-toggle="modal" data-bs-target="#assignModal">'
            . '<i class="bi bi-person-plus"></i></button>';
    }

    if ($canClose && $permissions['closure']) {
        $html .= '<button type="button" class="btn-complaint-action closure-btn" '
            . 'data-id="' . $id . '" title="Complaint Closure" data-bs-toggle="modal" data-bs-target="#closureModal">'
            . '<i class="bi bi-check2-square"></i></button>';
    }

    if ($permissions['view']) {
        $html .= '<a href="complaint_details.php?id=' . $encodedId . '&from=entry" '
            . 'class="btn-complaint-action" title="View">'
            . '<i class="bi bi-eye"></i></a>';
    }

    if ($permissions['delete'] && $status !== COMPLAINT_STATUS_RESOLVED) {
        $html .= '<a href="delete_complaint.php?id=' . $encodedId . '" '
            . 'class="btn-complaint-action" title="Delete" '
            . 'onclick="return confirm(\'Delete this complaint?\')">'
            . '<i class="bi bi-trash"></i></a>';
    }

    $html .= '</div>';

    return $html;
}

function complaint_assigned_actions(
    int $id,
    int $status,
    bool $hasServiceUpdate = false,
    array $permissions = []
): string {
    $permissions = array_merge([
        'view' => false,
        'service_update' => false,
    ], $permissions);
    $encodedId = base64_encode((string) $id);
    $html = '<div class="complaint-action-cell">';

    if ($permissions['view']) {
        $html .= '<a href="complaint_details.php?id=' . $encodedId . '&from=list" '
            . 'class="btn-complaint-action" title="View">'
            . '<i class="bi bi-eye"></i></a>';
    }

    if (
        $permissions['service_update']
        && in_array($status, [COMPLAINT_STATUS_IN_PROGRESS, COMPLAINT_STATUS_REOPEN], true)
        && !$hasServiceUpdate
    ) {
        $html .= '<button type="button" class="btn-complaint-action service-update-btn" '
            . 'data-id="' . $id . '" title="Service Update" data-bs-toggle="modal" data-bs-target="#serviceUpdateModal">'
            . '<i class="bi bi-tools"></i></button>';
    }

    $html .= '</div>';

    return $html;
}