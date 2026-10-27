<?php

require_once __DIR__ . '/rbac_helpers.php';
require_once __DIR__ . '/disposable_email_helpers.php';
require_once __DIR__ . '/user_helpers.php';

function outlook_email_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $conn->exec("
        CREATE TABLE IF NOT EXISTS outlook_emails (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            outlook_email VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            created_by VARCHAR(150) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        )
    ");
    $conn->exec("
        CREATE UNIQUE INDEX IF NOT EXISTS outlook_emails_user_id_active_idx
            ON outlook_emails (user_id)
            WHERE deleted_at IS NULL
    ");
    $conn->exec("
        CREATE UNIQUE INDEX IF NOT EXISTS outlook_emails_email_active_idx
            ON outlook_emails (LOWER(TRIM(outlook_email)))
            WHERE deleted_at IS NULL
    ");

    $ensured = true;
}

function outlook_email_from_post(array $post): array
{
    return [
        'user_id' => (int) ($post['user_id'] ?? 0),
        'outlook_email' => trim((string) ($post['outlook_email'] ?? '')),
    ];
}

function outlook_email_user_label(array $user): string
{
    $name = trim((string) ($user['name'] ?? ''));
    $username = trim((string) ($user['username'] ?? ''));

    if ($name !== '' && $username !== '') {
        return $name . ' (' . $username . ')';
    }

    if ($name !== '') {
        return $name;
    }

    return $username !== '' ? $username : '-';
}

function outlook_email_user_options(PDO $conn): array
{
    $stmt = $conn->query('
        SELECT id, username, name
        FROM user_master
        WHERE deleted_at IS NULL
        ORDER BY name ASC, username ASC
    ');

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function outlook_email_user_is_active(PDO $conn, int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }

    $stmt = $conn->prepare('
        SELECT 1
        FROM user_master
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function outlook_email_user_taken(PDO $conn, int $userId, int $excludeId = 0): bool
{
    $sql = '
        SELECT 1
        FROM outlook_emails
        WHERE user_id = :user_id
          AND deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function outlook_email_address_taken(PDO $conn, string $email, int $excludeId = 0): bool
{
    $sql = '
        SELECT 1
        FROM outlook_emails
        WHERE LOWER(TRIM(outlook_email)) = LOWER(TRIM(:outlook_email))
          AND deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':outlook_email', $email);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function outlook_email_validate(PDO $conn, array $data, int $excludeId = 0): ?string
{
    $userId = (int) ($data['user_id'] ?? 0);
    if ($userId <= 0 || !outlook_email_user_is_active($conn, $userId)) {
        return 'User is required.';
    }

    $email = trim((string) ($data['outlook_email'] ?? ''));
    if ($email === '') {
        return 'Outlook email is required.';
    }
    if (strlen($email) > 255) {
        return 'Outlook email cannot exceed 255 characters.';
    }
    if (
        !filter_var($email, FILTER_VALIDATE_EMAIL)
        || !preg_match(user_email_pattern(), $email)
    ) {
        return 'Please enter a valid Outlook email address.';
    }
    if (disposable_email_is_blocked($email)) {
        return disposable_email_blocked_message();
    }

    if (outlook_email_user_taken($conn, $userId, $excludeId)) {
        return 'This user already has an Outlook email address.';
    }
    if (outlook_email_address_taken($conn, $email, $excludeId)) {
        return 'This Outlook email address is already assigned.';
    }

    return null;
}

function outlook_email_get_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare('
        SELECT
            o.id,
            o.user_id,
            o.outlook_email,
            o.created_by,
            o.created_at,
            o.updated_at,
            um.name AS user_name,
            um.username
        FROM outlook_emails o
        LEFT JOIN user_master um ON um.id = o.user_id
        WHERE o.id = :id
          AND o.deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function outlook_email_search_filter(string $searchValue): array
{
    $like = '%' . trim($searchValue) . '%';
    $parts = [
        'um.name ILIKE :search_name',
        'um.username ILIKE :search_username',
        'o.outlook_email ILIKE :search_email',
        'CAST(o.id AS TEXT) ILIKE :search_id',
    ];
    $params = [
        ':search_name' => $like,
        ':search_username' => $like,
        ':search_email' => $like,
        ':search_id' => $like,
    ];

    return [
        'sql' => '(' . implode(' OR ', $parts) . ')',
        'params' => $params,
    ];
}

function outlook_email_entry_actions(int $id): string
{
    $encodedId = base64_encode((string) $id);

    return '
        <div class="d-flex gap-1">
            <a href="outlook_email_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-outlook-email-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_outlook_email.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this Outlook email?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function outlook_email_insert(PDO $conn, array $data, string $createdBy): void
{
    $stmt = $conn->prepare('
        INSERT INTO outlook_emails (user_id, outlook_email, created_by, created_at)
        VALUES (:user_id, :outlook_email, :created_by, CURRENT_TIMESTAMP)
    ');
    $stmt->bindValue(':user_id', (int) $data['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':outlook_email', $data['outlook_email']);
    $stmt->bindValue(':created_by', $createdBy);
    $stmt->execute();
}

function outlook_email_update(PDO $conn, int $id, array $data): void
{
    $stmt = $conn->prepare('
        UPDATE outlook_emails SET
            user_id = :user_id,
            outlook_email = :outlook_email,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':user_id', (int) $data['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':outlook_email', $data['outlook_email']);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function outlook_email_soft_delete(PDO $conn, int $id): void
{
    $stmt = $conn->prepare('
        UPDATE outlook_emails
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}
