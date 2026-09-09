<?php

require_once __DIR__ . '/rbac_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/disposable_email_helpers.php';

function customer_master_ensure_schema(PDO $conn): void
{
    // Migrate earlier module table name if present.
    $legacyStmt = $conn->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'customer_contact_masters'
        LIMIT 1
    ");
    $legacyStmt->execute();
    $hasLegacy = (bool) $legacyStmt->fetchColumn();

    $tableStmt = $conn->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'customer_masters'
        LIMIT 1
    ");
    $tableStmt->execute();
    $hasNew = (bool) $tableStmt->fetchColumn();

    if ($hasLegacy && !$hasNew) {
        $conn->exec('ALTER TABLE customer_contact_masters RENAME TO customer_masters');
        $conn->exec('ALTER INDEX IF EXISTS customer_contact_masters_email_active_uidx RENAME TO customer_masters_email_active_uidx');
        $conn->exec('ALTER INDEX IF EXISTS customer_contact_masters_mobile_active_uidx RENAME TO customer_masters_mobile_active_uidx');
        $hasNew = true;
    }

    if (!$hasNew) {
        $conn->exec("
            CREATE TABLE customer_masters (
                id SERIAL PRIMARY KEY,
                customer_name VARCHAR(150) NOT NULL,
                email VARCHAR(150) NOT NULL,
                mobile VARCHAR(15) NOT NULL,
                street_1 VARCHAR(255) NOT NULL,
                street_2 VARCHAR(255) NOT NULL,
                pincode VARCHAR(10) NOT NULL,
                city VARCHAR(100) NOT NULL,
                district VARCHAR(100) NOT NULL,
                state VARCHAR(100) NOT NULL,
                dealer_code VARCHAR(50) NULL,
                dealer_name VARCHAR(150) NULL,
                gst_number VARCHAR(30) NULL,
                pan_number VARCHAR(20) NULL,
                added_by VARCHAR(150) NULL,
                created_by VARCHAR(150) NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_by VARCHAR(150) NULL,
                updated_at TIMESTAMP NULL,
                deleted_at TIMESTAMP NULL
            )
        ");
        $conn->exec("
            CREATE UNIQUE INDEX customer_masters_email_active_uidx
            ON customer_masters (LOWER(TRIM(email)))
            WHERE deleted_at IS NULL
        ");
        $conn->exec("
            CREATE UNIQUE INDEX customer_masters_mobile_active_uidx
            ON customer_masters (TRIM(mobile))
            WHERE deleted_at IS NULL
        ");
    }

    $conn->exec("
        ALTER TABLE customer_masters
        ADD COLUMN IF NOT EXISTS dealer_code VARCHAR(50) NULL
    ");
    $conn->exec("
        ALTER TABLE customer_masters
        ADD COLUMN IF NOT EXISTS dealer_name VARCHAR(150) NULL
    ");
    $conn->exec("
        ALTER TABLE customer_masters
        ADD COLUMN IF NOT EXISTS gst_number VARCHAR(30) NULL
    ");
    $conn->exec("
        ALTER TABLE customer_masters
        ADD COLUMN IF NOT EXISTS pan_number VARCHAR(20) NULL
    ");
    $conn->exec("
        ALTER TABLE customer_masters
        ADD COLUMN IF NOT EXISTS added_by VARCHAR(150) NULL
    ");
    $conn->exec("
        UPDATE customer_masters
        SET added_by = created_by
        WHERE (added_by IS NULL OR TRIM(added_by) = '')
          AND created_by IS NOT NULL
          AND TRIM(created_by) <> ''
    ");
}

function customer_master_email_pattern(): string
{
    return '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/';
}

function customer_master_from_post(array $post): array
{
    $dealerCode = trim((string) ($post['dealer_code'] ?? ''));
    $dealerName = trim((string) ($post['dealer_name'] ?? ''));

    return [
        'customer_name' => trim((string) ($post['customer_name'] ?? '')),
        'email' => trim((string) ($post['email'] ?? '')),
        'mobile' => trim((string) ($post['mobile'] ?? '')),
        'street_1' => trim((string) ($post['street_1'] ?? '')),
        'street_2' => trim((string) ($post['street_2'] ?? '')),
        'pincode' => trim((string) ($post['pincode'] ?? '')),
        'city' => trim((string) ($post['city'] ?? '')),
        'district' => trim((string) ($post['district'] ?? '')),
        'state' => trim((string) ($post['state'] ?? '')),
        'dealer_code' => $dealerCode,
        'dealer_name' => $dealerName,
        'gst_number' => strtoupper(trim((string) ($post['gst_number'] ?? ''))),
        'pan_number' => strtoupper(trim((string) ($post['pan_number'] ?? ''))),
    ];
}

/**
 * Resolve dealer code from a user_master row (customer_code, else customer_number).
 */
function customer_master_dealer_code_from_user_row(array $row): string
{
    $code = trim((string) ($row['customer_code'] ?? ''));
    if ($code !== '') {
        return $code;
    }

    return trim((string) ($row['customer_number'] ?? ''));
}

/**
 * @return array{code: string, name: string, text: string}|null
 */
function customer_master_dealer_get(PDO $conn, string $cuno): ?array
{
    require_once __DIR__ . '/admin_access_helpers.php';

    $cuno = trim($cuno);
    if ($cuno === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT
            TRIM(COALESCE(NULLIF(TRIM(um.customer_code), \'\'), NULLIF(TRIM(um.customer_number), \'\'))) AS dealer_code,
            TRIM(COALESCE(
                NULLIF(TRIM(cm.cuname), \'\'),
                NULLIF(TRIM(um.name), \'\'),
                NULLIF(TRIM(um.username), \'\'),
                TRIM(COALESCE(NULLIF(TRIM(um.customer_code), \'\'), NULLIF(TRIM(um.customer_number), \'\')))
            )) AS dealer_name
        FROM user_master um
        LEFT JOIN customer_master cm
            ON TRIM(cm.cuno) = TRIM(COALESCE(NULLIF(TRIM(um.customer_code), \'\'), NULLIF(TRIM(um.customer_number), \'\')))
        WHERE um.deleted_at IS NULL
          AND um.role = :dealer_role
          AND TRIM(COALESCE(NULLIF(TRIM(um.customer_code), \'\'), NULLIF(TRIM(um.customer_number), \'\'))) = TRIM(:cuno)
        ORDER BY um.name ASC NULLS LAST, um.username ASC
        LIMIT 1
    ');
    $stmt->bindValue(':dealer_role', DEALER_USER_ROLE, PDO::PARAM_INT);
    $stmt->bindValue(':cuno', $cuno);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $code = trim((string) ($row['dealer_code'] ?? ''));
    $name = trim((string) ($row['dealer_name'] ?? ''));
    if ($code === '') {
        return null;
    }

    return [
        'code' => $code,
        'name' => $name !== '' ? $name : $code,
        'text' => ($name !== '' ? $name : $code) . ' - [' . $code . ']',
    ];
}

/**
 * Active Dealer role users only (excludes soft-deleted users).
 *
 * @return array<int, array{id: string, text: string, name: string}>
 */
function customer_master_dealer_search(PDO $conn, string $search, int $limit = 50): array
{
    require_once __DIR__ . '/admin_access_helpers.php';

    $limit = max(1, min(100, $limit));
    $search = trim($search);

    $sql = '
        SELECT
            dealer_code,
            MIN(dealer_name) AS dealer_name
        FROM (
            SELECT
                TRIM(COALESCE(NULLIF(TRIM(um.customer_code), \'\'), NULLIF(TRIM(um.customer_number), \'\'))) AS dealer_code,
                TRIM(COALESCE(
                    NULLIF(TRIM(cm.cuname), \'\'),
                    NULLIF(TRIM(um.name), \'\'),
                    NULLIF(TRIM(um.username), \'\'),
                    TRIM(COALESCE(NULLIF(TRIM(um.customer_code), \'\'), NULLIF(TRIM(um.customer_number), \'\')))
                )) AS dealer_name
            FROM user_master um
            LEFT JOIN customer_master cm
                ON TRIM(cm.cuno) = TRIM(COALESCE(NULLIF(TRIM(um.customer_code), \'\'), NULLIF(TRIM(um.customer_number), \'\')))
            WHERE um.deleted_at IS NULL
              AND um.role = :dealer_role
              AND TRIM(COALESCE(NULLIF(TRIM(um.customer_code), \'\'), NULLIF(TRIM(um.customer_number), \'\'))) <> \'\'
        ) dealers
        WHERE 1 = 1
    ';
    $params = [':dealer_role' => DEALER_USER_ROLE];
    if ($search !== '') {
        $sql .= ' AND (
            LOWER(dealer_name) LIKE LOWER(:search)
            OR LOWER(dealer_code) LIKE LOWER(:search)
        )';
        $params[':search'] = '%' . $search . '%';
    }
    $sql .= ' GROUP BY dealer_code ORDER BY dealer_name ASC NULLS LAST, dealer_code ASC LIMIT ' . (int) $limit;

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key === ':dealer_role') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();

    $results = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $code = trim((string) ($row['dealer_code'] ?? ''));
        if ($code === '') {
            continue;
        }
        $name = trim((string) ($row['dealer_name'] ?? ''));
        $labelName = $name !== '' ? $name : $code;
        $results[] = [
            'id' => $code,
            'text' => $labelName . ' - [' . $code . ']',
            'name' => $labelName,
        ];
    }

    return $results;
}

/**
 * Dealer role users only see customers added by their dealer (same dealer code users).
 * Other roles see all customers.
 *
 * @return array{sql: string, params: array<string, mixed>}
 */
function customer_master_list_scope_filter(PDO $conn): array
{
    require_once __DIR__ . '/admin_access_helpers.php';
    require_once __DIR__ . '/current_username_helpers.php';

    if (!is_dealer_user()) {
        return ['sql' => '', 'params' => []];
    }

    $username = current_username();
    $dealerCtx = customer_master_logged_in_dealer_context($conn);
    $dealerCode = trim((string) ($dealerCtx['code'] ?? ''));

    if ($dealerCode !== '') {
        return [
            'sql' => ' AND LOWER(TRIM(COALESCE(added_by, created_by, \'\'))) IN (
                SELECT LOWER(TRIM(um.username))
                FROM user_master um
                WHERE um.deleted_at IS NULL
                  AND um.role = :cm_scope_dealer_role
                  AND TRIM(COALESCE(
                      NULLIF(TRIM(um.customer_code), \'\'),
                      NULLIF(TRIM(um.customer_number), \'\')
                  )) = TRIM(:cm_scope_dealer_code)
            )',
            'params' => [
                ':cm_scope_dealer_role' => DEALER_USER_ROLE,
                ':cm_scope_dealer_code' => $dealerCode,
            ],
        ];
    }

    if ($username === '') {
        return [
            'sql' => ' AND 1 = 0',
            'params' => [],
        ];
    }

    return [
        'sql' => ' AND LOWER(TRIM(COALESCE(added_by, created_by, \'\'))) = LOWER(TRIM(:cm_scope_username))',
        'params' => [':cm_scope_username' => $username],
    ];
}

/**
 * Whether the current user may view/edit this customer master record.
 */
function customer_master_user_can_access_record(PDO $conn, ?array $record): bool
{
    require_once __DIR__ . '/admin_access_helpers.php';

    if ($record === null) {
        return false;
    }

    if (!is_dealer_user()) {
        return true;
    }

    $addedBy = trim((string) ($record['added_by'] ?? ''));
    if ($addedBy === '') {
        $addedBy = trim((string) ($record['created_by'] ?? ''));
    }
    if ($addedBy === '') {
        return false;
    }

    $dealerCtx = customer_master_logged_in_dealer_context($conn);
    $dealerCode = trim((string) ($dealerCtx['code'] ?? ''));

    if ($dealerCode !== '') {
        $stmt = $conn->prepare('
            SELECT 1
            FROM user_master um
            WHERE um.deleted_at IS NULL
              AND um.role = :dealer_role
              AND LOWER(TRIM(um.username)) = LOWER(TRIM(:username))
              AND TRIM(COALESCE(
                  NULLIF(TRIM(um.customer_code), \'\'),
                  NULLIF(TRIM(um.customer_number), \'\')
              )) = TRIM(:dealer_code)
            LIMIT 1
        ');
        $stmt->bindValue(':dealer_role', DEALER_USER_ROLE, PDO::PARAM_INT);
        $stmt->bindValue(':username', $addedBy);
        $stmt->bindValue(':dealer_code', $dealerCode);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    }

    require_once __DIR__ . '/current_username_helpers.php';

    return strcasecmp($addedBy, current_username()) === 0;
}

/**
 * Format dealer label for list/details (dealer who owns / added the customer).
 */
function customer_master_dealer_display_label(array $row): string
{
    $dealerName = trim((string) ($row['dealer_name'] ?? ''));
    $dealerCode = trim((string) ($row['dealer_code'] ?? ''));

    if ($dealerName !== '' && $dealerCode !== '') {
        return $dealerName . ' - [' . $dealerCode . ']';
    }
    if ($dealerName !== '') {
        return $dealerName;
    }
    if ($dealerCode !== '') {
        return $dealerCode;
    }

    return '-';
}

/**
 * Full address line for Customer Master list/details.
 */
function customer_master_full_address_label(array $row): string
{
    $parts = [];
    foreach (['street_1', 'street_2', 'city', 'district', 'state', 'pincode'] as $field) {
        $value = trim((string) ($row[$field] ?? ''));
        if ($value !== '') {
            $parts[] = $value;
        }
    }

    return $parts !== [] ? implode(', ', $parts) : '-';
}

/**
 * Logged-in Dealer User context for auto-filling Dealer Name.
 *
 * @return array{code: string, name: string, text: string, locked: bool}|null
 */
function customer_master_logged_in_dealer_context(PDO $conn): ?array
{
    require_once __DIR__ . '/admin_access_helpers.php';
    require_once __DIR__ . '/current_username_helpers.php';

    if (!is_dealer_user()) {
        return null;
    }

    $username = current_username();
    $code = '';
    if ($username !== '') {
        $stmt = $conn->prepare('
            SELECT
                TRIM(COALESCE(customer_code, \'\')) AS customer_code,
                TRIM(COALESCE(customer_number, \'\')) AS customer_number
            FROM user_master
            WHERE deleted_at IS NULL
              AND LOWER(TRIM(username)) = LOWER(TRIM(:username))
            LIMIT 1
        ');
        $stmt->bindValue(':username', $username);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $code = trim((string) ($row['customer_code'] ?? ''));
        if ($code === '') {
            $code = trim((string) ($row['customer_number'] ?? ''));
        }
    }
    if ($code === '') {
        $code = trim((string) ($_SESSION['customer_number_vayu'] ?? ''));
    }
    if ($code === '') {
        return null;
    }

    $dealer = customer_master_dealer_get($conn, $code);
    if ($dealer === null) {
        $dealer = [
            'code' => $code,
            'name' => $code,
            'text' => $code . ' - [' . $code . ']',
        ];
    }

    return [
        'code' => $dealer['code'],
        'name' => $dealer['name'],
        'text' => $dealer['text'],
        'locked' => true,
    ];
}

/**
 * Force dealer fields for logged-in dealer users; resolve dealer_name from code for others.
 *
 * @param array<string, mixed> $data
 * @return array<string, mixed>
 */
function customer_master_apply_dealer_rules(PDO $conn, array $data): array
{
    $lockedDealer = customer_master_logged_in_dealer_context($conn);
    if ($lockedDealer !== null) {
        $data['dealer_code'] = $lockedDealer['code'];
        $data['dealer_name'] = $lockedDealer['name'];
        return $data;
    }

    $dealerCode = trim((string) ($data['dealer_code'] ?? ''));
    if ($dealerCode !== '') {
        $dealer = customer_master_dealer_get($conn, $dealerCode);
        if ($dealer !== null) {
            $data['dealer_code'] = $dealer['code'];
            $data['dealer_name'] = $dealer['name'];
        }
    }

    return $data;
}

function customer_master_lookup_pincode(PDO $conn, string $pincode): ?array
{
    $pincode = trim($pincode);
    if ($pincode === '' || !preg_match('/^\d{6}$/', $pincode)) {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT
            TRIM(postcode) AS postcode,
            TRIM(city) AS city,
            TRIM(district) AS district,
            TRIM(state) AS state
        FROM postcodes
        WHERE TRIM(postcode) = :pincode
        LIMIT 1
    ');
    $stmt->bindValue(':pincode', $pincode);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function customer_master_validate(PDO $conn, array $data): ?string
{
    if ($data['customer_name'] === '') {
        return 'Customer Name is required.';
    }
    if (strlen($data['customer_name']) > 150) {
        return 'Customer Name cannot exceed 150 characters.';
    }

    if ($data['email'] === '') {
        return 'Email is required.';
    }
    if (
        !filter_var($data['email'], FILTER_VALIDATE_EMAIL)
        || !preg_match(customer_master_email_pattern(), $data['email'])
    ) {
        return 'Please enter a valid email address.';
    }
    if (strlen($data['email']) > 150) {
        return 'Email cannot exceed 150 characters.';
    }
    if (disposable_email_is_blocked($data['email'])) {
        return disposable_email_blocked_message();
    }

    if ($data['mobile'] === '') {
        return 'Mobile is required.';
    }
    if (!preg_match('/^[1-9]\d{9}$/', $data['mobile'])) {
        return 'Mobile must be a valid 10-digit number.';
    }

    if ($data['street_1'] === '') {
        return 'Street 1 is required.';
    }
    if (strlen($data['street_1']) > 255) {
        return 'Street 1 cannot exceed 255 characters.';
    }

    if ($data['street_2'] === '') {
        return 'Street 2 is required.';
    }
    if (strlen($data['street_2']) > 255) {
        return 'Street 2 cannot exceed 255 characters.';
    }

    if ($data['pincode'] === '') {
        return 'Pincode is required.';
    }
    if (!preg_match('/^\d{6}$/', $data['pincode'])) {
        return 'Pincode must be a 6-digit number.';
    }

    $postcode = customer_master_lookup_pincode($conn, $data['pincode']);
    if ($postcode === null) {
        return 'Selected pincode is invalid.';
    }

    // Readonly location fields must match the selected pincode.
    if (
        strcasecmp($data['city'], (string) $postcode['city']) !== 0
        || strcasecmp($data['district'], (string) $postcode['district']) !== 0
        || strcasecmp($data['state'], (string) $postcode['state']) !== 0
    ) {
        return 'City, District, and State must match the selected pincode.';
    }

    if ($data['city'] === '') {
        return 'City is required.';
    }
    if ($data['district'] === '') {
        return 'District is required.';
    }
    if ($data['state'] === '') {
        return 'State is required.';
    }

    $dealerCode = trim((string) ($data['dealer_code'] ?? ''));
    $dealerName = trim((string) ($data['dealer_name'] ?? ''));
    if ($dealerCode === '') {
        return 'Dealer Name is required.';
    }
    if (strlen($dealerCode) > 50) {
        return 'Dealer code cannot exceed 50 characters.';
    }
    if ($dealerName === '') {
        return 'Dealer Name is required.';
    }
    if (strlen($dealerName) > 150) {
        return 'Dealer Name cannot exceed 150 characters.';
    }

    $lockedDealer = customer_master_logged_in_dealer_context($conn);
    if ($lockedDealer === null) {
        $dealer = customer_master_dealer_get($conn, $dealerCode);
        if ($dealer === null) {
            return 'Selected dealer is invalid.';
        }
    }

    $gstNumber = trim((string) ($data['gst_number'] ?? ''));
    if ($gstNumber !== '') {
        if (strlen($gstNumber) > 30) {
            return 'GST Number cannot exceed 30 characters.';
        }
        // if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/', $gstNumber)) {
        //     return 'Enter a valid 15-character GSTIN or leave blank.';
        // }
    }

    $panNumber = trim((string) ($data['pan_number'] ?? ''));
    if ($panNumber !== '') {
        if (strlen($panNumber) > 20) {
            return 'PAN Number cannot exceed 20 characters.';
        }
        if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $panNumber)) {
            return 'Enter a valid 10-character PAN or leave blank.';
        }
    }

    return null;
}

function customer_master_email_exists(PDO $conn, string $email, int $excludeId = 0): bool
{
    customer_master_ensure_schema($conn);

    $sql = '
        SELECT id
        FROM customer_masters
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

function customer_master_mobile_exists(PDO $conn, string $mobile, int $excludeId = 0): bool
{
    customer_master_ensure_schema($conn);

    $sql = '
        SELECT id
        FROM customer_masters
        WHERE TRIM(mobile) = TRIM(:mobile)
          AND deleted_at IS NULL
    ';
    if ($excludeId > 0) {
        $sql .= ' AND id != :exclude_id';
    }
    $sql .= ' LIMIT 1';

    $stmt = $conn->prepare($sql);
    $stmt->bindValue(':mobile', $mobile);
    if ($excludeId > 0) {
        $stmt->bindValue(':exclude_id', $excludeId, PDO::PARAM_INT);
    }
    $stmt->execute();

    return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
}

function customer_master_search_filter(string $searchValue): array
{
    return [
        'sql' => '(
            customer_name ILIKE :search
            OR email ILIKE :search
            OR mobile ILIKE :search
            OR street_1 ILIKE :search
            OR street_2 ILIKE :search
            OR pincode ILIKE :search
            OR city ILIKE :search
            OR district ILIKE :search
            OR state ILIKE :search
            OR dealer_code ILIKE :search
            OR dealer_name ILIKE :search
            OR gst_number ILIKE :search
            OR pan_number ILIKE :search
            OR added_by ILIKE :search
            OR created_by ILIKE :search
        )',
        'params' => [':search' => '%' . $searchValue . '%'],
    ];
}

function customer_master_get_by_id(PDO $conn, int $id): ?array
{
    customer_master_ensure_schema($conn);

    $stmt = $conn->prepare('
        SELECT
            cm.*,
            COALESCE(NULLIF(TRIM(cm.added_by), \'\'), NULLIF(TRIM(cm.created_by), \'\'), \'\') AS added_by_username,
            COALESCE(
                NULLIF(TRIM(um_added.name), \'\'),
                NULLIF(TRIM(um_created.name), \'\'),
                NULLIF(TRIM(cm.added_by), \'\'),
                NULLIF(TRIM(cm.created_by), \'\'),
                \'-\'
            ) AS created_by_name
        FROM customer_masters cm
        LEFT JOIN user_master um_added
            ON LOWER(TRIM(um_added.username)) = LOWER(TRIM(cm.added_by))
           AND um_added.deleted_at IS NULL
        LEFT JOIN user_master um_created
            ON LOWER(TRIM(um_created.username)) = LOWER(TRIM(cm.created_by))
           AND um_created.deleted_at IS NULL
        WHERE cm.id = :id
          AND cm.deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function customer_master_created_by_label(array $record): string
{
    $name = trim((string) ($record['created_by_name'] ?? ''));
    if ($name !== '' && $name !== '-') {
        return $name;
    }

    $username = trim((string) ($record['added_by'] ?? ''));
    if ($username === '') {
        $username = trim((string) ($record['created_by'] ?? ''));
    }

    return $username !== '' ? $username : '-';
}

function customer_master_entry_actions(int $id, array $permissions = [], bool $canAddContact = false): string
{
    $permissions = customer_master_normalize_action_permissions($permissions);
    $encodedId = base64_encode((string) $id);

    $html = '<div class="d-flex gap-1">';

    if ($permissions['view']) {
        $html .= '
            <a href="customer_master_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>';
    }

    if ($permissions['edit']) {
        $html .= '
            <button type="button" class="btn btn-sm btn-outline-dark edit-customer-master-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>';
    }

    if ($canAddContact) {
        $html .= '
            <a href="contact.php?open_form=1&customer_id=' . (int) $id . '"
                class="btn btn-sm btn-outline-dark" title="Add Contact">
                <i class="bi bi-person-plus"></i>
            </a>';
    }

    if ($permissions['delete']) {
        $html .= '
            <a href="delete_customer_master.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this customer?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * @return array{view: bool, add: bool, edit: bool, delete: bool}
 */
function customer_master_normalize_action_permissions(array $permissions): array
{
    return [
        'view' => !empty($permissions['view']),
        'add' => !empty($permissions['add']),
        'edit' => !empty($permissions['edit']),
        'delete' => !empty($permissions['delete']),
    ];
}

/**
 * @return array{view: bool, add: bool, edit: bool, delete: bool}
 */
function customer_master_action_permissions(PDO $conn): array
{
    require_once __DIR__ . '/rbac_access_helpers.php';
    require_once __DIR__ . '/rbac_module_seed_helpers.php';

    customer_master_ensure_rbac($conn);

    return customer_master_normalize_action_permissions([
        'view' => rbac_user_can($conn, 'customer-master', 'view'),
        'add' => rbac_user_can($conn, 'customer-master', 'add'),
        'edit' => rbac_user_can($conn, 'customer-master', 'edit'),
        'delete' => rbac_user_can($conn, 'customer-master', 'delete'),
    ]);
}

function customer_master_ensure_rbac(PDO $conn): void
{
    require_once __DIR__ . '/rbac_module_seed_helpers.php';
    rbac_ensure_module_with_defaults(
        $conn,
        'Customer Master',
        'customer-master',
        'Manage customer master records',
        210
    );
}

function customer_master_select2_label(array $row): string
{
    $name = trim((string) ($row['customer_name'] ?? ''));
    $mobile = trim((string) ($row['mobile'] ?? ''));
    $city = trim((string) ($row['city'] ?? ''));

    if ($name === '') {
        return '-';
    }

    $parts = [$name];
    if ($mobile !== '') {
        $parts[] = $mobile;
    }
    if ($city !== '') {
        $parts[] = $city;
    }

    return implode(' - ', $parts);
}

/**
 * @return array<int, array<string, mixed>>
 */
function customer_master_search_select2(PDO $conn, string $search, int $limit = 50): array
{
    customer_master_ensure_schema($conn);
    $limit = max(1, min(100, $limit));
    $scope = customer_master_list_scope_filter($conn);

    $sql = '
        SELECT id, customer_name, email, mobile, street_1, street_2, pincode, city, district, state
        FROM customer_masters
        WHERE deleted_at IS NULL
    ' . $scope['sql'];
    $params = $scope['params'];

    if ($search !== '') {
        $sql .= ' AND (
            customer_name ILIKE :search
            OR email ILIKE :search
            OR mobile ILIKE :search
            OR city ILIKE :search
        )';
        $params[':search'] = '%' . $search . '%';
    }

    $sql .= ' ORDER BY customer_name ASC, id DESC LIMIT ' . (int) $limit;

    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        if ($key === ':cm_scope_dealer_role') {
            $stmt->bindValue($key, $value, PDO::PARAM_INT);
        } else {
            $stmt->bindValue($key, $value);
        }
    }
    $stmt->execute();

    $results = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $results[] = [
            'id' => (int) $row['id'],
            'text' => customer_master_select2_label($row),
            'customer_name' => trim((string) ($row['customer_name'] ?? '')),
            'email' => trim((string) ($row['email'] ?? '')),
            'mobile' => trim((string) ($row['mobile'] ?? '')),
            'street_1' => trim((string) ($row['street_1'] ?? '')),
            'street_2' => trim((string) ($row['street_2'] ?? '')),
            'pincode' => trim((string) ($row['pincode'] ?? '')),
            'city' => trim((string) ($row['city'] ?? '')),
            'district' => trim((string) ($row['district'] ?? '')),
            'state' => trim((string) ($row['state'] ?? '')),
        ];
    }

    return $results;
}

function customer_master_sanitize_return_url(?string $returnUrl): string
{
    $returnUrl = trim((string) $returnUrl);
    if ($returnUrl === '') {
        return '';
    }

    if (preg_match('#^(https?:)?//#i', $returnUrl) || str_contains($returnUrl, "\n") || str_contains($returnUrl, "\r")) {
        return '';
    }

    $path = parse_url($returnUrl, PHP_URL_PATH);
    $path = is_string($path) ? ltrim($path, '/') : '';
    $base = basename($path !== '' ? $path : $returnUrl);

    if (!in_array($base, ['installed_base.php', 'new_complaint.php'], true)) {
        return '';
    }

    return $returnUrl;
}

function customer_master_user_can_create_from_return(PDO $conn, string $returnUrl = ''): bool
{
    require_once __DIR__ . '/rbac_access_helpers.php';

    $safeReturn = customer_master_sanitize_return_url($returnUrl);
    if ($safeReturn === '') {
        return false;
    }

    $base = basename(parse_url($safeReturn, PHP_URL_PATH) ?: $safeReturn);

    if ($base === 'installed_base.php') {
        return rbac_role_has_permission($conn, 'installed-base-capture', 'add');
    }

    if ($base === 'new_complaint.php') {
        return rbac_role_has_permission($conn, 'complaint-entry', 'add');
    }

    return false;
}

/** @deprecated Use customer_master_user_can_create_from_return() */
function customer_master_user_can_create_from_installed_base(PDO $conn): bool
{
    require_once __DIR__ . '/rbac_access_helpers.php';

    return rbac_role_has_permission($conn, 'installed-base-capture', 'add')
        || rbac_role_has_permission($conn, 'complaint-entry', 'add');
}

function customer_master_require_page_access(PDO $conn, string $returnUrl = ''): void
{
    require_once __DIR__ . '/admin_access_helpers.php';
    require_once __DIR__ . '/login_helpers.php';
    require_once __DIR__ . '/rbac_access_helpers.php';

    login_enforce_session_version($conn);
    admin_ensure_session_role($conn);
    customer_master_ensure_rbac($conn);

    if (customer_master_action_permissions($conn)['view']) {
        return;
    }

    if (customer_master_user_can_create_from_return($conn, $returnUrl)) {
        return;
    }

    rbac_access_denied_redirect();
}

function customer_master_insert(PDO $conn, array $data, string $username): int
{
    customer_master_ensure_schema($conn);

    $stmt = $conn->prepare('
        INSERT INTO customer_masters (
            customer_name, email, mobile, street_1, street_2,
            pincode, city, district, state,
            dealer_code, dealer_name, gst_number, pan_number,
            added_by, created_by, created_at
        ) VALUES (
            :customer_name, :email, :mobile, :street_1, :street_2,
            :pincode, :city, :district, :state,
            :dealer_code, :dealer_name, :gst_number, :pan_number,
            :added_by, :created_by, CURRENT_TIMESTAMP
        )
    ');
    $stmt->bindValue(':customer_name', $data['customer_name']);
    $stmt->bindValue(':email', $data['email']);
    $stmt->bindValue(':mobile', $data['mobile']);
    $stmt->bindValue(':street_1', $data['street_1']);
    $stmt->bindValue(':street_2', $data['street_2']);
    $stmt->bindValue(':pincode', $data['pincode']);
    $stmt->bindValue(':city', $data['city']);
    $stmt->bindValue(':district', $data['district']);
    $stmt->bindValue(':state', $data['state']);
    $stmt->bindValue(':dealer_code', $data['dealer_code'] !== '' ? $data['dealer_code'] : null, $data['dealer_code'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':dealer_name', $data['dealer_name'] !== '' ? $data['dealer_name'] : null, $data['dealer_name'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':gst_number', $data['gst_number'] !== '' ? $data['gst_number'] : null, $data['gst_number'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':pan_number', $data['pan_number'] !== '' ? $data['pan_number'] : null, $data['pan_number'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    if ($username === '') {
        $stmt->bindValue(':added_by', null, PDO::PARAM_NULL);
        $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':added_by', $username);
        $stmt->bindValue(':created_by', $username);
    }
    $stmt->execute();

    return (int) $conn->lastInsertId();
}

function customer_master_update(PDO $conn, int $id, array $data, string $username): void
{
    customer_master_ensure_schema($conn);

    $stmt = $conn->prepare('
        UPDATE customer_masters SET
            customer_name = :customer_name,
            email = :email,
            mobile = :mobile,
            street_1 = :street_1,
            street_2 = :street_2,
            pincode = :pincode,
            city = :city,
            district = :district,
            state = :state,
            dealer_code = :dealer_code,
            dealer_name = :dealer_name,
            gst_number = :gst_number,
            pan_number = :pan_number,
            updated_by = :updated_by,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':customer_name', $data['customer_name']);
    $stmt->bindValue(':email', $data['email']);
    $stmt->bindValue(':mobile', $data['mobile']);
    $stmt->bindValue(':street_1', $data['street_1']);
    $stmt->bindValue(':street_2', $data['street_2']);
    $stmt->bindValue(':pincode', $data['pincode']);
    $stmt->bindValue(':city', $data['city']);
    $stmt->bindValue(':district', $data['district']);
    $stmt->bindValue(':state', $data['state']);
    $stmt->bindValue(':dealer_code', $data['dealer_code'] !== '' ? $data['dealer_code'] : null, $data['dealer_code'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':dealer_name', $data['dealer_name'] !== '' ? $data['dealer_name'] : null, $data['dealer_name'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':gst_number', $data['gst_number'] !== '' ? $data['gst_number'] : null, $data['gst_number'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    $stmt->bindValue(':pan_number', $data['pan_number'] !== '' ? $data['pan_number'] : null, $data['pan_number'] !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
    if ($username === '') {
        $stmt->bindValue(':updated_by', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':updated_by', $username);
    }
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function customer_master_soft_delete(PDO $conn, int $id, string $username = ''): void
{
    customer_master_ensure_schema($conn);

    $stmt = $conn->prepare('
        UPDATE customer_masters
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP,
            updated_by = COALESCE(NULLIF(:updated_by, \'\'), updated_by)
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':updated_by', $username);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}
