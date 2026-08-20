<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/current_username_helpers.php';
require_once 'includes/warranty_claims_helpers.php';
require_once 'includes/distance_wise_price_helpers.php';

warranty_claims_ensure_schema($obconn);
distance_wise_price_ensure_schema($obconn);

$success_message = '';
$error_message   = '';
$userName        = current_username();

$canMarkCcs      = rbac_user_can($obconn, 'service-claims', 'mark-warranty');
$canApproveL1    = rbac_user_can($obconn, 'service-claims', 'approve-l1');
$canRaiseInvoice = rbac_user_can($obconn, 'service-claims', 'raise-invoice');
$canSettle       = rbac_user_can($obconn, 'service-claims', 'settle-claim');

// ─── Handle Call Closure Submission (Process 2, steps 1-2) ───────────────────
// Anyone who can view this page can log a call closure (same convention as new_complaint.php).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_service_claim'])) {
    $complaintId     = (int) ($_POST['complaint_id'] ?? 0);
    $kmTravelled     = trim($_POST['km_travelled'] ?? '');
    $serviceDate     = trim($_POST['service_date'] ?? '');
    $resolutionNotes = trim($_POST['resolution_notes'] ?? '');

    $complaint = warranty_claims_find_complaint($obconn, $complaintId);

    if ($complaint === null) {
        $error_message = 'Please select a valid Call Ticket Number.';
    } elseif (!is_numeric($kmTravelled) || (float) $kmTravelled <= 0) {
        $error_message = 'Distance travelled (KMs) is required and must be greater than zero.';
    } elseif ($serviceDate === '') {
        $error_message = 'Service Date is required.';
    } elseif (strlen($resolutionNotes) > 1000) {
        $error_message = 'Resolution notes cannot exceed 1000 characters.';
    } else {
        try {
            $visitCharge = distance_wise_price_find_for_km($obconn, (float) $kmTravelled);

            $stmt = $obconn->prepare("
                INSERT INTO service_claims
                (
                    complaint_id, km_travelled, service_date, resolution_notes,
                    visit_charge_price, overall_status, created_by_username
                )
                VALUES
                (
                    :complaint_id, :km_travelled, :service_date, :resolution_notes,
                    :visit_charge_price, :overall_status, :created_by_username
                )
                RETURNING id
            ");
            $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
            $stmt->bindValue(':km_travelled', (float) $kmTravelled);
            $stmt->bindValue(':service_date', $serviceDate);
            $stmt->bindValue(':resolution_notes', $resolutionNotes !== '' ? $resolutionNotes : null);
            if ($visitCharge === null) {
                $stmt->bindValue(':visit_charge_price', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':visit_charge_price', number_format((float) $visitCharge['price'], 2, '.', ''));
            }
            $stmt->bindValue(':overall_status', 'Pending CCS Review');
            $stmt->bindValue(':created_by_username', $userName);
            $stmt->execute();

            $newClaimId = (int) $stmt->fetchColumn();

            warranty_claims_notify_role_holders(
                $obconn,
                'service-claims',
                'mark-warranty',
                'New Service Claim Pending CCS Review',
                'Service claim #' . $newClaimId . ' for call ticket #' . $complaintId . ' needs a warranty eligibility decision.',
                $newClaimId
            );

            $_SESSION['success_message'] = 'Call closure submitted successfully. Pending CCS warranty review.';
            header('Location: service_claims.php');
            exit;
        } catch (PDOException $e) {
            $error_message = 'Failed to submit call closure. Please try again.';
        }
    }
}

