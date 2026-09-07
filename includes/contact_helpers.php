<?php

require_once __DIR__ . '/rbac_helpers.php';
require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/disposable_email_helpers.php';
require_once __DIR__ . '/customer_master_helpers.php';

function contact_ensure_schema(PDO $conn): void
{
    customer_master_ensure_schema($conn);

    $tableStmt = $conn->prepare("
        SELECT 1
        FROM information_schema.tables
        WHERE table_schema = 'public'
          AND table_name = 'contacts'
        LIMIT 1
    ");
    $tableStmt->execute();
    if ((bool) $tableStmt->fetchColumn()) {
        return;
    }

    $conn->exec("
        CREATE TABLE contacts (
            id SERIAL PRIMARY KEY,
            customer_id INTEGER NOT NULL,
            first_name VARCHAR(100) NOT NULL,
            last_name VARCHAR(100) NOT NULL,
            email VARCHAR(150) NOT NULL,
            mobile VARCHAR(15) NOT NULL,
            created_by VARCHAR(150) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_by VARCHAR(150) NULL,
            updated_at TIMESTAMP NULL,
            deleted_at TIMESTAMP NULL,
            CONSTRAINT contacts_customer_id_fkey
                FOREIGN KEY (customer_id) REFERENCES customer_masters (id)
        )
    ");
    $conn->exec("
        CREATE UNIQUE INDEX contacts_email_active_uidx
        ON contacts (LOWER(TRIM(email)))
        WHERE deleted_at IS NULL
    ");
    $conn->exec("
        CREATE UNIQUE INDEX contacts_mobile_active_uidx
        ON contacts (TRIM(mobile))
        WHERE deleted_at IS NULL
    ");
    $conn->exec("
        CREATE INDEX contacts_customer_id_idx
        ON contacts (customer_id)
        WHERE deleted_at IS NULL
    ");
}

function contact_email_pattern(): string
{
    return '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$/';
}

function contact_name_pattern(): string
{
    return '/^[A-Za-z]+(?:\s+[A-Za-z]+)*$/';
}

function contact_from_post(array $post): array
{
    return [
        'customer_id' => (int) ($post['customer_id'] ?? 0),
        'first_name' => trim((string) ($post['first_name'] ?? '')),
        'last_name' => trim((string) ($post['last_name'] ?? '')),
        'email' => trim((string) ($post['email'] ?? '')),
        'mobile' => trim((string) ($post['mobile'] ?? '')),
    ];
}

function contact_validate(PDO $conn, array $data): ?string
{
    contact_ensure_schema($conn);

    if ((int) ($data['customer_id'] ?? 0) <= 0) {
        return 'Customer is required.';
    }
    if (customer_master_get_by_id($conn, (int) $data['customer_id']) === null) {
        return 'Selected customer is invalid.';
    }

    if ($data['first_name'] === '') {
        return 'First Name is required.';
    }
    if (strlen($data['first_name']) > 100) {
        return 'First Name cannot exceed 100 characters.';
    }
    if (!preg_match(contact_name_pattern(), $data['first_name'])) {
        return 'First Name can contain only alphabetic characters and spaces.';
    }

    if ($data['last_name'] === '') {
        return 'Last Name is required.';
    }
    if (strlen($data['last_name']) > 100) {
        return 'Last Name cannot exceed 100 characters.';
    }
    if (!preg_match(contact_name_pattern(), $data['last_name'])) {
        return 'Last Name can contain only alphabetic characters and spaces.';
    }

    if ($data['email'] === '') {
        return 'Email is required.';
    }
    if (
        !filter_var($data['email'], FILTER_VALIDATE_EMAIL)
        || !preg_match(contact_email_pattern(), $data['email'])
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

    return null;
}

function contact_email_exists(PDO $conn, string $email, int $excludeId = 0): bool
{
    contact_ensure_schema($conn);

    $sql = '
        SELECT id
        FROM contacts
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

function contact_mobile_exists(PDO $conn, string $mobile, int $excludeId = 0): bool
{
    contact_ensure_schema($conn);

    $sql = '
        SELECT id
        FROM contacts
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

function contact_customer_join_sql(string $contactAlias = 'ct', string $cmAlias = 'cm'): string
{
    return "LEFT JOIN customer_masters {$cmAlias}
        ON {$cmAlias}.id = {$contactAlias}.customer_id
       AND {$cmAlias}.deleted_at IS NULL";
}

function contact_search_filter(string $searchValue): array
{
    return [
        'sql' => '(
            ct.first_name ILIKE :search
            OR ct.last_name ILIKE :search
            OR ct.email ILIKE :search
            OR ct.mobile ILIKE :search
            OR cm.customer_name ILIKE :search
            OR CAST(ct.id AS TEXT) ILIKE :search
        )',
        'params' => [':search' => '%' . $searchValue . '%'],
    ];
}

function contact_get_by_id(PDO $conn, int $id): ?array
{
    contact_ensure_schema($conn);

    $stmt = $conn->prepare('
        SELECT
            ct.*,
            cm.customer_name,
            cm.mobile AS customer_mobile,
            cm.city AS customer_city,
            COALESCE(NULLIF(TRIM(um.name), \'\'), NULLIF(TRIM(ct.created_by), \'\'), \'-\') AS created_by_name
        FROM contacts ct
        ' . contact_customer_join_sql('ct', 'cm') . '
        LEFT JOIN user_master um
            ON LOWER(TRIM(um.username)) = LOWER(TRIM(ct.created_by))
           AND um.deleted_at IS NULL
        WHERE ct.id = :id
          AND ct.deleted_at IS NULL
        LIMIT 1
    ');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function contact_created_by_label(array $record): string
{
    $name = trim((string) ($record['created_by_name'] ?? ''));
    if ($name !== '' && $name !== '-') {
        return $name;
    }

    $username = trim((string) ($record['created_by'] ?? ''));

    return $username !== '' ? $username : '-';
}

function contact_full_name(array $record): string
{
    $first = trim((string) ($record['first_name'] ?? ''));
    $last = trim((string) ($record['last_name'] ?? ''));
    $full = trim($first . ' ' . $last);

    return $full !== '' ? $full : '-';
}

function contact_customer_label(array $row): string
{
    return customer_master_select2_label([
        'customer_name' => $row['customer_name'] ?? '',
        'mobile' => $row['customer_mobile'] ?? ($row['mobile'] ?? ''),
        'city' => $row['customer_city'] ?? ($row['city'] ?? ''),
    ]);
}

function contact_entry_actions(int $id): string
{
    $encodedId = base64_encode((string) $id);

    return '
        <div class="d-flex gap-1">
            <a href="contact_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>
            <button type="button" class="btn btn-sm btn-outline-dark edit-contact-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>
            <a href="delete_contact.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this contact?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>
        </div>
    ';
}

function contact_require_page_access(PDO $conn): void
{
    require_once __DIR__ . '/admin_access_helpers.php';
    require_once __DIR__ . '/login_helpers.php';
    login_enforce_session_version($conn);
    admin_ensure_session_role($conn);

    if (is_system_admin()) {
        return;
    }

    $_SESSION['error_message'] = 'Access denied. System Admin privileges required.';
    header('Location: dashboard.php');
    exit;
}

function contact_insert(PDO $conn, array $data, string $username): int
{
    contact_ensure_schema($conn);

    $stmt = $conn->prepare('
        INSERT INTO contacts (
            customer_id, first_name, last_name, email, mobile, created_by, created_at
        ) VALUES (
            :customer_id, :first_name, :last_name, :email, :mobile, :created_by, CURRENT_TIMESTAMP
        )
    ');
    $stmt->bindValue(':customer_id', (int) $data['customer_id'], PDO::PARAM_INT);
    $stmt->bindValue(':first_name', $data['first_name']);
    $stmt->bindValue(':last_name', $data['last_name']);
    $stmt->bindValue(':email', $data['email']);
    $stmt->bindValue(':mobile', $data['mobile']);
    if ($username === '') {
        $stmt->bindValue(':created_by', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':created_by', $username);
    }
    $stmt->execute();

    return (int) $conn->lastInsertId();
}

function contact_update(PDO $conn, int $id, array $data, string $username): void
{
    contact_ensure_schema($conn);

    $stmt = $conn->prepare('
        UPDATE contacts SET
            customer_id = :customer_id,
            first_name = :first_name,
            last_name = :last_name,
            email = :email,
            mobile = :mobile,
            updated_by = :updated_by,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':customer_id', (int) $data['customer_id'], PDO::PARAM_INT);
    $stmt->bindValue(':first_name', $data['first_name']);
    $stmt->bindValue(':last_name', $data['last_name']);
    $stmt->bindValue(':email', $data['email']);
    $stmt->bindValue(':mobile', $data['mobile']);
    if ($username === '') {
        $stmt->bindValue(':updated_by', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':updated_by', $username);
    }
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function contact_soft_delete(PDO $conn, int $id, string $username): void
{
    contact_ensure_schema($conn);

    $stmt = $conn->prepare('
        UPDATE contacts SET
            deleted_at = CURRENT_TIMESTAMP,
            updated_by = :updated_by,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    if ($username === '') {
        $stmt->bindValue(':updated_by', null, PDO::PARAM_NULL);
    } else {
        $stmt->bindValue(':updated_by', $username);
    }
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}
