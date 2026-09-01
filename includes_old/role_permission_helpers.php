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
