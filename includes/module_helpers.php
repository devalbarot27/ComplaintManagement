<?php

require_once __DIR__ . '/rbac_helpers.php';

function module_default_permissions(): array
{
    return [
        ['permission_name' => 'View', 'permission_slug' => 'view', 'description' => 'View records'],
        ['permission_name' => 'Add', 'permission_slug' => 'add', 'description' => 'Add new records'],
        ['permission_name' => 'Edit', 'permission_slug' => 'edit', 'description' => 'Edit existing records'],
        ['permission_name' => 'Delete', 'permission_slug' => 'delete', 'description' => 'Delete records'],
    ];
}

function module_from_post(array $post): array
{
    return [
        'module_name' => trim((string) ($post['module_name'] ?? '')),
        'module_slug' => strtolower(trim((string) ($post['module_slug'] ?? ''))),
        'description' => trim((string) ($post['description'] ?? '')),
        'status' => 'active',
        'create_default_permissions' => !empty($post['create_default_permissions']),
    ];
}

function module_validate(array $data): ?string
{
    if ($data['module_name'] === '') {
        return 'Module Name is required.';
    }

    if (strlen($data['module_name']) > 100) {
        return 'Module Name cannot exceed 100 characters.';
    }

    if ($error = rbac_validate_slug($data['module_slug'])) {
        return str_replace('Slug', 'Module Slug', $error);
    }

    return null;
}

function module_slug_exists(PDO $conn, string $moduleSlug, int $excludeId = 0): bool
{
    $sql = '
        SELECT id
        FROM modules
        WHERE LOWER(TRIM(module_slug)) = LOWER(TRIM(:module_slug))
          AND deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':module_slug', $moduleSlug);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function module_search_filter(string $searchValue): array
{
    return rbac_search_filter($searchValue, ['module_name', 'module_slug', 'description']);
}

function module_get_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare('
        SELECT *
        FROM modules
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function module_get_all_active(PDO $conn): array
{
    $stmt = $conn->query("
        SELECT id, module_name, module_slug
        FROM modules
        WHERE deleted_at IS NULL
          AND status = 'active'
        ORDER BY module_name ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function module_entry_actions(int $id): string
{
    $encodedId = base64_encode((string) $id);

    return '
        <div class="d-flex gap-1">
            <a href="module_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-module-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_module.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this module?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function module_create_default_permissions(PDO $conn, int $moduleId, string $createdBy): void
{
    require_once __DIR__ . '/permission_helpers.php';

    foreach (module_default_permissions() as $permission) {
        if (permission_slug_exists($conn, $moduleId, $permission['permission_slug'])) {
            continue;
        }

        permission_insert($conn, [
            'module_id' => $moduleId,
            'permission_name' => $permission['permission_name'],
            'permission_slug' => $permission['permission_slug'],
            'description' => $permission['description'],
            'status' => 'active',
        ], $createdBy);
    }
}

function module_insert(PDO $conn, array $data, string $createdBy): int
{
    $stmt = $conn->prepare('
        INSERT INTO modules (module_name, module_slug, description, status, created_by, created_at)
        VALUES (:module_name, :module_slug, :description, :status, :created_by, CURRENT_TIMESTAMP)
        RETURNING id
    ');
    $stmt->bindValue(':module_name', $data['module_name']);
    $stmt->bindValue(':module_slug', $data['module_slug']);
    $stmt->bindValue(':description', $data['description'] !== '' ? $data['description'] : null);
    $stmt->bindValue(':status', $data['status']);
    $stmt->bindValue(':created_by', $createdBy);
    $stmt->execute();

    $moduleId = (int) $stmt->fetchColumn();

    if (!empty($data['create_default_permissions'])) {
        module_create_default_permissions($conn, $moduleId, $createdBy);
    }

    return $moduleId;
}

function module_update(PDO $conn, int $id, array $data): void
{
    $stmt = $conn->prepare('
        UPDATE modules SET
            module_name = :module_name,
            module_slug = :module_slug,
            description = :description,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':module_name', $data['module_name']);
    $stmt->bindValue(':module_slug', $data['module_slug']);
    $stmt->bindValue(':description', $data['description'] !== '' ? $data['description'] : null);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function module_soft_delete(PDO $conn, int $id): void
{
    $stmt = $conn->prepare('
        UPDATE modules
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $conn->prepare('
        UPDATE permissions
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE module_id = :module_id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':module_id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $conn->prepare('
        UPDATE role_permissions rp
        SET deleted_at = CURRENT_TIMESTAMP
        FROM permissions p
        WHERE rp.permission_id = p.id
          AND p.module_id = :module_id
          AND rp.deleted_at IS NULL
    ');
    $stmt->bindValue(':module_id', $id, PDO::PARAM_INT);
    $stmt->execute();
}
