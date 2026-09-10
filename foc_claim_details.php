<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/warranty_claims_helpers.php';
require_once 'includes/installed_base_helpers.php';
require_once 'includes/record_details_layout.php';
require_once 'includes/amc_helpers.php';

warranty_claims_ensure_schema($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid record.');
}

$record = foc_claim_get_by_id($obconn, $id);

if (!$record) {
    die('FOC claim not found.');
}

if (!foc_parts_user_can_access_claim($obconn, $record)) {
    rbac_access_denied_redirect();
}

$canSeeSubmittedBy = foc_parts_user_can_see_submitted_by($obconn);
$canEditFoc = rbac_user_can($obconn, 'foc-parts', 'edit');
$canResubmit = $canEditFoc && foc_claim_user_can_resubmit($obconn, $record);
$encodedClaimId = rawurlencode(base64_encode((string) (int) $record['id']));
$complaintId = (int) ($record['complaint_id'] ?? 0);
$items = foc_claim_items_for_claim($obconn, $id, $complaintId);
$encodedComplaintId = rawurlencode(base64_encode((string) $complaintId));
$fabNumber = trim((string) ($record['fab_number'] ?? ''));
$installedBaseId = $fabNumber !== '' ? installed_base_find_id_by_fab($obconn, $fabNumber) : null;

