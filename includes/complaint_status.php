<?php

require_once __DIR__ . '/admin_access_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/user_helpers.php';
require_once __DIR__ . '/sales_coordinator_access_helpers.php';

/** Complaint status IDs */
const COMPLAINT_STATUS_OPEN = 1;
const COMPLAINT_STATUS_IN_PROGRESS = 2;
const COMPLAINT_STATUS_PENDING_HO = 3;
const COMPLAINT_STATUS_REOPEN = 4;
const COMPLAINT_STATUS_RESOLVED = 5;
const COMPLAINT_STATUS_PENDING = 6;

function complaint_status_map(): array
{
    return [
        COMPLAINT_STATUS_OPEN => 'Open',
        COMPLAINT_STATUS_IN_PROGRESS => 'In Progress',
        COMPLAINT_STATUS_PENDING => 'Pending',
        COMPLAINT_STATUS_PENDING_HO => 'Pending With HO',
        COMPLAINT_STATUS_REOPEN => 'Re-Open',
        COMPLAINT_STATUS_RESOLVED => 'Resolved',
    ];
}

function complaint_status_label(int $status): string
{
    return complaint_status_map()[$status] ?? 'Unknown';
}

function complaint_status_badge_class(int $status): string
{
    $classes = [
        COMPLAINT_STATUS_OPEN => 'border border-dark',
        COMPLAINT_STATUS_IN_PROGRESS => 'border border-dark',
        COMPLAINT_STATUS_PENDING => 'border border-dark',
        COMPLAINT_STATUS_PENDING_HO => 'border border-dark',
        COMPLAINT_STATUS_REOPEN => 'border border-dark',
        COMPLAINT_STATUS_RESOLVED => 'border border-dark',
    ];

    return $classes[$status] ?? 'status-badge--default';
}

function complaint_status_badge(int $status): string
{
    $label = htmlspecialchars(complaint_status_label($status), ENT_QUOTES, 'UTF-8');
    $class = complaint_status_badge_class($status);

    return '<span class="status-badge ' . $class . '">' . $label . '</span>';
}

/**
 * Complaint Entry visibility: System Admin, Management, and CCS Admin see all;
 * L1/L2 approval users also see complaints submitted by associated dealers;
 * others see own or assigned records.
 *
 * @return array{where: string, params: array<string, mixed>}
 */
function complaint_entry_list_scope(PDO $conn): array
{
    if (!isset($_SESSION['role'])) {
        admin_refresh_session_role($conn);
    }

    if (is_system_admin() || is_management_user() || is_ccs_admin_user()) {
        return [
            'where' => 'deleted_at IS NULL',
            'params' => [],
        ];
    }

    if (is_sales_coordinator_user()) {
        return sales_coordinator_complaint_entry_list_scope($conn);
    }

    $username = current_username();
    $userId = current_user_id($conn);
    $whereParts = ['complaints.username = :username'];
    $params = [
        ':username' => $username,
    ];

    if ($userId !== null && $userId > 0) {
        $whereParts[] = 'EXISTS (
                    SELECT 1
                    FROM complaint_assignments ca
                    WHERE ca.complaint_id = complaints.id
                      AND ca.assigned_to = :user_id
                )';
        $params[':user_id'] = $userId;
    }

    if (user_is_associated_dealer_approver($conn)) {
        $whereParts[] = user_associated_dealer_complaint_exists_sql(
            'complaints.username',
            'complaints.added_by',
            'ce'
        );
        $params = array_merge($params, user_associated_dealer_scope_params($conn, 'ce'));
    }

    if ($userId !== null && $userId > 0 && is_elgi_engineer_user()) {
        require_once __DIR__ . '/complaint_closure_approval_helpers.php';
        complaint_closure_approval_ensure_schema($conn);
        $whereParts[] = 'EXISTS (
                    SELECT 1
                    FROM complaint_closure_approvals cca
                    WHERE cca.complaint_id = complaints.id
                      AND cca.approver_user_id = :closure_approver_user_id
                      AND cca.id = (
                          SELECT MAX(cca2.id)
                          FROM complaint_closure_approvals cca2
                          WHERE cca2.complaint_id = complaints.id
                      )
                )';
        $params[':closure_approver_user_id'] = $userId;
    }

    return [
        'where' => 'complaints.deleted_at IS NULL AND (
                ' . implode("\n                OR ", $whereParts) . '
            )',
        'params' => $params,
    ];
}

