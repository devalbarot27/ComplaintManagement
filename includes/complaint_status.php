<?php

require_once __DIR__ . '/admin_access_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/sales_coordinator_access_helpers.php';

/** Complaint status IDs */
const COMPLAINT_STATUS_OPEN = 1;
const COMPLAINT_STATUS_IN_PROGRESS = 2;
const COMPLAINT_STATUS_PENDING_HO = 3;
const COMPLAINT_STATUS_REOPEN = 4;
const COMPLAINT_STATUS_RESOLVED = 5;

function complaint_status_map(): array
{
    return [
        COMPLAINT_STATUS_OPEN => 'Open',
        COMPLAINT_STATUS_IN_PROGRESS => 'In Progress',
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
 * Complaint Entry visibility: System Admin, Management, and CCS Admin see all; others see own or assigned records.
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

    if ($userId !== null && $userId > 0) {
        return [
            'where' => 'deleted_at IS NULL AND (
                username = :username
                OR EXISTS (
                    SELECT 1
                    FROM complaint_assignments ca
                    WHERE ca.complaint_id = complaints.id
                      AND ca.assigned_to = :user_id
                )
            )',
            'params' => [
                ':username' => $username,
                ':user_id' => $userId,
            ],
        ];
    }

    return [
        'where' => 'deleted_at IS NULL AND username = :username',
        'params' => [
            ':username' => $username,
        ],
    ];
}

function complaint_status_counts(PDO $conn, bool $assignedOnly = false, string $username = ''): array
{
    $counts = [
        'open' => 0,
        'in_progress' => 0,
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

    foreach (complaint_status_map() as $id => $label) {
        $labelLower = strtolower($label);

        if (
            $search === $labelLower
            || str_contains($labelLower, $search)
            || str_contains($search, $labelLower)
        ) {
            $matched[] = (int) $id;
        }
    }

    if (preg_match('/\bopen\b/', $search) && !preg_match('/\bre[\s-]?open\b/', $search)) {
        $matched[] = COMPLAINT_STATUS_OPEN;
    }

    if (preg_match('/\b(in[\s-]?progress|progress)\b/', $search)) {
        $matched[] = COMPLAINT_STATUS_IN_PROGRESS;
    }

    if (preg_match('/\b(pending[\s-]?with[\s-]?ho|pending|ho)\b/', $search)) {
        $matched[] = COMPLAINT_STATUS_PENDING_HO;
    }

    if (preg_match('/\b(re[\s-]?open|reopen)\b/', $search)) {
        $matched[] = COMPLAINT_STATUS_REOPEN;
    }

    if (preg_match('/\b(resolved|closed)\b/', $search)) {
        $matched[] = COMPLAINT_STATUS_RESOLVED;
    }

    return array_values(array_unique($matched));
}