$warrantyBadge = '<span class="status-badge border border-dark">'
    . htmlspecialchars((string) ($record['warranty_status'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</span>';
$l1Badge = '<span class="status-badge border border-dark">'
    . htmlspecialchars((string) ($record['l1_status'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</span>';
$l2Badge = '<span class="status-badge border border-dark">'
    . htmlspecialchars((string) ($record['l2_status'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</span>';
$lnRefNo = trim((string) ($record['ln_order_number'] ?? ''));
$lnAoNumber = $lnRefNo !== '' ? foc_claim_ao_number_for_refno($obconn, $lnRefNo) : '';
$headerMeta = [
    record_details_id_chip((int) $record['id']),
    '<span class="status-badge border border-dark">' . htmlspecialchars((string) ($record['overall_status'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>',
];
if ($lnRefNo !== '') {
    $headerMeta[] = '<span class="record-details-chip"><i class="bi bi-hash"></i> Ref No: '
        . '<a class="text-primary" href="recent_order_details.php?refno=' . htmlspecialchars(rawurlencode($lnRefNo), ENT_QUOTES, 'UTF-8')
        . '" target="_blank" rel="noopener">'
        . htmlspecialchars($lnRefNo, ENT_QUOTES, 'UTF-8') . '</a></span>';
}
if ($lnAoNumber !== '') {
    $headerMeta[] = '<span class="record-details-chip"><i class="bi bi-upc-scan"></i> AO Number: '
        . htmlspecialchars($lnAoNumber, ENT_QUOTES, 'UTF-8') . '</span>';
}

$partsTable = '<div class="table-responsive"><table class="table table-sm table-bordered mb-0">'
    . '<thead><tr><th>Part Number</th><th>Description</th><th style="width:90px;">Qty</th><th style="width:110px;">Source</th></tr></thead><tbody>';
if ($items === []) {
    $partsTable .= '<tr><td colspan="4" class="text-muted text-center">No parts recorded.</td></tr>';
} else {
    foreach ($items as $item) {
                        $partNumberCell = foc_part_number_link_html(
                            (string) ($item['part_number'] ?? ''),
                            (int) ($item['service_log_id'] ?? 0)
                        );
        $partsTable .= '<tr>'
            . '<td>' . $partNumberCell . '</td>'
            . '<td>' . htmlspecialchars((string) ($item['part_description'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td>' . htmlspecialchars((string) ($item['qty'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>'
            . '<td><span class="status-badge border border-dark">'
            . htmlspecialchars((string) ($item['source'] ?? '-'), ENT_QUOTES, 'UTF-8')
            . '</span></td>'
            . '</tr>';
    }
}
$partsTable .= '</tbody></table></div>';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FOC Claim Details #<?php echo htmlspecialchars((string) (int) $record['id'], ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include 'header_css.php'; ?>
    <link href="css/orderbook_style.css" rel="stylesheet" />
    <link href="css/complaint_form.css" rel="stylesheet" />
    <link href="css/complaint_details.css" rel="stylesheet" />
    <link href="css/record_details.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body>
    <div class="main-wrapper" id="mainWrapper">
        <?php include 'sidebar.php'; ?>

        <div class="content">
            <?php
            record_details_page_header(
                'FOC Part Claim',
                'Claim #' . (int) $record['id'],
                'foc_parts.php',
                'Back to List',
                'bi-wrench-adjustable',
                $headerMeta
            );
            if ($canResubmit) {
                echo '<div class="mb-3">'
                    . '<a href="foc_parts.php?edit=' . htmlspecialchars($encodedClaimId, ENT_QUOTES, 'UTF-8') . '" class="btn btn-complaint-primary">'
                    . '<i class="bi bi-pencil-square"></i> Edit &amp; Resubmit'
                    . '</a></div>';
            }

            record_details_card_start();

            record_details_section_start(1, 'Call Ticket', 'Complaint this FOC request relates to');
            record_details_field(
                'Call Ticket',
                '<a class="text-primary" href="complaint_details.php?id=' . htmlspecialchars($encodedComplaintId, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">#' . $complaintId . '</a>',
                'col-md-4',
                false,
                true
            );
            if ($installedBaseId !== null && $installedBaseId > 0 && $fabNumber !== '') {
                $encodedInstalledBaseId = rawurlencode(base64_encode((string) $installedBaseId));
                record_details_field(
                    'Fab Number',
                    '<a class="text-primary" href="installed_base_details.php?id=' . htmlspecialchars($encodedInstalledBaseId, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">'
                        . htmlspecialchars($fabNumber, ENT_QUOTES, 'UTF-8') . '</a>',
                    'col-md-4',
                    false,
                    true
                );
            } else {
                record_details_field('Fab Number', $fabNumber, 'col-md-4');
            }
            $focAmcCoverage = amc_coverage_for_machine($obconn, (int) ($installedBaseId ?? 0), $fabNumber);
            record_details_field('Under AMC', !empty($focAmcCoverage['under_amc']) ? 'Yes' : 'No', 'col-md-4');
            if (!empty($focAmcCoverage['under_amc'])) {
                record_details_field('AMC End Date', (string) $focAmcCoverage['end_date_label'], 'col-md-4');
            }
            record_details_section_end();

            record_details_section_start(2, 'Customer Details', 'Customer linked to the call ticket');
            record_details_field('Customer Name', (string) ($record['customer_name'] ?? ''), 'col-md-4');
            record_details_field('Email', (string) ($record['customer_email'] ?? ''), 'col-md-4');
            record_details_field('Mobile', (string) ($record['customer_mobile'] ?? ''), 'col-md-4');
            record_details_field('Street 1', (string) ($record['customer_street_1'] ?? ''), 'col-md-4');
            record_details_field('Street 2', (string) ($record['customer_street_2'] ?? ''), 'col-md-4');
            record_details_field('Pincode', (string) ($record['customer_pincode'] ?? ''), 'col-md-4');
            record_details_field('City', (string) ($record['customer_city'] ?? ''), 'col-md-4');
            record_details_field('District', (string) ($record['customer_district'] ?? ''), 'col-md-4');
            record_details_field('State', (string) ($record['customer_state'] ?? ''), 'col-md-4');
            record_details_section_end();

            record_details_section_start(3, 'Parts', 'Parts requested on this FOC claim');
            record_details_field('Parts', $partsTable, 'col-md-12', false, true);
            record_details_field('Justification', (string) ($record['justification'] ?? ''), 'col-md-12', true);
            record_details_section_end();

            record_details_section_start(4, 'Warranty & Approval', 'Warranty flag, L1/L2 decisions, Ref No and AO Number');
            record_details_field('Machine Warranty Status', $warrantyBadge, 'col-md-4', false, true);
            record_details_field('Lock-in Engineer', $l1Badge, 'col-md-4', false, true);
            record_details_field('Business Head', $l2Badge, 'col-md-4', false, true);
            if ($lnRefNo !== '') {
                $refNoLink = '<a class="text-primary" href="recent_order_details.php?refno='
                    . htmlspecialchars(rawurlencode($lnRefNo), ENT_QUOTES, 'UTF-8')
                    . '" target="_blank" rel="noopener">'
                    . htmlspecialchars($lnRefNo, ENT_QUOTES, 'UTF-8') . '</a>';
                record_details_field('Ref No', $refNoLink, 'col-md-4', false, true);
                record_details_field('AO Number', $lnAoNumber !== '' ? $lnAoNumber : '-', 'col-md-8');
            }
            record_details_field('L1 Remarks', (string) ($record['l1_remarks'] ?? ''), 'col-md-4');
            record_details_field('L1 By', (string) ($record['l1_by_name'] ?? $record['l1_by_username'] ?? ''), 'col-md-4');
            record_details_field('L1 At', rbac_format_datetime($record['l1_at'] ?? null), 'col-md-4');
            record_details_field('L2 Remarks', (string) ($record['l2_remarks'] ?? ''), 'col-md-4');
            record_details_field('L2 By', (string) ($record['l2_by_name'] ?? $record['l2_by_username'] ?? ''), 'col-md-4');
            record_details_field('L2 At', rbac_format_datetime($record['l2_at'] ?? null), 'col-md-4');
            record_details_section_end();

            record_details_section_start(5, 'Audit Trail', 'Creation and update history', true);
            if ($canSeeSubmittedBy) {
                record_details_field('Submitted By', rbac_display_value($record['created_by_name'] ?? $record['created_by_username'] ?? ''), 'col-md-6');
            }
            record_details_field('Created At', rbac_format_datetime($record['created_at'] ?? null), $canSeeSubmittedBy ? 'col-md-6' : 'col-md-4');
            record_details_section_end();

            record_details_card_end();
            ?>
        </div>
    </div>
</body>

</html>