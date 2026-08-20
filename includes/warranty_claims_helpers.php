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
        SELECT sc.*, c.fab_number, c.customer_name
        FROM service_claims sc
        INNER JOIN complaints c ON c.id = sc.complaint_id
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
