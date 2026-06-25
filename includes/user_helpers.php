<?php

require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/password_reset_helpers.php';
require_once __DIR__ . '/role_helpers.php';

/**
 * Legacy role map used only for seeding/syncing roles with user_master.role values.
 *
 * @return array<int, string>
 */
function user_legacy_role_seed_map(): array
{
    return [
        1 => 'Dealer User',
        2 => 'Dealer Engineer',
        3 => 'ELGi Engineer',
        4 => 'Sales Coordinator',
        5 => 'Management',
        6 => 'System Admin',
    ];
}

/**
 * Active roles from the roles table, most recently added first (LIFO).
 *
 * @return array<int, string>
 */
function user_role_options(PDO $conn): array
{
    return role_active_options_lifo($conn);
}

function user_role_label(PDO $conn, $role): string
{
    $roleId = (int) $role;
    if ($roleId <= 0) {
        return 'Unknown';
    }

    $row = role_get_by_id($conn, $roleId);

    return $row ? (string) $row['role_name'] : 'Unknown';
}

function user_role_search_ids(PDO $conn, string $searchValue): array
{
    $searchValue = strtolower(trim($searchValue));
    if ($searchValue === '') {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT id
        FROM roles
        WHERE deleted_at IS NULL
          AND role_name ILIKE :search
    ");
    $stmt->bindValue(':search', '%' . $searchValue . '%');
    $stmt->execute();

    $matches = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $matches[] = (int) $row['id'];
    }

    return $matches;
}

function user_search_filter(PDO $conn, string $searchValue): array
{
    $parts = [];
    $params = [':search' => '%' . $searchValue . '%'];

    foreach (['username', 'name', 'email', 'mobile_number'] as $column) {
        $parts[] = "{$column} ILIKE :search";
    }

    $roleIds = user_role_search_ids($conn, $searchValue);
    if (!empty($roleIds)) {
        $rolePlaceholders = [];
        foreach ($roleIds as $index => $roleId) {
            $paramKey = ':role_search_' . $index;
            $rolePlaceholders[] = $paramKey;
            $params[$paramKey] = $roleId;
        }
        $parts[] = 'role IN (' . implode(', ', $rolePlaceholders) . ')';
    }

    return [
        'sql' => '(' . implode(' OR ', $parts) . ')',
        'params' => $params,
    ];
}

function user_from_post(array $post): array
{
    return [
        'role' => trim((string) ($post['role'] ?? '')),
        'username' => trim((string) ($post['username'] ?? '')),
        'name' => trim((string) ($post['name'] ?? '')),
        'email' => trim((string) ($post['email'] ?? '')),
        'password' => (string) ($post['password'] ?? ''),
        'mobile_number' => trim((string) ($post['mobile_number'] ?? '')),
    ];
}

function user_validate(array $data, bool $isEdit, PDO $conn): ?string
{
    $roles = array_keys(user_role_options($conn));

    if ($data['role'] === '' || !in_array((int) $data['role'], $roles, true)) {
        return 'Role is required.';
    }

    if ($data['username'] === '') {
        return 'Username is required.';
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $data['username'])) {
        return 'Username may only contain letters, numbers, and underscore.';
    }

    if (strlen($data['username']) > 100) {
        return 'Username cannot exceed 100 characters.';
    }

    if ($data['name'] === '') {
        return 'Name is required.';
    }

    if ($data['email'] === '') {
        return 'Email is required.';
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }

    if ($data['mobile_number'] === '') {
        return 'Mobile Number is required.';
    }

    if (!preg_match('/^[1-9]\d{9}$/', $data['mobile_number'])) {
        return 'Mobile Number must be a valid 10-digit number.';
    }

    if (!$isEdit || $data['password'] !== '') {
        $passwordError = password_reset_rules_error($data['password']);
        if ($passwordError !== null) {
            return $passwordError;
        }
    }

    return null;
}

function user_username_exists(PDO $conn, string $username, int $excludeId = 0): bool
{
    $sql = '
        SELECT id
        FROM user_master
        WHERE LOWER(TRIM(username)) = LOWER(TRIM(:username))
          AND deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':username', $username);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function user_email_exists(PDO $conn, string $email, int $excludeId = 0): bool
{
    $sql = '
        SELECT id
        FROM user_master
        WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email))
          AND deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':email', $email);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function user_mobile_exists(PDO $conn, string $mobileNumber, int $excludeId = 0): bool
{
    $sql = '
        SELECT id
        FROM user_master
        WHERE TRIM(mobile_number) = TRIM(:mobile_number)
          AND deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':mobile_number', $mobileNumber);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function user_get_by_id(PDO $conn, int $id): ?array
{
    $stmt = $conn->prepare('
        SELECT *
        FROM user_master
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function user_format_datetime(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y h:i A', strtotime($value));
}

function user_display_value($value): string
{
    if ($value === null || trim((string) $value) === '') {
        return '-';
    }

    return trim((string) $value);
}

function user_entry_actions(int $id): string
{
    $encodedId = base64_encode((string) $id);

    return '
        <div class="d-flex gap-1">
            <a href="user_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-user-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_user.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this user?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function user_insert(PDO $conn, array $data, string $createdBy): void
{
    $stmt = $conn->prepare('
        INSERT INTO user_master (
            role, username, name, email, password, mobile_number, created_by, created_at
        ) VALUES (
            :role, :username, :name, :email, :password, :mobile_number, :created_by, CURRENT_TIMESTAMP
        )
    ');
    $stmt->bindValue(':role', (int) $data['role'], PDO::PARAM_INT);
    $stmt->bindValue(':username', $data['username']);
    $stmt->bindValue(':name', $data['name']);
    $stmt->bindValue(':email', $data['email']);
    $stmt->bindValue(':password', md5($data['password']));
    $stmt->bindValue(':mobile_number', $data['mobile_number']);
    $stmt->bindValue(':created_by', $createdBy);
    $stmt->execute();
}

function user_update(PDO $conn, int $id, array $data): void
{
    if ($data['password'] !== '') {
        $stmt = $conn->prepare('
            UPDATE user_master SET
                role = :role,
                username = :username,
                name = :name,
                email = :email,
                password = :password,
                mobile_number = :mobile_number,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND deleted_at IS NULL
        ');
        $stmt->bindValue(':password', md5($data['password']));
    } else {
        $stmt = $conn->prepare('
            UPDATE user_master SET
                role = :role,
                username = :username,
                name = :name,
                email = :email,
                mobile_number = :mobile_number,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND deleted_at IS NULL
        ');
    }

    $stmt->bindValue(':role', (int) $data['role'], PDO::PARAM_INT);
    $stmt->bindValue(':username', $data['username']);
    $stmt->bindValue(':name', $data['name']);
    $stmt->bindValue(':email', $data['email']);
    $stmt->bindValue(':mobile_number', $data['mobile_number']);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}