// ─── Handle CCS warranty marking (Process 2, steps 3-4) ──────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_warranty'])) {
    if (!$canMarkCcs) {
        header('Location: access_denied.php');
        exit;
    }

    $claimId       = (int) ($_POST['claim_id'] ?? 0);
    $warrantyClaim = trim($_POST['mark_warranty'] ?? '');
    $ccsRemarks    = trim($_POST['ccs_remarks'] ?? '');

    if ($claimId <= 0 || !in_array($warrantyClaim, ['Yes', 'No'], true)) {
        $_SESSION['error_message'] = 'Please select Yes or No for warranty eligibility.';
        header('Location: service_claims.php');
        exit;
    }

    $claimStmt = $obconn->prepare("SELECT * FROM service_claims WHERE id = :id AND deleted_at IS NULL");
    $claimStmt->bindValue(':id', $claimId, PDO::PARAM_INT);
    $claimStmt->execute();
    $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);

    if ($claim === false || $claim['overall_status'] !== 'Pending CCS Review') {
        $_SESSION['error_message'] = 'This claim is not pending CCS review.';
        header('Location: service_claims.php');
        exit;
    }

    // Either Yes or No moves the claim to Lock-in Engineer approval (BRD step 4).
    $update = $obconn->prepare("
        UPDATE service_claims
        SET ccs_warranty_claim = :warranty_claim, ccs_remarks = :remarks,
            ccs_marked_by_username = :by, ccs_marked_at = CURRENT_TIMESTAMP,
            overall_status = 'Pending L1 Approval', updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $update->bindValue(':warranty_claim', $warrantyClaim);
    $update->bindValue(':remarks', $ccsRemarks !== '' ? $ccsRemarks : null);
    $update->bindValue(':by', $userName);
    $update->bindValue(':id', $claimId, PDO::PARAM_INT);
    $update->execute();

    warranty_claims_notify_role_holders(
        $obconn,
        'service-claims',
        'approve-l1',
        'Service Claim Pending L1 Approval',
        'Service claim #' . $claimId . ' has been marked "' . $warrantyClaim . '" by CCS and needs Lock-in Engineer approval.',
        $claimId
    );

    $_SESSION['success_message'] = 'Warranty eligibility recorded. Claim moved to L1 approval.';
    header('Location: service_claims.php');
    exit;
}

// ─── Handle L1 (Lock-in Engineer) decision (Process 2, step 5-6) ─────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['l1_decision'])) {
    if (!$canApproveL1) {
        header('Location: access_denied.php');
        exit;
    }

    $claimId  = (int) ($_POST['claim_id'] ?? 0);
    $decision = trim($_POST['l1_decision'] ?? '');
    $remarks  = trim($_POST['l1_remarks'] ?? '');
    // Send the user back to the Approvals dashboard if that's where the decision was submitted from.
    $redirectTo = ($_POST['return_to'] ?? '') === 'approvals.php' ? 'approvals.php' : 'service_claims.php';

    if ($claimId <= 0 || !in_array($decision, [SERVICE_CLAIM_L1_APPROVED, SERVICE_CLAIM_L1_REJECTED], true)) {
        header('Location: access_denied.php');
        exit;
    }

    if ($decision === SERVICE_CLAIM_L1_REJECTED && $remarks === '') {
        $_SESSION['error_message'] = 'Remarks are required to reject a claim.';
        header('Location: ' . $redirectTo);
        exit;
    }

    $claimStmt = $obconn->prepare("SELECT * FROM service_claims WHERE id = :id AND deleted_at IS NULL");
    $claimStmt->bindValue(':id', $claimId, PDO::PARAM_INT);
    $claimStmt->execute();
    $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);

    if ($claim === false || $claim['overall_status'] !== 'Pending L1 Approval') {
        $_SESSION['error_message'] = 'This claim is not pending L1 approval.';
        header('Location: ' . $redirectTo);
        exit;
    }

    $overallStatus = $decision === SERVICE_CLAIM_L1_APPROVED ? 'Approved - Pending Invoice' : 'Rejected';
    $update = $obconn->prepare("
        UPDATE service_claims
        SET l1_status = :status, l1_by_username = :by, l1_at = CURRENT_TIMESTAMP,
            l1_remarks = :remarks, overall_status = :overall_status, updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $update->bindValue(':status', $decision);
    $update->bindValue(':by', $userName);
    $update->bindValue(':remarks', $remarks !== '' ? $remarks : null);
    $update->bindValue(':overall_status', $overallStatus);
    $update->bindValue(':id', $claimId, PDO::PARAM_INT);
    $update->execute();

    if ($decision === SERVICE_CLAIM_L1_APPROVED) {
        warranty_claims_notify_role_holders(
            $obconn,
            'service-claims',
            'raise-invoice',
            'Service Claim Approved - Invoice Pending',
            'Service claim #' . $claimId . ' has been approved. Please raise the predefined visit-charge invoice.',
            $claimId
        );
    }

    $_SESSION['success_message'] = 'Service claim #' . $claimId . ' has been ' . strtolower($decision) . ' at L1.';
    header('Location: ' . $redirectTo);
    exit;
}

