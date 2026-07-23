<?php

require_once __DIR__ . '/complaint_address_helpers.php';
require_once __DIR__ . '/ln_invoice_helpers.php';
require_once __DIR__ . '/system_config_master_helpers.php';
require_once __DIR__ . '/rbac_access_helpers.php';

function installed_base_industry_segments(PDO $conn): array
{
    return scm_get_active_names($conn, 'industry_segment');
}

function installed_base_address_search_columns(): array
{
    return [
        'street_1',
        'street_2',
        'pincode',
        'city',
        'district',
        'state',
        'address',
    ];
}

function installed_base_address_display_value(array $row, string $field): string
{
    $value = trim((string) ($row[$field] ?? ''));

    if ($field === 'street_1' && $value === '' && !empty($row['address'])) {
        return trim((string) $row['address']);
    }

    return $value !== '' ? $value : '-';
}

function installed_base_from_post(array $post): array
{
    return array_merge([
        'order_ref_id' => trim((string) ($post['order_ref_id'] ?? '')),
        'order_id' => trim((string) ($post['order_id'] ?? '')),
        'fab_number' => trim((string) ($post['fab_number'] ?? '')),
        'customer_name' => trim((string) ($post['customer_name'] ?? '')),
        'mobile' => trim((string) ($post['mobile'] ?? '')),
        'email' => trim((string) ($post['email'] ?? '')),
        'dealer_name' => trim((string) ($post['dealer_name'] ?? '')),
        'machine_model_code' => trim((string) ($post['machine_model_code'] ?? '')),
        'machine_model' => trim((string) ($post['machine_model'] ?? '')),
        'invoice_date' => trim((string) ($post['invoice_date'] ?? '')),
        'commissioning_date' => trim((string) ($post['commissioning_date'] ?? '')),
        'running_hours' => trim((string) ($post['running_hours'] ?? '')),
        'industry_segment' => trim((string) ($post['industry_segment'] ?? '')),
        'remarks' => trim((string) ($post['remarks'] ?? '')),
    ], complaint_address_from_post($post));
}

