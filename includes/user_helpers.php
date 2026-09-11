<?php

require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/password_reset_helpers.php';
require_once __DIR__ . '/role_helpers.php';
require_once __DIR__ . '/admin_access_helpers.php';
require_once __DIR__ . '/disposable_email_helpers.php';

function user_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $conn->exec('
        ALTER TABLE user_master
        ADD COLUMN IF NOT EXISTS level_1_approval BOOLEAN NOT NULL DEFAULT FALSE
    ');
    $conn->exec('
        ALTER TABLE user_master
        ADD COLUMN IF NOT EXISTS level_2_approval BOOLEAN NOT NULL DEFAULT FALSE
    ');
    $conn->exec('
        ALTER TABLE user_master
        ADD COLUMN IF NOT EXISTS customer_code VARCHAR(20) NULL
    ');
    // APPROVAL MODULE: Dealer User assigns Level 1 / Level 2 approvers
    $conn->exec('
        ALTER TABLE user_master
        ADD COLUMN IF NOT EXISTS level_1_approver_id INTEGER NULL
    ');
    $conn->exec('
        ALTER TABLE user_master
        ADD COLUMN IF NOT EXISTS level_2_approver_id INTEGER NULL
    ');

    $ensured = true;
}

function user_bool_from_value($value): bool
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

function user_yes_no(bool $value): string
{
    return $value ? 'Yes' : 'No';
}

/**
 * Roles that show the Approval fields (Dealer User: L1 + L2; ELGi Engineer: L2 only, L1 = self).
 *
 * @return array<int, int>
 */
function user_roles_with_approval_options(): array
{
    return [
        DEALER_USER_ROLE,
        ELGI_ENGINEER_USER_ROLE,
    ];
}

function user_role_has_approval_options(int $roleId): bool
{
    return in_array($roleId, user_roles_with_approval_options(), true);
}

/**
 * Roles whose Level 1 approver is always the same user (hidden in the form).
 *
 * @return array<int, int>
 */
function user_roles_auto_assign_level1_to_self(): array
{
    return [
        ELGI_ENGINEER_USER_ROLE,
    ];
}

function user_role_auto_assigns_level1_to_self(int $roleId): bool
{
    return in_array($roleId, user_roles_auto_assign_level1_to_self(), true);
}

/**
 * Roles that can be selected as Level 1 / Level 2 approvers.
 *
 * @return array<int, int>
 */
function user_approver_role_ids(): array
{
    return [
        ELGI_ENGINEER_USER_ROLE,
        MANAGEMENT_USER_ROLE,
    ];
}

/**
 * Logged-in user can act on Level 1 / Level 2 if a Dealer User assigned them.
 *
 * @return array{l1: bool, l2: bool}
 */
function user_current_approval_flags(PDO $conn): array
{
    if (is_system_admin()) {
        return ['l1' => true, 'l2' => true];
    }

    $userId = current_user_id($conn);
    if ($userId === null || $userId <= 0) {
        return ['l1' => false, 'l2' => false];
    }

    user_ensure_schema($conn);
    $stmt = $conn->prepare('
        SELECT
            EXISTS (
                SELECT 1
                FROM user_master
                WHERE deleted_at IS NULL
                  AND level_1_approver_id = :l1_id
            ) AS is_l1,
            EXISTS (
                SELECT 1
                FROM user_master
                WHERE deleted_at IS NULL
                  AND level_2_approver_id = :l2_id
            ) AS is_l2
    ');
    $stmt->bindValue(':l1_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':l2_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'l1' => user_bool_from_value($row['is_l1'] ?? false),
        'l2' => user_bool_from_value($row['is_l2'] ?? false),
    ];
}

/**
 * @return array{level_1_approver_id: int|null, level_2_approver_id: int|null}
 */
function user_normalized_approver_ids(array $data, int $userId = 0): array
{
    if (!user_role_has_approval_options((int) ($data['role'] ?? 0))) {
        return [
            'level_1_approver_id' => null,
            'level_2_approver_id' => null,
        ];
    }

    $level1 = (int) ($data['level_1_approver_id'] ?? 0);
    $level2 = (int) ($data['level_2_approver_id'] ?? 0);
    if (user_role_auto_assigns_level1_to_self((int) ($data['role'] ?? 0))) {
        $level1 = $userId > 0 ? $userId : 0;
    }

    return [
        'level_1_approver_id' => $level1 > 0 ? $level1 : null,
        'level_2_approver_id' => $level2 > 0 ? $level2 : null,
    ];
}

function user_assigned_approver_id(PDO $conn, int $requesterUserId, string $level): ?int
{
    if ($requesterUserId <= 0) {
        return null;
    }

    $row = user_get_by_id($conn, $requesterUserId);
    if ($row === null) {
        return null;
    }

    $column = $level === 'level_2' ? 'level_2_approver_id' : 'level_1_approver_id';
    $approverId = (int) ($row[$column] ?? 0);

    return $approverId > 0 ? $approverId : null;
}

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
 * @return array<int, array<string, mixed>>
 */
function user_approver_options(PDO $conn): array
{
    $roleIds = user_approver_role_ids();
    $placeholders = [];
    $params = [];
    foreach ($roleIds as $index => $roleId) {
        $key = ':approver_role_' . $index;
        $placeholders[] = $key;
        $params[$key] = $roleId;
    }

    $stmt = $conn->prepare('
        SELECT um.id, um.username, um.name
        FROM user_master um
        WHERE um.deleted_at IS NULL
          AND um.role IN (' . implode(', ', $placeholders) . ')
        ORDER BY um.name ASC, um.username ASC
    ');
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

/**
 * @return array<int, array<string, mixed>>
 */
function user_approver_options_for_form(
    PDO $conn,
    ?int $selectedApproverId = null,
    ?int $selectedApproverId2 = null
): array {
    $options = user_approver_options($conn);
    $existingIds = [];
    foreach ($options as $option) {
        $existingIds[(int) ($option['id'] ?? 0)] = true;
    }

    foreach ([$selectedApproverId, $selectedApproverId2] as $selectedId) {
        if ($selectedId === null || $selectedId <= 0 || isset($existingIds[$selectedId])) {
            continue;
        }
        $stmt = $conn->prepare('
            SELECT id, username, name
            FROM user_master
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->bindValue(':id', $selectedId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            array_unshift($options, $row);
            $existingIds[$selectedId] = true;
        }
    }

    return $options;
}

function user_is_valid_approver(PDO $conn, int $approverId): bool
{
    if ($approverId <= 0) {
        return false;
    }

    $roleIds = user_approver_role_ids();
    $placeholders = [];
    $params = [':id' => $approverId];
    foreach ($roleIds as $index => $roleId) {
        $key = ':approver_role_' . $index;
        $placeholders[] = $key;
        $params[$key] = $roleId;
    }

    $stmt = $conn->prepare('
        SELECT um.id
        FROM user_master um
        WHERE um.id = :id
          AND um.deleted_at IS NULL
          AND um.role IN (' . implode(', ', $placeholders) . ')
        LIMIT 1
    ');
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function user_created_by_display_name(PDO $conn, $createdBy): string
{
    $value = trim((string) $createdBy);
    if ($value === '') {
        return '-';
    }

    $stmt = $conn->prepare('
        SELECT username, name
        FROM user_master
        WHERE LOWER(TRIM(username)) = LOWER(TRIM(:username))
        LIMIT 1
    ');
    $stmt->bindValue(':username', $value);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $value;
    }

    $label = user_sales_coordinator_option_label($row);

    return $label !== '' ? $label : $value;
}

function user_approver_display_name(PDO $conn, ?int $approverId): string
{
    if ($approverId === null || $approverId <= 0) {
        return '-';
    }

    $stmt = $conn->prepare('
        SELECT username, name
        FROM user_master
        WHERE id = :id
          AND deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $approverId, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return '-';
    }

    return user_sales_coordinator_option_label($row);
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
        'customer_code' => trim((string) ($row['customer_code'] ?? '')),
        'level_1_approver_id' => isset($row['level_1_approver_id']) ? (int) $row['level_1_approver_id'] : 0,
        'level_2_approver_id' => isset($row['level_2_approver_id']) ? (int) $row['level_2_approver_id'] : 0,
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
        'customer_code' => trim((string) ($data['customer_code'] ?? '')),
        'level_1_approver_id' => (int) ($data['level_1_approver_id'] ?? 0),
        'level_2_approver_id' => (int) ($data['level_2_approver_id'] ?? 0),
    ];
}

function user_customer_code_format_label(array $row): string
{
    $cuno = trim((string) ($row['cuno'] ?? $row['customer_code'] ?? ''));
    $cuname = trim((string) ($row['cuname'] ?? $row['customer_name'] ?? ''));

    if ($cuno === '') {
        return '-';
    }

    return $cuname !== '' ? ($cuno . ' - ' . $cuname) : $cuno;
}

function user_customer_code_get(PDO $conn, string $cuno): ?array
{
    $cuno = trim($cuno);
    if ($cuno === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT
            TRIM(cms.customer_code) AS cuno,
            TRIM(cm.cuname) AS cuname
        FROM customer_master_sync cms
        LEFT JOIN customer_master cm ON TRIM(cm.cuno) = TRIM(cms.customer_code)
        WHERE TRIM(cms.customer_code) = :cuno
          AND cms.deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':cuno', $cuno);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function user_customer_code_label(PDO $conn, string $cuno): string
{
    $row = user_customer_code_get($conn, $cuno);
    if ($row === null) {
        return $cuno !== '' ? $cuno : '-';
    }

    return user_customer_code_format_label($row);
}

/**
 * @return array<int, array{id: string, text: string}>
 */
function user_customer_code_search(PDO $conn, string $search, int $limit = 50): array
{
    $limit = max(1, min(100, $limit));
    $sql = '
        SELECT
            TRIM(cms.customer_code) AS cuno,
            TRIM(cm.cuname) AS cuname
        FROM customer_master_sync cms
        LEFT JOIN customer_master cm ON TRIM(cm.cuno) = TRIM(cms.customer_code)
        WHERE cms.deleted_at IS NULL
          AND length(TRIM(cms.customer_code)) > 0
    ';
    $params = [];

    if ($search !== '') {
        $sql .= ' AND (
            LOWER(cms.customer_code) LIKE LOWER(:search)
            OR LOWER(COALESCE(cm.cuname, \'\')) LIKE LOWER(:search)
        )';
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY cms.customer_code ASC LIMIT ' . (int) $limit;

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $results = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $cuno = trim((string) ($row['cuno'] ?? ''));
        if ($cuno === '') {
            continue;
        }
        $results[] = [
            'id' => $cuno,
            'text' => user_customer_code_format_label($row),
        ];
    }

    return $results;
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

    foreach (['username', 'name', 'email', 'mobile_number', 'customer_code'] as $column) {
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
    $data = [
        'role' => trim((string) ($post['role'] ?? '')),
        'username' => trim((string) ($post['username'] ?? '')),
        'name' => trim((string) ($post['name'] ?? '')),
        'email' => trim((string) ($post['email'] ?? '')),
        'password' => (string) ($post['password'] ?? ''),
        'mobile_number' => trim((string) ($post['mobile_number'] ?? '')),
        'sales_coordinator_id' => trim((string) ($post['sales_coordinator_id'] ?? '')),
        'customer_code' => trim((string) ($post['customer_code'] ?? '')),
        'level_1_approver_id' => trim((string) ($post['level_1_approver_id'] ?? '')),
        'level_2_approver_id' => trim((string) ($post['level_2_approver_id'] ?? '')),
    ];

    return array_merge($data, user_normalized_approver_ids($data));
}

/**
 * Username: letters, numbers, underscore only.
 */
function user_username_pattern(): string
{
    return '/^[A-Za-z0-9_]+$/';
}

/**
 * Display name: letters, spaces, dot, apostrophe, hyphen only (no XSS/special chars).
 */
function user_name_pattern(): string
{
    return '/^[A-Za-z]+(?:[ .\'-][A-Za-z]+)*$/';
}

/**
 * Email: conservative ASCII email shape (no angle brackets / script-friendly chars).
 */
function user_email_pattern(): string
{
    return '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/';
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

    if (!preg_match(user_username_pattern(), $data['username'])) {
        return 'Username may only contain letters, numbers, and underscore. Special characters are not allowed.';
    }

    if (strlen($data['username']) > 100) {
        return 'Username cannot exceed 100 characters.';
    }

    if ($data['name'] === '') {
        return 'Name is required.';
    }

    if (strlen($data['name']) > 255) {
        return 'Name cannot exceed 255 characters.';
    }

    if (!preg_match(user_name_pattern(), $data['name'])) {
        return 'Name may only contain letters, spaces, dots, hyphens, and apostrophes. Special characters are not allowed.';
    }

    if ($data['email'] === '') {
        return 'Email is required.';
    }

    if (strlen($data['email']) > 255) {
        return 'Email cannot exceed 255 characters.';
    }

    if (
        !filter_var($data['email'], FILTER_VALIDATE_EMAIL)
        || !preg_match(user_email_pattern(), $data['email'])
    ) {
        return 'Please enter a valid email address without special characters.';
    }

    if (disposable_email_is_blocked($data['email'])) {
        return disposable_email_blocked_message();
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

    if (user_role_has_approval_options((int) $data['role'])) {
        $level2ApproverId = (int) ($data['level_2_approver_id'] ?? 0);
        if ($level2ApproverId <= 0) {
            return 'Level 2 Approval is required.';
        }
        if (!user_is_valid_approver($conn, $level2ApproverId)) {
            return 'Selected Level 2 Approval user is invalid.';
        }
        if (!user_role_auto_assigns_level1_to_self((int) $data['role'])) {
            $level1ApproverId = (int) ($data['level_1_approver_id'] ?? 0);
            if ($level1ApproverId <= 0) {
                return 'Level 1 Approval is required.';
            }
            if (!user_is_valid_approver($conn, $level1ApproverId)) {
                return 'Selected Level 1 Approval user is invalid.';
            }
        }
    }

    $customerCode = trim((string) ($data['customer_code'] ?? ''));
    if ($customerCode === '') {
        return 'Customer Code is required.';
    }
    if (strlen($customerCode) > 9) {
        return 'Customer Code cannot exceed 9 characters.';
    }
    if (user_customer_code_get($conn, $customerCode) === null) {
        return 'Selected Customer Code is invalid.';
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

function user_customer_code_exists(PDO $conn, string $customerCode, int $excludeId = 0): bool
{
    $sql = '
        SELECT id
        FROM user_master
        WHERE LOWER(TRIM(customer_code)) = LOWER(TRIM(:customer_code))
          AND deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':customer_code', $customerCode);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function user_get_by_id(PDO $conn, int $id): ?array
{
    user_ensure_schema($conn);

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

function user_bind_customer_code(PDOStatement $stmt, array $data): void
{
    $customerCode = trim((string) ($data['customer_code'] ?? ''));
    if ($customerCode === '') {
        $stmt->bindValue(':customer_code', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':customer_code', $customerCode);
    }
}

function user_bind_approver_ids(PDOStatement $stmt, array $data, int $userId = 0): void
{
    $ids = user_normalized_approver_ids($data, $userId);
    if ($ids['level_1_approver_id'] === null) {
        $stmt->bindValue(':level_1_approver_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':level_1_approver_id', $ids['level_1_approver_id'], PDO::PARAM_INT);
    }
    if ($ids['level_2_approver_id'] === null) {
        $stmt->bindValue(':level_2_approver_id', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':level_2_approver_id', $ids['level_2_approver_id'], PDO::PARAM_INT);
    }
    $stmt->bindValue(':level_1_approval', false, PDO::PARAM_BOOL);
    $stmt->bindValue(':level_2_approval', false, PDO::PARAM_BOOL);
}

function user_insert(PDO $conn, array $data, string $createdBy): void
{
    user_ensure_schema($conn);

    $stmt = $conn->prepare('
        INSERT INTO user_master (
            role, username, name, email, password, mobile_number, sales_coordinator_id, customer_code,
            level_1_approval, level_2_approval, level_1_approver_id, level_2_approver_id, created_by, created_at
        ) VALUES (
            :role, :username, :name, :email, :password, :mobile_number, :sales_coordinator_id, :customer_code,
            :level_1_approval, :level_2_approval, :level_1_approver_id, :level_2_approver_id, :created_by, CURRENT_TIMESTAMP
        )
        RETURNING id
    ');
    $stmt->bindValue(':role', (int) $data['role'], PDO::PARAM_INT);
    $stmt->bindValue(':username', $data['username']);
    $stmt->bindValue(':name', $data['name']);
    $stmt->bindValue(':email', $data['email']);
    $stmt->bindValue(':password', user_password_hash($data['password']));
    $stmt->bindValue(':mobile_number', $data['mobile_number']);
    user_bind_sales_coordinator_id($stmt, $data);
    user_bind_customer_code($stmt, $data);
    user_bind_approver_ids($stmt, $data);
    $stmt->bindValue(':created_by', $createdBy);
    $stmt->execute();
    $newId = (int) $stmt->fetchColumn();
    if ($newId > 0 && user_role_auto_assigns_level1_to_self((int) ($data['role'] ?? 0))) {
        $stamp = $conn->prepare('
            UPDATE user_master
            SET level_1_approver_id = :approver_id
            WHERE id = :user_id
              AND deleted_at IS NULL
        ');
        $stamp->bindValue(':approver_id', $newId, PDO::PARAM_INT);
        $stamp->bindValue(':user_id', $newId, PDO::PARAM_INT);
        $stamp->execute();
    }
}

function user_update(PDO $conn, int $id, array $data): void
{
    user_ensure_schema($conn);

    $existing = user_get_by_id($conn, $id);
    $passwordChanged = $data['password'] !== '';
    $roleChanged = $existing !== null
        && (int) $data['role'] !== (int) ($existing['role'] ?? 0);
    $emailChanged = $existing !== null
        && strcasecmp(
            trim((string) $data['email']),
            trim((string) ($existing['email'] ?? ''))
        ) !== 0;

    if ($passwordChanged) {
        $stmt = $conn->prepare('
            UPDATE user_master SET
                role = :role,
                username = :username,
                name = :name,
                email = :email,
                password = :password,
                mobile_number = :mobile_number,
                sales_coordinator_id = :sales_coordinator_id,
                customer_code = :customer_code,
                level_1_approval = :level_1_approval,
                level_2_approval = :level_2_approval,
                level_1_approver_id = :level_1_approver_id,
                level_2_approver_id = :level_2_approver_id,
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
                customer_code = :customer_code,
                level_1_approval = :level_1_approval,
                level_2_approval = :level_2_approval,
                level_1_approver_id = :level_1_approver_id,
                level_2_approver_id = :level_2_approver_id,
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
    user_bind_customer_code($stmt, $data);
    user_bind_approver_ids($stmt, $data, $id);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    // Password, role, or email change invalidates sessions for this user.
    if ($passwordChanged || $roleChanged || $emailChanged) {
        login_bump_session_version_by_id($conn, $id);
    }
}