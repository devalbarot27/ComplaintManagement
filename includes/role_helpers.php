<?php

require_once __DIR__ . '/rbac_helpers.php';

function role_from_post(array $post): array
{
    return [
        'role_name' => trim((string) ($post['role_name'] ?? '')),
        'description' => trim((string) ($post['description'] ?? '')),
        'status' => 'active',
    ];
}

function role_validate(array $data): ?string
{
    if ($data['role_name'] === '') {
        return 'Role Name is required.';
    }

    if (strlen($data['role_name']) > 100) {
        return 'Role Name cannot exceed 100 characters.';
    }

    return null;
}

function role_name_exists(PDO $conn, string $roleName, int $excludeId = 0): bool
{
    $sql = '
        SELECT id
        FROM roles
        WHERE LOWER(TRIM(role_name)) = LOWER(TRIM(:role_name))
          AND deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':role_name', $roleName);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function role_search_filter(string $searchValue): array
{
    return rbac_search_filter($searchValue, ['role_name', 'description']);
}

function role_get_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare('
        SELECT *
        FROM roles
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function role_get_all_active(PDO $conn): array
{
    $stmt = $conn->query("
        SELECT id, role_name
        FROM roles
        WHERE deleted_at IS NULL
          AND status = 'active'
        ORDER BY role_name ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function role_entry_actions(int $id): string
{
    $encodedId = base64_encode((string) $id);

    return '
        <div class="d-flex gap-1">
            <a href="role_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-role-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_role.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this role?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function role_insert(PDO $conn, array $data, string $createdBy): void
{
    $stmt = $conn->prepare('
        INSERT INTO roles (role_name, description, status, created_by, created_at)
        VALUES (:role_name, :description, :status, :created_by, CURRENT_TIMESTAMP)
    ');
    $stmt->bindValue(':role_name', $data['role_name']);
    $stmt->bindValue(':description', $data['description'] !== '' ? $data['description'] : null);
    $stmt->bindValue(':status', $data['status']);
    $stmt->bindValue(':created_by', $createdBy);
    $stmt->execute();
}

function role_update(PDO $conn, int $id, array $data): void
{
    $stmt = $conn->prepare('
        UPDATE roles SET
            role_name = :role_name,
            description = :description,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':role_name', $data['role_name']);
    $stmt->bindValue(':description', $data['description'] !== '' ? $data['description'] : null);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function role_soft_delete(PDO $conn, int $id): void
{
    $stmt = $conn->prepare('
        UPDATE roles
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
        WHERE role_id = :role_id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':role_id', $id, PDO::PARAM_INT);
    $stmt->execute();
}
