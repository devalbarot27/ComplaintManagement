<?php

require_once __DIR__ . '/rbac_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';

function user_approval_config_module_foc(): string
{
    return 'foc-parts';
}

function user_approval_config_module_service(): string
{
    return 'service-claims';
}

/**
 * @return array<string, string>
 */
function user_approval_config_module_options(): array
{
    return [
        user_approval_config_module_foc() => 'FOC Parts',
        user_approval_config_module_service() => 'Service Claims',
    ];
}

function user_approval_config_module_label(string $moduleSlug): string
{
    $options = user_approval_config_module_options();

    return $options[$moduleSlug] ?? $moduleSlug;
}

function user_approval_config_bool_from_value($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if ($value === null) {
        return false;
    }
    if (is_int($value) || is_float($value)) {
        return (int) $value === 1;
    }

    $normalized = strtolower(trim((string) $value));

    return in_array($normalized, ['1', 't', 'true', 'yes', 'on'], true);
}

function user_approval_config_yes_no(bool $value): string
{
    return $value ? 'Yes' : 'No';
}

/**
 * @return array{l1: bool, l2: bool}
 */
function user_approval_config_levels_for_user(PDO $conn, ?int $userId, string $moduleSlug): array
{
    $levels = ['l1' => false, 'l2' => false];
    if ($userId === null || $userId <= 0 || $moduleSlug === '') {
        return $levels;
    }

    user_approval_config_ensure_schema($conn);

    $stmt = $conn->prepare('
        SELECT level_1_approval, level_2_approval
        FROM user_approval_configurations
        WHERE user_id = :user_id
          AND module_slug = :module_slug
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':module_slug', $moduleSlug);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $levels;
    }

    $levels['l1'] = user_approval_config_bool_from_value($row['level_1_approval'] ?? false);
    if ($moduleSlug !== user_approval_config_module_service()) {
        $levels['l2'] = user_approval_config_bool_from_value($row['level_2_approval'] ?? false);
    }

    return $levels;
}

function user_approval_config_user_can_level(PDO $conn, ?int $userId, string $moduleSlug, string $level): bool
{
    $levels = user_approval_config_levels_for_user($conn, $userId, $moduleSlug);

    if ($level === 'l1') {
        return $levels['l1'];
    }
    if ($level === 'l2') {
        return $levels['l2'];
    }

    return false;
}

