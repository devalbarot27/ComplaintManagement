<?php

/**
 * Customer Quality (CQ) qualification + CCS Server referral.
 *
 * Complaint Category + Warranty Status (from Commissioned Date) + Service Type
 * (captured on the Service Update, from the Service Log) determine whether a
 * complaint qualifies for Customer Quality review. Qualifying complaints
 * require the service engineer to pick failed part(s) before saving the
 * service update; cq_create_referral() then inserts directly into the real
 * CCS/RCA database's `autorca_details` table (one row per failed part) via
 * the $ccsconn connection in pdo_obconn.php - there is no local staging copy.
 *
 * NOTE: the warranty thresholds/labels here (1 year / 1.6 years, "Under
 * Warranty" / "Uptime Warranty" / "Out of Warranty") are specific to this CQ
 * qualification rule and intentionally separate from
 * installed_base_warranty_status() in warranty_claims_helpers.php (12/24/36
 * month "Standard/Uptime/Out of Warranty" used by the Warranty Claims report)
 * - the two features use different BRDs with different thresholds.
 */

require_once __DIR__ . '/installed_base_helpers.php';

const CQ_QUALIFYING_COMPLAINT_CATEGORIES = [
    'Product Performance Issue',
    'Parts / Accessories',
];

const CQ_WARRANTY_UNDER = 'Under Warranty';
const CQ_WARRANTY_UPTIME = 'Uptime Warranty';
const CQ_WARRANTY_OUT = 'Out of Warranty';

/** Warranty thresholds in years, measured from the machine's Commissioned Date. */
const CQ_WARRANTY_UNDER_YEARS = 1.0;
const CQ_WARRANTY_UPTIME_YEARS = 1.6;

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

/** Step 1: Complaint Category = Product Performance Issue / Parts / Accessories. */
function cq_complaint_category_qualifies(string $categoryName): bool
{
    // Tolerate whitespace/case/"Issue"-suffix variants (category names are admin-managed free text).
    $categoryName = strtolower(trim(preg_replace('/\s+/', ' ', $categoryName) ?? ''));
    $categoryName = preg_replace('/\s*issue$/', '', $categoryName) ?? $categoryName;

    foreach (CQ_QUALIFYING_COMPLAINT_CATEGORIES as $qualifying) {
        $normalizedQualifying = strtolower(preg_replace('/\s*issue$/', '', $qualifying) ?? $qualifying);
        if ($categoryName === $normalizedQualifying) {
            return true;
        }
    }

    return false;
}


/**
 * Step 2: Warranty status from the Commissioned Date.
 * < 1 year = Under Warranty, 1 to <1.6 years = Uptime Warranty, >=1.6 years = Out of Warranty.
 *
 * @return array{status: string, years_elapsed: ?float}
 */
function cq_warranty_status_from_commissioning_date(?string $commissioningDate): array
{
    $commissioningDate = trim((string) $commissioningDate);
    if ($commissioningDate === '') {
        return ['status' => '', 'years_elapsed' => null];
    }

    $normalized = installed_base_format_date_for_input($commissioningDate);
    $timestamp = $normalized !== '' ? strtotime($normalized) : false;
    if ($timestamp === false) {
        return ['status' => '', 'years_elapsed' => null];
    }

    $commissioned = (new DateTimeImmutable('@' . $timestamp))->setTime(0, 0, 0);
    $today = new DateTimeImmutable('today');
    $daysElapsed = $today < $commissioned ? 0 : $today->diff($commissioned)->days;
    $yearsElapsed = $daysElapsed / 365.25;

    if ($yearsElapsed < CQ_WARRANTY_UNDER_YEARS) {
        $status = CQ_WARRANTY_UNDER;
    } elseif ($yearsElapsed < CQ_WARRANTY_UPTIME_YEARS) {
        $status = CQ_WARRANTY_UPTIME;
    } else {
        $status = CQ_WARRANTY_OUT;
    }

    return ['status' => $status, 'years_elapsed' => $yearsElapsed];
}

function cq_warranty_status_badge_class(string $status): string
{
    $map = [
        CQ_WARRANTY_UNDER => 'bg-success',
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

    if (in_array($warrantyStatus, [CQ_WARRANTY_UNDER, CQ_WARRANTY_UPTIME], true)) {
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

    if (strpos($normalized, 'no parts replaced') !== false || strpos($normalized, 'setting done') !== false) {
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

/** Whether the complaint qualifies for CQ review (mandatory failed-part selection + CCS referral). */
function cq_should_require_failed_parts(string $categoryName, string $warrantyStatus, string $serviceType): bool
{
    return cq_complaint_category_qualifies($categoryName) && cq_service_type_qualifies($warrantyStatus, $serviceType);
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

    $remarks = 'CQ referral - Service Type: ' . $serviceType . ', Warranty Status: ' . $warrantyStatus . ', Category: ' . $categoryName;
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
