<?php

require_once __DIR__ . '/admin_access_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/rbac_access_helpers.php';

/**
 * List scope for after-market modules (installed base, service log, spare parts).
 * System Administrator sees all; others see own records (username).
 *
 * @return array{where: string, params: array<string, mixed>}
 */
function after_market_list_scope(PDO $conn): array
{
    if (!isset($_SESSION['role'])) {
        admin_refresh_session_role($conn);
    }

    if (is_system_admin()) {
        return [
            'where' => 'deleted_at IS NULL',
            'params' => [],
        ];
    }

    return [
        'where' => 'deleted_at IS NULL AND username = :username',
        'params' => [
            ':username' => current_username(),
        ],
    ];
}

function after_market_user_can_access_record(PDO $conn, string $table, int $id): bool
{
    $allowedTables = ['installed_base', 'service_logs', 'spare_parts_consumption'];
    if ($id <= 0 || !in_array($table, $allowedTables, true)) {
        return false;
    }

    $scope = after_market_list_scope($conn);
    $stmt = $conn->prepare("
        SELECT id
        FROM {$table}
        WHERE id = :id
          AND {$scope['where']}
        LIMIT 1
    ");

    foreach ($scope['params'] as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function installed_base_action_permissions(PDO $conn): array
{
    return [
        'view' => rbac_user_can($conn, 'installed-base-capture', 'view'),
        'add' => rbac_user_can($conn, 'installed-base-capture', 'add'),
        'edit' => rbac_user_can($conn, 'installed-base-capture', 'edit'),
        'delete' => rbac_user_can($conn, 'installed-base-capture', 'delete'),
        'service_log_add' => rbac_user_can($conn, 'service-log-capture', 'add'),
        'spare_parts_add' => rbac_user_can($conn, 'spare-parts-consumption', 'add'),
    ];
}

function service_log_action_permissions(PDO $conn): array
{
    return [
        'view' => rbac_user_can($conn, 'service-log-capture', 'view'),
        'add' => rbac_user_can($conn, 'service-log-capture', 'add'),
        'edit' => rbac_user_can($conn, 'service-log-capture', 'edit'),
        'delete' => rbac_user_can($conn, 'service-log-capture', 'delete'),
        'spare_parts_add' => rbac_user_can($conn, 'spare-parts-consumption', 'add'),
    ];
}

function spare_parts_action_permissions(PDO $conn): array
{
    return [
        'view' => rbac_user_can($conn, 'spare-parts-consumption', 'view'),
        'add' => rbac_user_can($conn, 'spare-parts-consumption', 'add'),
        'edit' => rbac_user_can($conn, 'spare-parts-consumption', 'edit'),
        'delete' => rbac_user_can($conn, 'spare-parts-consumption', 'delete'),
    ];
}

function installed_base_require_permission(
    PDO $conn,
    string $permissionSlug,
    string $redirect = 'installed_base.php'
): void {
    if (!rbac_user_can($conn, 'installed-base-capture', $permissionSlug)) {
        $_SESSION['error_message'] = 'Access denied. You do not have permission for this action.';
        header('Location: ' . $redirect);
        exit;
    }
}

function service_log_require_permission(
    PDO $conn,
    string $permissionSlug,
    string $redirect = 'service_log.php'
): void {
    if (!rbac_user_can($conn, 'service-log-capture', $permissionSlug)) {
        $_SESSION['error_message'] = 'Access denied. You do not have permission for this action.';
        header('Location: ' . $redirect);
        exit;
    }
}

function spare_parts_require_permission(
    PDO $conn,
    string $permissionSlug,
    string $redirect = 'spare_parts_consumption.php'
): void {
    if (!rbac_user_can($conn, 'spare-parts-consumption', $permissionSlug)) {
        $_SESSION['error_message'] = 'Access denied. You do not have permission for this action.';
        header('Location: ' . $redirect);
        exit;
    }
}
