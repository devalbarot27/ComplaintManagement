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
                ccs_warranty_claim VARCHAR(5) NULL,
                ccs_remarks VARCHAR(500) NULL,
                ccs_marked_by_username VARCHAR(150) NULL,
                ccs_marked_at TIMESTAMP NULL,
                l1_status VARCHAR(20) NOT NULL DEFAULT 'Pending',
                l1_by_username VARCHAR(150) NULL,
                l1_at TIMESTAMP NULL,
                l1_remarks VARCHAR(500) NULL,
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
function foc_claim_items_for_claim(PDO $conn, int $focClaimId): array
{
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
