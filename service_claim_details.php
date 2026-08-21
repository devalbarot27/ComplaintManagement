<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/warranty_claims_helpers.php';
require_once 'includes/distance_wise_price_helpers.php';
require_once 'includes/record_details_layout.php';

warranty_claims_ensure_schema($obconn);

$id = (int) base64_decode($_GET['id'] ?? '', true);

if ($id <= 0) {
    die('Invalid record.');
}

$record = service_claim_get_by_id($obconn, $id);

if (!$record) {
    die('Service claim not found.');
}

$complaintId = (int) ($record['complaint_id'] ?? 0);
$encodedComplaintId = rawurlencode(base64_encode((string) $complaintId));
$ccsClaim = trim((string) ($record['ccs_warranty_claim'] ?? ''));
$ccsBadge = $ccsClaim === ''
    ? '<span class="status-badge border border-dark">Pending</span>'
    : '<span class="status-badge border border-dark">'
        . htmlspecialchars($ccsClaim, ENT_QUOTES, 'UTF-8') . '</span>';
$l1Badge = '<span class="status-badge border border-dark">'
    . htmlspecialchars((string) ($record['l1_status'] ?? '-'), ENT_QUOTES, 'UTF-8') . '</span>';
$serviceDate = trim((string) ($record['service_date'] ?? ''));
$serviceDateLabel = $serviceDate !== '' ? date('d M Y', strtotime($serviceDate)) : '-';
$visitPrice = $record['visit_charge_price'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Claim Details #<?php echo htmlspecialchars((string) (int) $record['id'], ENT_QUOTES, 'UTF-8'); ?></title>
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
                'Service Claim',
                'Claim #' . (int) $record['id'],
                'service_claims.php',
                'Back to List',
                'bi-clipboard-check',
                [
                    record_details_id_chip((int) $record['id']),
                    '<span class="status-badge border border-dark">' . htmlspecialchars((string) ($record['overall_status'] ?? ''), ENT_QUOTES, 'UTF-8') . '</span>',
                ]
            );

            record_details_card_start();

            record_details_section_start(1, 'Call Ticket', 'Complaint this service visit relates to');
            record_details_field(
                'Call Ticket',
                '<a class="text-primary" href="complaint_details.php?id=' . htmlspecialchars($encodedComplaintId, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="noopener">#' . $complaintId . '</a>',
                'col-md-4',
                false,
                true
            );
            record_details_field('Fab Number', (string) ($record['fab_number'] ?? ''), 'col-md-4');
            record_details_field('Customer', (string) ($record['customer_name'] ?? ''), 'col-md-4');
            record_details_section_end();

            record_details_section_start(2, 'Call Closure Details', 'Distance, visit charge and resolution');
            record_details_field('Distance Travelled (KMs)', (string) ($record['km_travelled'] ?? ''), 'col-md-4');
            record_details_field(
                'Price',
                $visitPrice === null || $visitPrice === ''
                    ? '-'
                    : distance_wise_price_format_rupees($visitPrice),
                'col-md-4'
            );
            record_details_field('Service Date', $serviceDateLabel, 'col-md-4');
            record_details_field('Resolution Notes', (string) ($record['resolution_notes'] ?? ''), 'col-md-12', true);
            record_details_section_end();

            record_details_section_start(3, 'Approval & Settlement', 'CCS, L1, invoice and settlement status');
            record_details_field('Warranty (CCS)', $ccsBadge, 'col-md-4', false, true);
            record_details_field('CCS Remarks', (string) ($record['ccs_remarks'] ?? ''), 'col-md-4');
            record_details_field('CCS Marked By', (string) ($record['ccs_marked_by_username'] ?? ''), 'col-md-4');
            record_details_field('L1 Status', $l1Badge, 'col-md-4', false, true);
            record_details_field('L1 Remarks', (string) ($record['l1_remarks'] ?? ''), 'col-md-4');
            record_details_field('L1 By', (string) ($record['l1_by_username'] ?? ''), 'col-md-4');
            record_details_field('Invoice Number', (string) ($record['invoice_number'] ?? ''), 'col-md-4');
            record_details_field('Invoice Amount', (string) ($record['invoice_amount'] ?? ''), 'col-md-4');
            record_details_field('Settlement', trim((string) (($record['settlement_type'] ?? '') . ' ' . ($record['settlement_reference'] ?? ''))), 'col-md-4');
            record_details_section_end();

            record_details_section_start(4, 'Audit Trail', 'Creation and update history', true);
            record_details_field('Submitted By', rbac_display_value($record['created_by_name'] ?? $record['created_by_username'] ?? ''), 'col-md-4');
            record_details_field('Created At', rbac_format_datetime($record['created_at'] ?? null), 'col-md-4');
            record_details_field('Updated At', rbac_format_datetime($record['updated_at'] ?? null), 'col-md-4');
            record_details_section_end();

            record_details_card_end();
            ?>
        </div>
    </div>
</body>

</html>