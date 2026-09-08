<?php

require_once __DIR__ . '/module_helpers.php';
require_once __DIR__ . '/permission_helpers.php';

/**
 * Ensure an RBAC module exists with default view/add/edit/delete permissions.
 * Access is granted only via Assign Permissions (role_permissions).
 */
function rbac_ensure_module_with_defaults(
    PDO $conn,
    string $moduleName,
    string $moduleSlug,
    string $description = '',
    int $ordering = 200
): int {
    static $ensured = [];

    $moduleSlug = strtolower(trim($moduleSlug));
    if ($moduleSlug === '') {
        return 0;
    }

    if (isset($ensured[$moduleSlug])) {
        return $ensured[$moduleSlug];
    }

    $stmt = $conn->prepare('
        SELECT id
        FROM modules
        WHERE LOWER(TRIM(module_slug)) = :module_slug
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':module_slug', $moduleSlug);
    $stmt->execute();
    $moduleId = (int) $stmt->fetchColumn();

    $createdBy = 'system';

    if ($moduleId <= 0) {
        $moduleId = module_insert($conn, [
            'module_name' => $moduleName,
            'module_slug' => $moduleSlug,
            'description' => $description,
            'ordering' => $ordering,
            'status' => 'active',
            'create_default_permissions' => true,
        ], $createdBy);
    } else {
        module_create_default_permissions($conn, $moduleId, $createdBy);
    }

    $ensured[$moduleSlug] = $moduleId;

    return $moduleId;
}
