<?php

require_once __DIR__ . '/admin_access_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';

/**
 * @return array<int, int>
 */
function sales_coordinator_assigned_role_ids(): array
{
    return [
        DEALER_USER_ROLE,
        DEALER_ENGINEER_USER_ROLE,
        ELGI_ENGINEER_USER_ROLE,
    ];
}

function sales_coordinator_assigned_roles_sql_in(): string
{
    return implode(', ', array_map('intval', sales_coordinator_assigned_role_ids()));
}

function sales_coordinator_scope_user_id(PDO $conn): ?int
{
    if (!is_sales_coordinator_user()) {
        return null;
    }

    $userId = current_user_id($conn);

    return ($userId !== null && $userId > 0) ? $userId : null;
}

/**
 * @return array<string, int>
 */
function sales_coordinator_scope_params(PDO $conn): array
{
    $userId = sales_coordinator_scope_user_id($conn);
    if ($userId === null) {
        return [];
    }

    return [':sales_coordinator_id' => $userId];
}

function sales_coordinator_assigned_usernames_in_sql(): string
{
    return '
        SELECT TRIM(um_sc.username)
        FROM user_master um_sc
        WHERE um_sc.deleted_at IS NULL
          AND um_sc.sales_coordinator_id = :sales_coordinator_id
          AND um_sc.role IN (' . sales_coordinator_assigned_roles_sql_in() . ')
    ';
}

function sales_coordinator_assigned_user_ids_in_sql(): string
{
    return '
        SELECT um_sc.id
        FROM user_master um_sc
        WHERE um_sc.deleted_at IS NULL
          AND um_sc.sales_coordinator_id = :sales_coordinator_id
          AND um_sc.role IN (' . sales_coordinator_assigned_roles_sql_in() . ')
    ';
}

function sales_coordinator_assigned_display_names_in_sql(): string
{
    return '
        SELECT COALESCE(NULLIF(TRIM(um_sc.name), \'\'), TRIM(um_sc.username))
        FROM user_master um_sc
        WHERE um_sc.deleted_at IS NULL
          AND um_sc.sales_coordinator_id = :sales_coordinator_id
          AND um_sc.role IN (' . sales_coordinator_assigned_roles_sql_in() . ')
    ';
}

function sales_coordinator_username_scope_where(string $usernameColumn = 'username'): string
{
    return 'TRIM(' . $usernameColumn . ') IN (' . sales_coordinator_assigned_usernames_in_sql() . ')';
}

/**
 * @return array{where: string, params: array<string, mixed>}
 */
function sales_coordinator_after_market_list_scope(PDO $conn, string $usernameColumn = 'username'): array
{
    $params = sales_coordinator_scope_params($conn);
    if ($params === []) {
        return [
            'where' => '1 = 0',
            'params' => [],
        ];
    }

    return [
        'where' => 'deleted_at IS NULL AND ' . sales_coordinator_username_scope_where($usernameColumn),
        'params' => $params,
    ];
}

/**
 * @return array{where: string, params: array<string, mixed>}
 */
function sales_coordinator_complaint_entry_list_scope(PDO $conn): array
{
    $params = sales_coordinator_scope_params($conn);
    if ($params === []) {
        return [
            'where' => '1 = 0',
            'params' => [],
        ];
    }

    return [
        'where' => 'deleted_at IS NULL AND (
            ' . sales_coordinator_username_scope_where('username') . '
            OR EXISTS (
                SELECT 1
                FROM complaint_assignments ca
                WHERE ca.complaint_id = complaints.id
                  AND ca.assigned_to IN (' . sales_coordinator_assigned_user_ids_in_sql() . ')
            )
        )',
        'params' => $params,
    ];
}

function sales_coordinator_complaint_assigned_extra_where(): string
{
    return '(
        ca.assigned_to IN (' . sales_coordinator_assigned_user_ids_in_sql() . ')
        OR TRIM(ca.assign_complaint) IN (' . sales_coordinator_assigned_display_names_in_sql() . ')
    )';
}

function complaint_user_can_access_entry_complaint(PDO $conn, int $complaintId): bool
{
    if ($complaintId <= 0) {
        return false;
    }

    $scope = complaint_entry_list_scope($conn);
    $stmt = $conn->prepare("
        SELECT id
        FROM complaints
        WHERE id = :id
          AND {$scope['where']}
        LIMIT 1
    ");

    foreach ($scope['params'] as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}