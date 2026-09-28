<?php

/**
 * Customer Quality (CQ) qualification + CCS Server referral.
 *
 * Call Type + Warranty Status (from Commissioned Date) + Service Type
 * (captured on the Service Update, from the Service Log) determine whether a
 * complaint qualifies for Customer Quality review. Qualifying complaints
 * require the service engineer to pick failed part(s) before saving the
 * service update; cq_create_referral() then inserts directly into the real
 * CCS/RCA database's `autorca_details` table (one row per failed part) via
 * the $ccsconn connection in pdo_obconn.php - there is no local staging copy.
 *
 * Warranty status uses the same Standard / Uptime / Out of Warranty windows as
 * installed_base_warranty_status() (12 months, then 24 months, then out).
 * Out of Warranty also requires an FOC claim on the complaint before failed
 * parts are requested.
 */

require_once __DIR__ . '/installed_base_helpers.php';

const CQ_QUALIFYING_COMPLAINT_CATEGORIES = [
    'Product Performance Issue',
    'Parts / Accessories',
];

const CQ_WARRANTY_STANDARD = 'Standard Warranty';
const CQ_WARRANTY_UPTIME = 'Uptime Warranty';
const CQ_WARRANTY_OUT = 'Out of Warranty';

const CQ_SERVICE_TYPE_FACTORY_FITTED = 'Complaint - Factory Fitted Parts Failure';
const CQ_SERVICE_TYPE_NO_PARTS_REPLACED = 'No Parts Replaced - Setting Done';
const CQ_SERVICE_TYPE_SUPPLIED_SPARES = 'Complaint - Supplied Spares Failure';
const CQ_SERVICE_TYPE_OTHER = 'Other';

const CQ_SERVICE_TYPE_OPTIONS = [
    CQ_SERVICE_TYPE_FACTORY_FITTED,
    CQ_SERVICE_TYPE_NO_PARTS_REPLACED,
    CQ_SERVICE_TYPE_SUPPLIED_SPARES,
    CQ_SERVICE_TYPE_OTHER,
];

/** Fixed CCS product group for this portal's complaints (BRD: "Vayu" product group added in CCS). */
const CQ_CCS_PRODUCT_GROUP = 'Vayu';

/**
 * Next auto-generated trackno for autorca_details, based on the current max numeric value.
 * Not safe against concurrent inserts on its own - cq_create_referral() retries on a
 * unique-violation the same way amc_next_contract_number()/amc_insert_record() do.
 */
