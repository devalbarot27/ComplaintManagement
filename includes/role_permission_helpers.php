<?php

require_once __DIR__ . '/permission_helpers.php';

function role_permission_get_assigned_ids(PDO $conn, int $roleId): array
{
    $stmt = $conn->prepare('
        SELECT permission_id
        FROM role_permissions
        WHERE role_id = :role_id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
    $stmt->execute();

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function role_permission_save(PDO $conn, int $roleId, array $permissionIds, string $createdBy): void
{
    $permissionIds = array_values(array_unique(array_filter(array_map('intval', $permissionIds))));

    $existingStmt = $conn->prepare('
        SELECT id, permission_id, deleted_at
        FROM role_permissions
        WHERE role_id = :role_id
    ');
    $existingStmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
    $existingStmt->execute();

    $existing = [];
    foreach ($existingStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[(int) $row['permission_id']] = $row;
    }

    foreach ($permissionIds as $permissionId) {
        if (isset($existing[$permissionId])) {
            if ($existing[$permissionId]['deleted_at'] !== null) {
                $stmt = $conn->prepare('
                    UPDATE role_permissions
                    SET deleted_at = NULL,
                        created_by = :created_by,
                        created_at = CURRENT_TIMESTAMP
                    WHERE id = :id
                ');
                $stmt->bindValue(':created_by', $createdBy);
                $stmt->bindValue(':id', (int) $existing[$permissionId]['id'], PDO::PARAM_INT);
                $stmt->execute();
            }
            continue;
        }

        $stmt = $conn->prepare('
            INSERT INTO role_permissions (role_id, permission_id, created_by, created_at)
            VALUES (:role_id, :permission_id, :created_by, CURRENT_TIMESTAMP)
        ');
        $stmt->bindValue(':role_id', $roleId, PDO::PARAM_INT);
        $stmt->bindValue(':permission_id', $permissionId, PDO::PARAM_INT);
        $stmt->bindValue(':created_by', $createdBy);
        $stmt->execute();
    }

    foreach ($existing as $permissionId => $row) {
        if (!in_array($permissionId, $permissionIds, true) && $row['deleted_at'] === null) {
            $stmt = $conn->prepare('
                UPDATE role_permissions
                SET deleted_at = CURRENT_TIMESTAMP
                WHERE id = :id
            ');
            $stmt->bindValue(':id', (int) $row['id'], PDO::PARAM_INT);
            $stmt->execute();
        }
    }
}

/**
 * Modules Management cannot be granted on Assign Permissions.
 *
 * @return list<string>
 */
function role_permission_management_hidden_module_slugs(): array
{
    return ['order-booking', 'foc-parts', 'service-claims', 'warranty-claims'];
}

/**
 * @param array<int, array<string, mixed>> $matrix
 * @param list<string> $moduleSlugs
 * @return array<int, array<string, mixed>>
 */
function role_permission_matrix_without_modules(array $matrix, array $moduleSlugs): array
{
    $blocked = [];
    foreach ($moduleSlugs as $slug) {
        $slug = strtolower(trim((string) $slug));
        if ($slug !== '') {
            $blocked[$slug] = true;
        }
    }

    if ($blocked === []) {
        return $matrix;
    }

    $visible = [];
    foreach ($matrix as $module) {
        $slug = strtolower(trim((string) ($module['module_slug'] ?? '')));
        if (!isset($blocked[$slug])) {
            $visible[] = $module;
        }
    }

    return $visible;
}

/**
 * @param list<string> $moduleSlugs
 * @return list<int>
 */
function role_permission_ids_for_module_slugs(PDO $conn, array $moduleSlugs): array
{
    $slugs = [];
    foreach ($moduleSlugs as $slug) {
        $slug = strtolower(trim((string) $slug));
        if ($slug !== '') {
            $slugs[] = $slug;
        }
    }

    if ($slugs === []) {
        return [];
    }

    $placeholders = [];
    foreach ($slugs as $index => $slug) {
        $placeholders[] = ':slug' . $index;
    }

    $stmt = $conn->prepare('
        SELECT p.id
        FROM permissions p
        INNER JOIN modules m ON m.id = p.module_id
        WHERE p.deleted_at IS NULL
          AND m.deleted_at IS NULL
          AND LOWER(TRIM(m.module_slug)) IN (' . implode(', ', $placeholders) . ')
    ');
    foreach ($slugs as $index => $slug) {
        $stmt->bindValue(':slug' . $index, $slug);
    }
    $stmt->execute();

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function role_permission_matrix(PDO $conn, int $roleId): array
{
    $modules = permission_get_by_module_grouped($conn);
    $assignedIds = role_permission_get_assigned_ids($conn, $roleId);
    $assignedLookup = array_fill_keys($assignedIds, true);

    foreach ($modules as &$module) {
        foreach ($module['permissions'] as &$permission) {
            $permission['assigned'] = isset($assignedLookup[$permission['id']]);
        }
        unset($permission);
    }
    unset($module);

    return $modules;
}