// ─── Handle Dealer raising the invoice (Process 2, step 6) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['raise_invoice'])) {
    if (!$canRaiseInvoice) {
        header('Location: access_denied.php');
        exit;
    }

    $claimId       = (int) ($_POST['claim_id'] ?? 0);
    $invoiceNumber = trim($_POST['invoice_number'] ?? '');
    $invoiceAmount = trim($_POST['invoice_amount'] ?? '');

    $claimStmt = $obconn->prepare("SELECT * FROM service_claims WHERE id = :id AND deleted_at IS NULL");
    $claimStmt->bindValue(':id', $claimId, PDO::PARAM_INT);
    $claimStmt->execute();
    $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);

    if ($claim === false || $claim['overall_status'] !== 'Approved - Pending Invoice') {
        $_SESSION['error_message'] = 'This claim is not ready for invoicing.';
        header('Location: service_claims.php');
        exit;
    }

    if ($invoiceNumber === '' || !is_numeric($invoiceAmount) || (float) $invoiceAmount <= 0) {
        $_SESSION['error_message'] = 'A valid invoice number and amount are required.';
        header('Location: service_claims.php');
        exit;
    }

    $update = $obconn->prepare("
        UPDATE service_claims
        SET invoice_number = :invoice_number, invoice_amount = :invoice_amount,
            invoice_raised_by_username = :by, invoice_raised_at = CURRENT_TIMESTAMP,
            overall_status = 'Invoice Raised - Pending Settlement', updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $update->bindValue(':invoice_number', $invoiceNumber);
    $update->bindValue(':invoice_amount', (float) $invoiceAmount);
    $update->bindValue(':by', $userName);
    $update->bindValue(':id', $claimId, PDO::PARAM_INT);
    $update->execute();

    warranty_claims_notify_role_holders(
        $obconn,
        'service-claims',
        'settle-claim',
        'Service Claim Invoice Raised',
        'Invoice ' . $invoiceNumber . ' raised for service claim #' . $claimId . '. Please process settlement.',
        $claimId
    );

    $_SESSION['success_message'] = 'Invoice recorded. Awaiting Finance settlement.';
    header('Location: service_claims.php');
    exit;
}

// ─── Handle Finance settlement (Process 2, step 7) ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['settle_claim'])) {
    if (!$canSettle) {
        header('Location: access_denied.php');
        exit;
    }

    $claimId             = (int) ($_POST['claim_id'] ?? 0);
    $settlementType      = trim($_POST['settlement_type'] ?? '');
    $settlementReference = trim($_POST['settlement_reference'] ?? '');

    $claimStmt = $obconn->prepare("SELECT * FROM service_claims WHERE id = :id AND deleted_at IS NULL");
    $claimStmt->bindValue(':id', $claimId, PDO::PARAM_INT);
    $claimStmt->execute();
    $claim = $claimStmt->fetch(PDO::FETCH_ASSOC);

    if ($claim === false || $claim['overall_status'] !== 'Invoice Raised - Pending Settlement') {
        $_SESSION['error_message'] = 'This claim is not ready for settlement.';
        header('Location: service_claims.php');
        exit;
    }

    if (!in_array($settlementType, ['Reimbursement', 'Credit Note'], true) || $settlementReference === '') {
        $_SESSION['error_message'] = 'Please select a settlement type and provide a reference.';
        header('Location: service_claims.php');
        exit;
    }

    $update = $obconn->prepare("
        UPDATE service_claims
        SET settlement_type = :settlement_type, settlement_reference = :settlement_reference,
            settled_by_username = :by, settled_at = CURRENT_TIMESTAMP,
            overall_status = 'Settled', updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $update->bindValue(':settlement_type', $settlementType);
    $update->bindValue(':settlement_reference', $settlementReference);
    $update->bindValue(':by', $userName);
    $update->bindValue(':id', $claimId, PDO::PARAM_INT);
    $update->execute();

    $_SESSION['success_message'] = 'Service claim #' . $claimId . ' settled via ' . $settlementType . '.';
    header('Location: service_claims.php');
    exit;
}

