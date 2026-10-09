<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/current_username_helpers.php';
require_once 'includes/warranty_claims_helpers.php';

warranty_claims_ensure_schema($obconn);

$encodedClaimId = (string) ($_GET['id'] ?? '');
$decodedClaimId = base64_decode($encodedClaimId, true);
$claimId = $decodedClaimId !== false && ctype_digit($decodedClaimId)
    ? (int) $decodedClaimId
    : 0;

if ($claimId <= 0) {
    http_response_code(400);
    exit('Invalid service claim ID.');
}

try {
    $claimStmt = $obconn->prepare("
        SELECT
            sc.*,
            c.fab_number,
            cm.customer_name,
            rp.batch_code,
            rp.cuno AS reimbursement_customer_number,
            rp.area AS reimbursement_area,
            rp.claim_amount,
            rp.dispute,
            rp.dispute_remarks,
            rp.invno AS reimbursement_invoice_number,
            rp.invdt AS reimbursement_invoice_date
        FROM service_claims sc
        INNER JOIN complaints c ON c.id = sc.complaint_id
        LEFT JOIN customer_masters cm
            ON cm.id = c.customer_id
           AND cm.deleted_at IS NULL
        LEFT JOIN service_claim_reimbursement_pending rp
            ON rp.service_claim_id = sc.id
        WHERE sc.id = :id
          AND sc.deleted_at IS NULL
    ");
    $claimStmt->bindValue(':id', $claimId, PDO::PARAM_INT);
    $claimStmt->execute();
    $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $exception) {
    error_log('Service claim PDF query failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('Unable to generate the service claim PDF.');
}

if (
    $claim === false
    || (string) ($claim['overall_status'] ?? '') !== 'Approved'
    || !service_claims_user_can_access_claim($obconn, $claim)
) {
    http_response_code(404);
    exit('Service claim not found.');
}

$value = static function (array $record, string $key): string {
    $result = trim((string) ($record[$key] ?? ''));
    return $result !== '' ? $result : '-';
};

$sections = [
    'CLAIM & CUSTOMER' => [
        ['Service Claim ID', (string) $claimId],
        ['Call Ticket Number', (string) ($claim['complaint_id'] ?? '-')],
        ['Fab Number', $value($claim, 'fab_number')],
        ['Customer', $value($claim, 'customer_name')],
        ['Submitted By', $value($claim, 'created_by_username')],
        ['Created At', $value($claim, 'created_at')],
    ],
    'SERVICE DETAILS' => [
        ['Service Date', $value($claim, 'service_date')],
        ['Distance Travelled (KM)', $value($claim, 'km_travelled')],
        ['Visit Charge', $value($claim, 'visit_charge_price')],
        ['Invoice / PO Number', $value($claim, 'po_number')],
        ['Invoice / PO Attachment', $value($claim, 'po_attachment_original')],
        ['Resolution Notes', $value($claim, 'resolution_notes')],
        ['Warranty Status', $value($claim, 'warranty_status')],
    ],
    'REVIEW & APPROVALS' => [
        ['CCS Warranty Claim', $value($claim, 'ccs_warranty_claim')],
        ['CCS Remarks', $value($claim, 'ccs_remarks')],
        ['Lock-in Engineer Status', $value($claim, 'l1_status')],
        ['Lock-in Engineer', $value($claim, 'l1_by_username')],
        ['Lock-in Engineer Remarks', $value($claim, 'l1_remarks')],
        ['Business Head Status', $value($claim, 'l2_status')],
        ['Business Head', $value($claim, 'l2_by_username')],
        ['Business Head Remarks', $value($claim, 'l2_remarks')],
    ],
    'INVOICE & REIMBURSEMENT' => [
        ['Invoice Number', $value($claim, 'invoice_number')],
        ['Invoice Amount', $value($claim, 'invoice_amount')],
        ['Invoice Raised By', $value($claim, 'invoice_raised_by_username')],
        ['Invoice Raised At', $value($claim, 'invoice_raised_at')],
        ['Reimbursement Batch Code', $value($claim, 'batch_code')],
        ['Reimbursement Customer Number', $value($claim, 'reimbursement_customer_number')],
        ['Reimbursement Area', $value($claim, 'reimbursement_area')],
        ['Claim Amount', $value($claim, 'claim_amount')],
        ['Dispute', $value($claim, 'dispute')],
        ['Dispute Remarks', $value($claim, 'dispute_remarks')],
        ['Reimbursement Invoice Number', $value($claim, 'reimbursement_invoice_number')],
        ['Reimbursement Invoice Date', $value($claim, 'reimbursement_invoice_date')],
    ],
    'SETTLEMENT' => [
        ['Settlement Type', $value($claim, 'settlement_type')],
        ['Settlement Reference', $value($claim, 'settlement_reference')],
        ['Settled By', $value($claim, 'settled_by_username')],
        ['Settled At', $value($claim, 'settled_at')],
        ['Overall Status', $value($claim, 'overall_status')],
    ],
];

$pdfText = static function (string $text): string {
    if (function_exists('iconv')) {
        $converted = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }

    $text = preg_replace('/[^\x20-\x7E\x80-\xFF]/', '?', $text) ?? '';
    return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
};

$commands = '';
$pages = [];
$pageNumber = 0;
$y = 0;
$startPage = static function () use (&$commands, &$pages, &$pageNumber, &$y, $pdfText, $claimId, $claim): void {
    if ($pageNumber > 0) {
        $pages[] = $commands;
    }
    $pageNumber++;
    $commands = '';
    $y = 680;

    $commands .= "q 0.08 0.19 0.32 rg 0 735 612 57 re f Q\n";
    $commands .= "1 1 1 rg BT /F2 10 Tf 42 768 Td (" . $pdfText('SERVICE CLAIM') . ") Tj ET\n";
    $commands .= "1 1 1 rg BT /F2 21 Tf 42 742 Td (" . $pdfText('Claim #' . $claimId) . ") Tj ET\n";
    $commands .= "1 1 1 rg BT /F2 10 Tf 430 765 Td (" . $pdfText('APPROVED') . ") Tj ET\n";
    $commands .= "1 1 1 rg BT /F1 9 Tf 430 748 Td (" . $pdfText('Generated ' . date('d M Y')) . ") Tj ET\n";

    if ($pageNumber === 1) {
        $commands .= "q 0.94 0.96 0.98 rg 42 690 528 34 re f Q\n";
        $commands .= "0.08 0.19 0.32 rg BT /F2 9 Tf 54 710 Td (" . $pdfText('CALL TICKET') . ") Tj ET\n";
        $commands .= "0.08 0.19 0.32 rg BT /F1 11 Tf 54 696 Td (" . $pdfText('#' . (string) ($claim['complaint_id'] ?? '-')) . ") Tj ET\n";
        $commands .= "0.08 0.19 0.32 rg BT /F2 9 Tf 231 710 Td (" . $pdfText('FAB NUMBER') . ") Tj ET\n";
        $commands .= "0.08 0.19 0.32 rg BT /F1 11 Tf 231 696 Td (" . $pdfText(trim((string) ($claim['fab_number'] ?? '')) ?: '-') . ") Tj ET\n";
        $commands .= "0.08 0.19 0.32 rg BT /F2 9 Tf 420 710 Td (" . $pdfText('SERVICE DATE') . ") Tj ET\n";
        $commands .= "0.08 0.19 0.32 rg BT /F1 11 Tf 420 696 Td (" . $pdfText(trim((string) ($claim['service_date'] ?? '')) ?: '-') . ") Tj ET\n";
        $commands .= "q 0.84 0.88 0.92 rg 42 650 528 18 re f Q\n";
        $commands .= "0.08 0.19 0.32 rg BT /F2 8 Tf 51 656 Td (" . $pdfText('DESCRIPTION') . ") Tj ET\n";
        $commands .= "0.08 0.19 0.32 rg BT /F2 8 Tf 216 656 Td (" . $pdfText('CLAIM DETAILS') . ") Tj ET\n";
        $y = 648;
    } else {
        $commands .= "0.08 0.19 0.32 rg BT /F1 9 Tf 42 714 Td (" . $pdfText('Service Claim #' . $claimId . ' - continued') . ") Tj ET\n";
        $commands .= "q 0.84 0.88 0.92 rg 42 670 528 18 re f Q\n";
        $commands .= "0.08 0.19 0.32 rg BT /F2 8 Tf 51 676 Td (" . $pdfText('DESCRIPTION') . ") Tj ET\n";
        $commands .= "0.08 0.19 0.32 rg BT /F2 8 Tf 216 676 Td (" . $pdfText('CLAIM DETAILS') . ") Tj ET\n";
        $y = 668;
    }
};

$drawSection = static function (string $title) use (&$commands, &$y, $startPage, $pdfText): void {
    if ($y < 65) {
        $startPage();
    }
    $y -= 19;
    $commands .= "q 0.08 0.19 0.32 rg 42 {$y} 528 19 re f Q\n";
    $commands .= "1 1 1 rg BT /F2 9 Tf 52 " . ($y + 6) . " Td (" . $pdfText($title) . ") Tj ET\n";
    $y -= 2;
};

$drawRow = static function (string $sectionTitle, string $label, string $value) use (&$commands, &$y, $startPage, $drawSection, $pdfText): void {
    $labelLines = [];
    foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $label)) as $line) {
        $labelLines = array_merge($labelLines, explode("\n", wordwrap($line, 24, "\n", true)));
    }
    $valueLines = [];
    foreach (explode("\n", str_replace(["\r\n", "\r"], "\n", $value)) as $line) {
        $line = $line === '' ? ' ' : $line;
        $valueLines = array_merge($valueLines, explode("\n", wordwrap($line, 57, "\n", true)));
    }
    $lineCount = max(count($labelLines), count($valueLines), 1);
    $rowHeight = max(24, $lineCount * 12 + 10);
    if ($y - $rowHeight < 54) {
        $startPage();
        $drawSection($sectionTitle . ' - CONTINUED');
    }

    $bottom = $y - $rowHeight;
    $commands .= "q 0.82 0.86 0.89 RG 0.55 w 42 {$bottom} 528 {$rowHeight} re S Q\n";
    $commands .= "q 0.94 0.96 0.98 rg 42 {$bottom} 164 {$rowHeight} re f Q\n";
    $commands .= "0.12 0.16 0.20 rg\n";
    for ($index = 0; $index < $lineCount; $index++) {
        $lineY = $y - 14 - ($index * 12);
        if (isset($labelLines[$index])) {
            $commands .= "BT /F2 9 Tf 51 {$lineY} Td (" . $pdfText($labelLines[$index]) . ") Tj ET\n";
        }
        if (isset($valueLines[$index])) {
            $commands .= "BT /F1 9 Tf 216 {$lineY} Td (" . $pdfText($valueLines[$index]) . ") Tj ET\n";
        }
    }
    $commands .= "q 0.82 0.86 0.89 RG 0.55 w 206 {$bottom} m 206 {$y} l S Q\n";
    $y = $bottom;
};

