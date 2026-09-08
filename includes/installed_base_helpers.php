<?php

require_once __DIR__ . '/complaint_address_helpers.php';
require_once __DIR__ . '/ln_invoice_helpers.php';
require_once __DIR__ . '/system_config_master_helpers.php';
require_once __DIR__ . '/rbac_access_helpers.php';
require_once __DIR__ . '/customer_master_helpers.php';

function installed_base_industry_segments(PDO $conn): array
{
    return scm_get_active_names($conn, 'industry_segment');
}

/**
 * @return array<int, string>
 */
function installed_base_legacy_customer_columns(): array
{
    return [
        'customer_name',
        'street_1',
        'street_2',
        'pincode',
        'city',
        'district',
        'state',
        'mobile',
        'email',
    ];
}

function installed_base_table_has_column(PDO $conn, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'installed_base'
          AND column_name = :column_name
        LIMIT 1
    ");
    $stmt->bindValue(':column_name', $column);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function installed_base_ensure_schema(PDO $conn): void
{
    customer_master_ensure_schema($conn);

    if (!installed_base_table_has_column($conn, 'customer_id')) {
        $conn->exec('ALTER TABLE installed_base ADD COLUMN customer_id INTEGER NULL');
    }

    if (!installed_base_table_has_column($conn, 'downstream')) {
        $conn->exec("ALTER TABLE installed_base ADD COLUMN downstream VARCHAR(3) NULL");
    }

    if (installed_base_table_has_column($conn, 'customer_name')) {
        installed_base_migrate_legacy_customer_fields($conn);

        foreach (installed_base_legacy_customer_columns() as $column) {
            if (installed_base_table_has_column($conn, $column)) {
                $conn->exec('ALTER TABLE installed_base DROP COLUMN IF EXISTS ' . $column);
            }
        }
    }
}

function installed_base_migrate_legacy_customer_fields(PDO $conn): void
{
    $selectCols = ['id'];
    foreach (installed_base_legacy_customer_columns() as $column) {
        if (installed_base_table_has_column($conn, $column)) {
            $selectCols[] = $column;
        }
    }

    $stmt = $conn->query('
        SELECT ' . implode(', ', $selectCols) . '
        FROM installed_base
        WHERE customer_id IS NULL
          AND deleted_at IS NULL
        ORDER BY id ASC
    ');

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $customerId = installed_base_resolve_or_create_customer_from_legacy_row($conn, $row);
        if ($customerId <= 0) {
            continue;
        }

        $update = $conn->prepare('
            UPDATE installed_base
            SET customer_id = :customer_id,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
              AND customer_id IS NULL
        ');
        $update->bindValue(':customer_id', $customerId, PDO::PARAM_INT);
        $update->bindValue(':id', (int) $row['id'], PDO::PARAM_INT);
        $update->execute();
    }
}

function installed_base_resolve_or_create_customer_from_legacy_row(PDO $conn, array $row): int
{
    $email = trim((string) ($row['email'] ?? ''));
    $mobile = trim((string) ($row['mobile'] ?? ''));
    $customerName = trim((string) ($row['customer_name'] ?? ''));

    if ($email !== '') {
        $existing = $conn->prepare('
            SELECT id
            FROM customer_masters
            WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email))
              AND deleted_at IS NULL
            LIMIT 1
        ');
        $existing->bindValue(':email', $email);
        $existing->execute();
        $id = $existing->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
    }

    if ($mobile !== '') {
        $existing = $conn->prepare('
            SELECT id
            FROM customer_masters
            WHERE TRIM(mobile) = TRIM(:mobile)
              AND deleted_at IS NULL
            LIMIT 1
        ');
        $existing->bindValue(':mobile', $mobile);
        $existing->execute();
        $id = $existing->fetchColumn();
        if ($id !== false) {
            return (int) $id;
        }
    }

    if ($customerName === '' || $email === '' || $mobile === '') {
        return 0;
    }

    $street1 = trim((string) ($row['street_1'] ?? ''));
    if ($street1 === '') {
        $street1 = '-';
    }
    $street2 = trim((string) ($row['street_2'] ?? ''));
    if ($street2 === '') {
        $street2 = '-';
    }
    $pincode = trim((string) ($row['pincode'] ?? ''));
    if ($pincode === '' || !preg_match('/^\d{6}$/', $pincode)) {
        $pincode = '000000';
    }
    $city = trim((string) ($row['city'] ?? ''));
    if ($city === '') {
        $city = '-';
    }
    $district = trim((string) ($row['district'] ?? ''));
    if ($district === '') {
        $district = '-';
    }
    $state = trim((string) ($row['state'] ?? ''));
    if ($state === '') {
        $state = '-';
    }

    try {
        return customer_master_insert($conn, [
            'customer_name' => $customerName,
            'email' => $email,
            'mobile' => $mobile,
            'street_1' => $street1,
            'street_2' => $street2,
            'pincode' => $pincode,
            'city' => $city,
            'district' => $district,
            'state' => $state,
        ], 'system-migration');
    } catch (Throwable $e) {
        return 0;
    }
}

function installed_base_customer_join_sql(string $ibAlias = 'ib', string $cmAlias = 'cm'): string
{
    return "LEFT JOIN customer_masters {$cmAlias}
        ON {$cmAlias}.id = {$ibAlias}.customer_id
       AND {$cmAlias}.deleted_at IS NULL";
}

function installed_base_from_post(array $post): array
{
    return [
        'order_ref_id' => trim((string) ($post['order_ref_id'] ?? '')),
        'order_id' => trim((string) ($post['order_id'] ?? '')),
        'fab_number' => trim((string) ($post['fab_number'] ?? '')),
        'customer_id' => trim((string) ($post['customer_id'] ?? '')),
        'dealer_name' => trim((string) ($post['dealer_name'] ?? '')),
        'machine_model_code' => trim((string) ($post['machine_model_code'] ?? '')),
        'machine_model' => trim((string) ($post['machine_model'] ?? '')),
        'invoice_date' => trim((string) ($post['invoice_date'] ?? '')),
        'commissioning_date' => trim((string) ($post['commissioning_date'] ?? '')),
        'running_hours' => trim((string) ($post['running_hours'] ?? '')),
        'downstream' => strtoupper(trim((string) ($post['downstream'] ?? ''))),
        'industry_segment' => trim((string) ($post['industry_segment'] ?? '')),
        'remarks' => trim((string) ($post['remarks'] ?? '')),
    ];
}

function installed_base_validate(PDO $conn, array $data): ?string
{
    installed_base_ensure_schema($conn);

    if ($data['fab_number'] === '') {
        return 'Fab Number is required.';
    }

    $customerId = (int) ($data['customer_id'] ?? 0);
    if ($customerId <= 0) {
        return 'Customer is required.';
    }

    if (customer_master_get_by_id($conn, $customerId) === null) {
        return 'Selected customer is invalid.';
    }

    if ($data['dealer_name'] === '') {
        return 'Dealer Name is required.';
    }

    if ($data['machine_model_code'] === '' || $data['machine_model'] === '') {
        return 'Machine Model is required.';
    }

    if ($data['invoice_date'] === '') {
        return 'Invoice Date is required.';
    }

    if ($data['commissioning_date'] === '') {
        return 'Commissioning Date is required.';
    }

    if ($data['running_hours'] === '') {
        return 'Running Hours is required.';
    }

    if (!is_numeric($data['running_hours']) || (float) $data['running_hours'] <= 0) {
        return 'Running Hours must be greater than 0.';
    }

    if ($data['downstream'] === '') {
        return 'Downstream is required.';
    }

    if (!in_array($data['downstream'], ['YES', 'NO'], true)) {
        return 'Downstream must be YES or NO.';
    }

    if ($data['industry_segment'] === '') {
        return 'Industry Segment is required.';
    }

    if (!scm_option_exists($conn, 'industry_segment', $data['industry_segment'])) {
        return 'Invalid Industry Segment selected.';
    }

    if (strlen($data['remarks']) > 1000) {
        return 'Remarks cannot exceed 1000 characters.';
    }

    return null;
}

/**
 * Find the active installed base id for a FAB number (unique key, case-insensitive).
 */
function installed_base_find_id_by_fab(PDO $conn, string $fabNumber): ?int
{
    $fabNumber = trim($fabNumber);
    if ($fabNumber === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT id
        FROM installed_base
        WHERE LOWER(TRIM(fab_number)) = LOWER(TRIM(:fab_number))
          AND deleted_at IS NULL
        ORDER BY created_at ASC, id ASC
        LIMIT 1
    ');
    $stmt->bindValue(':fab_number', $fabNumber);
    $stmt->execute();

    $id = $stmt->fetchColumn();

    return $id !== false ? (int) $id : null;
}

function installed_base_fab_link_html(PDO $conn, string $fabNumber, array &$cache): string
{
    $fabNumber = trim($fabNumber);
    if ($fabNumber === '') {
        return '-';
    }

    $escaped = htmlspecialchars($fabNumber, ENT_QUOTES, 'UTF-8');
    $key = strtolower($fabNumber);
    if (!array_key_exists($key, $cache)) {
        $cache[$key] = installed_base_find_id_by_fab($conn, $fabNumber) ?? 0;
    }

    $installedBaseId = (int) $cache[$key];
    if ($installedBaseId <= 0) {
        return $escaped;
    }

    $href = htmlspecialchars(
        'installed_base_details.php?id=' . rawurlencode(base64_encode((string) $installedBaseId)),
        ENT_QUOTES,
        'UTF-8'
    );

    return '<a href="' . $href . '" target="_blank" rel="noopener" class="text-primary fw-semibold text-decoration-none">'
        . $escaped
        . '</a>';
}

/**
 * @return array<int, int>
 */
function installed_base_find_ids_by_fab(PDO $conn, string $fabNumber): array
{
    $fabNumber = trim($fabNumber);
    if ($fabNumber === '') {
        return [];
    }

    $stmt = $conn->prepare('
        SELECT id
        FROM installed_base
        WHERE LOWER(TRIM(fab_number)) = LOWER(TRIM(:fab_number))
          AND deleted_at IS NULL
        ORDER BY created_at ASC, id ASC
    ');
    $stmt->bindValue(':fab_number', $fabNumber);
    $stmt->execute();

    $ids = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $ids[] = (int) $id;
    }

    return $ids;
}

/**
 * Prefer a FAB record owned by the current user (username / created_by only).
 * Privileged after-market access does not count as ownership for FAB reuse.
 */
function installed_base_find_accessible_id_by_fab(PDO $conn, string $fabNumber): ?int
{
    foreach (installed_base_find_ids_by_fab($conn, $fabNumber) as $id) {
        if ($id > 0 && installed_base_current_user_owns_record($conn, $id)) {
            return $id;
        }
    }

    return null;
}

function installed_base_fab_assigned_to_other_user_message(): string
{
    return 'This FAB Number is already assigned to another user and cannot be used.';
}

function installed_base_record_has_fab(PDO $conn, int $recordId, string $fabNumber): bool
{
    $fabNumber = trim($fabNumber);
    if ($recordId <= 0 || $fabNumber === '') {
        return false;
    }

    $stmt = $conn->prepare('
        SELECT 1
        FROM installed_base
        WHERE id = :id
          AND deleted_at IS NULL
          AND LOWER(TRIM(fab_number)) = LOWER(TRIM(:fab_number))
        LIMIT 1
    ');
    $stmt->bindValue(':id', $recordId, PDO::PARAM_INT);
    $stmt->bindValue(':fab_number', $fabNumber);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

/**
 * True when the current user is the record owner (created_by or username).
 * Does not grant ownership via admin / after-market list scope.
 */
function installed_base_current_user_owns_record(PDO $conn, int $recordId): bool
{
    if ($recordId <= 0) {
        return false;
    }

    $userId = function_exists('current_user_id') ? (int) (current_user_id($conn) ?? 0) : 0;
    $username = function_exists('current_username') ? trim(current_username()) : '';

    if ($userId <= 0 && $username === '') {
        return false;
    }

    $stmt = $conn->prepare('
        SELECT id
        FROM installed_base
        WHERE id = :id
          AND deleted_at IS NULL
          AND (
                (:check_created_by = 1 AND created_by = :created_by)
             OR (
                    :check_username = 1
                AND LOWER(TRIM(COALESCE(username, \'\'))) = LOWER(TRIM(:username))
             )
          )
        LIMIT 1
    ');
    $stmt->bindValue(':id', $recordId, PDO::PARAM_INT);
    $stmt->bindValue(':check_created_by', $userId > 0 ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':created_by', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':check_username', $username !== '' ? 1 : 0, PDO::PARAM_INT);
    $stmt->bindValue(':username', $username);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

/**
 * True when current user may view/edit the record (owner or after-market scope).
 */
function installed_base_current_user_can_use_record(PDO $conn, int $recordId): bool
{
    if ($recordId <= 0) {
        return false;
    }

    if (function_exists('after_market_user_can_access_record')
        && after_market_user_can_access_record($conn, 'installed_base', $recordId)
    ) {
        return true;
    }

    return installed_base_current_user_owns_record($conn, $recordId);
}

/**
 * Returns an error when FAB exists on another user's record.
 * Applies to all roles (including System Admin / CCS Admin / Management).
 * Own FAB or new FAB => allowed. Claiming another user's FAB => blocked.
 * Editing an existing row that already has this FAB => allowed (edit ACL checked separately).
 */
function installed_base_validate_fab_for_current_user(
    PDO $conn,
    string $fabNumber,
    ?int $existingByFabId = null,
    int $editingRecordId = 0
): ?string {
    $fabNumber = trim($fabNumber);
    if ($fabNumber === '') {
        return 'Fab Number is required.';
    }

    // Updating the same existing record with the same FAB is allowed when the
    // user can open that record. Create/claim of another user's FAB is not.
    if (
        $editingRecordId > 0
        && installed_base_record_has_fab($conn, $editingRecordId, $fabNumber)
        && installed_base_current_user_can_use_record($conn, $editingRecordId)
    ) {
        return null;
    }

    $fabIds = installed_base_find_ids_by_fab($conn, $fabNumber);
    if ($fabIds === []) {
        return null;
    }

    foreach ($fabIds as $fabId) {
        if (installed_base_current_user_owns_record($conn, $fabId)) {
            return null;
        }
    }

    return installed_base_fab_assigned_to_other_user_message();
}

function installed_base_update_record(PDO $conn, int $id, array $data): void
{
    installed_base_ensure_schema($conn);

    $update = $conn->prepare('
        UPDATE installed_base
        SET
            order_ref_id = :order_ref_id,
            order_id = :order_id,
            fab_number = :fab_number,
            customer_id = :customer_id,
            dealer_name = :dealer_name,
            machine_model_code = :machine_model_code,
            machine_model = :machine_model,
            invoice_date = :invoice_date,
            commissioning_date = :commissioning_date,
            running_hours = :running_hours,
            downstream = :downstream,
            industry_segment = :industry_segment,
            remarks = :remarks,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');

    $update->bindValue(':order_ref_id', '0', PDO::PARAM_INT);
    $update->bindValue(':order_id', '0', PDO::PARAM_INT);
    $update->bindValue(':fab_number', $data['fab_number']);
    $update->bindValue(':customer_id', (int) $data['customer_id'], PDO::PARAM_INT);
    $update->bindValue(':dealer_name', $data['dealer_name']);
    $update->bindValue(':machine_model_code', $data['machine_model_code']);
    $update->bindValue(':machine_model', $data['machine_model']);
    $update->bindValue(':invoice_date', $data['invoice_date']);
    $update->bindValue(':commissioning_date', $data['commissioning_date']);
    $update->bindValue(':running_hours', $data['running_hours']);
    $update->bindValue(':downstream', $data['downstream']);
    $update->bindValue(':industry_segment', $data['industry_segment']);
    $update->bindValue(':remarks', $data['remarks'] !== '' ? $data['remarks'] : null);
    $update->bindValue(':id', $id, PDO::PARAM_INT);
    $update->execute();
}

/** Targeted update used by the Warranty Claims "New" modal (commissioning date only). */
function installed_base_update_commissioning_date(PDO $conn, int $id, string $commissioningDate): void
{
    $normalized = installed_base_format_date_for_input($commissioningDate);
    $stmt = $conn->prepare('
        UPDATE installed_base
        SET commissioning_date = :commissioning_date, updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');
    $stmt->bindValue(':commissioning_date', $normalized !== '' ? $normalized : $commissioningDate);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

function installed_base_insert_record(PDO $conn, array $data, int $createdBy, string $username): int
{
    installed_base_ensure_schema($conn);

    $insert = $conn->prepare('
        INSERT INTO installed_base
        (
            order_ref_id,
            order_id,
            fab_number,
            customer_id,
            dealer_name,
            machine_model_code,
            machine_model,
            invoice_date,
            commissioning_date,
            running_hours,
            downstream,
            industry_segment,
            remarks,
            created_by,
            username
        )
        VALUES
        (
            :order_ref_id,
            :order_id,
            :fab_number,
            :customer_id,
            :dealer_name,
            :machine_model_code,
            :machine_model,
            :invoice_date,
            :commissioning_date,
            :running_hours,
            :downstream,
            :industry_segment,
            :remarks,
            :created_by,
            :username
        )
    ');

    $insert->bindValue(':order_ref_id', '0', PDO::PARAM_INT);
    $insert->bindValue(':order_id', '0', PDO::PARAM_INT);
    $insert->bindValue(':fab_number', $data['fab_number']);
    $insert->bindValue(':customer_id', (int) $data['customer_id'], PDO::PARAM_INT);
    $insert->bindValue(':dealer_name', $data['dealer_name']);
    $insert->bindValue(':machine_model_code', $data['machine_model_code']);
    $insert->bindValue(':machine_model', $data['machine_model']);
    $insert->bindValue(':invoice_date', $data['invoice_date']);
    $insert->bindValue(':commissioning_date', $data['commissioning_date']);
    $insert->bindValue(':running_hours', $data['running_hours']);
    $insert->bindValue(':downstream', $data['downstream']);
    $insert->bindValue(':industry_segment', $data['industry_segment']);
    $insert->bindValue(':remarks', $data['remarks'] !== '' ? $data['remarks'] : null);
    $insert->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    $insert->bindValue(':username', $username);
    $insert->execute();

    return (int) $conn->lastInsertId();
}

function installed_base_fab_prefill_row(PDO $conn, string $fabNumber, ?int $complaintId = null): ?array
{
    installed_base_ensure_schema($conn);
    require_once __DIR__ . '/complaint_address_helpers.php';
    complaint_ensure_schema($conn);

    $fabNumber = trim($fabNumber);
    if ($fabNumber === '' && ($complaintId === null || $complaintId <= 0)) {
        return null;
    }

    if ($complaintId !== null && $complaintId > 0) {
        $complaintStmt = $conn->prepare('
            SELECT
                c.customer_id,
                cm.customer_name,
                cm.street_1,
                cm.street_2,
                cm.pincode,
                cm.city,
                cm.district,
                cm.state,
                cm.mobile,
                cm.email,
                c.complaint_description AS remarks
            FROM complaints c
            ' . complaint_customer_join_sql('c', 'cm') . '
            WHERE c.id = :id
              AND c.deleted_at IS NULL
            LIMIT 1
        ');
        $complaintStmt->bindValue(':id', $complaintId, PDO::PARAM_INT);
        $complaintStmt->execute();
        $complaintRow = $complaintStmt->fetch(PDO::FETCH_ASSOC);
        if ($complaintRow) {
            $complaintRow['customer_label'] = customer_master_select2_label([
                'customer_name' => $complaintRow['customer_name'] ?? '',
                'mobile' => $complaintRow['mobile'] ?? '',
                'city' => $complaintRow['city'] ?? '',
            ]);
            $complaintRow['has_installed_base'] = false;
            return $complaintRow;
        }
    }

    if ($fabNumber === '') {
        return null;
    }

    $installedBaseRow = installed_base_latest_record_by_fab($conn, $fabNumber);
    if ($installedBaseRow !== null) {
        $installedBaseRow['has_installed_base'] = true;
        return $installedBaseRow;
    }

    $complaintByFabStmt = $conn->prepare('
        SELECT
            c.customer_id,
            cm.customer_name,
            cm.street_1,
            cm.street_2,
            cm.pincode,
            cm.city,
            cm.district,
            cm.state,
            cm.mobile,
            cm.email,
            c.complaint_description AS remarks
        FROM complaints c
        ' . complaint_customer_join_sql('c', 'cm') . '
        WHERE c.fab_number = :fab_number
          AND c.deleted_at IS NULL
        ORDER BY c.created_at DESC, c.id DESC
        LIMIT 1
    ');
    $complaintByFabStmt->bindValue(':fab_number', $fabNumber);
    $complaintByFabStmt->execute();
    $complaintByFab = $complaintByFabStmt->fetch(PDO::FETCH_ASSOC);

    if (!$complaintByFab) {
        return null;
    }

    $complaintByFab['customer_label'] = customer_master_select2_label([
        'customer_name' => $complaintByFab['customer_name'] ?? '',
        'mobile' => $complaintByFab['mobile'] ?? '',
        'city' => $complaintByFab['city'] ?? '',
    ]);
    $complaintByFab['has_installed_base'] = false;

    return $complaintByFab;
}

/**
 * @return array{id:int,text:string,mobile:string,email:string}|null
 */
function installed_base_match_customer_from_name(PDO $conn, string $customerName): ?array
{
    $customerName = trim($customerName);
    if ($customerName === '') {
        return null;
    }

    customer_master_ensure_schema($conn);

    $stmt = $conn->prepare('
        SELECT id, customer_name, mobile, email, city
        FROM customer_masters
        WHERE LOWER(TRIM(customer_name)) = LOWER(TRIM(:customer_name))
          AND deleted_at IS NULL
        ORDER BY id DESC
        LIMIT 1
    ');
    $stmt->bindValue(':customer_name', $customerName);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'text' => customer_master_select2_label($row),
        'mobile' => trim((string) ($row['mobile'] ?? '')),
        'email' => trim((string) ($row['email'] ?? '')),
    ];
}

/**
 * Latest active Installed Base row for a FAB (for prefill of saved IB fields).
 */
function installed_base_latest_record_by_fab(PDO $conn, string $fabNumber): ?array
{
    installed_base_ensure_schema($conn);

    $fabNumber = trim($fabNumber);
    if ($fabNumber === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT
            ib.customer_id,
            cm.customer_name,
            cm.street_1,
            cm.street_2,
            cm.pincode,
            cm.city,
            cm.district,
            cm.state,
            cm.mobile,
            cm.email,
            ib.remarks,
            ib.commissioning_date,
            ib.running_hours,
            ib.downstream,
            ib.industry_segment,
            ib.machine_model_code,
            ib.machine_model
        FROM installed_base ib
        ' . installed_base_customer_join_sql('ib', 'cm') . '
        WHERE TRIM(ib.fab_number) = TRIM(:fab_number)
          AND ib.deleted_at IS NULL
        ORDER BY COALESCE(ib.updated_at, ib.created_at) DESC, ib.id DESC
        LIMIT 1
    ');
    $stmt->bindValue(':fab_number', $fabNumber);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $row['customer_label'] = customer_master_select2_label([
        'customer_name' => $row['customer_name'] ?? '',
        'mobile' => $row['mobile'] ?? '',
        'city' => $row['city'] ?? '',
    ]);

    return $row;
}

function installed_base_pending_order_normalize_row(array $row): array
{
    $ordno = trim((string) ($row['ordno'] ?? ''));
    $row['ordno'] = $ordno;
    $row['order_id'] = $ordno;

    return $row;
}

function installed_base_pending_order_search(PDO $conn, string $term, int $limit = 25): array
{
    $term = trim($term);

    if ($term === '') {
        return [];
    }

    $stmt = $conn->prepare("
        SELECT DISTINCT ON (TRIM(p.ordno))
            TRIM(p.ordno) AS ordno,
            TRIM(p.cuname) AS cuname,
            p.orddt,
            TRIM(COALESCE(p.indentno, '')) AS indentno
        FROM pendingordersnew p
        WHERE TRIM(p.ordno) ILIKE :term
           OR TRIM(p.cuname) ILIKE :term
           OR TRIM(COALESCE(p.indentno, '')) ILIKE :term
        ORDER BY TRIM(p.ordno), p.orddt DESC NULLS LAST
        LIMIT :limit
    ");
    $stmt->bindValue(':term', '%' . $term . '%');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = [];

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = installed_base_pending_order_normalize_row($row);
    }

    return $rows;
}

function installed_base_pending_order_get_by_ordno(PDO $conn, string $ordno): ?array
{
    $ordno = trim($ordno);

    if ($ordno === '') {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT DISTINCT ON (TRIM(p.ordno))
            TRIM(p.ordno) AS ordno,
            TRIM(p.cuname) AS cuname,
            p.orddt,
            TRIM(COALESCE(p.indentno, '')) AS indentno
        FROM pendingordersnew p
        WHERE TRIM(p.ordno) = :ordno
        ORDER BY TRIM(p.ordno), p.orddt DESC NULLS LAST
        LIMIT 1
    ");
    $stmt->bindValue(':ordno', $ordno);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ? installed_base_pending_order_normalize_row($row) : null;
}

function installed_base_pending_order_to_select2_result(array $row): array
{
    $row = installed_base_pending_order_normalize_row($row);
    $ordno = $row['order_id'];
    $customerName = trim((string) ($row['cuname'] ?? ''));
    //$text = $customerName !== '' ? $ordno . '   ' . $customerName : $ordno;
    $text = $ordno;

    return [
        'id' => $ordno,
        'text' => $text,
        'order_id' => $ordno,
        'order_ref_id' => $ordno,
    ];
}

function installed_base_resolve_order_ref_id(string $ordno): ?int
{
    $ordno = trim($ordno);

    if ($ordno === '' || !ctype_digit($ordno)) {
        return null;
    }

    return (int) $ordno;
}

function installed_base_bind_order_ref_id(PDOStatement $stmt, string $param, string $ordno): void
{
    $orderRefId = installed_base_resolve_order_ref_id($ordno);

    if ($orderRefId !== null) {
        $stmt->bindValue($param, $orderRefId, PDO::PARAM_INT);

        return;
    }

    $stmt->bindValue($param, null, PDO::PARAM_NULL);
}

function installed_base_get_order(PDO $conn, string $ordno): ?array
{
    return installed_base_pending_order_get_by_ordno($conn, $ordno);
}

function installed_base_format_date(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    $normalized = installed_base_format_date_for_input((string) $value);
    if ($normalized === '') {
        return '-';
    }

    $timestamp = strtotime($normalized);
    if ($timestamp === false) {
        return '-';
    }

    return date('d M Y', $timestamp);
}

/**
 * Normalize DB date values to Y-m-d for HTML date inputs.
 * Supports Y-m-d, DD.MM.YYYY, DD/MM/YYYY, and DD-MM-YYYY.
 */
function installed_base_format_date_for_input(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $value, $matches)) {
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
    }

    if (preg_match('/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})/', $value, $matches)) {
        return sprintf(
            '%04d-%02d-%02d',
            (int) $matches[3],
            (int) $matches[2],
            (int) $matches[1]
        );
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        return '';
    }

    return date('Y-m-d', $timestamp);
}

function installed_base_format_datetime(?string $value): string
{
    if (empty($value)) {
        return '-';
    }

    return date('d M Y h:i A', strtotime($value));
}

function installed_base_display_value($value): string
{
    if ($value === null || trim((string) $value) === '') {
        return '-';
    }

    return trim((string) $value);
}

function installed_base_added_by_label(array $row): string
{
    $name = trim((string) ($row['added_by_name'] ?? ''));
    if ($name !== '') {
        return $name;
    }

    return installed_base_display_value($row['username'] ?? null);
}

function installed_base_machine_model_label(array $row): string
{
    $code = trim((string) ($row['machine_model_code'] ?? ''));
    $description = trim((string) ($row['machine_model'] ?? ''));

    if ($code !== '' && $description !== '') {
        return $code . ' - ' . $description;
    }

    if ($description !== '') {
        return $description;
    }

    if ($code !== '') {
        return $code;
    }

    return '-';
}

/**
 * @return array{view: bool, add: bool, edit: bool, delete: bool, service_log_add: bool, spare_parts_add: bool}
 */
function installed_base_normalize_action_permissions(array $permissions): array
{
    return [
        'view' => !empty($permissions['view']),
        'add' => !empty($permissions['add']),
        'edit' => !empty($permissions['edit']),
        'delete' => !empty($permissions['delete']),
        'service_log_add' => !empty($permissions['service_log_add']),
        'spare_parts_add' => !empty($permissions['spare_parts_add']),
    ];
}

function installed_base_entry_actions(
    int $id,
    array $permissions = [],
    bool $hasServiceLog = false
): string {
    $permissions = installed_base_normalize_action_permissions($permissions);
    $encodedId = base64_encode((string) $id);

    $html = '<div class="d-flex gap-1">';

    if ($permissions['view']) {
        $html .= '
            <a href="installed_base_details.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark" title="View">
                <i class="bi bi-eye"></i>
            </a>';
    }

    if ($permissions['edit']) {
        $html .= '
            <button type="button" class="btn btn-sm btn-outline-dark edit-installed-base-btn"
                data-id="' . $id . '" title="Edit">
                <i class="bi bi-pencil"></i>
            </button>';
    }

    if ($permissions['service_log_add']) {
        $html .= '
            <button type="button" class="btn btn-sm btn-outline-dark add-service-log-btn"
                data-id="' . $id . '" title="Add Service Log Capture">
                <i class="bi bi-clipboard-pulse"></i>
            </button>';
    }

    if ($permissions['spare_parts_add']) {
        $html .= '
            <button type="button" class="btn btn-sm btn-outline-dark add-spare-parts-btn"
                data-id="' . $id . '" data-prefill="installed_base" title="Add Spare Parts Consumption">
                <i class="bi bi-gear"></i>
            </button>';
    }

    if ($permissions['delete']) {
        $html .= '
            <a href="delete_installed_base.php?id=' . htmlspecialchars($encodedId, ENT_QUOTES, 'UTF-8') . '"
                class="btn btn-sm btn-outline-dark"
                onclick="return confirm(\'Delete this installed base record?\');" title="Delete">
                <i class="bi bi-trash"></i>
            </a>';
    }

    $html .= '</div>';

    return $html;
}