// ─── Fetch existing claims for the datatable ─────────────────────────────────
$claims = [];
try {
    $claimStmt = $obconn->query("
        SELECT
            sc.*, c.fab_number, c.customer_name
        FROM service_claims sc
        INNER JOIN complaints c ON c.id = sc.complaint_id
        WHERE sc.deleted_at IS NULL
        ORDER BY sc.created_at DESC
    ");
    $claims = $claimStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist yet; silently continue
}

$recentComplaints = warranty_claims_recent_complaints($obconn);
$distanceWisePriceSlabs = distance_wise_price_slabs_for_js(distance_wise_price_get_active_slabs($obconn));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warranty Service Claim</title>
    <?php include 'header_css.php'; ?>
    <link href="css/new_complaint.css" rel="stylesheet">
    <link href="css/complaint_buttons.css" rel="stylesheet">
    <link href="css/orderbook_style.css" rel="stylesheet">
    <link href="css/complaint_form.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
    <link href="css/datatable_custom.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="css/select2_change.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
</head>
<body>
<div class="main-wrapper" id="mainWrapper">

    <?php include 'sidebar.php'; ?>

    <div class="content">

        <!-- Flash messages -->
        <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($error_message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($_SESSION['success_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success_message']); endif; ?>
        <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
            <?= htmlspecialchars($_SESSION['error_message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error_message']); endif; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <div class="page-subtitle">
                    Log warranty service visit call closures and track approval, invoicing and settlement.
                </div>
            </div>
            <div class="header-btn-group">
                <button class="new-order-btn btn-complaint-primary" id="openClaimForm" type="button">
                    <i class="bi bi-plus-lg"></i> New Call Closure
                </button>
                <button class="close-form-btn cancel-btn" id="closeClaimForm" type="button" style="display:none;">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
            </div>
        </div>

        <!-- ── Call Closure Entry Form ────────────────────────────────────── -->
        <div class="complaint-form-card" id="claimFormCard" style="display:none;">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon">
                        <i class="bi bi-wrench-adjustable"></i>
                    </div>
                    <div>
                        <h2 class="complaint-form-header__title">New Warranty Service Claim</h2>
                        <p class="complaint-form-header__subtitle">
                            Close the call and submit the service visit details for warranty claim review.
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST" id="serviceClaimForm" novalidate>
                <div class="complaint-form-body">

                    <!-- Section 1 – Call Ticket -->
                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">1</span>
                            <div>
                                <h3 class="complaint-form-section__title">Call Ticket</h3>
                                <p class="complaint-form-section__hint">Select the complaint (call ticket) this service visit relates to.</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-8 form-group">
                                <label class="form-label" for="complaintId">
                                    <i class="bi bi-upc-scan"></i> Call Ticket Number <span class="text-danger">*</span>
                                </label>
                                <select class="form-control" id="complaintId" name="complaint_id">
                                    <option value="">-- Select Call Ticket --</option>
                                    <?php foreach ($recentComplaints as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"
                                        <?= (((int) ($_POST['complaint_id'] ?? 0)) === (int) $c['id']) ? 'selected' : '' ?>>
                                        #<?= (int) $c['id'] ?> — <?= htmlspecialchars($c['fab_number']) ?> (<?= htmlspecialchars($c['customer_name']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="text-danger validation-msg" data-field="complaint_id"></div>
                            </div>
                            <div class="col-md-4 form-group d-flex align-items-end">
                                <a href="#" id="viewTicketLink" target="_blank" rel="noopener"
                                    class="btn btn-outline-secondary w-100 disabled">
                                    <i class="bi bi-box-arrow-up-right"></i> View Ticket Details
                                </a>
                            </div>
                        </div>
                    </section>

                    <!-- Section 2 – Call Closure Details -->
                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">2</span>
                            <div>
                                <h3 class="complaint-form-section__title">Call Closure Details</h3>
                                <p class="complaint-form-section__hint">Distance travelled is mandatory to close the call.</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4 form-group">
                                <label class="form-label" for="kmTravelled">
                                    <i class="bi bi-signpost-split"></i> Distance Travelled (KMs) <span class="text-danger">*</span>
                                </label>
                                <input type="number" step="0.1" min="0.1" class="form-control" id="kmTravelled" name="km_travelled"
                                    placeholder="e.g. 42.5"
                                    value="<?= htmlspecialchars($_POST['km_travelled'] ?? '') ?>">
                                <div class="text-danger validation-msg" data-field="km_travelled"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label" for="visitChargePriceDisplay">
                                    <i class="bi bi-currency-rupee"></i> Price
                                </label>
                                <input type="text" class="form-control" id="visitChargePriceDisplay" value="" readonly
                                    placeholder="Auto from KM slab" style="background-color:#f8f9fa;">
                                <input type="hidden" name="visit_charge_price" id="visitChargePrice" value="">
                                <div class="form-text" id="visitChargePriceHint"></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label" for="serviceDate">
                                    <i class="bi bi-calendar-event"></i> Service Date <span class="text-danger">*</span>
                                </label>
                                <input type="date" class="form-control" id="serviceDate" name="service_date"
                                    value="<?= htmlspecialchars($_POST['service_date'] ?? '') ?>"
                                    max="<?= date('Y-m-d') ?>">
                                <div class="text-danger validation-msg" data-field="service_date"></div>
                            </div>
                            <div class="col-md-12 form-group">
                                <label class="form-label" for="resolutionNotes">
                                    <i class="bi bi-chat-left-text"></i> Complaint Resolution Notes
                                </label>
                                <textarea class="form-control" id="resolutionNotes" name="resolution_notes"
                                    rows="3" placeholder="Describe how the complaint was resolved (max 1000 characters)"
                                    maxlength="1000"><?= htmlspecialchars($_POST['resolution_notes'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </section>

                </div><!-- /.complaint-form-body -->

                <div class="complaint-form-footer d-flex justify-content-end gap-2 p-3">
                    <button type="button" class="btn btn-outline-secondary" id="cancelClaimForm">
                        <i class="bi bi-x-lg"></i> Cancel
                    </button>
                    <button type="submit" name="submit_service_claim" class="btn btn-complaint-primary">
                        <i class="bi bi-send"></i> Submit Call Closure
                    </button>
                </div>
            </form>
        </div>
        <!-- ── End Form ───────────────────────────────────────────────────── -->

        <!-- ── Claims List ────────────────────────────────────────────────── -->
        <div class="card mt-3" id="claimTableCard">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-list-ul"></i>
                <strong>Warranty Service Claims</strong>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="serviceClaimsTable" class="table table-hover mb-0 datatable-standard">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Call Ticket</th>
                                <th>Fab Number</th>
                                <th>Customer</th>
                                <th>KM</th>
                                <th>Service Date</th>
                                <th>Warranty (CCS)</th>
                                <th>L1 (Lock-in Engineer)</th>
                                <th>Invoice</th>
                                <th>Settlement</th>
                                <th>Overall Status</th>
                                <th>Submitted By</th>
                                <?php if ($canMarkCcs || $canApproveL1 || $canRaiseInvoice || $canSettle): ?>
                                <th>Actions</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($claims as $i => $row): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td>
                                    <a href="complaint_details.php?id=<?= rawurlencode(base64_encode((string) $row['complaint_id'])) ?>" target="_blank" rel="noopener">
                                        #<?= (int) $row['complaint_id'] ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($row['fab_number']) ?></td>
                                <td><?= htmlspecialchars($row['customer_name']) ?></td>
                                <td><?= htmlspecialchars($row['km_travelled']) ?></td>
                                <td><?= htmlspecialchars($row['service_date']) ?></td>
                                <td>
                                    <?php if (!empty($row['ccs_warranty_claim'])): ?>
                                        <span class="badge <?= $row['ccs_warranty_claim'] === 'Yes' ? 'bg-success' : 'bg-secondary' ?>"><?= htmlspecialchars($row['ccs_warranty_claim']) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?= foc_stage_badge_class($row['l1_status']) ?>"><?= htmlspecialchars($row['l1_status']) ?></span></td>
                                <td><?= !empty($row['invoice_number']) ? htmlspecialchars($row['invoice_number']) . ' (₹' . htmlspecialchars($row['invoice_amount']) . ')' : '—' ?></td>
                                <td><?= !empty($row['settlement_type']) ? htmlspecialchars($row['settlement_type']) . ' - ' . htmlspecialchars($row['settlement_reference']) : '—' ?></td>
                                <td><?= htmlspecialchars($row['overall_status']) ?></td>
                                <td><?= htmlspecialchars($row['created_by_username']) ?></td>
                                <?php if ($canMarkCcs || $canApproveL1 || $canRaiseInvoice || $canSettle): ?>
                                <td>
                                    <?php if ($canMarkCcs && $row['overall_status'] === 'Pending CCS Review'): ?>
                                        <form method="POST" class="d-flex gap-1">
                                            <input type="hidden" name="claim_id" value="<?= (int) $row['id'] ?>">
                                            <input type="text" name="ccs_remarks" class="form-control form-control-sm" placeholder="CCS remarks">
                                            <button type="submit" name="mark_warranty" value="Yes" class="btn btn-sm btn-success">Warranty: Yes</button>
                                            <button type="submit" name="mark_warranty" value="No" class="btn btn-sm btn-secondary">Warranty: No</button>
                                        </form>
                                    <?php elseif ($canApproveL1 && $row['overall_status'] === 'Pending L1 Approval'): ?>
                                        <form method="POST" class="d-flex gap-1">
                                            <input type="hidden" name="claim_id" value="<?= (int) $row['id'] ?>">
                                            <input type="text" name="l1_remarks" class="form-control form-control-sm" placeholder="Remarks">
                                            <button type="submit" name="l1_decision" value="Approved" class="btn btn-sm btn-success">Approve</button>
                                            <button type="submit" name="l1_decision" value="Rejected" class="btn btn-sm btn-danger">Reject</button>
                                        </form>
                                    <?php elseif ($canRaiseInvoice && $row['overall_status'] === 'Approved - Pending Invoice'): ?>
                                        <form method="POST" class="d-flex gap-1">
                                            <input type="hidden" name="claim_id" value="<?= (int) $row['id'] ?>">
                                            <input type="text" name="invoice_number" class="form-control form-control-sm" placeholder="Invoice #" required>
                                            <input type="number" step="0.01" min="0.01" name="invoice_amount" class="form-control form-control-sm" placeholder="Amount" required>
                                            <button type="submit" name="raise_invoice" value="1" class="btn btn-sm btn-primary">Raise Invoice</button>
                                        </form>
                                    <?php elseif ($canSettle && $row['overall_status'] === 'Invoice Raised - Pending Settlement'): ?>
                                        <form method="POST" class="d-flex gap-1">
                                            <input type="hidden" name="claim_id" value="<?= (int) $row['id'] ?>">
                                            <select name="settlement_type" class="form-control form-control-sm" required>
                                                <option value="">Type</option>
                                                <option value="Reimbursement">Reimbursement</option>
                                                <option value="Credit Note">Credit Note</option>
                                            </select>
                                            <input type="text" name="settlement_reference" class="form-control form-control-sm" placeholder="Reference #" required>
                                            <button type="submit" name="settle_claim" value="1" class="btn btn-sm btn-primary">Settle</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <?php endif; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <!-- ── End Claims List ───────────────────────────────────────────── -->

    </div><!-- /.content -->
</div><!-- /.main-wrapper -->

<script>
(function () {
    const openBtn   = document.getElementById('openClaimForm');
    const closeBtn  = document.getElementById('closeClaimForm');
    const cancelBtn = document.getElementById('cancelClaimForm');
    const formCard  = document.getElementById('claimFormCard');
    const tableCard = document.getElementById('claimTableCard');
    const complaintSelect = document.getElementById('complaintId');
    const viewTicketLink  = document.getElementById('viewTicketLink');

    if (typeof $ !== 'undefined' && $.fn.select2) {
        $('#complaintId').select2({
            placeholder: '-- Select Call Ticket --',
            allowClear: true,
            width: '100%'
        });
    }

    function showForm() {
        if (!formCard) return;
        formCard.style.display = 'block';
        tableCard.style.display = 'none';
        if (openBtn) openBtn.style.display  = 'none';
        if (closeBtn) closeBtn.style.display = '';
        formCard.scrollIntoView({ behavior: 'smooth' });
    }

    function hideForm() {
        if (!formCard) return;
        formCard.style.display = 'none';
        tableCard.style.display = 'block';
        if (openBtn) openBtn.style.display  = '';
        if (closeBtn) closeBtn.style.display = 'none';
    }

    if (openBtn) openBtn.addEventListener('click', showForm);
    if (closeBtn) closeBtn.addEventListener('click', hideForm);
    if (cancelBtn) cancelBtn.addEventListener('click', hideForm);

    if (complaintSelect && viewTicketLink) {
        complaintSelect.addEventListener('change', function () {
            const id = this.value;
            if (id) {
                viewTicketLink.href = 'complaint_details.php?id=' + encodeURIComponent(btoa(id));
                viewTicketLink.classList.remove('disabled');
            } else {
                viewTicketLink.href = '#';
                viewTicketLink.classList.add('disabled');
            }
        });
    }

    // Show form if there was a POST validation error
    <?php if (!empty($error_message) && $_SERVER['REQUEST_METHOD'] === 'POST'): ?>
    showForm();
    <?php endif; ?>

    // DataTable
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#serviceClaimsTable').DataTable({
            order: [[0, 'desc']],
            pageLength: 25,
            language: { emptyTable: 'No warranty service claims submitted yet.' }
        });
    }

    const kmInput = document.getElementById('kmTravelled');
    const priceDisplay = document.getElementById('visitChargePriceDisplay');
    const priceHidden = document.getElementById('visitChargePrice');
    const priceHint = document.getElementById('visitChargePriceHint');
    const slabs = <?= json_encode($distanceWisePriceSlabs, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?> || [];

    function slabMatchesKm(slab, km) {
        const type = slab.range_type || 'between';
        if (type === 'lt') {
            return slab.to_km !== null && km < Number(slab.to_km);
        }
        if (type === 'gt') {
            return slab.from_km !== null && km > Number(slab.from_km);
        }
        return slab.from_km !== null && slab.to_km !== null
            && km >= Number(slab.from_km)
            && km <= Number(slab.to_km);
    }

    function findPriceForKm(km) {
        const matches = slabs.filter(function (slab) {
            return slabMatchesKm(slab, km);
        });
        if (!matches.length) {
            return null;
        }
        matches.sort(function (a, b) {
            const aStart = a.from_km === null ? Number.NEGATIVE_INFINITY : Number(a.from_km);
            const bStart = b.from_km === null ? Number.NEGATIVE_INFINITY : Number(b.from_km);
            return aStart - bStart;
        });
        return matches[matches.length - 1];
    }

    function updateVisitChargePrice() {
        if (!kmInput || !priceDisplay) {
            return;
        }
        const km = parseFloat(kmInput.value);
        if (!kmInput.value || isNaN(km) || km <= 0) {
            priceDisplay.value = '';
            if (priceHidden) priceHidden.value = '';
            if (priceHint) priceHint.textContent = '';
            return;
        }
        const match = findPriceForKm(km);
        if (!match) {
            priceDisplay.value = '';
            if (priceHidden) priceHidden.value = '';
            if (priceHint) priceHint.textContent = 'No matching distance slab found.';
            return;
        }
        priceDisplay.value = match.price;
        if (priceHidden) priceHidden.value = match.price;
        if (priceHint) priceHint.textContent = '';
    }

    if (kmInput) {
        kmInput.addEventListener('input', updateVisitChargePrice);
        kmInput.addEventListener('change', updateVisitChargePrice);
        updateVisitChargePrice();
    }
})();
</script>
<script>
// Require remarks before a Reject decision can be submitted (Approve stays optional).
document.addEventListener('click', function (e) {
    const btn = e.target.closest('button[value="Rejected"]');
    if (!btn) return;
    const form = btn.closest('form');
    const remarksInput = form && form.querySelector('input[name="approval_remarks"], input[name="l1_remarks"]');
    if (remarksInput && remarksInput.value.trim() === '') {
        e.preventDefault();
        remarksInput.classList.add('is-invalid');
        remarksInput.focus();
        alert('Please enter remarks before rejecting.');
    }
});
</script>
</body>
</html>
