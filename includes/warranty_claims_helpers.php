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

/** Fetch a non-deleted call ticket (complaint) by id, or null. */
function warranty_claims_find_complaint(PDO $conn, int $complaintId): ?array
{
    if ($complaintId <= 0) {
        return null;
    }

    $stmt = $conn->prepare("
        SELECT id, fab_number, customer_name, status
        FROM complaints
        WHERE id = :id
          AND deleted_at IS NULL
    ");
    $stmt->bindValue(':id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();

    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return $row !== false ? $row : null;
}

/** Recent call tickets for the "Call Ticket Number" select box. */
function warranty_claims_recent_complaints(PDO $conn, int $limit = 200): array
{
    $stmt = $conn->prepare("
        SELECT id, fab_number, customer_name
        FROM complaints
        WHERE deleted_at IS NULL
        ORDER BY id DESC
        LIMIT :limit
    ");
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

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
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
            SELECT part_number, part_description, qty, source
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
            fc.*, c.fab_number, c.customer_name,
            COALESCE(NULLIF(TRIM(um.name), ''), NULLIF(TRIM(fc.created_by_username), ''), '-') AS created_by_name,
            COALESCE(NULLIF(TRIM(um_l1.name), ''), NULLIF(TRIM(fc.l1_by_username), ''), '-') AS l1_by_name,
            COALESCE(NULLIF(TRIM(um_l2.name), ''), NULLIF(TRIM(fc.l2_by_username), ''), '-') AS l2_by_name
        FROM foc_claims fc
        INNER JOIN complaints c ON c.id = fc.complaint_id
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

function warranty_status_badge_class(?string $status): string
{
    return $status === WARRANTY_STATUS_UNDER ? 'bg-success' : 'bg-secondary';
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
            sc.*, c.fab_number, c.customer_name,
            COALESCE(NULLIF(TRIM(um.name), ''), NULLIF(TRIM(sc.created_by_username), ''), '-') AS created_by_name
        FROM service_claims sc
        INNER JOIN complaints c ON c.id = sc.complaint_id
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