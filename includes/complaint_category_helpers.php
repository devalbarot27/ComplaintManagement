<?php

require_once __DIR__ . '/rbac_helpers.php';
require_once __DIR__ . '/user_helpers.php';

function complaint_category_from_post(array $post): array
{
    return [
        'name' => trim((string) ($post['name'] ?? '')),
        'status' => strtolower(trim((string) ($post['status'] ?? 'active'))),
    ];
}

function complaint_category_validate(array $data): ?string
{
    if ($data['name'] === '') {
        return 'Category name is required.';
    }

    if (strlen($data['name']) > 100) {
        return 'Category name cannot exceed 100 characters.';
    }

    if ($error = rbac_validate_status($data['status'])) {
        return $error;
    }

    return null;
}

function complaint_category_name_exists(PDO $conn, string $name, int $excludeId = 0): bool
{
    $sql = '
        SELECT id
        FROM complaint_categories
        WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name))
          AND deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':name', $name);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function complaint_category_search_filter(string $searchValue): array
{
    return rbac_search_filter($searchValue, ['name']);
}

function complaint_category_get_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare('
        SELECT cc.*, um.username AS created_by_username, um.name AS created_by_name
        FROM complaint_categories cc
        LEFT JOIN user_master um ON um.id = cc.created_by AND um.deleted_at IS NULL
        WHERE cc.id = :id
          AND cc.deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function complaint_category_entry_actions(int $id): string
{
    $encodedId = base64_encode((string) $id);

    return '
        <div class="d-flex gap-1">
            <a href="complaint_category_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-complaint-category-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_complaint_category.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this complaint category?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function complaint_category_insert(PDO $conn, array $data, ?int $createdBy): void
{
    $stmt = $conn->prepare('
        INSERT INTO complaint_categories (name, status, created_by, created_at)
        VALUES (:name, :status, :created_by, CURRENT_TIMESTAMP)
    ');
    $stmt->bindValue(':name', $data['name']);
    $stmt->bindValue(':status', $data['status']);
    if ($createdBy === null) {
        $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    }
    $stmt->execute();
}

function complaint_category_update(PDO $conn, int $id, array $data): void
{
    $stmt = $conn->prepare('
        UPDATE complaint_categories SET
            name = :name,
            status = :status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':name', $data['name']);
    $stmt->bindValue(':status', $data['status']);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function complaint_category_soft_delete(PDO $conn, int $id): void
{
    $stmt = $conn->prepare('
        UPDATE complaint_categories
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function complaint_category_get_active_options(PDO $conn): array
{
    $stmt = $conn->query("
        SELECT id, name
        FROM complaint_categories
        WHERE deleted_at IS NULL
          AND status = 'active'
        ORDER BY created_at DESC, id DESC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function complaint_category_resolve_for_complaint(PDO $conn, int $categoryId): ?array
{
    if ($categoryId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT id, name
        FROM complaint_categories
        WHERE id = :id
          AND deleted_at IS NULL
          AND status = 'active'
        LIMIT 1
    ");
    $stmt->bindValue(':id', $categoryId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function complaint_category_render_options(array $categories): string
{
    $html = '<option value=""></option>';

    foreach ($categories as $category) {
        $id = (int) $category['id'];
        $name = htmlspecialchars((string) $category['name'], ENT_QUOTES, 'UTF-8');
        $html .= '<option value="' . $id . '" data-name="' . $name . '">' . $name . '</option>';
    }

    return $html;
}

function complaint_category_display_name(?array $complaint): string
{
    $name = trim((string) ($complaint['complaint_category_name'] ?? ''));

    return $name !== '' ? $name : '-';
}

function complaint_category_created_by_label(array $record): string
{
    $userId = (int) ($record['created_by'] ?? 0);
    if ($userId <= 0) {
        return '-';
    }

    $username = trim((string) ($record['created_by_username'] ?? ''));
    $name = trim((string) ($record['created_by_name'] ?? ''));

    if ($username !== '' && $name !== '') {
        return $name . ' (' . $username . ')';
    }

    return (string) $userId;
}
