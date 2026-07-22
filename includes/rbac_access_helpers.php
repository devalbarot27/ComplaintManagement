<?php

require_once __DIR__ . '/admin_access_helpers.php';
require_once __DIR__ . '/user_helpers.php';
require_once __DIR__ . '/role_helpers.php';

function rbac_admin_pages(): array
{
    return [
        'users.php',
        'user_details.php',
        'user_edit.php',
        'delete_user.php',
        'roles.php',
        'role_details.php',
        'delete_role.php',
        'modules.php',
        'module_details.php',
        'delete_module.php',
        'permissions.php',
        'permission_details.php',
        'delete_permission.php',
        'assign_permissions.php',
        'industry_segments.php',
        'industry_segment_details.php',
        'delete_industry_segment.php',
        'warranty_chargeable.php',
        'warranty_chargeable_details.php',
        'delete_warranty_chargeable.php',
        'part_replaced.php',
        'part_replaced_details.php',
        'delete_part_replaced.php',
        'customer_feedback.php',
        'customer_feedback_details.php',
        'delete_customer_feedback.php',
        'reasons.php',
        'reason_details.php',
        'delete_reason.php',
        'complaint_categories.php',
        'complaint_category_details.php',
        'delete_complaint_category.php',
    ];
}

function rbac_page_access_rules(): array
{
    return [
        'dashboard.php' => ['module' => 'dashboard', 'permission' => 'view'],
        'index.php' => ['module' => 'dashboard', 'permission' => 'view'],
        'orderbooking.php' => ['module' => 'order-booking', 'permission' => 'create-order'],
        'order_acknowledgement.php' => ['module' => 'order-acknowledgement', 'permission' => 'list'],
        'pending_order.php' => ['module' => 'pending-order', 'permission' => 'list'],
        'recent_orders.php' => ['module' => 'recent-orders', 'permission' => 'list'],
        'recent_order_details.php' => ['module' => 'recent-orders', 'permission' => 'view'],
        'despatch_details.php' => ['module' => 'despatch-details', 'permission' => 'list'],
        'lr_details.php' => ['module' => 'lr-details', 'permission' => 'list'],
        'installed_base.php' => ['module' => 'installed-base-capture', 'permission' => 'view'],
        'installed_base_details.php' => ['module' => 'installed-base-capture', 'permission' => 'view'],
        'delete_installed_base.php' => ['module' => 'installed-base-capture', 'permission' => 'delete'],
        'service_log.php' => ['module' => 'service-log-capture', 'permission' => 'view'],
        'service_log_details.php' => ['module' => 'service-log-capture', 'permission' => 'view'],
        'delete_service_log.php' => ['module' => 'service-log-capture', 'permission' => 'delete'],
        'spare_parts_consumption.php' => ['module' => 'spare-parts-consumption', 'permission' => 'view'],
        'spare_parts_consumption_details.php' => ['module' => 'spare-parts-consumption', 'permission' => 'view'],
        'delete_spare_parts_consumption.php' => ['module' => 'spare-parts-consumption', 'permission' => 'delete'],
        'new_complaint.php' => ['module' => 'complaint-entry', 'permission' => 'view'],
        'delete_complaint.php' => ['module' => 'complaint-entry', 'permission' => 'delete'],
        'dse_lse_complaint_list.php' => ['module' => 'assigned-complaint-list', 'permission' => 'view'],
        'service_update_complaint.php' => ['module' => 'assigned-complaint-list', 'permission' => 'service-update'],
        'access_denied.php' => null,
        'notifications.php' => null,
    ];
}