function cq_next_autorca_trackno(PDO $ccsConn): string
{
    $stmt = $ccsConn->query("
        SELECT COALESCE(MAX(trackno::bigint), 100000)
        FROM autorca_details
        WHERE trackno ~ '^[0-9]+$'
    ");

    return (string) ((int) $stmt->fetchColumn() + 1);
}

function cq_normalize_complaint_category(string $categoryName): string
{
    $categoryName = strtolower(trim(preg_replace('/\s+/', ' ', $categoryName) ?? ''));
    $categoryName = preg_replace('/\s*issue$/', '', $categoryName) ?? $categoryName;

    return trim($categoryName);
}

/** Step 1: Call Type = Product Performance Issue / Parts / Accessories. */
function cq_complaint_category_qualifies(string $categoryName): bool
{
    // Tolerate whitespace/case/"Issue"-suffix variants (category names are admin-managed free text).
    $categoryName = cq_normalize_complaint_category($categoryName);

    foreach (CQ_QUALIFYING_COMPLAINT_CATEGORIES as $qualifying) {
        if ($categoryName === cq_normalize_complaint_category($qualifying)) {
            return true;
        }
    }

    return false;
}


/**
 * Step 2: Standard Warranty (first 12 months), Uptime Warranty (months 13-36),
 * or Out of Warranty after that.
 *
 * @return array{status: string, years_elapsed: ?float}
 */
function cq_warranty_status_from_commissioning_date(?string $commissioningDate): array
{
    require_once __DIR__ . '/warranty_claims_helpers.php';

    $warranty = installed_base_warranty_status($commissioningDate);
    $status = trim((string) ($warranty['status'] ?? ''));
    if ($status === '' || $status === 'Unknown') {
        $status = '';
    }

    $monthsElapsed = $warranty['months_elapsed'] ?? null;

    return [
        'status' => $status,
        'years_elapsed' => $monthsElapsed === null ? null : ((int) $monthsElapsed) / 12,
    ];
}

function cq_warranty_status_badge_class(string $status): string
{
    $map = [
        CQ_WARRANTY_STANDARD => 'bg-success',
        CQ_WARRANTY_UPTIME => 'bg-info text-dark',
        CQ_WARRANTY_OUT => 'bg-danger',
    ];

    return $map[$status] ?? 'bg-secondary';
}

/** Step 3: which Service Type(s) qualify for a given warranty status. */
function cq_qualifying_service_types_for_warranty_status(string $warrantyStatus): array
{
    if ($warrantyStatus === CQ_WARRANTY_OUT) {
        return [CQ_SERVICE_TYPE_SUPPLIED_SPARES];
    }

    if (in_array($warrantyStatus, [CQ_WARRANTY_STANDARD, CQ_WARRANTY_UPTIME], true)) {
        return [CQ_SERVICE_TYPE_FACTORY_FITTED, CQ_SERVICE_TYPE_NO_PARTS_REPLACED, CQ_SERVICE_TYPE_SUPPLIED_SPARES];
    }

    return [];
}

/** Normalizes en/em dashes, case and whitespace so admin-typed Service Type text can be matched loosely. */
function cq_normalize_service_type_text(string $value): string
{
    $value = str_replace(["\xE2\x80\x93", "\xE2\x80\x94"], '-', $value);
    $value = strtolower(trim(preg_replace('/\s+/', ' ', $value) ?? ''));

    return $value;
}

/** Classifies a raw Service Type value (e.g. from the Service Log) into one of the known CQ buckets, or null. */
function cq_classify_service_type(string $rawServiceType): ?string
{
    $normalized = cq_normalize_service_type_text($rawServiceType);
    if ($normalized === '') {
        return null;
    }

    if (strpos($normalized, 'factory fitted') !== false) {
        return CQ_SERVICE_TYPE_FACTORY_FITTED;
    }

    if (strpos($normalized, 'no parts replaced') !== false
        || strpos($normalized, 'no part replaced') !== false
        || strpos($normalized, 'setting done') !== false
    ) {
        return CQ_SERVICE_TYPE_NO_PARTS_REPLACED;
    }

    if (strpos($normalized, 'supplied spares') !== false || strpos($normalized, 'spares failure') !== false) {
        return CQ_SERVICE_TYPE_SUPPLIED_SPARES;
    }

    return null;
}

function cq_service_type_qualifies(string $warrantyStatus, string $rawServiceType): bool
{
    $classified = cq_classify_service_type($rawServiceType);
    if ($classified === null) {
        return false;
    }

    return in_array($classified, cq_qualifying_service_types_for_warranty_status($warrantyStatus), true);
}

/** Whether an FOC claim has been raised for this complaint. */
function cq_complaint_has_foc_claim(PDO $conn, int $complaintId): bool
{
    if ($complaintId <= 0) {
        return false;
    }

    try {
        $stmt = $conn->prepare('
            SELECT 1
            FROM foc_claims
            WHERE complaint_id = :complaint_id
              AND deleted_at IS NULL
            LIMIT 1
        ');
        $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
        $stmt->execute();
    } catch (PDOException $e) {
        return false;
    }

    return (bool) $stmt->fetchColumn();
}

/**
 * Failed parts are required when the category matches and:
 * - Standard or Uptime Warranty, and the Service Log type is factory-fitted,
 *   no-part-replaced, or supplied-spares failure; or
 * - Out of Warranty, the Service Log type is supplied-spares failure, and an
 *   FOC claim has been raised for the complaint.
 */
function cq_should_require_failed_parts(
    string $categoryName,
    string $warrantyStatus,
    string $serviceType,
    bool $focRaised = false
): bool {
    if (!cq_complaint_category_qualifies($categoryName) || !cq_service_type_qualifies($warrantyStatus, $serviceType)) {
        return false;
    }

    if ($warrantyStatus === CQ_WARRANTY_OUT) {
        return $focRaised;
    }

    return true;
}

/**
 * The "Service Type" for CQ purposes is the Service Log's existing Service Type field
 * (DB column warranty_chargeable, labeled "Service Type" in the Add/Edit Service Log modal) -
 * there is no separate CQ-only input for it, it's read from whatever the engineer already
 * recorded on the complaint's current-cycle Service Log.
 */
function cq_resolve_service_type_for_complaint(PDO $conn, int $complaintId): string
{
    require_once __DIR__ . '/complaint_service_log_helpers.php';

    if ($complaintId <= 0) {
        return '';
    }

    $serviceLog = complaint_service_log_find_current_cycle($conn, $complaintId);

    return trim((string) ($serviceLog['warranty_chargeable'] ?? ''));
}

/** Warranty status of a complaint's linked installed-base machine, resolved by fab_number. */
function cq_resolve_warranty_status_for_complaint(PDO $conn, int $complaintId): array
{
    if ($complaintId <= 0) {
        return cq_warranty_status_from_commissioning_date(null);
    }

    $stmt = $conn->prepare("
        SELECT ib.commissioning_date
        FROM complaints c
        INNER JOIN installed_base ib ON ib.fab_number = c.fab_number AND ib.deleted_at IS NULL
        WHERE c.id = :complaint_id
          AND c.deleted_at IS NULL
        ORDER BY ib.created_at DESC, ib.id DESC
        LIMIT 1
    ");
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();
    $commissioningDate = $stmt->fetchColumn();

    return cq_warranty_status_from_commissioning_date($commissioningDate !== false ? (string) $commissioningDate : null);
}

/**
 * Inserts one autorca_details row per selected failed part into the real CCS/RCA database
 * (via $ccsConn - see pdo_obconn.php's $ccsconn, host/db/credentials are a placeholder
 * until the real CCS DB details are supplied). Columns we have no equivalent data for
 * (fmcode, rca_code, revno, dom_int, mis, build_year, build_month, miscat, ryg, part_req,
 * build_date) are inserted as NULL per BRD decision; nccode is always '0' (matches the
 * legacy call-center script this query was ported from); pgcode is fixed to "VAYU".
 *
 * @param array<int, array{part_number:string, part_description?:string, qty:int}> $failedParts
 * @return array<int, string> trackno values generated for the inserted rows
 */
function cq_create_referral(
    PDO $ccsConn,
    int $complaintId,
    string $fabNumber,
    string $complaintDescription,
    string $categoryName,
    string $warrantyStatus,
    string $serviceType,
    array $failedParts,
    int $userId,
    string $ipAddress
): array {
    $insert = $ccsConn->prepare("
        INSERT INTO autorca_details
            (trackno, warranty_type, fabno, usr_id, entry_date, ipaddr, pgcode, subsyscode,
             part_code, complain, fmcode, nccode, rca_code, revno, remarks, dom_int, mis,
             build_year, build_month, miscat, ryg, imp_date, part_req, build_date)
        VALUES
            (:trackno, :warranty_type, :fabno, :usr_id, CURRENT_DATE, :ipaddr, :pgcode, :subsyscode,
             :part_code, :complain, NULL, '0', NULL, NULL, :remarks, NULL, NULL,
             NULL, NULL, NULL, NULL, NULL, NULL, NULL)
    ");

    $remarks = 'CQ referral - Complaint Category: ' . $serviceType . ', Warranty Status: ' . $warrantyStatus . ', Category: ' . $categoryName;
    $trackNumbers = [];

    foreach ($failedParts as $part) {
        $partNumber = trim((string) ($part['part_number'] ?? ''));
        if ($partNumber === '') {
            continue;
        }

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $trackNo = cq_next_autorca_trackno($ccsConn);

            try {
                $insert->bindValue(':trackno', $trackNo);
                $insert->bindValue(':warranty_type', $warrantyStatus);
                $insert->bindValue(':fabno', $fabNumber);
                $insert->bindValue(':usr_id', (string) $userId);
                $insert->bindValue(':ipaddr', $ipAddress);
                $insert->bindValue(':pgcode', CQ_CCS_PRODUCT_GROUP);
                $insert->bindValue(':subsyscode', null, PDO::PARAM_NULL);
                $insert->bindValue(':part_code', $partNumber);
                $insert->bindValue(':complain', $complaintDescription);
                $insert->bindValue(':remarks', $remarks);
                $insert->execute();
                $trackNumbers[] = $trackNo;
                break;
            } catch (PDOException $e) {
                // 23505 = unique_violation on trackno - regenerate and retry, re-throw anything else.
                if ($e->getCode() !== '23505' || $attempt === 5) {
                    throw $e;
                }
            }
        }
    }

    // Direct write to the real autorca_details table - no local staging copy or push-back
    // reference exists for this yet.
    error_log('CQ referral tracknos [' . implode(',', $trackNumbers) . '] created in autorca_details for complaint #' . $complaintId . '.');

    return $trackNumbers;
}

function cq_ensure_schema(PDO $conn): void
{
    $conn->exec('
        CREATE TABLE IF NOT EXISTS cq_referrals (
            id SERIAL PRIMARY KEY,
            complaint_id INTEGER NOT NULL REFERENCES complaints(id),
            warranty_status VARCHAR(80) NULL,
            service_type VARCHAR(255) NULL,
            part_number VARCHAR(100) NOT NULL,
            part_description VARCHAR(255) NULL,
            qty INTEGER NOT NULL DEFAULT 1,
            trackno VARCHAR(50) NULL,
            created_by INTEGER NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ');
    $conn->exec('ALTER TABLE cq_referrals ALTER COLUMN warranty_status TYPE VARCHAR(80)');
    $conn->exec('ALTER TABLE cq_referrals ALTER COLUMN service_type TYPE VARCHAR(255)');
    $conn->exec('ALTER TABLE cq_referrals ALTER COLUMN part_number TYPE VARCHAR(100)');
    $conn->exec('ALTER TABLE cq_referrals ALTER COLUMN part_description TYPE VARCHAR(255)');
    $conn->exec('ALTER TABLE cq_referrals ALTER COLUMN trackno TYPE VARCHAR(50)');
}

/**
 * @param array<int, array{part_number:string, part_description?:string, qty:int}> $failedParts
 * @param array<int, string> $trackNumbers
 */
function cq_save_referrals(
    PDO $conn,
    int $complaintId,
    string $warrantyStatus,
    string $serviceType,
    array $failedParts,
    array $trackNumbers,
    int $createdBy
): void {
    if ($complaintId <= 0 || $failedParts === []) {
        return;
    }

    cq_ensure_schema($conn);

    $insert = $conn->prepare('
        INSERT INTO cq_referrals
            (complaint_id, warranty_status, service_type, part_number, part_description, qty, trackno, created_by)
        VALUES
            (:complaint_id, :warranty_status, :service_type, :part_number, :part_description, :qty, :trackno, :created_by)
    ');

    $trackIndex = 0;
    foreach ($failedParts as $part) {
        $partNumber = trim((string) ($part['part_number'] ?? ''));
        if ($partNumber === '') {
            continue;
        }

        $description = trim((string) ($part['part_description'] ?? ''));
        $trackNo = trim((string) ($trackNumbers[$trackIndex] ?? ''));
        $trackIndex++;

        $insert->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
        $insert->bindValue(':warranty_status', $warrantyStatus !== '' ? $warrantyStatus : null);
        $insert->bindValue(':service_type', $serviceType !== '' ? $serviceType : null);
        $insert->bindValue(':part_number', $partNumber);
        $insert->bindValue(':part_description', $description !== '' ? $description : null);
        $insert->bindValue(':qty', max(1, (int) ($part['qty'] ?? 1)), PDO::PARAM_INT);
        $insert->bindValue(':trackno', $trackNo !== '' ? $trackNo : null);
        $insert->bindValue(':created_by', $createdBy > 0 ? $createdBy : null, $createdBy > 0 ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $insert->execute();
    }
}

/**
 * @return array<int, array<string, mixed>>
 */
function cq_referrals_for_complaint(PDO $conn, int $complaintId): array
{
    if ($complaintId <= 0) {
        return [];
    }

    cq_ensure_schema($conn);

    $stmt = $conn->prepare('
        SELECT warranty_status, service_type, part_number, part_description, qty, trackno, created_at
        FROM cq_referrals
        WHERE complaint_id = :complaint_id
        ORDER BY created_at DESC, id DESC
    ');
    $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}