function installed_base_validate(PDO $conn, array $data): ?string
{
    if ($data['fab_number'] === '') {
        return 'Fab Number is required.';
    }

    if ($data['customer_name'] === '') {
        return 'Customer Name is required.';
    }

    $addressError = complaint_validate_address_fields($data);
    if ($addressError !== null) {
        return $addressError;
    }

    if ($data['mobile'] === '') {
        return 'Mobile is required.';
    }

    if (!preg_match('/^[1-9]\d{9}$/', $data['mobile'])) {
        return 'Mobile must be a valid 10-digit number.';
    }

    if ($data['email'] === '') {
        return 'Email is required.';
    }

    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return 'Email must be a valid email address.';
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
    $update = $conn->prepare('
        UPDATE installed_base
        SET
            order_ref_id = :order_ref_id,
            order_id = :order_id,
            fab_number = :fab_number,
            customer_name = :customer_name,
            street_1 = :street_1,
            street_2 = :street_2,
            pincode = :pincode,
            city = :city,
            district = :district,
            state = :state,
            mobile = :mobile,
            email = :email,
            dealer_name = :dealer_name,
            machine_model_code = :machine_model_code,
            machine_model = :machine_model,
            invoice_date = :invoice_date,
            commissioning_date = :commissioning_date,
            running_hours = :running_hours,
            industry_segment = :industry_segment,
            remarks = :remarks,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ');

    $update->bindValue(':order_ref_id', '0', PDO::PARAM_INT);
    $update->bindValue(':order_id', '0', PDO::PARAM_INT);
    $update->bindValue(':fab_number', $data['fab_number']);
    $update->bindValue(':customer_name', $data['customer_name']);
    $update->bindValue(':street_1', $data['street_1']);
    $update->bindValue(':street_2', $data['street_2'] !== '' ? $data['street_2'] : null);
    $update->bindValue(':pincode', $data['pincode']);
    $update->bindValue(':city', $data['city']);
    $update->bindValue(':district', $data['district']);
    $update->bindValue(':state', $data['state']);
    $update->bindValue(':mobile', $data['mobile']);
    $update->bindValue(':email', $data['email']);
    $update->bindValue(':dealer_name', $data['dealer_name']);
    $update->bindValue(':machine_model_code', $data['machine_model_code']);
    $update->bindValue(':machine_model', $data['machine_model']);
    $update->bindValue(':invoice_date', $data['invoice_date']);
    $update->bindValue(':commissioning_date', $data['commissioning_date']);
    $update->bindValue(':running_hours', $data['running_hours']);
    $update->bindValue(':industry_segment', $data['industry_segment']);
    $update->bindValue(':remarks', $data['remarks'] !== '' ? $data['remarks'] : null);
    $update->bindValue(':id', $id, PDO::PARAM_INT);
    $update->execute();
}

function installed_base_insert_record(PDO $conn, array $data, int $createdBy, string $username): int
{
    $insert = $conn->prepare('
        INSERT INTO installed_base
        (
            order_ref_id,
            order_id,
            fab_number,
            customer_name,
            street_1,
            street_2,
            pincode,
            city,
            district,
            state,
            mobile,
            email,
            dealer_name,
            machine_model_code,
            machine_model,
            invoice_date,
            commissioning_date,
            running_hours,
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
            :customer_name,
            :street_1,
            :street_2,
            :pincode,
            :city,
            :district,
            :state,
            :mobile,
            :email,
            :dealer_name,
            :machine_model_code,
            :machine_model,
            :invoice_date,
            :commissioning_date,
            :running_hours,
            :industry_segment,
            :remarks,
            :created_by,
            :username
        )
    ');

    $insert->bindValue(':order_ref_id', '0', PDO::PARAM_INT);
    $insert->bindValue(':order_id', '0', PDO::PARAM_INT);
    $insert->bindValue(':fab_number', $data['fab_number']);
    $insert->bindValue(':customer_name', $data['customer_name']);
    $insert->bindValue(':street_1', $data['street_1']);
    $insert->bindValue(':street_2', $data['street_2'] !== '' ? $data['street_2'] : null);
    $insert->bindValue(':pincode', $data['pincode']);
    $insert->bindValue(':city', $data['city']);
    $insert->bindValue(':district', $data['district']);
    $insert->bindValue(':state', $data['state']);
    $insert->bindValue(':mobile', $data['mobile']);
    $insert->bindValue(':email', $data['email']);
    $insert->bindValue(':dealer_name', $data['dealer_name']);
    $insert->bindValue(':machine_model_code', $data['machine_model_code']);
    $insert->bindValue(':machine_model', $data['machine_model']);
    $insert->bindValue(':invoice_date', $data['invoice_date']);
    $insert->bindValue(':commissioning_date', $data['commissioning_date']);
    $insert->bindValue(':running_hours', $data['running_hours']);
    $insert->bindValue(':industry_segment', $data['industry_segment']);
    $insert->bindValue(':remarks', $data['remarks'] !== '' ? $data['remarks'] : null);
    $insert->bindValue(':created_by', $createdBy, PDO::PARAM_INT);
    $insert->bindValue(':username', $username);
    $insert->execute();

    return (int) $conn->lastInsertId();
}

function installed_base_fab_prefill_row(PDO $conn, string $fabNumber, ?int $complaintId = null): ?array
{
    $fabNumber = trim($fabNumber);
    if ($fabNumber === '' && ($complaintId === null || $complaintId <= 0)) {
        return null;
    }

    if ($complaintId !== null && $complaintId > 0) {
        $complaintStmt = $conn->prepare('
            SELECT
                customer_name,
                street_1,
                street_2,
                pincode,
                city,
                district,
                state,
                complaint_description AS remarks
            FROM complaints
            WHERE id = :id
              AND deleted_at IS NULL
            LIMIT 1
        ');
        $complaintStmt->bindValue(':id', $complaintId, PDO::PARAM_INT);
        $complaintStmt->execute();
        $complaintRow = $complaintStmt->fetch(PDO::FETCH_ASSOC);
        if ($complaintRow) {
            $complaintRow['mobile'] = '';
            $complaintRow['email'] = '';
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
            customer_name,
            street_1,
            street_2,
            pincode,
            city,
            district,
            state,
            complaint_description AS remarks
        FROM complaints
        WHERE fab_number = :fab_number
          AND deleted_at IS NULL
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ');
    $complaintByFabStmt->bindValue(':fab_number', $fabNumber);
    $complaintByFabStmt->execute();
    $complaintByFab = $complaintByFabStmt->fetch(PDO::FETCH_ASSOC);

    if (!$complaintByFab) {
        return null;
    }

    $complaintByFab['mobile'] = '';
    $complaintByFab['email'] = '';
    $complaintByFab['has_installed_base'] = false;

    return $complaintByFab;
}

/**
 * Latest active Installed Base row for a FAB (for prefill of saved IB fields).
 */
function installed_base_latest_record_by_fab(PDO $conn, string $fabNumber): ?array
{
    $fabNumber = trim($fabNumber);
    if ($fabNumber === '') {
        return null;
    }

    $stmt = $conn->prepare('
        SELECT
            customer_name,
            street_1,
            street_2,
            pincode,
            city,
            district,
            state,
            mobile,
            email,
            remarks,
            commissioning_date,
            running_hours,
            industry_segment,
            machine_model_code,
            machine_model
        FROM installed_base
        WHERE TRIM(fab_number) = TRIM(:fab_number)
          AND deleted_at IS NULL
        ORDER BY COALESCE(updated_at, created_at) DESC, id DESC
        LIMIT 1
    ');
    $stmt->bindValue(':fab_number', $fabNumber);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
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