function rbac_api_access_rules(): array
{
    return [
        'installed_base_datatable.php' => ['module' => 'installed-base-capture', 'permission' => 'view'],
        'installed_base_get.php' => ['module' => 'installed-base-capture', 'permission' => 'view'],
        'installed_base_link_search.php' => ['module' => 'installed-base-capture', 'permission' => 'view'],
        'installed_base_fab_prefill.php' => ['module' => 'installed-base-capture', 'permission' => 'view'],
        'installed_base_fab_availability.php' => ['module' => 'installed-base-capture', 'permission' => 'view'],
        'service_log_datatable.php' => ['module' => 'service-log-capture', 'permission' => 'view'],
        'service_log_get.php' => ['module' => 'service-log-capture', 'permission' => 'view'],
        'service_log_link_search.php' => ['module' => 'service-log-capture', 'permission' => 'view'],
        'service_log_create.php' => ['module' => 'service-log-capture', 'permission' => 'add'],
        'installed_base_service_log_draft_create.php' => ['module' => 'service-log-capture', 'permission' => 'add'],
        'service_log_next_serial.php' => ['module' => 'service-log-capture', 'permission' => 'add'],
        'installed_base_service_log_prefill.php' => ['module' => 'service-log-capture', 'permission' => 'add'],
        'spare_parts_datatable.php' => ['module' => 'spare-parts-consumption', 'permission' => 'view'],
        'spare_parts_get.php' => ['module' => 'spare-parts-consumption', 'permission' => 'view'],
        'spare_parts_create.php' => ['module' => 'spare-parts-consumption', 'permission' => 'add'],
        'service_log_spare_parts_prefill.php' => ['module' => 'spare-parts-consumption', 'permission' => 'add'],
        'installed_base_spare_parts_prefill.php' => ['module' => 'spare-parts-consumption', 'permission' => 'add'],
        'complaints_datatable.php' => ['module' => 'complaint-entry', 'permission' => 'view'],
        'complaint_fab_prefill.php' => ['module' => 'complaint-entry', 'permission' => 'add'],
        'assigned_complaints_datatable.php' => ['module' => 'assigned-complaint-list', 'permission' => 'view'],
        'complaint_service_log_summary.php' => ['module' => 'assigned-complaint-list', 'permission' => 'service-update'],
        'complaint_service_log_prefill.php' => ['module' => 'assigned-complaint-list', 'permission' => 'service-update'],
        'complaint_service_log_draft_save.php' => ['module' => 'assigned-complaint-list', 'permission' => 'service-update'],
        'service_log_update.php' => ['module' => 'service-log-capture', 'permission' => 'edit'],
    ];
}

function rbac_sidebar_modules(): array
{
    return [
        'dashboard.php' => ['module' => 'dashboard', 'permission' => 'view'],
        'orderbooking.php' => ['module' => 'order-booking', 'permission' => 'create-order'],
        'order_acknowledgement.php' => ['module' => 'order-acknowledgement', 'permission' => 'list'],
        'pending_order.php' => ['module' => 'pending-order', 'permission' => 'list'],
        'recent_orders.php' => ['module' => 'recent-orders', 'permission' => 'list'],
        'despatch_details.php' => ['module' => 'despatch-details', 'permission' => 'list'],
        'lr_details.php' => ['module' => 'lr-details', 'permission' => 'list'],
        'installed_base.php' => ['module' => 'installed-base-capture', 'permission' => 'view'],
        'service_log.php' => ['module' => 'service-log-capture', 'permission' => 'view'],
        'spare_parts_consumption.php' => ['module' => 'spare-parts-consumption', 'permission' => 'view'],
        'new_complaint.php' => ['module' => 'complaint-entry', 'permission' => 'view'],
        'dse_lse_complaint_list.php' => ['module' => 'assigned-complaint-list', 'permission' => 'view'],
    ];
}

