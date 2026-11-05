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
    $conn->exec("
        ALTER TABLE outlook_emails
        ADD COLUMN IF NOT EXISTS category VARCHAR(100) NULL
    ");
    $conn->exec("
        ALTER TABLE outlook_emails
        ADD COLUMN IF NOT EXISTS group_id INTEGER NULL
    ");
    $conn->exec('ALTER TABLE outlook_emails ALTER COLUMN user_id DROP NOT NULL');

    $ensured = true;
}

function outlook_email_from_post(array $post): array
{
    $rawEmails = $post['emails'] ?? [];
    if (!is_array($rawEmails)) {
        $rawEmails = [$rawEmails];
    }

    $emails = [];
    foreach ($rawEmails as $email) {
        $emails[] = trim((string) $email);
    }

    return [
        'category' => trim((string) ($post['category'] ?? '')),
        'emails' => $emails,
    ];
}

function outlook_email_category_is_valid(string $category): bool
{
    return (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9 .,&\'\/()-]{0,99}$/', $category);
}

function outlook_email_user_label(array $user): string
{
    $name = trim((string) ($user['name'] ?? ''));
    $username = trim((string) ($user['username'] ?? ''));

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
        $sql .= ' AND id <> :exclude_id AND COALESCE(group_id, 0) <> :exclude_group';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':outlook_email', $email);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
        $stmt->bindValue(':exclude_group', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

/**
 * @return array<string, array<int, string>>
 */
function outlook_email_configuration_errors(PDO $conn, array $data, int $excludeId = 0): array
{
    $errors = [];
    $category = trim((string) ($data['category'] ?? ''));
    if ($category === '') {
        $errors['category'] = ['Category is required.'];
    } elseif (!outlook_email_category_is_valid($category)) {
        $errors['category'] = ['Enter a valid category.'];
    }

    $emails = $data['emails'] ?? [];
    if (!is_array($emails)) {
        $emails = [];
    }

    $seen = [];
    $filled = 0;
    foreach ($emails as $index => $email) {
        $email = trim((string) $email);
        $field = 'emails_' . (int) $index;
        if ($email === '') {
            $errors[$field] = ['Email is required.'];
            continue;
        }
        $filled++;
        if (strlen($email) > 255) {
            $errors[$field] = ['Email cannot exceed 255 characters.'];
            continue;
        }
        if (
            !filter_var($email, FILTER_VALIDATE_EMAIL)
            || !preg_match(user_email_pattern(), $email)
        ) {
            $errors[$field] = ['Enter a valid email address.'];
            continue;
        }
        if (disposable_email_is_blocked($email)) {
            $errors[$field] = [disposable_email_blocked_message()];
            continue;
        }
        $key = strtolower($email);
        if (isset($seen[$key])) {
            $errors[$field] = ['This email address is already entered.'];
            continue;
        }
        $seen[$key] = true;
        if (outlook_email_address_taken($conn, $email, $excludeId)) {
            $errors[$field] = ['This email address is already saved.'];
        }
    }

    if ($filled === 0 && !isset($errors['emails_0'])) {
        $errors['emails_0'] = ['Email is required.'];
    }
    if ($filled > 20) {
        $errors['emails'] = ['You can add up to 20 email addresses.'];
    }

    return $errors;
}

function outlook_email_validate(PDO $conn, array $data, int $excludeId = 0): ?string
{
    $errors = outlook_email_configuration_errors($conn, $data, $excludeId);
    if ($errors === []) {
        return null;
    }

    $first = reset($errors);

    return is_array($first) ? (string) ($first[0] ?? 'Unable to save this configuration.') : 'Unable to save this configuration.';
}

function outlook_email_configuration_rows(PDO $conn, int $groupId): array
{
    if ($groupId <= 0) {
        return [];
    }

    $stmt = $conn->prepare('
        SELECT
            o.id,
            o.group_id,
            o.category,
            o.outlook_email,
            o.created_by,
            o.created_at,
            o.updated_at,
            creator.name AS created_by_name
        FROM outlook_emails o
        LEFT JOIN user_master creator
            ON LOWER(TRIM(creator.username)) = LOWER(TRIM(o.created_by))
           AND creator.deleted_at IS NULL
        WHERE o.deleted_at IS NULL
          AND o.user_id IS NULL
          AND o.group_id = :group_id
        ORDER BY o.id ASC
    ');
    $stmt->bindValue(':group_id', $groupId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function outlook_email_configuration_from_rows(array $rows): ?array
{
    if ($rows === []) {
        return null;
    }

    $first = $rows[0];
    $emails = [];
    foreach ($rows as $row) {
        $email = trim((string) ($row['outlook_email'] ?? ''));
        if ($email !== '') {
            $emails[] = $email;
        }
    }

    return [
        'id' => (int) ($first['group_id'] ?? $first['id'] ?? 0),
        'category' => trim((string) ($first['category'] ?? '')),
        'emails' => $emails,
        'outlook_email' => $emails === [] ? '' : implode(', ', $emails),
        'created_by' => (string) ($first['created_by'] ?? ''),
        'created_by_name' => (string) ($first['created_by_name'] ?? ''),
        'created_at' => $first['created_at'] ?? null,
        'updated_at' => $rows[count($rows) - 1]['updated_at'] ?? ($first['updated_at'] ?? null),
    ];
}

function outlook_email_get_by_id(PDO $conn, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $lookup = $conn->prepare('
        SELECT id, group_id, user_id
        FROM outlook_emails
        WHERE id = :id
        LIMIT 1
    ');
    $lookup->bindValue(':id', $id, PDO::PARAM_INT);
    $lookup->execute();
    $row = $lookup->fetch(PDO::FETCH_ASSOC);
    if ($row === false || $row['user_id'] !== null) {
        return null;
    }

    $groupId = (int) ($row['group_id'] ?? 0);
    if ($groupId <= 0) {
        $groupId = (int) $row['id'];
    }

    return outlook_email_configuration_from_rows(outlook_email_configuration_rows($conn, $groupId));
}

function outlook_email_search_filter(string $searchValue): array
{
    $like = '%' . trim($searchValue) . '%';

    return [
        'sql' => '(
            configs.category ILIKE :search_category
            OR configs.emails ILIKE :search_email
            OR CAST(configs.id AS TEXT) ILIKE :search_id
        )',
        'params' => [
            ':search_category' => $like,
            ':search_email' => $like,
            ':search_id' => $like,
        ],
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
                onclick="return confirm(\'Delete this brochure email configuration?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function outlook_email_insert_address(
    PDO $conn,
    string $email,
    string $category,
    ?int $groupId,
    string $createdBy
): int {
    $stmt = $conn->prepare('
        INSERT INTO outlook_emails (
            user_id, outlook_email, category, group_id, created_by, created_at
        ) VALUES (
            NULL, :outlook_email, :category, :group_id, :created_by, CURRENT_TIMESTAMP
        )
        RETURNING id
    ');
    $stmt->bindValue(':outlook_email', $email);
    $stmt->bindValue(':category', $category);
    if ($groupId === null || $groupId <= 0) {
        $stmt->bindValue(':group_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':group_id', $groupId, PDO::PARAM_INT);
    }
    $stmt->bindValue(':created_by', $createdBy);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

function outlook_email_insert(PDO $conn, array $data, string $createdBy): int
{
    $category = trim((string) ($data['category'] ?? ''));
    $emails = array_values(array_filter(array_map(
        static fn ($email): string => trim((string) $email),
        is_array($data['emails'] ?? null) ? $data['emails'] : []
    ), static fn (string $email): bool => $email !== ''));

    $conn->beginTransaction();
    try {
        $groupId = outlook_email_insert_address($conn, $emails[0], $category, null, $createdBy);
        $mark = $conn->prepare('
            UPDATE outlook_emails
            SET group_id = :group_id
            WHERE id = :id
        ');
        $mark->bindValue(':group_id', $groupId, PDO::PARAM_INT);
        $mark->bindValue(':id', $groupId, PDO::PARAM_INT);
        $mark->execute();

        foreach (array_slice($emails, 1) as $email) {
            outlook_email_insert_address($conn, $email, $category, $groupId, $createdBy);
        }
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    return $groupId;
}

function outlook_email_update(PDO $conn, int $id, array $data): void
{
    $current = outlook_email_get_by_id($conn, $id);
    if ($current === null) {
        return;
    }

    $groupId = (int) $current['id'];
    $category = trim((string) ($data['category'] ?? ''));
    $emails = array_values(array_filter(array_map(
        static fn ($email): string => trim((string) $email),
        is_array($data['emails'] ?? null) ? $data['emails'] : []
    ), static fn (string $email): bool => $email !== ''));
    $existing = outlook_email_configuration_rows($conn, $groupId);
    $existingByEmail = [];
    foreach ($existing as $row) {
        $existingByEmail[strtolower(trim((string) $row['outlook_email']))] = (int) $row['id'];
    }

    $conn->beginTransaction();
    try {
        $keptIds = [];
        foreach ($emails as $email) {
            $key = strtolower($email);
            if (isset($existingByEmail[$key])) {
                $keptIds[] = $existingByEmail[$key];
                continue;
            }
            $keptIds[] = outlook_email_insert_address(
                $conn,
                $email,
                $category,
                $groupId,
                (string) ($existing[0]['created_by'] ?? '')
            );
        }

        if ($keptIds !== []) {
            $delete = $conn->prepare('
                UPDATE outlook_emails
                SET deleted_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE deleted_at IS NULL
                  AND user_id IS NULL
                  AND group_id = :group_id
                  AND id NOT IN (' . implode(', ', array_map(
                      static function (int $index): string {
                          return ':keep_' . $index;
                      },
                      array_keys($keptIds)
                  )) . ')
            ');
            $delete->bindValue(':group_id', $groupId, PDO::PARAM_INT);
            foreach ($keptIds as $index => $keptId) {
                $delete->bindValue(':keep_' . $index, $keptId, PDO::PARAM_INT);
            }
            $delete->execute();
        }

        $updateCategory = $conn->prepare('
            UPDATE outlook_emails
            SET category = :category,
                updated_at = CURRENT_TIMESTAMP
            WHERE deleted_at IS NULL
              AND user_id IS NULL
              AND group_id = :group_id
        ');
        $updateCategory->bindValue(':category', $category);
        $updateCategory->bindValue(':group_id', $groupId, PDO::PARAM_INT);
        $updateCategory->execute();
        $conn->commit();
    } catch (Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }
}

function outlook_email_address_for_user(PDO $conn, int $userId): string
{
    if ($userId <= 0) {
        return '';
    }

    outlook_email_ensure_schema($conn);
    $stmt = $conn->prepare('
        SELECT outlook_email
        FROM outlook_emails
        WHERE user_id = :user_id
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    return trim((string) $stmt->fetchColumn());
}

function outlook_email_soft_delete(PDO $conn, int $id): void
{
    $current = outlook_email_get_by_id($conn, $id);
    if ($current === null) {
        return;
    }

    $stmt = $conn->prepare('
        UPDATE outlook_emails
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE group_id = :group_id
          AND user_id IS NULL
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':group_id', (int) $current['id'], PDO::PARAM_INT);
    $stmt->execute();
}

function outlook_email_is_deliverable(string $email): bool
{
    $email = trim($email);
    if ($email === '' || strlen($email) > 255) {
        return false;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match(user_email_pattern(), $email)) {
        return false;
    }

    return !disposable_email_is_blocked($email);
}

/**
 * Categories that have at least one deliverable brochure email address.
 *
 * @return array<string, string>
 */
function outlook_email_configured_categories(PDO $conn): array
{
    $categories = [];
    foreach (outlook_email_category_directory($conn) as $category => $emails) {
        if ($emails !== []) {
            $categories[$category] = $category;
        }
    }

    return $categories;
}

function outlook_email_canonical_category(PDO $conn, string $category): string
{
    $needle = strtolower(trim($category));
    if ($needle === '') {
        return '';
    }

    foreach (outlook_email_category_directory($conn) as $name => $emails) {
        if (strtolower($name) === $needle && $emails !== []) {
            return $name;
        }
    }

    return '';
}

/**
 * @return array<int, string>
 */
function outlook_email_addresses_for_category(PDO $conn, string $category): array
{
    $needle = strtolower(trim($category));
    if ($needle === '') {
        return [];
    }

    foreach (outlook_email_category_directory($conn) as $name => $emails) {
        if (strtolower($name) === $needle) {
            return $emails;
        }
    }

    return [];
}

/**
 * @return array<string, array<int, string>>
 */
function outlook_email_category_directory(PDO $conn): array
{
    outlook_email_ensure_schema($conn);
    $stmt = $conn->query('
        SELECT category, outlook_email
        FROM outlook_emails
        WHERE deleted_at IS NULL
          AND user_id IS NULL
          AND TRIM(COALESCE(category, \'\')) <> \'\'
        ORDER BY category ASC, id ASC
    ');

    $directory = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $category = trim((string) ($row['category'] ?? ''));
        $email = trim((string) ($row['outlook_email'] ?? ''));
        if ($category === '' || !outlook_email_is_deliverable($email)) {
            continue;
        }
        if (!isset($directory[$category])) {
            $directory[$category] = [];
        }
        $key = strtolower($email);
        if (!in_array($key, array_map('strtolower', $directory[$category]), true)) {
            $directory[$category][] = $email;
        }
    }

    return $directory;
}