function user_approval_config_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $conn->exec("
        CREATE TABLE IF NOT EXISTS user_approval_configurations (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            module_slug VARCHAR(50) NOT NULL,
            level_1_approval BOOLEAN NOT NULL DEFAULT FALSE,
            level_2_approval BOOLEAN NOT NULL DEFAULT FALSE,
            created_by VARCHAR(150) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL
        )
    ");
    $conn->exec("
        CREATE UNIQUE INDEX IF NOT EXISTS user_approval_configurations_user_module_unique
            ON user_approval_configurations (user_id, module_slug)
            WHERE deleted_at IS NULL
    ");

    $ensured = true;
}

/**
 * @return array<int, array<string, mixed>>
 */
function user_approval_config_user_options(PDO $conn): array
{
    $stmt = $conn->query("
        SELECT id, username, name
        FROM user_master
        WHERE deleted_at IS NULL
        ORDER BY LOWER(COALESCE(NULLIF(TRIM(name), ''), username)) ASC, id ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function user_approval_config_user_label(array $row): string
{
    $name = trim((string) ($row['name'] ?? ''));
    $username = trim((string) ($row['username'] ?? ''));

    if ($name !== '' && $username !== '') {
        return $name . ' (' . $username . ')';
    }

    return $name !== '' ? $name : ($username !== '' ? $username : '-');
}

/**
 * @return array<string, mixed>
 */
function user_approval_config_from_post(array $post): array
{
    $moduleSlug = trim((string) ($post['module_slug'] ?? ''));
    $level1 = !empty($post['level_1_approval']);
    $level2 = !empty($post['level_2_approval']);

    if ($moduleSlug === user_approval_config_module_service()) {
        $level2 = false;
    }

    return [
        'user_id' => (int) ($post['user_id'] ?? 0),
        'module_slug' => $moduleSlug,
        'level_1_approval' => $level1,
        'level_2_approval' => $level2,
    ];
}

function user_approval_config_validate(PDO $conn, array $data): ?string
{
    if ((int) ($data['user_id'] ?? 0) <= 0) {
        return 'User is required.';
    }

    $stmt = $conn->prepare('
        SELECT 1
        FROM user_master
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', (int) $data['user_id'], PDO::PARAM_INT);
    $stmt->execute();
    if (!$stmt->fetchColumn()) {
        return 'Please select a valid user.';
    }

    $moduleSlug = (string) ($data['module_slug'] ?? '');
    if ($moduleSlug === '' || !array_key_exists($moduleSlug, user_approval_config_module_options())) {
        return 'Module is required.';
    }

    $level1 = !empty($data['level_1_approval']);
    $level2 = !empty($data['level_2_approval']);
    if ($moduleSlug === user_approval_config_module_service()) {
        $level2 = false;
    }

    if (!$level1 && !$level2) {
        return 'Select at least one approval level.';
    }

    return null;
}

function user_approval_config_exists(PDO $conn, int $userId, string $moduleSlug, int $excludeId = 0): bool
{
    $sql = '
        SELECT 1
        FROM user_approval_configurations
        WHERE user_id = :user_id
          AND module_slug = :module_slug
          AND deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id <> :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':module_slug', $moduleSlug);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function user_approval_config_get_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare("
        SELECT
            uac.*,
            um.username,
            um.name
        FROM user_approval_configurations uac
        INNER JOIN user_master um ON um.id = uac.user_id
        WHERE uac.id = :id
          AND uac.deleted_at IS NULL
    ");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function user_approval_config_search_filter(string $searchValue): array
{
    $like = '%' . $searchValue . '%';

    return [
        'sql' => '(
            CAST(uac.id AS TEXT) ILIKE :search
            OR um.username ILIKE :search
            OR um.name ILIKE :search
            OR uac.module_slug ILIKE :search
        )',
        'params' => [':search' => $like],
    ];
}

function user_approval_config_entry_actions(int $id): string
{
    $encodedId = base64_encode((string) $id);

    return '
        <div class="d-flex gap-1">
            <a href="user_approval_configuration_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-user-approval-config-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_user_approval_configuration.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this user approval configuration?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function user_approval_config_insert(PDO $conn, array $data, string $createdBy): void
{
    $stmt = $conn->prepare('
        INSERT INTO user_approval_configurations (
            user_id, module_slug, level_1_approval, level_2_approval, created_by, created_at
        ) VALUES (
            :user_id, :module_slug, :level_1_approval, :level_2_approval, :created_by, CURRENT_TIMESTAMP
        )
    ');
    $stmt->bindValue(':user_id', (int) $data['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':module_slug', $data['module_slug']);
    $stmt->bindValue(':level_1_approval', !empty($data['level_1_approval']), PDO::PARAM_BOOL);
    $stmt->bindValue(':level_2_approval', !empty($data['level_2_approval']), PDO::PARAM_BOOL);
    $stmt->bindValue(':created_by', $createdBy);
    $stmt->execute();
}

function user_approval_config_update(PDO $conn, int $id, array $data): void
{
    $stmt = $conn->prepare('
        UPDATE user_approval_configurations SET
            user_id = :user_id,
            module_slug = :module_slug,
            level_1_approval = :level_1_approval,
            level_2_approval = :level_2_approval,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':user_id', (int) $data['user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':module_slug', $data['module_slug']);
    $stmt->bindValue(':level_1_approval', !empty($data['level_1_approval']), PDO::PARAM_BOOL);
    $stmt->bindValue(':level_2_approval', !empty($data['level_2_approval']), PDO::PARAM_BOOL);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function user_approval_config_soft_delete(PDO $conn, int $id): void
{
    $stmt = $conn->prepare('
        UPDATE user_approval_configurations
        SET deleted_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}