function complaint_status_counts(PDO $conn, bool $assignedOnly = false, string $username = ''): array
{
    $counts = [
        'open' => 0,
        'in_progress' => 0,
        'pending' => 0,
        'pending_ho' => 0,
        'reopen' => 0,
        'resolved' => 0,
    ];

    if (!isset($_SESSION['role'])) {
        admin_refresh_session_role($conn);
    }

    if ($assignedOnly) {
        require_once __DIR__ . '/complaint_assignment_helpers.php';

        $scope = complaint_assigned_list_scope($conn);
        $sql = "
            SELECT c.status, COUNT(DISTINCT c.id) AS total
            FROM complaints c
            " . complaint_assigned_list_join_sql() . "
            WHERE {$scope['where']}
            GROUP BY c.status
        ";
        $params = $scope['params'];
    } else {
        $scope = complaint_entry_list_scope($conn);
        $sql = "
            SELECT status, COUNT(*) AS total
            FROM complaints
            WHERE {$scope['where']}
            GROUP BY status
        ";
        $params = $scope['params'];
    }

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        switch ((int) $row['status']) {
            case COMPLAINT_STATUS_OPEN:
                $counts['open'] = (int) $row['total'];
                break;
            case COMPLAINT_STATUS_IN_PROGRESS:
                $counts['in_progress'] = (int) $row['total'];
                break;
            case COMPLAINT_STATUS_PENDING:
                $counts['pending'] = (int) $row['total'];
                break;
            case COMPLAINT_STATUS_PENDING_HO:
                $counts['pending_ho'] = (int) $row['total'];
                break;
            case COMPLAINT_STATUS_REOPEN:
                $counts['reopen'] = (int) $row['total'];
                break;
            case COMPLAINT_STATUS_RESOLVED:
                $counts['resolved'] = (int) $row['total'];
                break;
        }
    }

    return $counts;
}

function dt_match_status_ids(string $searchValue): array
{
    $search = strtolower(trim($searchValue));

    if ($search === '') {
        return [];
    }

    $matched = [];
    $isReopenSearch = (bool) preg_match('/\bre[\s-]?open\b|\breopen\b/', $search);
    $isBareOpenSearch = (bool) preg_match('/\bopen\b/', $search) && !$isReopenSearch;
    $isPendingHoSearch = (bool) preg_match('/\bpending[\s-]?with[\s-]?ho\b|\bho\b/', $search);
    $isBarePendingSearch = (bool) preg_match('/\bpending\b/', $search) && !$isPendingHoSearch;

    foreach (complaint_status_map() as $id => $label) {
        $labelLower = strtolower($label);

        // Keep "Open" and "Re-Open" distinct for global search.
        if ((int) $id === COMPLAINT_STATUS_OPEN && $isReopenSearch) {
            continue;
        }
        if ((int) $id === COMPLAINT_STATUS_REOPEN && $isBareOpenSearch) {
            continue;
        }
        if ((int) $id === COMPLAINT_STATUS_PENDING_HO && $isBarePendingSearch) {
            continue;
        }
        if ((int) $id === COMPLAINT_STATUS_PENDING && $isPendingHoSearch) {
            continue;
        }

        if (
            $search === $labelLower
            || str_contains($labelLower, $search)
            || str_contains($search, $labelLower)
        ) {
            $matched[] = (int) $id;
        }
    }

    if ($isBareOpenSearch) {
        $matched[] = COMPLAINT_STATUS_OPEN;
    }

    if (preg_match('/\b(in[\s-]?progress|progress)\b/', $search)) {
        $matched[] = COMPLAINT_STATUS_IN_PROGRESS;
    }

    if ($isPendingHoSearch) {
        $matched[] = COMPLAINT_STATUS_PENDING_HO;
    }

    if ($isBarePendingSearch) {
        $matched[] = COMPLAINT_STATUS_PENDING;
    }

    if ($isReopenSearch) {
        $matched[] = COMPLAINT_STATUS_REOPEN;
    }

    if (preg_match('/\b(resolved|closed)\b/', $search)) {
        $matched[] = COMPLAINT_STATUS_RESOLVED;
    }

    return array_values(array_unique($matched));
}