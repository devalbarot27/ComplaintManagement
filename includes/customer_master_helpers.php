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
        return;
    }

    if ($hasNew) {
        return;
    }

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

function customer_master_email_pattern(): string
{
    return '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/';
}

function customer_master_from_post(array $post): array
{
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
    ];
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
            COALESCE(NULLIF(TRIM(um.name), \'\'), NULLIF(TRIM(cm.created_by), \'\'), \'-\') AS created_by_name
        FROM customer_masters cm
        LEFT JOIN user_master um
            ON LOWER(TRIM(um.username)) = LOWER(TRIM(cm.created_by))
           AND um.deleted_at IS NULL
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

    $username = trim((string) ($record['created_by'] ?? ''));

    return $username !== '' ? $username : '-';
}

function customer_master_entry_actions(int $id): string
{
    $encodedId = base64_encode((string) $id);

    return '
        <div class="d-flex gap-1">
            <a href="customer_master_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-customer-master-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_customer_master.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this customer?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
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

    $sql = '
        SELECT id, customer_name, email, mobile, street_1, street_2, pincode, city, district, state
        FROM customer_masters
        WHERE deleted_at IS NULL
    ';
    $params = [];

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
        $stmt->bindValue($key, $value);
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

    if (is_system_admin()) {
        return true;
    }

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

    return is_system_admin()
        || rbac_role_has_permission($conn, 'installed-base-capture', 'add')
        || rbac_role_has_permission($conn, 'complaint-entry', 'add');
}

function customer_master_require_page_access(PDO $conn, string $returnUrl = ''): void
{
    require_once __DIR__ . '/admin_access_helpers.php';
    require_once __DIR__ . '/login_helpers.php';
    login_enforce_session_version($conn);
    admin_ensure_session_role($conn);

    if (is_system_admin()) {
        return;
    }

    if (customer_master_user_can_create_from_return($conn, $returnUrl)) {
        return;
    }

    $_SESSION['error_message'] = 'Access denied. System Admin privileges required.';
    header('Location: dashboard.php');
    exit;
}

function customer_master_insert(PDO $conn, array $data, string $username): int
{
    customer_master_ensure_schema($conn);

    $stmt = $conn->prepare('
        INSERT INTO customer_masters (
            customer_name, email, mobile, street_1, street_2,
            pincode, city, district, state, created_by, created_at
        ) VALUES (
            :customer_name, :email, :mobile, :street_1, :street_2,
            :pincode, :city, :district, :state, :created_by, CURRENT_TIMESTAMP
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
    if ($username === '') {
        $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
    } else {
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
