<?php

require_once __DIR__ . '/admin_access_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/sales_coordinator_access_helpers.php';

function dashboard_is_full_access_role(): bool
{
    return is_system_admin() || is_ccs_admin_user();
}

function dashboard_is_self_scoped_role(): bool
{
    return is_management_user() || is_dealer_user() || is_dealer_engineer_user() || is_elgi_engineer_user();
}

/**
 * @return array{
 *   mode: 'self'|'assigned'|'all'|'none',
 *   username: string,
 *   params: array<string, mixed>
 * }
 */
function dashboard_resolve_view_scope(PDO $conn): array
{
    admin_ensure_session_role($conn);
    $username = current_username();

    if (dashboard_is_full_access_role()) {
        return [
            'mode' => 'all',
            'username' => $username,
            'params' => [],
        ];
    }

    if (is_sales_coordinator_user()) {
        $params = sales_coordinator_scope_params($conn);

        return [
            'mode' => $params === [] ? 'none' : 'assigned',
            'username' => $username,
            'params' => $params,
        ];
    }

    if (dashboard_is_self_scoped_role() && $username !== '') {
        return [
            'mode' => 'self',
            'username' => $username,
            'params' => [':uname' => $username],
        ];
    }

    if ($username !== '') {
        return [
            'mode' => 'self',
            'username' => $username,
            'params' => [':uname' => $username],
        ];
    }

    return [
        'mode' => 'none',
        'username' => $username,
        'params' => [],
    ];
}

function dashboard_scope_mapping_username_sql(array $scope, string $column = 'u.usr_name'): string
{
    switch ($scope['mode']) {
        case 'all':
            return '';
        case 'assigned':
            return 'AND TRIM(' . $column . ') IN (' . sales_coordinator_assigned_usernames_in_sql() . ')';
        case 'self':
            return 'AND TRIM(' . $column . ') = :uname';
        default:
            return 'AND 1 = 0';
    }
}

function dashboard_scope_cuno_sql(array $scope, string $column = 'cuno'): string
{
    switch ($scope['mode']) {
        case 'all':
            return '';
        case 'assigned':
            return 'AND TRIM(' . $column . ') IN (' . sales_coordinator_assigned_usernames_in_sql() . ')';
        case 'self':
            return 'AND TRIM(' . $column . ') = :uname';
        default:
            return 'AND 1 = 0';
    }
}

function dashboard_scope_created_by_sql(array $scope, string $column = 'created_by'): string
{
    switch ($scope['mode']) {
        case 'all':
            return '';
        case 'assigned':
            return 'AND TRIM(' . $column . ') IN (' . sales_coordinator_assigned_usernames_in_sql() . ')';
        case 'self':
            return 'AND TRIM(' . $column . ') = :uname';
        default:
            return 'AND 1 = 0';
    }
}

function dashboard_bind_scope_params(PDOStatement $stmt, array $scope): void
{
    foreach ($scope['params'] as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}

function dashboard_scope_pendingorders_cuno_sql(array $scope, string $column = 'p.cuno'): string
{
    switch ($scope['mode']) {
        case 'all':
            return '';
        case 'assigned':
            return 'AND TRIM(' . $column . ') IN (
                SELECT TRIM(um.customer_number)
                FROM user_master um
                WHERE um.deleted_at IS NULL
                  AND TRIM(COALESCE(um.customer_number, \'\')) <> \'\'
                  AND TRIM(um.username) IN (' . sales_coordinator_assigned_usernames_in_sql() . ')
            )';
        case 'self':
            return 'AND ' . $column . ' = :uname';
        default:
            return 'AND 1 = 0';
    }
}

function dashboard_bind_pendingorders_scope_params(PDOStatement $stmt, array $scope): void
{
    if ($scope['mode'] === 'self') {
        $stmt->bindValue(':uname', dashboard_resolve_customer_code(), PDO::PARAM_STR);
    }

    if ($scope['mode'] === 'assigned') {
        dashboard_bind_scope_params($stmt, $scope);
    }
}

function dashboard_scope_maintdealer_cuno_sql(array $scope, string $column = 'm.cuno'): string
{
    return dashboard_scope_pendingorders_cuno_sql($scope, $column);
}

function dashboard_bind_maintdealer_scope_params(PDOStatement $stmt, array $scope): void
{
    dashboard_bind_pendingorders_scope_params($stmt, $scope);
}


/* plexecom code */
function dashboard_resolve_customer_code(): string
{
    $customerCode = trim((string) ($_SESSION['customer_number_vayu'] ?? ''));

    return $customerCode;
}

function dashboard_scope_plexecom_cuno_sql(array $scope, string $column = 'cuno'): string
{
    switch ($scope['mode']) {
        case 'all':
            return '';
        case 'assigned':
            return 'AND TRIM(' . $column . ') IN (
                SELECT TRIM(um.customer_number)
                FROM user_master um
                WHERE um.deleted_at IS NULL
                  AND TRIM(COALESCE(um.customer_number, \'\')) <> \'\'
                  AND TRIM(um.username) IN (' . sales_coordinator_assigned_usernames_in_sql() . ')
            )';
        case 'self':
            return 'AND ' . $column . ' = :customer_code';
        default:
            return 'AND 1 = 0';
    }
}

function dashboard_bind_plexecom_scope_params(PDOStatement $stmt, array $scope): void
{
    if ($scope['mode'] === 'self') {
        $stmt->bindValue(':customer_code', dashboard_resolve_customer_code(), PDO::PARAM_STR);
    }

    if ($scope['mode'] === 'assigned') {
        dashboard_bind_scope_params($stmt, $scope);
    }
}