$startPage();
foreach ($sections as $sectionTitle => $rows) {
    $drawSection($sectionTitle);
    foreach ($rows as [$label, $fieldValue]) {
        $drawRow($sectionTitle, $label, $fieldValue);
    }
}

$pages[] = $commands;
$pageCount = count($pages);
foreach ($pages as $index => &$pageCommands) {
    $pageCommands .= "q 0.82 0.86 0.89 RG 0.7 w 42 43 m 570 43 l S Q\n";
    $pageCommands .= "BT /F1 8 Tf 42 28 Td (" . $pdfText('VayuPower | Service Claim #' . $claimId) . ") Tj ET\n";
    $pageCommands .= "BT /F1 8 Tf 520 28 Td (" . ($index + 1) . " / {$pageCount}) Tj ET\n";
}
unset($pageCommands);

$objects = [
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
    4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
];
$pageObjectIds = [];
$nextObjectId = 5;

foreach ($pages as $pageCommands) {
    $pageObjectId = $nextObjectId++;
    $streamObjectId = $nextObjectId++;
    $pageObjectIds[] = $pageObjectId;

    $objects[$pageObjectId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $streamObjectId . ' 0 R >>';
    $objects[$streamObjectId] = '<< /Length ' . strlen($pageCommands) . " >>\nstream\n{$pageCommands}endstream";
}

$kids = implode(' ', array_map(static fn (int $id): string => $id . ' 0 R', $pageObjectIds));
$objects[2] = '<< /Type /Pages /Kids [' . $kids . '] /Count ' . count($pageObjectIds) . ' >>';
ksort($objects);

$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $objectId => $objectBody) {
    $offsets[$objectId] = strlen($pdf);
    $pdf .= $objectId . " 0 obj\n{$objectBody}\nendobj\n";
}

$xrefOffset = strlen($pdf);
$objectCount = max(array_keys($objects)) + 1;
$pdf .= "xref\n0 {$objectCount}\n0000000000 65535 f \n";
for ($objectId = 1; $objectId < $objectCount; $objectId++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectId] ?? 0);
}
$pdf .= "trailer\n<< /Size {$objectCount} /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="service-claim-' . $claimId . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
exit;
