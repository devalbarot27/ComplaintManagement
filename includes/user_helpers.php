<?php

require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/password_reset_helpers.php';
require_once __DIR__ . '/role_helpers.php';
require_once __DIR__ . '/admin_access_helpers.php';

/**
 * @return array<int, int>
 */
function user_roles_requiring_sales_coordinator(): array
{
    return [
        DEALER_USER_ROLE,
        DEALER_ENGINEER_USER_ROLE,
        ELGI_ENGINEER_USER_ROLE,
    ];
}

function user_role_requires_sales_coordinator(int $roleId): bool
{
    return in_array($roleId, user_roles_requiring_sales_coordinator(), true);
}

function user_sales_coordinator_role_name(): string
{
    return 'Sales Coordinator';
}

function user_sales_coordinator_role_id(PDO $conn): ?int
{
    static $cachedRoleId = null;
    static $cacheConnId = null;

    $connId = spl_object_id($conn);
    if ($cacheConnId === $connId && $cachedRoleId !== null) {
        return $cachedRoleId > 0 ? $cachedRoleId : null;
    }

    $stmt = $conn->prepare('
        SELECT id
        FROM roles
        WHERE deleted_at IS NULL
          AND status = \'active\'
          AND LOWER(TRIM(role_name)) = LOWER(TRIM(:role_name))
        LIMIT 1
    ');
    $stmt->bindValue(':role_name', user_sales_coordinator_role_name());
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $cacheConnId = $connId;
    $cachedRoleId = $row ? (int) $row['id'] : 0;

    return $cachedRoleId > 0 ? $cachedRoleId : null;
}

/**
 * @return array<int, array<string, mixed>>
 */
function user_sales_coordinator_options(PDO $conn): array
{
    $stmt = $conn->prepare('
        SELECT um.id, um.username, um.name
        FROM user_master um
        INNER JOIN roles r
            ON r.id = um.role
           AND r.deleted_at IS NULL
           AND r.status = \'active\'
           AND LOWER(TRIM(r.role_name)) = LOWER(TRIM(:role_name))
        WHERE um.deleted_at IS NULL
        ORDER BY um.name ASC, um.username ASC
    ');
    $stmt->bindValue(':role_name', user_sales_coordinator_role_name());
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Sales Coordinator options for add/edit forms, keeping the current selection when editing.
 *
 * @return array<int, array<string, mixed>>
 */
function user_sales_coordinator_options_for_form(PDO $conn, ?int $selectedSalesCoordinatorId = null): array
{
    $options = user_sales_coordinator_options($conn);
    if ($selectedSalesCoordinatorId === null || $selectedSalesCoordinatorId <= 0) {
        return $options;
    }

    foreach ($options as $option) {
        if ((int) ($option['id'] ?? 0) === $selectedSalesCoordinatorId) {
            return $options;
        }
    }

    $stmt = $conn->prepare('
        SELECT id, username, name
        FROM user_master
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $selectedSalesCoordinatorId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        array_unshift($options, $row);
    }

    return $options;
}

/**
 * @return array<string, mixed>
 */
function user_form_record_from_row(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'role' => (int) ($row['role'] ?? 0),
        'username' => (string) ($row['username'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'email' => (string) ($row['email'] ?? ''),
        'mobile_number' => (string) ($row['mobile_number'] ?? ''),
        'sales_coordinator_id' => isset($row['sales_coordinator_id']) ? (int) $row['sales_coordinator_id'] : 0,
    ];
}

/**
 * @return array<string, mixed>
 */
function user_form_record_from_post(array $data, int $id): array
{
    return [
        'id' => $id,
        'role' => (int) ($data['role'] ?? 0),
        'username' => (string) ($data['username'] ?? ''),
        'name' => (string) ($data['name'] ?? ''),
        'email' => (string) ($data['email'] ?? ''),
        'mobile_number' => (string) ($data['mobile_number'] ?? ''),
        'sales_coordinator_id' => (int) ($data['sales_coordinator_id'] ?? 0),
    ];
}

function user_sales_coordinator_option_label(array $user): string
{
    $name = trim((string) ($user['name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    return trim((string) ($user['username'] ?? ''));
}

function user_sales_coordinator_display_name(PDO $conn, ?int $salesCoordinatorId): string
{
    if ($salesCoordinatorId === null || $salesCoordinatorId <= 0) {
        return '-';
    }

    $stmt = $conn->prepare('
        SELECT um.username, um.name
        FROM user_master um
        INNER JOIN roles r
            ON r.id = um.role
           AND r.deleted_at IS NULL
           AND r.status = \'active\'
           AND LOWER(TRIM(r.role_name)) = LOWER(TRIM(:role_name))
        WHERE um.id = :id
          AND um.deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $salesCoordinatorId, PDO::PARAM_INT);
    $stmt->bindValue(':role_name', user_sales_coordinator_role_name());
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return '-';
    }

    return user_sales_coordinator_option_label($row);
}

function user_is_valid_sales_coordinator(PDO $conn, int $salesCoordinatorId): bool
{
    if ($salesCoordinatorId <= 0) {
        return false;
    }

    $stmt = $conn->prepare('
        SELECT um.id
        FROM user_master um
        INNER JOIN roles r
            ON r.id = um.role
           AND r.deleted_at IS NULL
           AND r.status = \'active\'
           AND LOWER(TRIM(r.role_name)) = LOWER(TRIM(:role_name))
        WHERE um.id = :id
          AND um.deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $salesCoordinatorId, PDO::PARAM_INT);
    $stmt->bindValue(':role_name', user_sales_coordinator_role_name());
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function user_normalized_sales_coordinator_id(array $data): ?int
{
    if (!user_role_requires_sales_coordinator((int) $data['role'])) {
        return null;
    }

    $salesCoordinatorId = (int) ($data['sales_coordinator_id'] ?? 0);

    return $salesCoordinatorId > 0 ? $salesCoordinatorId : null;
}

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
        'sales_coordinator_id' => trim((string) ($post['sales_coordinator_id'] ?? '')),
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

    if (user_role_requires_sales_coordinator((int) $data['role'])) {
        $salesCoordinatorId = (int) ($data['sales_coordinator_id'] ?? 0);
        if ($salesCoordinatorId <= 0) {
            return 'Sales Coordinator is required.';
        }
        if (!user_is_valid_sales_coordinator($conn, $salesCoordinatorId)) {
            return 'Selected Sales Coordinator is invalid.';
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
            <a href="user_edit.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="Edit">
                <i class="bi bi-pencil"></i>
            </a>
            <a href="delete_user.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this user?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function user_bind_sales_coordinator_id(PDOStatement $stmt, array $data): void
{
    $salesCoordinatorId = user_normalized_sales_coordinator_id($data);
    if ($salesCoordinatorId === null) {
        $stmt->bindValue(':sales_coordinator_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':sales_coordinator_id', $salesCoordinatorId, PDO::PARAM_INT);
    }
}

function user_insert(PDO $conn, array $data, string $createdBy): void
{
    $stmt = $conn->prepare('
        INSERT INTO user_master (
            role, username, name, email, password, mobile_number, sales_coordinator_id, created_by, created_at
        ) VALUES (
            :role, :username, :name, :email, :password, :mobile_number, :sales_coordinator_id, :created_by, CURRENT_TIMESTAMP
        )
    ');
    $stmt->bindValue(':role', (int) $data['role'], PDO::PARAM_INT);
    $stmt->bindValue(':username', $data['username']);
    $stmt->bindValue(':name', $data['name']);
    $stmt->bindValue(':email', $data['email']);
    $stmt->bindValue(':password', user_password_hash($data['password']));
    $stmt->bindValue(':mobile_number', $data['mobile_number']);
    user_bind_sales_coordinator_id($stmt, $data);
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
                sales_coordinator_id = :sales_coordinator_id,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND deleted_at IS NULL
        ');
        $stmt->bindValue(':password', user_password_hash($data['password']));
    } else {
        $stmt = $conn->prepare('
            UPDATE user_master SET
                role = :role,
                username = :username,
                name = :name,
                email = :email,
                mobile_number = :mobile_number,
                sales_coordinator_id = :sales_coordinator_id,
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
    user_bind_sales_coordinator_id($stmt, $data);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}