function rbac_resolve_role_id(PDO $conn): int
{
    $userRole = current_user_role();
    if ($userRole <= 0) {
        return 0;
    }

    $roleName = user_role_label($conn, $userRole);
    if ($roleName !== 'Unknown') {
        $stmt = $conn->prepare('
            SELECT id
            FROM roles
            WHERE LOWER(TRIM(role_name)) = LOWER(TRIM(:role_name))
              AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->bindValue(':role_name', $roleName);
        $stmt->execute();
        $resolvedId = $stmt->fetchColumn();
        if ($resolvedId !== false) {
            return (int) $resolvedId;
        }
    }

    if (role_get_by_id($conn, $userRole) !== null) {
        return $userRole;
    }

    return $userRole;
}

function rbac_clear_permissions_cache(): void
{
    unset($_SESSION['rbac_permissions']);
}

/**
 * Modules where System Administrator must use assigned role_permissions
 * instead of the global admin bypass.
 */
function rbac_modules_enforcing_role_permissions(): array
{
    return [
        'dashboard',
        'complaint-entry',
        'assigned-complaint-list',
        'installed-base-capture',
        'service-log-capture',
        'spare-parts-consumption',
    ];
}

function rbac_module_enforces_role_permissions(string $moduleSlug): bool
{
    return in_array(strtolower(trim($moduleSlug)), rbac_modules_enforcing_role_permissions(), true);
}

function rbac_load_user_permissions(PDO $conn, bool $enforceRoleAssignments = false): array
{
    if (is_system_admin() && !$enforceRoleAssignments) {
        return ['__all__' => true];
    }

    $roleId = rbac_resolve_role_id($conn);
    $permissions = [];

    if ($roleId > 0) {
        $stmt = $conn->prepare("
            SELECT m.module_slug, p.permission_slug
            FROM role_permissions rp
            INNER JOIN permissions p
                ON p.id = rp.permission_id
               AND p.deleted_at IS NULL
               AND p.status = 'active'
            INNER JOIN modules m
                ON m.id = p.module_id
               AND m.deleted_at IS NULL
               AND m.status = 'active'
            WHERE rp.role_id = :role_id
              AND rp.deleted_at IS NULL
        ");
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->execute();

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $moduleSlug = strtolower(trim((string) $row['module_slug']));
            $permissionSlug = strtolower(trim((string) $row['permission_slug']));
            if ($moduleSlug === '' || $permissionSlug === '') {
                continue;
            }
            if (!isset($permissions[$moduleSlug])) {
                $permissions[$moduleSlug] = [];
            }
            if (!in_array($permissionSlug, $permissions[$moduleSlug], true)) {
                $permissions[$moduleSlug][] = $permissionSlug;
            }
        }
    }

    return $permissions;
}

function rbac_role_has_permission(PDO $conn, string $moduleSlug, string $permissionSlug): bool
{
    $moduleSlug = strtolower(trim($moduleSlug));
    $permissionSlug = strtolower(trim($permissionSlug));

    if ($moduleSlug === '' || $permissionSlug === '') {
        return false;
    }

    admin_ensure_session_role($conn);

    $roleId = rbac_resolve_role_id($conn);
    if ($roleId <= 0) {
        return false;
    }

    $stmt = $conn->prepare("
        SELECT 1
        FROM role_permissions rp
        INNER JOIN permissions p
            ON p.id = rp.permission_id
           AND p.deleted_at IS NULL
           AND p.status = 'active'
           AND LOWER(TRIM(p.permission_slug)) = :permission_slug
        INNER JOIN modules m
            ON m.id = p.module_id
           AND m.deleted_at IS NULL
           AND m.status = 'active'
           AND LOWER(TRIM(m.module_slug)) = :module_slug
        WHERE rp.role_id = :role_id
          AND rp.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
    $stmt->bindValue(':module_slug', $moduleSlug);
    $stmt->bindValue(':permission_slug', $permissionSlug);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function rbac_has_permission(PDO $conn, string $moduleSlug, string $permissionSlug): bool
{
    $moduleSlug = strtolower(trim($moduleSlug));
    $permissionSlug = strtolower(trim($permissionSlug));

    if (rbac_module_enforces_role_permissions($moduleSlug)) {
        return rbac_role_has_permission($conn, $moduleSlug, $permissionSlug);
    }

    // if (is_system_admin()) {
    //     return true;
    // }

    return rbac_role_has_permission($conn, $moduleSlug, $permissionSlug);
}

function rbac_can_view_module(PDO $conn, string $moduleSlug): bool
{
    return rbac_has_permission($conn, $moduleSlug, 'view');
}

function rbac_can_access_menu(PDO $conn, string $menuPage): bool
{
    $modules = rbac_sidebar_modules();
    if (!isset($modules[$menuPage])) {
        return false;
    }

    return rbac_has_permission(
        $conn,
        $modules[$menuPage]['module'],
        $modules[$menuPage]['permission']
    );
}

function rbac_resolve_page_rule(string $page): ?array
{
    if ($page === 'complaint_details.php') {
        $from = trim((string) ($_GET['from'] ?? ''));
        if ($from === 'list') {
            return ['module' => 'assigned-complaint-list', 'permission' => 'view'];
        }

        return ['module' => 'complaint-entry', 'permission' => 'view'];
    }

    $rules = rbac_page_access_rules();

    return $rules[$page] ?? null;
}

function rbac_resolve_api_rule(string $script): ?array
{
    $rules = rbac_api_access_rules();

    return $rules[$script] ?? null;
}

function rbac_access_denied_redirect(): void
{
    header('Location: access_denied.php');
    exit;
}

function rbac_require_page_access(PDO $conn): void
{
    $page = basename($_SERVER['PHP_SELF']);

    if (in_array($page, rbac_admin_pages(), true)) {
        return;
    }

    if ($page === 'access_denied.php') {
        return;
    }

    $rule = rbac_resolve_page_rule($page);
    if ($rule === null) {
        return;
    }

    if (!rbac_has_permission($conn, $rule['module'], $rule['permission'])) {
        rbac_access_denied_redirect();
    }
}

function rbac_require_api_access(PDO $conn): void
{
    if (empty($_SESSION['usr_name'])) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unauthorized.']);
        exit;
    }

    admin_ensure_session_role($conn);

    $script = basename($_SERVER['PHP_SELF']);
    $rule = rbac_resolve_api_rule($script);
    if ($rule === null) {
        return;
    }

    if (!rbac_has_permission($conn, $rule['module'], $rule['permission'])) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Access denied. You do not have permission for this action.']);
        exit;
    }
}

function rbac_user_can(PDO $conn, string $moduleSlug, string $permissionSlug): bool
{
    return rbac_has_permission($conn, $moduleSlug, $permissionSlug);
}