<?php

/**
 * Shared helpers for Warranty Management:
 *  - Business Process 1: FOC Parts (foc_parts.php)
 *  - Business Process 2: Warranty Service Claims (service_claims.php)
 *
 * Both flows reference an existing complaint ("Call Ticket") for machine/customer
 * context. ERP LN master data (warranty eligibility, pricing, dispatch status) is
 * not wired up yet; warranty_status/ln_* fields are captured/stored here so the
 * ERP integration can populate or override them once available.
 */

require_once __DIR__ . '/current_username_helpers.php';
require_once __DIR__ . '/notification_helpers.php';
require_once __DIR__ . '/installed_base_helpers.php';
require_once __DIR__ . '/admin_access_helpers.php';

/**
 * System Admin / CCS Admin / Management see all FOC claims.
 * Other roles see only claims they submitted.
 *
 * @return array{where: string, params: array<string, mixed>, see_all: bool}
 */
function foc_parts_list_scope(PDO $conn): array
{
    if (!isset($_SESSION['role'])) {
        admin_refresh_session_role($conn);
    }

    if (is_system_admin() || is_ccs_admin_user() || is_management_user()) {
        return [
            'where' => 'fc.deleted_at IS NULL',
            'params' => [],
            'see_all' => true,
        ];
    }

    return [
        'where' => 'fc.deleted_at IS NULL
            AND LOWER(TRIM(COALESCE(fc.created_by_username, \'\'))) = LOWER(TRIM(:foc_scope_username))',
        'params' => [
            ':foc_scope_username' => current_username(),
        ],
        'see_all' => false,
    ];
}

function foc_parts_user_can_see_submitted_by(PDO $conn): bool
{
    if (!isset($_SESSION['role'])) {
        admin_refresh_session_role($conn);
    }

    return is_system_admin() || is_ccs_admin_user() || is_management_user();
}

/**
 * Whether the current user may open a FOC claim details record.
 */
function foc_parts_user_can_access_claim(PDO $conn, ?array $record): bool
{
    if ($record === null) {
        return false;
    }

    if (foc_parts_user_can_see_submitted_by($conn)) {
        return true;
    }

    $createdBy = trim((string) ($record['created_by_username'] ?? ''));
    if ($createdBy !== '' && strcasecmp($createdBy, current_username()) === 0) {
        return true;
    }

    // L1/L2 approvers may open claims from the Approvals inbox.
    require_once __DIR__ . '/user_helpers.php';
    $flags = user_current_approval_flags($conn);

    return !empty($flags['l1']) || !empty($flags['l2']);
}

function foc_claim_is_rejected(?array $record): bool
{
    if ($record === null) {
        return false;
    }

    $overall = trim((string) ($record['overall_status'] ?? ''));
    if (strcasecmp($overall, 'Rejected') === 0) {
        return true;
    }

    return ($record['l1_status'] ?? '') === FOC_STAGE_REJECTED
        || ($record['l2_status'] ?? '') === FOC_STAGE_REJECTED;
}

/**
 * Original submitter (or System Admin / CCS Admin / Management) may edit a
 * rejected FOC claim and send it back through L1/L2.
 */
function foc_claim_user_can_resubmit(PDO $conn, ?array $record): bool
{
    if ($record === null || !foc_claim_is_rejected($record)) {
        return false;
    }

    if (trim((string) ($record['ln_order_number'] ?? '')) !== '') {
        return false;
    }

    if (foc_parts_user_can_see_submitted_by($conn)) {
        return true;
    }

    $createdBy = trim((string) ($record['created_by_username'] ?? ''));

    return $createdBy !== '' && strcasecmp($createdBy, current_username()) === 0;
}

/**
 * @param array<int, array<string, mixed>> $items
 * @return array<int, array<string, mixed>>
 */
function foc_claim_items_as_cart(array $items): array
{
    $cart = [];
    foreach ($items as $item) {
        $partNumber = trim((string) ($item['part_number'] ?? ''));
        if ($partNumber === '') {
            continue;
        }
        $sourceRefId = (int) ($item['source_reference_id'] ?? 0);
        $cart[] = [
            'part_number' => $partNumber,
            'part_description' => trim((string) ($item['part_description'] ?? '')),
            'qty' => max(1, (int) ($item['qty'] ?? 1)),
            'source' => (($item['source'] ?? '') === 'existing') ? 'existing' : 'new',
            'source_reference_id' => $sourceRefId > 0 ? $sourceRefId : null,
        ];
    }

    return $cart;
}

function foc_claim_replace_items(PDO $conn, int $focClaimId, array $items): void
{
    if ($focClaimId <= 0) {
        return;
    }

    $delete = $conn->prepare('DELETE FROM foc_claim_items WHERE foc_claim_id = :foc_claim_id');
    $delete->bindValue(':foc_claim_id', $focClaimId, PDO::PARAM_INT);
    $delete->execute();

    foc_claim_insert_items($conn, $focClaimId, $items);
}

/**
 * Reset a rejected FOC claim and send it back for L1 approval.
 * Returns an error message, or null on success.
 */
function foc_claim_resubmit(
    PDO $conn,
    int $claimId,
    string $justification,
    string $warrantyStatus,
    array $items
): ?string {
    $record = foc_claim_get_by_id($conn, $claimId);
    if ($record === null) {
        return 'FOC claim not found.';
    }
    if (!foc_claim_user_can_resubmit($conn, $record)) {
        return 'This FOC claim cannot be resubmitted.';
    }

    $update = $conn->prepare("
        UPDATE foc_claims
        SET justification = :justification,
            warranty_status = :warranty_status,
            l1_status = :l1_status,
            l2_status = :l2_status,
            l1_by_username = NULL,
            l1_at = NULL,
            l1_remarks = NULL,
            l2_by_username = NULL,
            l2_at = NULL,
            l2_remarks = NULL,
            overall_status = :overall_status,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ");
    $update->bindValue(':justification', $justification !== '' ? $justification : null);
    $update->bindValue(':warranty_status', $warrantyStatus);
    $update->bindValue(':l1_status', FOC_STAGE_PENDING);
    $update->bindValue(':l2_status', FOC_STAGE_PENDING);
    $update->bindValue(':overall_status', 'Pending L1 Approval');
    $update->bindValue(':id', $claimId, PDO::PARAM_INT);
    $update->execute();

    foc_claim_replace_items($conn, $claimId, $items);

    $complaintId = (int) ($record['complaint_id'] ?? 0);
    warranty_claims_notify_role_holders(
        $conn,
        'foc-parts',
        'approve-l1-foc',
        'FOC Claim Resubmitted for L1 Approval',
        'FOC claim #' . $claimId . ' for call ticket #' . $complaintId . ' was updated and resubmitted for Lock-in Engineer approval.',
        $claimId
    );

    return null;
}

/**
 * System Admin / CCS Admin / Management see all service claims.
 * Other roles see only claims they submitted.
 *
 * @return array{where: string, params: array<string, mixed>, see_all: bool}
 */
function service_claims_list_scope(PDO $conn): array
{
    if (!isset($_SESSION['role'])) {
        admin_refresh_session_role($conn);
    }

    if (is_system_admin() || is_ccs_admin_user() || is_management_user()) {
        return [
            'where' => 'sc.deleted_at IS NULL',
            'params' => [],
            'see_all' => true,
        ];
    }

    return [
        'where' => 'sc.deleted_at IS NULL
            AND LOWER(TRIM(COALESCE(sc.created_by_username, \'\'))) = LOWER(TRIM(:sc_scope_username))',
        'params' => [
            ':sc_scope_username' => current_username(),
        ],
        'see_all' => false,
    ];
}

function service_claims_user_can_see_submitted_by(PDO $conn): bool
{
    return foc_parts_user_can_see_submitted_by($conn);
}

/**
 * Whether the current user may open a service claim details record.
 */
function service_claims_user_can_access_claim(PDO $conn, ?array $record): bool
{
    if ($record === null) {
        return false;
    }

    if (service_claims_user_can_see_submitted_by($conn)) {
        return true;
    }

    $createdBy = trim((string) ($record['created_by_username'] ?? ''));
    if ($createdBy !== '' && strcasecmp($createdBy, current_username()) === 0) {
        return true;
    }

    // Approvers / settlement actors may open claims from Approvals.
    require_once __DIR__ . '/user_helpers.php';
    $flags = user_current_approval_flags($conn);

    return !empty($flags['l1']) || !empty($flags['l2']);
}

/** Warranty flag values shown to approvers (Process 1 step 7 / Process 2 step 5). */
const WARRANTY_STATUS_UNDER = 'Under Warranty';
const WARRANTY_STATUS_NOT_UNDER = 'Not Under Warranty';

/** FOC claim approval-stage statuses. */
const FOC_STAGE_PENDING = 'Pending';
const FOC_STAGE_APPROVED = 'Approved';
const FOC_STAGE_REJECTED = 'Rejected';

/** Service claim approval-stage statuses. */
const SERVICE_CLAIM_L1_PENDING = 'Pending';
const SERVICE_CLAIM_L1_APPROVED = 'Approved';
const SERVICE_CLAIM_L1_REJECTED = 'Rejected';

/** Fallback approver user_master.id assigned to new claims until a real assignment UI exists. */
const DEFAULT_APPROVER_USER_ID = 102464;

/**
 * Installed-base warranty lifecycle (Warranty Claims report), derived purely from
 * the machine's commissioning_date — not related to the FOC/Service claim flags above.
 *  - Standard Warranty: first 12 months from commissioning date.
 *  - Uptime Warranty: next 24 months after Standard Warranty ends (months 13-36).
 *  - Out of Warranty: after 36 months from commissioning date — the only status
 *    that allows a warranty claim request to proceed through approval.
 */
const INSTALLED_BASE_WARRANTY_STANDARD_MONTHS = 12;
const INSTALLED_BASE_WARRANTY_UPTIME_MONTHS = 24;
const INSTALLED_BASE_WARRANTY_STANDARD = 'Standard Warranty';
const INSTALLED_BASE_WARRANTY_UPTIME = 'Uptime Warranty';
const INSTALLED_BASE_WARRANTY_OUT = 'Out of Warranty';

function warranty_claims_ensure_schema(PDO $conn): void
{
    static $ensured = false;
    if ($ensured) {
        return;
    }

    $tableExists = static function (PDO $conn, string $table): bool {
        $stmt = $conn->prepare("
            SELECT 1
            FROM information_schema.tables
            WHERE table_schema = 'public'
              AND table_name = :table
            LIMIT 1
        ");
        $stmt->bindValue(':table', $table);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    };

    $columnExists = static function (PDO $conn, string $table, string $column): bool {
        $stmt = $conn->prepare("
            SELECT 1
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND table_name = :table
              AND column_name = :column
            LIMIT 1
        ");
        $stmt->bindValue(':table', $table);
        $stmt->bindValue(':column', $column);
        $stmt->execute();

        return (bool) $stmt->fetchColumn();
    };

    if (!$tableExists($conn, 'foc_claims')) {
        $conn->exec("
            CREATE TABLE foc_claims (
                id SERIAL PRIMARY KEY,
                complaint_id INTEGER NOT NULL REFERENCES complaints(id),
                part_number VARCHAR(100) NULL,
                part_description VARCHAR(255) NULL,
                qty INTEGER NULL,
                justification VARCHAR(500) NULL,
                warranty_status VARCHAR(20) NOT NULL,
                l1_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
                l1_by_username VARCHAR(150) NULL,
                l1_at TIMESTAMP NULL,
                l1_remarks VARCHAR(500) NULL,
                l2_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
                l2_by_username VARCHAR(150) NULL,
                l2_at TIMESTAMP NULL,
                l2_remarks VARCHAR(500) NULL,
                l1_approver_user_id INTEGER NULL,
                l2_approver_user_id INTEGER NULL,
                overall_status VARCHAR(40) NOT NULL DEFAULT 'Pending L1 Approval',
                ln_order_number VARCHAR(100) NULL,
                created_by_username VARCHAR(150) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL
            )
        ");
    } else {
        // Upgrade from the single-item version: part_number/qty now live in foc_claim_items.
        $conn->exec("ALTER TABLE foc_claims ALTER COLUMN part_number DROP NOT NULL");
        $conn->exec("ALTER TABLE foc_claims ALTER COLUMN qty DROP NOT NULL");
    }

    if (!$columnExists($conn, 'foc_claims', 'l1_approver_user_id')) {
        $conn->exec("ALTER TABLE foc_claims ADD COLUMN l1_approver_user_id INTEGER NULL");
    }
    if (!$columnExists($conn, 'foc_claims', 'l2_approver_user_id')) {
        $conn->exec("ALTER TABLE foc_claims ADD COLUMN l2_approver_user_id INTEGER NULL");
    }

    if (!$tableExists($conn, 'foc_claim_items')) {
        $conn->exec("
            CREATE TABLE foc_claim_items (
                id SERIAL PRIMARY KEY,
                foc_claim_id INTEGER NOT NULL REFERENCES foc_claims(id),
                part_number VARCHAR(150) NOT NULL,
                part_description VARCHAR(255) NULL,
                qty INTEGER NOT NULL,
                source VARCHAR(20) NOT NULL DEFAULT 'new',
                source_reference_id INTEGER NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )
        ");
    } elseif (!$columnExists($conn, 'foc_claim_items', 'source_reference_id')
        && $columnExists($conn, 'foc_claim_items', 'spare_parts_consumption_item_id')) {
        $conn->exec("ALTER TABLE foc_claim_items RENAME COLUMN spare_parts_consumption_item_id TO source_reference_id");
    }

    if (!$tableExists($conn, 'service_claims')) {
        $conn->exec("
            CREATE TABLE service_claims (
                id SERIAL PRIMARY KEY,
                complaint_id INTEGER NOT NULL REFERENCES complaints(id),
                km_travelled NUMERIC(8,2) NOT NULL,
                service_date DATE NOT NULL,
                resolution_notes VARCHAR(1000) NULL,
                visit_charge_price NUMERIC(12,2) NULL,
                ccs_warranty_claim VARCHAR(5) NULL,
                ccs_remarks VARCHAR(500) NULL,
                ccs_marked_by_username VARCHAR(150) NULL,
                ccs_marked_at TIMESTAMP NULL,
                l1_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
                l1_by_username VARCHAR(150) NULL,
                l1_at TIMESTAMP NULL,
                l1_remarks VARCHAR(500) NULL,
                l1_approver_user_id INTEGER NULL,
                invoice_number VARCHAR(100) NULL,
                invoice_amount NUMERIC(12,2) NULL,
                invoice_raised_by_username VARCHAR(150) NULL,
                invoice_raised_at TIMESTAMP NULL,
                settlement_type VARCHAR(20) NULL,
                settlement_reference VARCHAR(100) NULL,
                settled_by_username VARCHAR(150) NULL,
                settled_at TIMESTAMP NULL,
                overall_status VARCHAR(40) NOT NULL DEFAULT 'Pending CCS Review',
                created_by_username VARCHAR(150) NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                deleted_at TIMESTAMP NULL
            )
        ");
    }

    if ($tableExists($conn, 'service_claims') && !$columnExists($conn, 'service_claims', 'l1_approver_user_id')) {
        $conn->exec("ALTER TABLE service_claims ADD COLUMN l1_approver_user_id INTEGER NULL");
    }

    if ($tableExists($conn, 'service_claims') && !$columnExists($conn, 'service_claims', 'visit_charge_price')) {
        $conn->exec("ALTER TABLE service_claims ADD COLUMN visit_charge_price NUMERIC(12,2) NULL");
    }

    $ensured = true;
}

/**
 * Call Ticket dropdown (FOC / service claims).
 * System Admin, CCS Admin, and Management see all tickets.
 * Other roles see only tickets they created or that are assigned to them.
 *
 * @return array{where: string, params: array<string, mixed>}
 */
function warranty_claims_call_ticket_scope(PDO $conn): array
{
    if (!isset($_SESSION['role'])) {
        admin_refresh_session_role($conn);
    }

    if (is_system_admin() || is_ccs_admin_user() || is_management_user()) {
        return [
            'where' => 'c.deleted_at IS NULL',
            'params' => [],
        ];
    }

    $username = current_username();
    $userId = (int) (current_user_id($conn) ?? 0);

    $where = 'c.deleted_at IS NULL
        AND (
            LOWER(TRIM(COALESCE(c.username, \'\'))) = LOWER(TRIM(:wc_ticket_username))
            OR TRIM(COALESCE(c.added_by::text, \'\')) = :wc_ticket_user_id
            OR LOWER(TRIM(COALESCE(c.added_by::text, \'\'))) = LOWER(TRIM(:wc_ticket_added_by_username))
        ';
    $params = [
        ':wc_ticket_username' => $username,
        ':wc_ticket_user_id' => (string) $userId,
        ':wc_ticket_added_by_username' => $username,
    ];

    if ($userId > 0) {
        $where .= '
            OR EXISTS (
                SELECT 1
                FROM complaint_assignments ca
                WHERE ca.complaint_id = c.id
                  AND ca.assigned_to = :wc_ticket_assigned_to
            )
        ';
        $params[':wc_ticket_assigned_to'] = $userId;
    }

    $where .= ')';

    return [
        'where' => $where,
        'params' => $params,
    ];
}

function warranty_claims_bind_scope_params(PDOStatement $stmt, array $params): void
{
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}

/** Fetch a non-deleted call ticket (complaint) by id, or null. */
function warranty_claims_find_complaint(PDO $conn, int $complaintId): ?array
{
    if ($complaintId <= 0) {
        return null;
    }

    require_once __DIR__ . '/complaint_address_helpers.php';
    complaint_ensure_schema($conn);

    $scope = warranty_claims_call_ticket_scope($conn);
    $stmt = $conn->prepare("
        SELECT c.id, c.fab_number, cm.customer_name, c.status
        FROM complaints c
        " . complaint_customer_join_sql('c', 'cm') . "
        WHERE c.id = :id
          AND {$scope['where']}
    ");
    warranty_claims_bind_scope_params($stmt, $scope['params']);
    $stmt->bindValue(':id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

/** Recent call tickets for the "Call Ticket Number" select box. */
function warranty_claims_recent_complaints(PDO $conn, int $limit = 200): array
{
    require_once __DIR__ . '/complaint_address_helpers.php';
    complaint_ensure_schema($conn);

    $scope = warranty_claims_call_ticket_scope($conn);
    $stmt = $conn->prepare("
        SELECT c.id, c.fab_number, cm.customer_name
        FROM complaints c
        " . complaint_customer_join_sql('c', 'cm') . "
        WHERE {$scope['where']}
        ORDER BY c.id DESC
        LIMIT :limit
    ");
    warranty_claims_bind_scope_params($stmt, $scope['params']);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Parts already recorded (Service Log "Part Replaced" entries) against a call
 * ticket's service visit(s), offered as pre-checkable "existing items" when
 * raising an FOC claim.
 */
function warranty_claims_existing_items_for_complaint(PDO $conn, int $complaintId): array
{
    if ($complaintId <= 0) {
        return [];
    }

    try {
        $stmt = $conn->prepare("
            SELECT
                spr.id AS source_reference_id,
                spr.machine_model_code AS part_number,
                spr.machine_model AS part_description,
                spr.quantity AS qty
            FROM complaint_service_logs csl
            INNER JOIN service_log_part_replacements spr
                ON spr.service_log_id = csl.service_log_id
               AND spr.deleted_at IS NULL
            WHERE csl.complaint_id = :complaint_id
            ORDER BY spr.sort_order ASC, spr.id ASC
        ");
        $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        // Service log / part replacement tables may not exist yet on older installs.
        return [];
    }

    $items = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $partNumber = trim((string) ($row['part_number'] ?? ''));
        if ($partNumber === '') {
            continue;
        }
        $items[] = [
            'source_reference_id' => (int) ($row['source_reference_id'] ?? 0),
            'part_number' => $partNumber,
            'part_description' => trim((string) ($row['part_description'] ?? '')),
            'qty' => max(1, (int) ($row['qty'] ?? 1)),
        ];
    }

    return $items;
}

/**
 * @param array<int, int> $complaintIds
 * @return array<int, array<int, array<string, mixed>>>
 */
function complaint_service_log_parts_for_complaints(PDO $conn, array $complaintIds): array
{
    $ids = [];
    foreach ($complaintIds as $complaintId) {
        $complaintId = (int) $complaintId;
        if ($complaintId > 0) {
            $ids[] = $complaintId;
        }
    }
    $ids = array_values(array_unique($ids));
    $indexed = [];
    foreach ($ids as $id) {
        $indexed[$id] = [];
    }
    if ($ids === []) {
        return $indexed;
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    try {
        $stmt = $conn->prepare("
            SELECT
                csl.complaint_id,
                spr.machine_model_code AS part_number,
                spr.machine_model AS part_description,
                spr.quantity AS qty,
                spr.service_log_id
            FROM complaint_service_logs csl
            INNER JOIN service_log_part_replacements spr
                ON spr.service_log_id = csl.service_log_id
               AND spr.deleted_at IS NULL
            WHERE csl.complaint_id IN ($placeholders)
            ORDER BY csl.complaint_id, spr.sort_order ASC, spr.id ASC
        ");
        foreach ($ids as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $complaintId = (int) ($row['complaint_id'] ?? 0);
            if ($complaintId > 0) {
                $indexed[$complaintId][] = $row;
            }
        }
    } catch (PDOException $e) {
        // Service log tables may not exist yet.
    }

    return $indexed;
}

/** Item master search (Spares only) for the "Search & Add New Item" box, same source as orderbooking.php. */
function warranty_claims_search_spare_items(PDO $conn, string $search, int $limit = 20): array
{
    $stmt = $conn->prepare("
        SELECT tplcode, tpldesc
        FROM product_master_vayu
        WHERE (tplcode ILIKE :search OR tpldesc ILIKE :search)
          AND order_type = 2
        ORDER BY tplcode
        LIMIT :limit
    ");
    $stmt->bindValue(':search', '%' . $search . '%');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Insert the cart line items for a submitted FOC claim. */
function foc_claim_insert_items(PDO $conn, int $focClaimId, array $items): void
{
    if ($focClaimId <= 0 || $items === []) {
        return;
    }

    $insert = $conn->prepare("
        INSERT INTO foc_claim_items
        (foc_claim_id, part_number, part_description, qty, source, source_reference_id)
        VALUES
        (:foc_claim_id, :part_number, :part_description, :qty, :source, :source_reference_id)
    ");

    foreach ($items as $item) {
        $insert->bindValue(':foc_claim_id', $focClaimId, PDO::PARAM_INT);
        $insert->bindValue(':part_number', $item['part_number']);
        $insert->bindValue(':part_description', $item['part_description'] !== '' ? $item['part_description'] : null);
        $insert->bindValue(':qty', $item['qty'], PDO::PARAM_INT);
        $insert->bindValue(':source', $item['source']);
        $sourceRefId = (int) ($item['source_reference_id'] ?? 0);
        $insert->bindValue(':source_reference_id', $sourceRefId > 0 ? $sourceRefId : null, $sourceRefId > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $insert->execute();
    }
}

/** Line items for a given FOC claim (used on the list/detail view). */
function foc_claim_items_for_claim(PDO $conn, int $focClaimId, int $complaintId = 0): array
{
    if ($focClaimId <= 0) {
        return [];
    }

    try {
        $stmt = $conn->prepare("
            SELECT
                fci.part_number,
                fci.part_description,
                fci.qty,
                fci.source,
                fci.source_reference_id,
                COALESCE(
                    (
                        SELECT spr.service_log_id
                        FROM service_log_part_replacements spr
                        WHERE spr.id = fci.source_reference_id
                          AND spr.deleted_at IS NULL
                        LIMIT 1
                    ),
                    (
                        SELECT spr.service_log_id
                        FROM complaint_service_logs csl
                        INNER JOIN service_log_part_replacements spr
                            ON spr.service_log_id = csl.service_log_id
                           AND spr.deleted_at IS NULL
                        WHERE csl.complaint_id = :complaint_id
                          AND LOWER(TRIM(spr.machine_model_code)) = LOWER(TRIM(fci.part_number))
                        ORDER BY spr.id DESC
                        LIMIT 1
                    )
                ) AS service_log_id
            FROM foc_claim_items fci
            WHERE fci.foc_claim_id = :foc_claim_id
            ORDER BY fci.id
        ");
        $stmt->bindValue(':foc_claim_id', $focClaimId, PDO::PARAM_INT);
        $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $stmt = $conn->prepare("
            SELECT part_number, part_description, qty, source, source_reference_id
            FROM foc_claim_items
            WHERE foc_claim_id = :foc_claim_id
            ORDER BY id
        ");
        $stmt->bindValue(':foc_claim_id', $focClaimId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

function foc_service_log_details_url(int $serviceLogId): string
{
    return 'service_log_details.php?id=' . rawurlencode(base64_encode((string) $serviceLogId));
}

function foc_part_number_link_html(string $partNumber, int $serviceLogId): string
{
    $escaped = htmlspecialchars($partNumber, ENT_QUOTES, 'UTF-8');
    if ($partNumber === '') {
        return '-';
    }
    if ($serviceLogId <= 0) {
        return $escaped;
    }

    $href = htmlspecialchars(foc_service_log_details_url($serviceLogId), ENT_QUOTES, 'UTF-8');

    return '<a href="' . $href . '" target="_blank" rel="noopener" class="text-primary fw-semibold text-decoration-none">'
        . $escaped
        . '</a>';
}

/**
 * @param array<int, array<string, mixed>> $items
 */
function foc_parts_linked_cell_html(array $items, string $separator = '<br>'): string
{
    if ($items === []) {
        return '-';
    }

    $parts = [];
    foreach ($items as $item) {
        $parts[] = foc_part_number_link_html(
            (string) ($item['part_number'] ?? ''),
            (int) ($item['service_log_id'] ?? 0)
        );
    }

    return implode($separator, $parts);
}

/**
 * @param array<int, array<string, mixed>> $items
 */
function foc_parts_linked_summary_html(array $items): string
{
    if ($items === []) {
        return '-';
    }

    $parts = [];
    foreach ($items as $item) {
        $link = foc_part_number_link_html(
            (string) ($item['part_number'] ?? ''),
            (int) ($item['service_log_id'] ?? 0)
        );
        $qty = trim((string) ($item['qty'] ?? ''));
        $parts[] = $qty !== ''
            ? $link . ' x' . htmlspecialchars($qty, ENT_QUOTES, 'UTF-8')
            : $link;
    }

    return implode(', ', $parts);
}

/**
 * @param array<int, int> $claimIdToComplaintId
 * @return array<int, array<int, array<string, mixed>>>
 */
function foc_claim_items_for_claims(PDO $conn, array $claimIdToComplaintId): array
{
    $ids = [];
    foreach (array_keys($claimIdToComplaintId) as $claimId) {
        $claimId = (int) $claimId;
        if ($claimId > 0) {
            $ids[] = $claimId;
        }
    }
    $ids = array_values(array_unique($ids));
    if ($ids === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $indexed = [];
    foreach ($ids as $id) {
        $indexed[$id] = [];
    }

    try {
        $stmt = $conn->prepare("
            SELECT
                fci.foc_claim_id,
                fci.part_number,
                fci.part_description,
                fci.qty,
                fci.source,
                COALESCE(
                    (
                        SELECT spr.service_log_id
                        FROM service_log_part_replacements spr
                        WHERE spr.id = fci.source_reference_id
                          AND spr.deleted_at IS NULL
                        LIMIT 1
                    ),
                    (
                        SELECT spr.service_log_id
                        FROM complaint_service_logs csl
                        INNER JOIN service_log_part_replacements spr
                            ON spr.service_log_id = csl.service_log_id
                           AND spr.deleted_at IS NULL
                        WHERE csl.complaint_id = fc.complaint_id
                          AND LOWER(TRIM(spr.machine_model_code)) = LOWER(TRIM(fci.part_number))
                        ORDER BY spr.id DESC
                        LIMIT 1
                    )
                ) AS service_log_id
            FROM foc_claim_items fci
            INNER JOIN foc_claims fc ON fc.id = fci.foc_claim_id
            WHERE fci.foc_claim_id IN ($placeholders)
            ORDER BY fci.foc_claim_id, fci.id
        ");
        foreach ($ids as $index => $id) {
            $stmt->bindValue($index + 1, $id, PDO::PARAM_INT);
        }
        $stmt->execute();
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $claimId = (int) ($row['foc_claim_id'] ?? 0);
            if ($claimId > 0) {
                $indexed[$claimId][] = $row;
            }
        }
    } catch (PDOException $e) {
        foreach ($claimIdToComplaintId as $claimId => $complaintId) {
            $indexed[(int) $claimId] = foc_claim_items_for_claim($conn, (int) $claimId, (int) $complaintId);
        }
    }

    return $indexed;
}

function foc_claim_get_by_id(PDO $conn, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT
            fc.*,
            c.fab_number,
            cm.customer_name,
            cm.email AS customer_email,
            cm.mobile AS customer_mobile,
            cm.street_1 AS customer_street_1,
            cm.street_2 AS customer_street_2,
            cm.pincode AS customer_pincode,
            cm.city AS customer_city,
            cm.district AS customer_district,
            cm.state AS customer_state,
            COALESCE(NULLIF(TRIM(um.name), ''), NULLIF(TRIM(fc.created_by_username), ''), '-') AS created_by_name,
            COALESCE(NULLIF(TRIM(um_l1.name), ''), NULLIF(TRIM(fc.l1_by_username), ''), '-') AS l1_by_name,
            COALESCE(NULLIF(TRIM(um_l2.name), ''), NULLIF(TRIM(fc.l2_by_username), ''), '-') AS l2_by_name
        FROM foc_claims fc
        INNER JOIN complaints c ON c.id = fc.complaint_id
        LEFT JOIN customer_masters cm
            ON cm.id = c.customer_id
           AND cm.deleted_at IS NULL
        LEFT JOIN user_master um
            ON LOWER(TRIM(um.username)) = LOWER(TRIM(fc.created_by_username))
           AND um.deleted_at IS NULL
        LEFT JOIN user_master um_l1
            ON LOWER(TRIM(um_l1.username)) = LOWER(TRIM(fc.l1_by_username))
           AND um_l1.deleted_at IS NULL
        LEFT JOIN user_master um_l2
            ON LOWER(TRIM(um_l2.username)) = LOWER(TRIM(fc.l2_by_username))
           AND um_l2.deleted_at IS NULL
        WHERE fc.id = :id
          AND fc.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

/**
 * AO Number written back by LN onto plexecom_customer_units.order_number
 * after the FOC refno (E/UNITS/...) has been processed.
 */
function foc_claim_ao_number_for_refno(PDO $conn, string $refno): string
{
    $refno = trim($refno);
    if ($refno === '') {
        return '';
    }

    try {
        $stmt = $conn->prepare("
            SELECT NULLIF(TRIM(order_number), '')
            FROM plexecom_customer_units
            WHERE TRIM(refno) = :refno
              AND NULLIF(TRIM(order_number), '') IS NOT NULL
            ORDER BY indent_date DESC NULLS LAST
            LIMIT 1
        ");
        $stmt->bindValue(':refno', $refno);
        $stmt->execute();
        $aoNumber = $stmt->fetchColumn();

        return $aoNumber !== false ? trim((string) $aoNumber) : '';
    } catch (PDOException $e) {
        return '';
    }
}

function foc_approval_escape(string $value): string
{
    $value = trim($value);

    return htmlspecialchars($value !== '' ? $value : '-', ENT_QUOTES, 'UTF-8');
}

function foc_approval_field_html(string $label, string $value, string $colClass = 'col-md-4', bool $multiline = false, bool $allowHtml = false): string
{
    $display = $allowHtml ? $value : foc_approval_escape($value);
    $style = $multiline ? ' style="white-space: pre-wrap;"' : '';

    return '<div class="' . htmlspecialchars($colClass, ENT_QUOTES, 'UTF-8') . ' form-group">'
        . '<label class="form-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</label>'
        . '<div class="approval-detail-value"' . $style . '>' . $display . '</div>'
        . '</div>';
}

/**
 * @return array<int, array<string, mixed>>
 */
function foc_claim_service_logs_for_complaint(PDO $conn, int $complaintId): array
{
    if ($complaintId <= 0) {
        return [];
    }

    require_once __DIR__ . '/service_log_helpers.php';

    try {
        $stmt = $conn->prepare('
            SELECT sl.*
            FROM complaint_service_logs csl
            INNER JOIN service_logs sl
                ON sl.id = csl.service_log_id
               AND sl.deleted_at IS NULL
            WHERE csl.complaint_id = :complaint_id
            ORDER BY sl.visit_date DESC NULLS LAST, sl.id DESC
        ');
        $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

function foc_approval_extra_html(PDO $conn, array $record): string
{
    require_once __DIR__ . '/service_log_helpers.php';
    require_once __DIR__ . '/complaint_status.php';
    require_once __DIR__ . '/amc_helpers.php';
    require_once __DIR__ . '/complaint_category_helpers.php';

    $claimId = (int) ($record['id'] ?? 0);
    $complaintId = (int) ($record['complaint_id'] ?? 0);
    $fabNumber = trim((string) ($record['fab_number'] ?? ''));
    $installedBaseId = $fabNumber !== '' ? (int) (installed_base_find_id_by_fab($conn, $fabNumber) ?? 0) : 0;
    $coverage = amc_coverage_for_machine($conn, $installedBaseId, $fabNumber);
    $warranty = installed_base_warranty_status(null);
    $machineModel = '';
    $commissioned = '';
    $ibCommissioningDate = null;
    if ($installedBaseId > 0) {
        try {
            $ibStmt = $conn->prepare('
                SELECT machine_model, machine_model_code, commissioning_date
                FROM installed_base
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1
            ');
            $ibStmt->bindValue(':id', $installedBaseId, PDO::PARAM_INT);
            $ibStmt->execute();
            $ibRow = $ibStmt->fetch(PDO::FETCH_ASSOC);
            if ($ibRow) {
                $machineModel = installed_base_machine_model_label($ibRow);
                $ibCommissioningDate = $ibRow['commissioning_date'] ?? null;
                $commissioned = installed_base_format_date($ibCommissioningDate);
                $warranty = installed_base_warranty_status($ibCommissioningDate);
            }
        } catch (PDOException $e) {
            // Installed base lookup is optional extra context.
        }
    }

    $complaintDescription = '';
    $complaintStatusLabel = '';
    $complaintCategory = '';
    if ($complaintId > 0) {
        try {
            $cStmt = $conn->prepare('
                SELECT complaint_description, status, complaint_category_name
                FROM complaints
                WHERE id = :id
                  AND deleted_at IS NULL
                LIMIT 1
            ');
            $cStmt->bindValue(':id', $complaintId, PDO::PARAM_INT);
            $cStmt->execute();
            $complaint = $cStmt->fetch(PDO::FETCH_ASSOC);
            if ($complaint) {
                $complaintDescription = trim((string) ($complaint['complaint_description'] ?? ''));
                $complaintStatusLabel = complaint_status_label((int) ($complaint['status'] ?? 0));
                $complaintCategory = complaint_category_display_name($complaint);
            }
        } catch (PDOException $e) {
            // Complaint extras are optional.
        }
    }

    $encodedClaimId = rawurlencode(base64_encode((string) $claimId));
    $encodedComplaintId = rawurlencode(base64_encode((string) $complaintId));
    $address = trim(implode(', ', array_filter([
        trim((string) ($record['customer_street_1'] ?? '')),
        trim((string) ($record['customer_street_2'] ?? '')),
        trim((string) ($record['customer_city'] ?? '')),
        trim((string) ($record['customer_district'] ?? '')),
        trim((string) ($record['customer_state'] ?? '')),
        trim((string) ($record['customer_pincode'] ?? '')),
    ], static function ($part) {
        return $part !== '';
    })));

    $html = '<div class="row g-3 pt-2 border-top">';
    $html .= '<div class="col-12"><label class="form-label mb-0"><i class="bi bi-info-circle"></i> More Information</label></div>';
    if ($installedBaseId > 0 && $machineModel !== '') {
        $encodedIb = rawurlencode(base64_encode((string) $installedBaseId));
        $machineModelHtml = '<a href="installed_base_details.php?id=' . htmlspecialchars($encodedIb, ENT_QUOTES, 'UTF-8')
            . '" target="_blank" rel="noopener" class="text-primary fw-semibold text-decoration-none">'
            . htmlspecialchars($machineModel, ENT_QUOTES, 'UTF-8') . '</a>';
        $html .= foc_approval_field_html('Machine Model', $machineModelHtml, 'col-md-4', false, true);
    } else {
        $html .= foc_approval_field_html('Machine Model', $machineModel);
    }
    $html .= foc_approval_field_html('Commissioned', $commissioned);
    $warrantyDetails = installed_base_warranty_details($ibCommissioningDate);
    $html .= foc_approval_field_html('Machine Warranty', (string) ($warrantyDetails['status'] ?? $warranty['status'] ?? $record['warranty_status'] ?? ''));
    if (!empty($warrantyDetails['end_date_label']) && $warrantyDetails['end_date_label'] !== '-') {
        $html .= foc_approval_field_html(
            (string) $warrantyDetails['end_date_heading'],
            (string) $warrantyDetails['end_date_label']
        );
    }
    $html .= foc_approval_field_html('Under AMC', !empty($coverage['under_amc']) ? 'Yes' : 'No');
    if (!empty($coverage['under_amc'])) {
        $html .= foc_approval_field_html('AMC End Date', (string) $coverage['end_date_label']);
    }
    $html .= foc_approval_field_html('Call Ticket Status', $complaintStatusLabel);
    $html .= foc_approval_field_html('Complaint Category', $complaintCategory);
    $html .= foc_approval_field_html('Mobile', (string) ($record['customer_mobile'] ?? ''));
    $html .= foc_approval_field_html('Email', (string) ($record['customer_email'] ?? ''));
    $html .= foc_approval_field_html('Address', $address, 'col-12');
    if ($complaintDescription !== '') {
        $html .= foc_approval_field_html('Complaint Description', $complaintDescription, 'col-12', true);
    }
    $links = [];
    if ($claimId > 0) {
        $links[] = '<a href="foc_claim_details.php?id=' . htmlspecialchars($encodedClaimId, ENT_QUOTES, 'UTF-8')
            . '" target="_blank" rel="noopener" class="text-primary fw-semibold text-decoration-none">Open FOC claim</a>';
    }
    if ($complaintId > 0) {
        $links[] = '<a href="complaint_details.php?id=' . htmlspecialchars($encodedComplaintId, ENT_QUOTES, 'UTF-8')
            . '" target="_blank" rel="noopener" class="text-primary fw-semibold text-decoration-none">Open call ticket</a>';
    }
    if ($links !== []) {
        $html .= '<div class="col-12 form-group"><div class="approval-detail-value">' . implode(' &nbsp;|&nbsp; ', $links) . '</div></div>';
    }
    $html .= '</div>';

    $logs = foc_claim_service_logs_for_complaint($conn, $complaintId);
    $html .= '<div class="mt-3"><label class="form-label mb-2"><i class="bi bi-clipboard-pulse"></i> Service Logs</label>';
    if ($logs === []) {
        $html .= '<div class="text-muted">No service log is linked to this call ticket.</div></div>';

        return $html;
    }

    foreach ($logs as $log) {
        $serviceLogId = (int) ($log['id'] ?? 0);
        $parts = service_log_part_replacements_for_service_log($conn, $serviceLogId);
        $partLines = [];
        foreach ($parts as $part) {
            $partLines[] = htmlspecialchars(service_log_part_model_label($part), ENT_QUOTES, 'UTF-8')
                . ' x' . (int) ($part['quantity'] ?? 0);
        }
        $logLink = $serviceLogId > 0
            ? '<a href="' . htmlspecialchars(foc_service_log_details_url($serviceLogId), ENT_QUOTES, 'UTF-8')
                . '" target="_blank" rel="noopener" class="text-primary fw-semibold text-decoration-none">#'
                . $serviceLogId . '</a>'
            : '-';

        $html .= '<div class="border rounded p-3 mb-2 bg-white">';
        $html .= '<div class="row g-3">';
        $html .= foc_approval_field_html('Service Log', $logLink, 'col-md-4', false, true);
        $html .= foc_approval_field_html('Visit Date', service_log_format_date($log['visit_date'] ?? null));
        $html .= foc_approval_field_html('Engineer', service_log_display_value($log['engineer_name'] ?? null));
        $html .= foc_approval_field_html('Service Type', service_log_display_value($log['warranty_chargeable'] ?? null));
        $html .= foc_approval_field_html('Running Hours', service_log_display_value($log['running_hours'] ?? null));
        $html .= foc_approval_field_html('Log Date', service_log_format_date($log['complaint_date'] ?? null));
        $html .= foc_approval_field_html('Serial Number', service_log_format_serial_number_for_display($log['serial_number'] ?? null));
        $html .= foc_approval_field_html('Issue / Service Description', service_log_display_value($log['issue_description'] ?? null), 'col-12', true);
        $html .= foc_approval_field_html('Action Taken', service_log_display_value($log['action_taken'] ?? null), 'col-12', true);
        $html .= foc_approval_field_html('Customer Feedback', service_log_display_value($log['customer_feedback'] ?? null));
        $html .= foc_approval_field_html('Remarks', service_log_display_value($log['remarks'] ?? null), 'col-12', true);
        $html .= foc_approval_field_html(
            'Parts Replaced',
            $partLines !== [] ? implode('<br>', $partLines) : service_log_display_value($log['part_replaced'] ?? null),
            'col-12',
            false,
            $partLines !== []
        );
        $html .= '</div></div>';
    }
    $html .= '</div>';

    return $html;
}

/**
 * Notify every user whose role currently holds the given module/permission.
 * Keeps the approval hierarchy configurable via the existing Assign Permissions UI.
 */
function warranty_claims_notify_role_holders(
    PDO $conn,
    string $moduleSlug,
    string $permissionSlug,
    string $title,
    string $message,
    ?int $referenceId = null
): void {
    $stmt = $conn->prepare("
        SELECT DISTINCT um.id
        FROM user_master um
        INNER JOIN role_permissions rp
            ON rp.role_id = um.role
           AND rp.deleted_at IS NULL
        INNER JOIN permissions p
            ON p.id = rp.permission_id
           AND p.deleted_at IS NULL
           AND p.status = 'active'
           AND LOWER(TRIM(p.permission_slug)) = :permission_slug
        INNER JOIN modules m
            ON m.id = p.module_id
           AND m.deleted_at IS NULL
           AND m.status = 'active'
           AND LOWER(TRIM(m.module_slug)) = :module_slug
        WHERE um.deleted_at IS NULL
    ");
    $stmt->bindValue(':module_slug', strtolower(trim($moduleSlug)));
    $stmt->bindValue(':permission_slug', strtolower(trim($permissionSlug)));
    $stmt->execute();

    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $userId) {
        notification_create($conn, (int) $userId, $title, $message, $moduleSlug, $referenceId);
    }
}

/**
 * Apply an L1 or L2 decision to an FOC claim.
 * Returns an error message, or null on success.
 */
function foc_claim_apply_decision(
    PDO $conn,
    int $claimId,
    string $level,
    string $decision,
    string $remarks,
    string $byUsername
): ?string {
    if (!in_array($level, ['l1', 'l2'], true)
        || !in_array($decision, [FOC_STAGE_APPROVED, FOC_STAGE_REJECTED], true)
        || $claimId <= 0
    ) {
        return 'Invalid FOC approval request.';
    }

    if ($decision === FOC_STAGE_REJECTED && $remarks === '') {
        return 'Remarks are required to reject a claim.';
    }

    $claimStmt = $conn->prepare('SELECT * FROM foc_claims WHERE id = :id AND deleted_at IS NULL');
    $claimStmt->bindValue(':id', $claimId, PDO::PARAM_INT);
    $claimStmt->execute();
    $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);

    if ($claim === false) {
        return 'FOC claim not found.';
    }

    if ($level === 'l1' && $claim['l1_status'] !== FOC_STAGE_PENDING) {
        return 'This claim has already been actioned at L1.';
    }

    if ($level === 'l2' && ($claim['l1_status'] !== FOC_STAGE_APPROVED || $claim['l2_status'] !== FOC_STAGE_PENDING)) {
        return 'This claim is not ready for L2 approval.';
    }

    if ($level === 'l1') {
        $overallStatus = $decision === FOC_STAGE_APPROVED ? 'Pending L2 Approval' : 'Rejected';
        $update = $conn->prepare("
            UPDATE foc_claims
            SET l1_status = :status, l1_by_username = :by, l1_at = CURRENT_TIMESTAMP,
                l1_remarks = :remarks, overall_status = :overall_status, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $update->bindValue(':status', $decision);
        $update->bindValue(':by', $byUsername);
        $update->bindValue(':remarks', $remarks !== '' ? $remarks : null);
        $update->bindValue(':overall_status', $overallStatus);
        $update->bindValue(':id', $claimId, PDO::PARAM_INT);
        $update->execute();

        if ($decision === FOC_STAGE_APPROVED) {
            warranty_claims_notify_role_holders(
                $conn,
                'foc-parts',
                'approve-l2-foc',
                'FOC Claim Pending L2 Approval',
                'FOC claim #' . $claimId . ' has been approved at L1 and needs Business Head approval.',
                $claimId
            );
        }
    } else {
        $overallStatus = $decision === FOC_STAGE_APPROVED ? 'Approved' : 'Rejected';
        $update = $conn->prepare("
            UPDATE foc_claims
            SET l2_status = :status, l2_by_username = :by, l2_at = CURRENT_TIMESTAMP,
                l2_remarks = :remarks, overall_status = :overall_status, updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $update->bindValue(':status', $decision);
        $update->bindValue(':by', $byUsername);
        $update->bindValue(':remarks', $remarks !== '' ? $remarks : null);
        $update->bindValue(':overall_status', $overallStatus);
        $update->bindValue(':id', $claimId, PDO::PARAM_INT);
        $update->execute();
    }

    return null;
}

/**
 * Apply a Lock-in Engineer decision to a service claim.
 * Returns an error message, or null on success.
 */
function service_claim_apply_l1_decision(
    PDO $conn,
    int $claimId,
    string $decision,
    string $remarks,
    string $byUsername
): ?string {
    if ($claimId <= 0 || !in_array($decision, [SERVICE_CLAIM_L1_APPROVED, SERVICE_CLAIM_L1_REJECTED], true)) {
        return 'Invalid service claim approval request.';
    }

    if ($decision === SERVICE_CLAIM_L1_REJECTED && $remarks === '') {
        return 'Remarks are required to reject a claim.';
    }

    $claimStmt = $conn->prepare('SELECT * FROM service_claims WHERE id = :id AND deleted_at IS NULL');
    $claimStmt->bindValue(':id', $claimId, PDO::PARAM_INT);
    $claimStmt->execute();
    $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);

    if ($claim === false || ($claim['overall_status'] ?? '') !== 'Pending L1 Approval') {
        return 'This claim is not pending L1 approval.';
    }

    $overallStatus = $decision === SERVICE_CLAIM_L1_APPROVED ? 'Approved - Pending Invoice' : 'Rejected';
    $update = $conn->prepare("
        UPDATE service_claims
        SET l1_status = :status, l1_by_username = :by, l1_at = CURRENT_TIMESTAMP,
            l1_remarks = :remarks, overall_status = :overall_status, updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $update->bindValue(':status', $decision);
    $update->bindValue(':by', $byUsername);
    $update->bindValue(':remarks', $remarks !== '' ? $remarks : null);
    $update->bindValue(':overall_status', $overallStatus);
    $update->bindValue(':id', $claimId, PDO::PARAM_INT);
    $update->execute();

    if ($decision === SERVICE_CLAIM_L1_APPROVED) {
        warranty_claims_notify_role_holders(
            $conn,
            'service-claims',
            'raise-invoice',
            'Service Claim Approved - Invoice Pending',
            'Service claim #' . $claimId . ' has been approved. Please raise the predefined visit-charge invoice.',
            $claimId
        );
    }

    return null;
}

function warranty_status_badge_class(?string $status): string
{
    $status = trim((string) $status);
    if ($status === WARRANTY_STATUS_UNDER
        || $status === INSTALLED_BASE_WARRANTY_STANDARD
        || $status === INSTALLED_BASE_WARRANTY_UPTIME
    ) {
        return 'bg-success';
    }
    if ($status === INSTALLED_BASE_WARRANTY_OUT || $status === WARRANTY_STATUS_NOT_UNDER) {
        return 'bg-secondary';
    }

    return 'bg-secondary';
}

/**
 * Determine an installed-base machine's warranty status from its commissioning date.
 *
 * @return array{status: string, badge_class: string, can_request_approval: bool, months_elapsed: ?int}
 */
function installed_base_warranty_status(?string $commissioningDate): array
{
    $commissioningDate = trim((string) $commissioningDate);
    if ($commissioningDate === '') {
        return [
            'status' => 'Unknown',
            'badge_class' => 'warranty-status--unknown',
            'can_request_approval' => false,
            'months_elapsed' => null,
        ];
    }

    $normalized = installed_base_format_date_for_input($commissioningDate);
    $timestamp = $normalized !== '' ? strtotime($normalized) : false;
    if ($timestamp === false) {
        return [
            'status' => 'Unknown',
            'badge_class' => 'warranty-status--unknown',
            'can_request_approval' => false,
            'months_elapsed' => null,
        ];
    }

    $commissioned = (new DateTimeImmutable('@' . $timestamp))->setTime(0, 0, 0);
    $today = new DateTimeImmutable('today');
    $monthsElapsed = ($today->diff($commissioned))->y * 12 + ($today->diff($commissioned))->m;
    if ($today < $commissioned) {
        $monthsElapsed = 0;
    }

    if ($monthsElapsed < INSTALLED_BASE_WARRANTY_STANDARD_MONTHS) {
        $status = INSTALLED_BASE_WARRANTY_STANDARD;
    } elseif ($monthsElapsed < INSTALLED_BASE_WARRANTY_STANDARD_MONTHS + INSTALLED_BASE_WARRANTY_UPTIME_MONTHS) {
        $status = INSTALLED_BASE_WARRANTY_UPTIME;
    } else {
        $status = INSTALLED_BASE_WARRANTY_OUT;
    }

    return [
        'status' => $status,
        'badge_class' => installed_base_warranty_status_badge_class($status),
        'can_request_approval' => $status === INSTALLED_BASE_WARRANTY_OUT,
        'months_elapsed' => $monthsElapsed,
    ];
}

function installed_base_warranty_status_badge_class(string $status): string
{
    $map = [
        INSTALLED_BASE_WARRANTY_STANDARD => 'warranty-status--standard',
        INSTALLED_BASE_WARRANTY_UPTIME => 'warranty-status--uptime',
        INSTALLED_BASE_WARRANTY_OUT => 'warranty-status--out',
    ];

    return $map[$status] ?? 'warranty-status--unknown';
}

/**
 * SQL expression matching installed_base_warranty_status() for server-side search/filter.
 */
function installed_base_warranty_status_sql(string $dateExpr = 'ib.commissioning_date'): string
{
    $monthsElapsed = '(EXTRACT(YEAR FROM AGE(CURRENT_DATE, (' . $dateExpr . ')::date)) * 12
        + EXTRACT(MONTH FROM AGE(CURRENT_DATE, (' . $dateExpr . ')::date)))';

    return 'CASE
        WHEN ' . $dateExpr . ' IS NULL THEN \'Unknown\'
        WHEN CURRENT_DATE < (' . $dateExpr . ')::date THEN \'' . INSTALLED_BASE_WARRANTY_STANDARD . '\'
        WHEN ' . $monthsElapsed . ' < ' . INSTALLED_BASE_WARRANTY_STANDARD_MONTHS . ' THEN \'' . INSTALLED_BASE_WARRANTY_STANDARD . '\'
        WHEN ' . $monthsElapsed . ' < ' . (INSTALLED_BASE_WARRANTY_STANDARD_MONTHS + INSTALLED_BASE_WARRANTY_UPTIME_MONTHS) . ' THEN \'' . INSTALLED_BASE_WARRANTY_UPTIME . '\'
        ELSE \'' . INSTALLED_BASE_WARRANTY_OUT . '\'
    END';
}

function installed_base_warranty_status_badge_html(array $warranty): string
{
    $status = trim((string) ($warranty['status'] ?? 'Unknown'));
    $badgeClass = trim((string) ($warranty['badge_class'] ?? 'warranty-status--unknown'));

    return '<span class="status-badge border border-dark">'
        . htmlspecialchars($status !== '' ? $status : 'Unknown', ENT_QUOTES, 'UTF-8')
        . '</span>';
}

/**
 * Warranty status plus the relevant end date for display with machine details.
 *
 * Standard Warranty ends at commissioned + 12 months.
 * Uptime Warranty ends at commissioned + 36 months.
 * Out of Warranty shows when coverage ended (uptime end).
 *
 * @return array{
 *     status: string,
 *     badge_class: string,
 *     can_request_approval: bool,
 *     months_elapsed: ?int,
 *     end_date: string,
 *     end_date_label: string,
 *     end_date_heading: string
 * }
 */
function installed_base_warranty_details(?string $commissioningDate): array
{
    $statusInfo = installed_base_warranty_status($commissioningDate);
    $details = array_merge($statusInfo, [
        'end_date' => '',
        'end_date_label' => '',
        'end_date_heading' => 'Warranty End Date',
    ]);

    $normalized = installed_base_format_date_for_input($commissioningDate);
    if ($normalized === '') {
        return $details;
    }

    $commissioned = DateTimeImmutable::createFromFormat('!Y-m-d', $normalized);
    if (!$commissioned instanceof DateTimeImmutable) {
        return $details;
    }

    $standardEnd = $commissioned->modify('+' . INSTALLED_BASE_WARRANTY_STANDARD_MONTHS . ' months');
    $uptimeEnd = $commissioned->modify(
        '+' . (INSTALLED_BASE_WARRANTY_STANDARD_MONTHS + INSTALLED_BASE_WARRANTY_UPTIME_MONTHS) . ' months'
    );
    $status = (string) ($details['status'] ?? 'Unknown');

    if ($status === INSTALLED_BASE_WARRANTY_STANDARD) {
        $details['end_date'] = $standardEnd->format('Y-m-d');
        $details['end_date_label'] = installed_base_format_date($details['end_date']);
        $details['end_date_heading'] = 'Warranty End Date';
    } elseif ($status === INSTALLED_BASE_WARRANTY_UPTIME) {
        $details['end_date'] = $uptimeEnd->format('Y-m-d');
        $details['end_date_label'] = installed_base_format_date($details['end_date']);
        $details['end_date_heading'] = 'Warranty End Date';
    } elseif ($status === INSTALLED_BASE_WARRANTY_OUT) {
        $details['end_date'] = $uptimeEnd->format('Y-m-d');
        $details['end_date_label'] = installed_base_format_date($details['end_date']);
        $details['end_date_heading'] = 'Warranty Ended On';
    }

    return $details;
}

/**
 * Compact warranty lines for list cells (shown under FAB / machine).
 */
function installed_base_warranty_meta_html(?string $commissioningDate): string
{
    $details = installed_base_warranty_details($commissioningDate);
    $html = '<div>Warranty: <strong>'
        . htmlspecialchars((string) $details['status'], ENT_QUOTES, 'UTF-8')
        . '</strong></div>';
    if ($details['end_date_label'] !== '' && $details['end_date_label'] !== '-') {
        $html .= '<div>'
            . htmlspecialchars((string) $details['end_date_heading'], ENT_QUOTES, 'UTF-8')
            . ': '
            . htmlspecialchars((string) $details['end_date_label'], ENT_QUOTES, 'UTF-8')
            . '</div>';
    }

    return $html;
}

/**
 * Header badges used on installed-base / complaint / service-log pages.
 */
function installed_base_warranty_header_html(?string $commissioningDate): string
{
    $details = installed_base_warranty_details($commissioningDate);
    $html = '<span class="badge border border-dark text-dark">'
        . 'Machine Warranty: '
        . htmlspecialchars((string) $details['status'], ENT_QUOTES, 'UTF-8')
        . '</span>';
    if ($details['end_date_label'] !== '' && $details['end_date_label'] !== '-') {
        $html .= '<span class="text-muted small">'
            . htmlspecialchars((string) $details['end_date_heading'], ENT_QUOTES, 'UTF-8')
            . ': '
            . htmlspecialchars((string) $details['end_date_label'], ENT_QUOTES, 'UTF-8')
            . '</span>';
    }

    return $html;
}

/**
 * @return array<int, array{label: string, value: string}>
 */
function installed_base_warranty_detail_fields(?string $commissioningDate): array
{
    $details = installed_base_warranty_details($commissioningDate);
    $fields = [
        ['label' => 'Machine Warranty', 'value' => (string) $details['status']],
    ];
    if ($details['end_date_label'] !== '' && $details['end_date_label'] !== '-') {
        $fields[] = [
            'label' => (string) $details['end_date_heading'],
            'value' => (string) $details['end_date_label'],
        ];
    }

    return $fields;
}

/**
 * Resolve Warranty Claims workflow status for a call ticket via its fab's commissioning date.
 *
 * @return array{status: string, badge_class: string, can_request_approval: bool, months_elapsed: ?int}
 */
function warranty_claims_resolve_status_for_complaint(PDO $conn, int $complaintId): array
{
    if ($complaintId <= 0) {
        return installed_base_warranty_status(null);
    }

    $stmt = $conn->prepare("
        SELECT ib.commissioning_date
        FROM complaints c
        INNER JOIN installed_base ib
            ON LOWER(TRIM(ib.fab_number)) = LOWER(TRIM(c.fab_number))
           AND ib.deleted_at IS NULL
        WHERE c.id = :complaint_id
          AND c.deleted_at IS NULL
        ORDER BY COALESCE(ib.updated_at, ib.created_at) DESC, ib.id DESC
        LIMIT 1
    ");
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();
    $commissioningDate = $stmt->fetchColumn();

    return installed_base_warranty_status($commissioningDate !== false ? (string) $commissioningDate : null);
}

/** Known Warranty Claims workflow statuses that may be stored on an FOC claim. */
function warranty_claims_workflow_statuses(): array
{
    return [
        INSTALLED_BASE_WARRANTY_STANDARD,
        INSTALLED_BASE_WARRANTY_UPTIME,
        INSTALLED_BASE_WARRANTY_OUT,
    ];
}

function foc_stage_badge_class(string $stage): string
{
    $map = [
        FOC_STAGE_PENDING => 'bg-warning text-dark',
        FOC_STAGE_APPROVED => 'bg-success',
        FOC_STAGE_REJECTED => 'bg-danger',
    ];

    return $map[$stage] ?? 'bg-secondary';
}

function service_claim_overall_badge_class(?string $status): string
{
    $status = trim((string) $status);
    $map = [
        'Pending CCS Review' => 'bg-warning text-dark',
        'Pending L1 Approval' => 'bg-info text-dark',
        'Approved - Pending Invoice' => 'bg-primary',
        'Invoice Raised - Pending Settlement' => 'bg-info text-dark',
        'Settled' => 'bg-success',
        'Rejected' => 'bg-danger',
    ];

    return $map[$status] ?? 'bg-secondary';
}

function service_claim_get_by_id(PDO $conn, int $id): ?array
{
    if ($id <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT
            sc.*,
            c.fab_number,
            cm.customer_name,
            cm.email AS customer_email,
            cm.mobile AS customer_mobile,
            cm.street_1 AS customer_street_1,
            cm.street_2 AS customer_street_2,
            cm.pincode AS customer_pincode,
            cm.city AS customer_city,
            cm.district AS customer_district,
            cm.state AS customer_state,
            COALESCE(NULLIF(TRIM(um.name), ''), NULLIF(TRIM(sc.created_by_username), ''), '-') AS created_by_name
        FROM service_claims sc
        INNER JOIN complaints c ON c.id = sc.complaint_id
        LEFT JOIN customer_masters cm
            ON cm.id = c.customer_id
           AND cm.deleted_at IS NULL
        LEFT JOIN user_master um
            ON LOWER(TRIM(um.username)) = LOWER(TRIM(sc.created_by_username))
           AND um.deleted_at IS NULL
        WHERE sc.id = :id
          AND sc.deleted_at IS NULL
        LIMIT 1
    ");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row ?: null;
}

function service_claim_soft_delete(PDO $conn, int $id): void
{
    $stmt = $conn->prepare("
        UPDATE service_claims
        SET deleted_at = CURRENT_TIMESTAMP,
            updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
          AND deleted_at IS NULL
    ");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * Latest FOC / service-claim status for a call ticket.
 * Returns "New" when no claim has been submitted yet.
 *
 * @return array{foc_status: string, foc_id: int, service_status: string, service_id: int}
 */
function complaint_warranty_claim_statuses(PDO $conn, int $complaintId): array
{
    $result = [
        'foc_status' => '-',
        'foc_id' => 0,
        'service_status' => 'New',
        'service_id' => 0,
    ];

    if ($complaintId <= 0) {
        return $result;
    }

    try {
        $stmt = $conn->prepare("
            SELECT id, overall_status
            FROM foc_claims
            WHERE complaint_id = :complaint_id
              AND deleted_at IS NULL
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $status = trim((string) ($row['overall_status'] ?? ''));
            $result['foc_id'] = (int) ($row['id'] ?? 0);
            $result['foc_status'] = $status !== '' ? $status : '-';
        }
    } catch (PDOException $e) {
        // FOC table may not exist yet.
    }

    try {
        $stmt = $conn->prepare("
            SELECT id, overall_status
            FROM service_claims
            WHERE complaint_id = :complaint_id
              AND deleted_at IS NULL
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");
        $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (is_array($row)) {
            $status = trim((string) ($row['overall_status'] ?? ''));
            $result['service_id'] = (int) ($row['id'] ?? 0);
            $result['service_status'] = $status !== '' ? $status : 'New';
        }
    } catch (PDOException $e) {
        // Service claims table may not exist yet.
    }

    return $result;
}

/**
 * Get an OAuth bearer token for ERP LN (Infor Mingle), same token endpoint/credentials
 * as orderClass::getBearerTokenLN() but usable outside that class.
 */
function warranty_claims_get_ln_bearer_token(): string
{
    $ch = curl_init();

    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://mingle-sso.eu1.inforcloudsuite.com:443/ELGI_TST/as/token.oauth2',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'token_name'    => getenv('API_TOKEN_NAME'),
            'grant_type'    => getenv('API_GRANT_TYPE'),
            'redirect_uri'  => 'https://mingle-sso.eu1.inforcloudsuite.com:443/ELGI_TST/as/token.oauth2',
            'client_id'     => getenv('API_CLIENT_ID'),
            'client_secret' => getenv('API_CLIENT_SECRET'),
            'username'      => getenv('API_USERNAME'),
            'password'      => getenv('API_PASSWORD'),
            'scope'         => ''
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);

    $response = curl_exec($ch);
    $hasError = curl_errno($ch) !== 0;
    curl_close($ch);

    if ($hasError) {
        return '';
    }

    $data = json_decode($response, true);

    return isset($data['access_token']) && is_string($data['access_token'])
        ? $data['access_token']
        : '';
}

/**
 * FOC claims don't capture delivery address/transporter/payment-term/order-type
 * themselves, so the dealer's most recent regular order (plexecom_customer_units)
 * is used to source those defaults for the zero-value LN sales order.
 */
function foc_claim_ln_reference_defaults(PDO $obconn, string $customerCode): ?array
{
    $customerCode = trim($customerCode);
    if ($customerCode === '') {
        return null;
    }

    $stmt = $obconn->prepare("
        SELECT areacode, deladdr, transporter, paycode, otcode,
               dpst, warehouse, aoseries, company
        FROM plexecom_customer_units
        WHERE cuno = :cuno
        ORDER BY indent_date DESC, oid DESC
        LIMIT 1
    ");
    $stmt->bindValue(':cuno', $customerCode, PDO::PARAM_STR);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

/**
 * Push an APPROVED FOC claim's line items (foc_claim_items) to ERP LN as a
 * zero-value Sales Order, mirroring orderClass::submitCartApi() but sourced
 * from the FOC claim instead of the paid-order cart (tbl_vayu_cartitems).
 *
 * Must be called by the caller's own transaction (foc_parts.php wraps the L2
 * status update + this call in one transaction) — throws Exception on any
 * failure so the caller can roll back the approval instead of leaving the
 * claim "Approved" with no corresponding LN order.
 *
 * @return string the LN order reference number (refno) on success
 */
function foc_claim_submit_ln_order(PDO $obconn, PDO $dpconn, int $claimId, string $customerCode, string $userId): string
{
    date_default_timezone_set('UTC');
    $datetime = date('Y-m-d\TH:i:s\Z');

    error_log("FOC claim #{$claimId}: LN submission started (customerCode='{$customerCode}', userId='{$userId}')");

    $cuno = trim($customerCode);
    if ($cuno === '') {
        error_log("FOC claim #{$claimId}: aborting - no ERP customer code in session.");
        throw new Exception('No ERP customer code found for the current session.');
    }

    $itemsStmt = $obconn->prepare("
        SELECT part_number AS item_code, part_description AS item_name, qty
        FROM foc_claim_items
        WHERE foc_claim_id = :claim_id
        ORDER BY id
    ");
    $itemsStmt->bindValue(':claim_id', $claimId, PDO::PARAM_INT);
    $itemsStmt->execute();
    $cartItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    error_log("FOC claim #{$claimId}: found " . count($cartItems) . " line item(s) in foc_claim_items.");

    if (empty($cartItems)) {
        error_log("FOC claim #{$claimId}: aborting - no line items in foc_claim_items.");
        throw new Exception('FOC claim has no line items to submit.');
    }

    $defaults = foc_claim_ln_reference_defaults($obconn, $cuno);
    if ($defaults === null) {
        error_log("FOC claim #{$claimId}: aborting - no plexecom_customer_units row found for cuno='{$cuno}'.");
        throw new Exception("No previous order found for customer {$cuno} to source delivery/payment details from.");
    }

    error_log("FOC claim #{$claimId}: LN defaults resolved - " . json_encode($defaults));

    $area      = (string) $defaults['areacode'];
    $deladdr   = strtoupper((string) $defaults['deladdr']);
    $trans     = (string) $defaults['transporter'];
    $delterms  = "CIF";
    $paycode   = (string) $defaults['paycode'];
    $pono      = 'FOC-' . $claimId;
    $frtamount = null; // FOC parts are free of charge - no freight billed.
    $sid       = session_id();
    $indcat1   = (string) $defaults['otcode'];
    $indcat    = "Normal Order";
    $otcode    = (string) $defaults['otcode'];
    $aoseries  = (string) $defaults['aoseries'];
    $cmp       = (string) $defaults['company'];
    $dpst      = (string) $defaults['dpst'];
    $warehouse = (string) $defaults['warehouse'];
    $state     = "TN";

    $getEmail = $obconn->prepare("SELECT email FROM user_master WHERE username=:username limit 1");
    $getEmail->bindParam(":username", $userId);
    $getEmail->execute();
    $fetchEmail = $getEmail->fetch(PDO::FETCH_ASSOC);

    $userEmail = preg_replace('/^\s+|\s+$/u', '', $fetchEmail['email'] ?? '');
    $userEmail = ($userEmail !== '') ? $userEmail : null;

    $cuname = '';
    $street1 = '';
    $street2 = '';
    $city = '';
    $pincode = '';
    $emailValue = null;
    $pincodeValue = 0;
    $districtValue = null;

    $getAddr = $dpconn->prepare("SELECT * FROM customer_address WHERE adr_code=:addrCode limit 1");
    $getAddr->bindParam(":addrCode", $deladdr);
    $getAddr->execute();

    if ($getAddr->rowCount() > 0) {
        $fetAddr = $getAddr->fetch(PDO::FETCH_ASSOC);

        $cuname = $fetAddr['cuname'];
        $street1 = $fetAddr['st1'];
        $street2 = $fetAddr['st2'];
        $city = $fetAddr['city'];
        $state = isset($fetAddr['state']) ? $fetAddr['state'] : 'TN';
        $country = $fetAddr['country'];
        $pincode = trim((string)($fetAddr['pin'] ?? ''));
        $emailValue = null;
        $pincodeValue = ($pincode) ? str_replace(' ', '', $pincode) : 000;
        $districtValue = $city ?? null;
    }

    $bearerToken = warranty_claims_get_ln_bearer_token();

    if (!$bearerToken) {
        error_log("FOC claim #{$claimId}: aborting - could not obtain ERP LN bearer token.");
        throw new Exception('Unable to authenticate with ERP LN.');
    }

    error_log("FOC claim #{$claimId}: obtained ERP LN bearer token.");

    $rs = $obconn->prepare("select to_char(current_date,'YYMMDD') as ymd");
    $rs->execute();
    $getData = $rs->fetch(PDO::FETCH_ASSOC);
    $ymd = $getData['ymd'];

    $rs = $obconn->prepare("select nextval('dp_spares') as slno");
    $rs->execute();
    $getData = $rs->fetch(PDO::FETCH_ASSOC);
    $slno = $getData['slno'];
    $slno = str_pad($slno, 4, "0", STR_PAD_LEFT);

    $refno = "E/UNITS/" . $ymd . $slno;

    $stmt = $obconn->prepare("SELECT max(substr(indent_number,7,9)) AS maxindno FROM 
                plexecom_customer_units15062026 WHERE areacode = :area AND indent_date >= '01.04.2022'");

    $stmt->execute([
        ':area' => $area
    ]);

    $maxIndno = $stmt->fetchColumn();

    $letter = substr($maxIndno, 0, 1);
    $number = substr($maxIndno, 1, 2);

    $letter = $letter ?: 'A';
    $number = $number ?: 0;

    if ($number == 99) {
        $letter = chr(ord($letter) + 1);
        $number = 1;
    } else {
        $number++;
    }

    $number   = str_pad($number, 2, "0", STR_PAD_LEFT);
    $newIndno = $indcat1 . 'N' . $letter . $number;

    $customerStmt = $obconn->prepare("SELECT cm.adr_code, cm.country,ca.custaddr FROM customer_master cm LEFT JOIN customer_address ca ON ca.adr_code = cm.adr_code AND ca.cuno = cm.cuno WHERE cm.cuno=:cuno ");

    $customerStmt->execute([
        ':cuno' => $cuno
    ]);

    $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);

    $adrcode = $customer['adr_code'] ?? null;
    $country = trim($customer['country'] ?? '');
    $invaddr = pg_escape_string($customer['custaddr'] ?? '');

    $dpstStmt = $obconn->prepare("SELECT product_group FROM dpst_master WHERE dpst_code = :dpst");
    $dpstStmt->execute([':dpst' => $dpst]);
    $div = $dpstStmt->fetchColumn();

    $indent_number = $div . substr($area, 2, 2) . 'A' . $newIndno;

    $seqStmt = $obconn->prepare("SELECT nextval('plexecom_unique_sequence')");

    // TRIM/UPPER both sides - foc_claim_items.part_number may carry whitespace/case
    // differences from the service-log source that a plain '=' would silently miss.
    $productStmt = $obconn->prepare("SELECT tpldesc, excisable, warehouse, otcode, mc, vc, fc, cos,dealer_price FROM product_master_vayu WHERE UPPER(TRIM(tplcode)) = UPPER(TRIM(:tplcode)) AND dpst = :dpst");
    $productFallbackStmt = $obconn->prepare("SELECT tpldesc, excisable, warehouse, otcode, mc, vc, fc, cos,dealer_price FROM product_master_vayu WHERE UPPER(TRIM(tplcode)) = UPPER(TRIM(:tplcode)) ORDER BY dpst LIMIT 1");

    $hsnStmt = $obconn->prepare("SELECT substr(replace(hsn,':',''),1,4) AS hsn FROM elgi_item_master WHERE UPPER(TRIM(item_code)) = UPPER(TRIM(:tplcode))");

    $xml = "";

                $xml .= "<?xml version='1.0' encoding='UTF-8'?>
                <messageRequest>
                    <documentName>Process.SalesOrder</documentName>
                    <fromLogicalId>lid://infor.ims.ho_mscrm</fromLogicalId> 
                    <toLogicalId>lid://default</toLogicalId> 
                        <messageId>lid://infor.ims.mscrm_sync_salesorder_" . $datetime . "</messageId> 
                        <document>
                        <value>			
                    <![CDATA[
                <ProcessSalesOrder	xmlns='http://schema.infor.com/InforOAGIS/2'
                    xmlns:xsi='http://www.w3.org/2001/XMLSchema-instance'
                    xsi:schemaLocation='http://schema.infor.com/InforOAGIS/2 http://schema.infor.com/trunk/InforOAGIS/BODs/Developer/ProcessSalesOrder.xsd'
                    xmlns:xsd='http://www.w3.org/2001/XMLSchema'
                    releaseID='9.2'
                    versionID='2.5.0'>
                    <ApplicationArea>
                    <Sender>
                            <LogicalID>lid://infor.ims.mscrm</LogicalID>
                            <ComponentID>crm</ComponentID> 
                            <ConfirmationCode>OnError</ConfirmationCode>
                        </Sender><CreationDateTime>" . $datetime . "</CreationDateTime><BODID>infor-nid:infor.ln:401::" . $refno . ":?SalesOrder&amp;verb=Sync</BODID>
                    </ApplicationArea>
                    <DataArea>
                        <Process>
                            <TenantID>ELGI2_PRD</TenantID> 
                            <AccountingEntityID>" . $cmp . "</AccountingEntityID> 
                            <LocationID>S_" . $cmp . "</LocationID>
                            <ActionCriteria>
                                <ActionExpression actionCode='Add' />
                            </ActionCriteria>
                        </Process>
                <SalesOrder>
                <SalesOrderHeader>
                    <DocumentID agencyRole='Supplier'>
                        <ID>" . $refno . "</ID>
                    </DocumentID>
                    <AlternateDocumentID agencyRole='Customer'><ID>" . $refno . "</ID></AlternateDocumentID>
                    <DocumentDateTime>" . $datetime . "</DocumentDateTime><Status><Code>Open</Code></Status>
                    <SupplierParty>
                    <Location type='Office'>
                        <ID>" . $dpst . "</ID> 
                    </Location>
                    </SupplierParty>
                    <CustomerParty>
                        <PartyIDs><ID>" . $cuno . "</ID></PartyIDs>
                    </CustomerParty> 
                    <ShipToParty>
                            <PartyIDs>
                                <ID>" . $cuno . "</ID>
                            </PartyIDs>
                            <Location>
                            <Address type='Discrete'>
                            <AttentionOfName>" . $cuname . "</AttentionOfName>
                            <StreetName>" . $street1 . "</StreetName>
                            <BuildingName>" . $street2 . "</BuildingName>
                            <Floor></Floor>
                            <CityName>" . $city . "</CityName>
                            <CountrySubDivisionCode>" . $state . "</CountrySubDivisionCode>
                            <CountryCode>IN</CountryCode>
                            <PostalCode>" . $pincode . "</PostalCode>
                            </Address>
                            </Location>
                    </ShipToParty>
                    <TransportationTerm>
                        <IncotermsCode>" . $delterms . "</IncotermsCode>
                    </TransportationTerm>
                    <PaymentTerm>
                        <IDs><ID>" . $paycode . "</ID></IDs>
                    </PaymentTerm>
                    <RequestedShipDateTime>" . $datetime . "</RequestedShipDateTime> 
                    <UserArea>
                        <Property>
                        <NameValue name='ln.Area' type='StringType'>051</NameValue>
                        </Property>
                         <Property>
                        <NameValue name='ln.SalesPriceList' type='StringType'>VAY</NameValue>
                        </Property>
                        <Property>
                        <NameValue name='Ln.CRMMAIL' type='StringType'>" . $userEmail . "</NameValue>
                        </Property>
                        <Property>
                        <NameValue name='ln.CRMID' type='StringType'>" . $refno . "</NameValue>
                        </Property>
                        <Property>
                        <NameValue name='crm.PriceOverride' type='StringType'>N</NameValue></Property>
                        <Property>
                        <NameValue name='crm.AccountType' type='StringType'>C</NameValue>
                        </Property>
                        <Property>
                        <NameValue name='crm.OrderCategory' type='StringType'>" . $indcat . "</NameValue>
                        </Property>
                        <Property>
                        <NameValue name='crm.OrderType' type='StringType'>" . $indcat . "</NameValue>
                        </Property>
                        <Property>
                        <NameValue name='crm.TODApplicable' type='StringType'>N</NameValue>
                        </Property>
                        <Property>
                        <NameValue name='crm.CustomerState' type='StringType'>" . $state . "</NameValue>
                        </Property>
                    </UserArea>
                    <SalesPersonReference>
                        <IDs><ID>" . $userId . "</ID></IDs>
                    <SalesPersonRole>Internal</SalesPersonRole>
                </SalesPersonReference>
                </SalesOrderHeader>";

    $line = 10;
    $insertedCount = 0;

    foreach ($cartItems as $item) {

        $tplcode = $item['item_code'];
        $itemPrice = 0; // FOC parts are free of charge.

        $productStmt->execute([
            ':tplcode' => $tplcode,
            ':dpst'    => $dpst
        ]);

        $product = $productStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            // Fall back to any dpst for this tplcode - the customer's last regular
            // order's dpst may not be the one this specific part is listed under.
            $productFallbackStmt->execute([':tplcode' => $tplcode]);
            $product = $productFallbackStmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$product) {
            error_log("FOC claim #{$claimId}: SKIPPING part - no product_master_vayu row found for tplcode=" . json_encode($tplcode) . " (tried dpst='{$dpst}' and any dpst).");
            continue;
        }

        $seqStmt->execute();
        $seqid = $seqStmt->fetchColumn();

        $hsnStmt->execute([
            ':tplcode' => $tplcode
        ]);
        $hsn = $hsnStmt->fetchColumn();

        $taxColumn = ($country == 'IND' && $state == 'TN') ? 'sgst' : 'igst';

        $taxStmt = $obconn->prepare("SELECT {$taxColumn} AS taxcode FROM gst_hsn WHERE replace(hsn,':','') = :hsn AND company = :company");

        $taxStmt->execute([
            ':hsn'     => $hsn,
            ':company' => $cmp
        ]);

        $taxcode = $taxStmt->fetchColumn();

        if (in_array($indcat, [4, 6])) {
            $taxcode = ($state == 'TN') ? 'GSTAG05' : 'GSTAG62';
        }

        $xml .= "<SalesOrderLine>

                    <LineNumber>" . $line . "</LineNumber>

                    <Item>
                        <ItemID><ID>" . $tplcode . "</ID></ItemID>
                    </Item>

                    <Quantity unitCode='NOS'>" . $item['qty'] . "</Quantity>

                    <UnitPrice>
                        <Amount>" . $itemPrice . "</Amount>
                        <PerQuantity unitCode='NOS'>" . $item['qty'] . "</PerQuantity>
                    </UnitPrice>

                    <UserArea>
                        <Property>
                        <NameValue name='ln.HSNCode' type='StringType'>80:11</NameValue>
                        </Property>
            
                        <Property><NameValue name='ln.Motor' type='StringType'>ELGI</NameValue>
                        </Property>
                    </UserArea>

                    <CarrierParty>
                        <PartyIDs>
                            <ID>TC3</ID>
                        </PartyIDs>
                    </CarrierParty>

                    <ShipFromParty>
                        <Location type='Warehouse'>
                            <ID>W_" . $warehouse . "</ID>
                        </Location>
                    </ShipFromParty>
                    
                    </SalesOrderLine>";

        $line += 10;

        $insertStmt = $obconn->prepare("INSERT INTO plexecom_customer_units(usr_name,emp_code,cuno,cuname,areacode,pono,indent_category,indent_number,indent_date,order_time,transporter,delterms_code,delivery_date,invaddr,email, pincode,district,deladdr,dpst,tplcode,price,qty,salestax_code,sessionid,paycode,insby,edi_cuno,seqid,status,aoseries,otcode,warehouse,edi_delivery_date,edi_delivery_code,tpldesc,mc,vc,fc,cos,delivery_code,frtamount,company,adrcode,refno,hsn,state,country,edistatus,edi_date)VALUES(:uname,:emp_code,:cuno,:cname,:area,:pono,:indcat,:indno,current_date,CURRENT_TIME,:trans,:delterms,:deldate,:invaddr,:email,:pincode,:district,:deladdr,:dpst,:tplcode,:price,:qty,:taxcode,:sid,:paycode,:insby,:edi_cuno,:seqid,:status,:aoseries,:otcode,:warehouse,:edi_delivery_date,:edi_delivery_code,:tpldesc,:mcval,:vcval,:fcval,:cosval,:shipto,:frtamount,:cmp,:adrcode,:refno,:hsn,:state,:country,:edistatus,:edi_date)");

        $success = $insertStmt->execute([
            ':uname'             => $userId,
            ':emp_code'          => '102464',
            ':cuno'              => $cuno,
            ':cname'             => '',
            ':area'              => $area,
            ':pono'              => $pono,
            ':indcat'            => $indcat1,
            ':indno'             => $indent_number,
            ':trans'             => $trans,
            ':delterms'          => $delterms,
            ':deldate'           => date('d.m.Y'),
            ':invaddr'           => $invaddr,
            ':email'             => $emailValue,
            ':pincode'           => $pincodeValue,
            ':district'          => $districtValue,
            ':deladdr'           => $deladdr,
            ':dpst'              => $dpst,
            ':tplcode'           => $tplcode,
            ':price'             => $itemPrice,
            ':qty'               => $item['qty'],
            ':taxcode'           => $taxcode,
            ':sid'               => $sid,
            ':paycode'           => $paycode,
            ':insby'             => '',
            ':edi_cuno'          => $cuno,
            ':seqid'             => $seqid,
            ':status'            => 'A',
            ':aoseries'          => $aoseries,
            ':otcode'            => $otcode,
            ':warehouse'         => $product['warehouse'],
            ':edi_delivery_date' => date('d.m.Y'),
            ':edi_delivery_code' => $deladdr,
            ':tpldesc'           => $product['tpldesc'],
            ':mcval'             => ($product['mc'] === '' || $product['mc'] === null) ? null : $product['mc'],
            ':vcval'             => ($product['vc'] === '' || $product['vc'] === null) ? null : $product['vc'],
            ':fcval'             => ($product['fc'] === '' || $product['fc'] === null) ? null : $product['fc'],
            ':cosval'            => ($product['cos'] === '' || $product['cos'] === null) ? null : $product['cos'],
            ':shipto'            => $deladdr,
            ':frtamount'         => $frtamount,
            ':cmp'               => $cmp,
            ':adrcode'           => $adrcode,
            ':refno'             => $refno,
            ':hsn'               => "80:11",
            ':state'             => (!empty(trim((string)$state))) ? trim($state) : 'TN',
            ':country'           => $country,
            ':edistatus'         => 'Y',
            ':edi_date'          => date('d.m.Y')
        ]);

        if (!$success) {
            error_log("FOC claim #{$claimId}: plexecom_customer_units insert FAILED for part {$tplcode} - " . json_encode($insertStmt->errorInfo()));
            throw new Exception('Unable to record LN order line for part ' . $tplcode . '.');
        }

        $insertedCount++;
        error_log("FOC claim #{$claimId}: inserted plexecom_customer_units row for part {$tplcode} (refno={$refno}).");
    }

    if ($insertedCount === 0) {
        error_log("FOC claim #{$claimId}: aborting - none of the claimed parts matched a product_master_vayu row, nothing was inserted.");
        throw new Exception('None of the claimed parts were found in the item master, so no LN order line could be created.');
    }

    $xml .= " </SalesOrder>
                </DataArea>
                </ProcessSalesOrder>]]>
                </value>
                <encoding>NONE</encoding>		
                <characterSet>UTF-8</characterSet> 
                </document>
                </messageRequest>";

    $url = "https://mingle-ionapi.eu1.inforcloudsuite.com/ELGI_TST/IONSERVICES/api/ion/messaging/service/v2/message";

    $maxRetries = 3;
    $retryDelay = 1;
    $response = false;
    $errno = 0;
    $error = '';
    $httpCode = 0;

    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $xml,
            CURLOPT_HTTPHEADER     => [
                "Content-Type: application/xml; charset=UTF-8",
                "Authorization: Bearer $bearerToken"
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4
        ]);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno == 0) {
            break;
        }

        error_log("FOC claim #{$claimId} LN submit attempt {$attempt}: cURL Error {$errno} - {$error}");

        if ($errno == CURLE_COULDNT_CONNECT && $attempt < $maxRetries) {
            sleep($retryDelay);
            continue;
        }

        throw new Exception("cURL Error {$errno}: {$error}");
    }

    if ($errno != 0) {
        throw new Exception($error !== '' ? $error : 'Unknown ERP LN connection error.');
    }

    $data = json_decode($response, true);

    error_log("FOC claim #{$claimId}: LN response httpCode={$httpCode} body=" . substr((string) $response, 0, 1000));

    if ($httpCode == 201 && isset($data['status']) && $data['status'] === 'OK') {
        error_log("FOC claim #{$claimId}: LN order created successfully, refno={$refno}.");
        return $refno;
    }

    throw new Exception($data['message'] ?? 'ERP LN rejected the FOC sales order.');
}
