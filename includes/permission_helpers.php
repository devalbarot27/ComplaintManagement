<?php

require_once __DIR__ . '/rbac_helpers.php';

function permission_from_post(array $post): array
{
    return [
        'module_id' => trim((string) ($post['module_id'] ?? '')),
        'permission_name' => trim((string) ($post['permission_name'] ?? '')),
        'permission_slug' => strtolower(trim((string) ($post['permission_slug'] ?? ''))),
        'description' => trim((string) ($post['description'] ?? '')),
        'status' => 'active',
    ];
}

function permission_validate(array $data): ?string
{
    if ($data['module_id'] === '' || (int) $data['module_id'] <= 0) {
        return 'Module is required.';
    }

    if ($data['permission_name'] === '') {
        return 'Permission Name is required.';
    }

    if (strlen($data['permission_name']) > 100) {
        return 'Permission Name cannot exceed 100 characters.';
    }

    if ($error = rbac_validate_slug($data['permission_slug'])) {
        return str_replace('Slug', 'Permission Slug', $error);
    }

    return null;
}

function permission_slug_exists(PDO $conn, int $moduleId, string $permissionSlug, int $excludeId = 0): bool
{
    $sql = '
        SELECT id
        FROM permissions
        WHERE module_id = :module_id
          AND LOWER(TRIM(permission_slug)) = LOWER(TRIM(:permission_slug))
          AND deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':module_id', $moduleId, PDO::PARAM_INT);
    $stmt->bindValue(':permission_slug', $permissionSlug);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function permission_search_filter(string $searchValue): array
{
    return rbac_search_filter($searchValue, ['permission_name', 'permission_slug', 'description'], function ($search) {
        return [
            'sql' => 'module_id IN (
                SELECT id FROM modules
                WHERE deleted_at IS NULL
                  AND (module_name ILIKE :module_search OR module_slug ILIKE :module_search)
            )',
            'params' => [':module_search' => '%' . $search . '%'],
        ];
    });
}

function permission_get_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare('
        SELECT p.*, m.module_name, m.module_slug
        FROM permissions p
        INNER JOIN modules m ON m.id = p.module_id AND m.deleted_at IS NULL
        WHERE p.id = :id
          AND p.deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function permission_get_module_name(PDO $conn, int $moduleId): string
{
    $stmt = $conn->prepare('
        SELECT module_name
        FROM modules
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $moduleId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? (string) $row['module_name'] : '-';
}

function permission_entry_actions(int $id): string
{
    $encodedId = base64_encode((string) $id);

    return '
        <div class="d-flex gap-1">
            <a href="permission_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-permission-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_permission.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this permission?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function permission_insert(PDO $conn, array $data, string $createdBy): void
{
    $stmt = $conn->prepare('
        INSERT INTO permissions (
            module_id, permission_name, permission_slug, description, status, created_by, created_at
        ) VALUES (
            :module_id, :permission_name, :permission_slug, :description, :status, :created_by, CURRENT_TIMESTAMP
        )
    ');
    $stmt->bindValue(':module_id', (int) $data['module_id'], PDO::PARAM_INT);
    $stmt->bindValue(':permission_name', $data['permission_name']);
    $stmt->bindValue(':permission_slug', $data['permission_slug']);
    $stmt->bindValue(':description', $data['description'] !== '' ? $data['description'] : null);
    $stmt->bindValue(':status', $data['status']);
    $stmt->bindValue(':created_by', $createdBy);
    $stmt->execute();
}

function permission_update(PDO $conn, int $id, array $data): void
{
    $stmt = $conn->prepare('
        UPDATE permissions SET
            module_id = :module_id,
            permission_name = :permission_name,
            permission_slug = :permission_slug,
            description = :description,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':module_id', (int) $data['module_id'], PDO::PARAM_INT);
    $stmt->bindValue(':permission_name', $data['permission_name']);
    $stmt->bindValue(':permission_slug', $data['permission_slug']);
    $stmt->bindValue(':description', $data['description'] !== '' ? $data['description'] : null);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function permission_soft_delete(PDO $conn, int $id): void
{
    $stmt = $conn->prepare('
        UPDATE permissions
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $conn->prepare('
        UPDATE role_permissions
        SET deleted_at = CURRENT_TIMESTAMP
        WHERE permission_id = :permission_id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':permission_id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function permission_get_by_module_grouped(PDO $conn): array
{
    $stmt = $conn->query("
        SELECT
            m.id AS module_id,
            m.module_name,
            m.module_slug,
            p.id AS permission_id,
            p.permission_name,
            p.permission_slug,
            p.status
        FROM modules m
        LEFT JOIN permissions p
            ON p.module_id = m.id
           AND p.deleted_at IS NULL
           AND p.status = 'active'
        WHERE m.deleted_at IS NULL
          AND m.status = 'active'
        ORDER BY m.module_name ASC, p.permission_name ASC
    ");

    $grouped = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $moduleId = (int) $row['module_id'];
        if (!isset($grouped[$moduleId])) {
            $grouped[$moduleId] = [
                'module_id' => $moduleId,
                'module_name' => $row['module_name'],
                'module_slug' => $row['module_slug'],
                'permissions' => [],
            ];
        }

        if (!empty($row['permission_id'])) {
            $grouped[$moduleId]['permissions'][] = [
                'id' => (int) $row['permission_id'],
                'permission_name' => $row['permission_name'],
                'permission_slug' => $row['permission_slug'],
            ];
        }
    }

    return array_values($grouped);
}
