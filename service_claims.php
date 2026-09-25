<?php
session_start();

include 'pdo_obconn.php';
require_once 'includes/rbac_page_guard.php';
require_once 'includes/current_username_helpers.php';
require_once 'includes/warranty_claims_helpers.php';
require_once 'includes/distance_wise_price_helpers.php';
require_once 'includes/installed_base_helpers.php';
require_once 'includes/amc_helpers.php';

warranty_claims_ensure_schema($obconn);
distance_wise_price_ensure_schema($obconn);

$success_message = '';
$error_message   = '';
$field_errors    = [];
$userName        = current_username();


$canCreateClaim  = rbac_user_can($obconn, 'service-claims', 'create-service-claims');
$canDeleteClaim  = rbac_user_can($obconn, 'service-claims', 'delete');
$canSeeSubmittedBy = service_claims_user_can_see_submitted_by($obconn);
//$canMarkCcs      = rbac_user_can($obconn, 'service-claims', 'mark-warranty');
//$canApproveL1    = rbac_user_can($obconn, 'service-claims', 'approve-l1');
//$canRaiseInvoice = rbac_user_can($obconn, 'service-claims', 'raise-invoice');
//$canSettle       = rbac_user_can($obconn, 'service-claims', 'settle-claim');
$canMarkCcs = true;
$canApproveL1 = true;
$canRaiseInvoice = true;
$canSettle = true;

// --- Handle Call Closure Submission (Process 2, steps 1-2) -------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_service_claim'])) {
    if (!$canCreateClaim) {
        header('Location: access_denied.php');
        exit;
    }
    $complaintId     = (int) ($_POST['complaint_id'] ?? 0);
    $kmTravelled     = trim($_POST['km_travelled'] ?? '');
    $serviceDate     = trim($_POST['service_date'] ?? '');
    $resolutionNotes = trim($_POST['resolution_notes'] ?? '');
    $poNumber        = trim($_POST['po_number'] ?? '');
    $batchCode       = trim($_POST['batch_code'] ?? '');
    $claimAmount     = trim($_POST['claim_amount'] ?? '');
    $dispute         = trim($_POST['dispute'] ?? '');
    $disputeRemarks  = trim($_POST['dispute_remarks'] ?? '');
    $claimInvoiceNo  = trim($_POST['claim_invoice_number'] ?? '');
    $claimInvoiceDate = trim($_POST['claim_invoice_date'] ?? '');
    $customerNumber   = trim((string) ($_SESSION['cuno'] ?? $_SESSION['customer_number_vayu'] ?? ''));
    $customerArea     = trim((string) ($_SESSION['area'] ?? $_SESSION['areacode'] ?? ''));
    $disputeFlag      = $dispute === 'Yes' ? 'Y' : ($dispute === 'No' ? 'N' : '');
    $poUpload        = service_claim_po_validate_upload($_FILES['po_attachment'] ?? null);

    $complaint = warranty_claims_find_complaint($obconn, $complaintId);

    if ($complaint === null) {
        $field_errors['complaint_id'] = 'Please select a valid Call Ticket Number.';
        $error_message = $field_errors['complaint_id'];
    } else {
        if ($kmTravelled === '' || !is_numeric($kmTravelled) || (float) $kmTravelled <= 0) {
            $prefillKm = service_claim_distance_from_service_log($obconn, $complaintId);
            if (!empty($prefillKm['found'])) {
                $kmTravelled = (string) $prefillKm['km_travelled'];
            }
        }

        if (!is_numeric($kmTravelled) || (float) $kmTravelled <= 0) {
            $field_errors['km_travelled'] = 'Distance Travelled (KMs) is required and must be greater than zero.';
            $error_message = $field_errors['km_travelled'];
        } elseif ($serviceDate === '') {
            $field_errors['service_date'] = 'Service Date is required.';
            $error_message = $field_errors['service_date'];
        } elseif (strlen($poNumber) > 100) {
            $field_errors['po_number'] = 'Invoice cannot exceed 100 characters.';
            $error_message = $field_errors['po_number'];
        } elseif ($poUpload['error'] !== null) {
            $field_errors['po_attachment'] = $poUpload['error'];
            $error_message = $field_errors['po_attachment'];
        } elseif (strlen($resolutionNotes) > 1000) {
            $error_message = 'Resolution notes cannot exceed 1000 characters.';
        } elseif ($customerNumber !== '' && strlen($customerNumber) > 9) {
            $error_message = 'Customer number cannot exceed 9 characters.';
        } elseif ($customerArea !== '' && strlen($customerArea) > 3) {
            $error_message = 'Customer area cannot exceed 3 characters.';
        } elseif (!is_numeric($claimAmount) || (float) $claimAmount < 0) {
            $field_errors['claim_amount'] = 'Claim Amount is required and must be a valid non-negative amount.';
            $error_message = $field_errors['claim_amount'];
        } elseif ($dispute !== '' && !in_array($dispute, ['Yes', 'No'], true)) {
            $error_message = 'Dispute must be Yes or No.';
        } elseif (strlen($disputeRemarks) > 500) {
            $error_message = 'Dispute remarks cannot exceed 500 characters.';
        } elseif (strlen($claimInvoiceNo) > 30) {
            $error_message = 'Invoice number cannot exceed 30 characters.';
        } else {
            $approvers = foc_claim_resolve_approver_ids($obconn, current_user_id($obconn));
            if ($approvers['error'] !== null) {
                $error_message = $approvers['error'];
            } else {
            $stage = foc_claim_initial_stage($approvers);
            $resolvedWarranty = warranty_claims_resolve_status_for_complaint($obconn, $complaintId);
            $warrantyStatus = trim((string) ($resolvedWarranty['status'] ?? ''));
            if (!in_array($warrantyStatus, warranty_claims_workflow_statuses(), true)) {
                $warrantyStatus = '';
            }
            $storedPo = null;
            try {
                if (!empty($poUpload['file'])) {
                    $storedPo = service_claim_po_store_upload($poUpload['file']);
                }
                $visitCharge = distance_wise_price_find_for_km($obconn, (float) $kmTravelled);

                $stmt = $obconn->prepare("
                    INSERT INTO service_claims
                    (
                        complaint_id, km_travelled, service_date, resolution_notes,
                        po_number, po_attachment, po_attachment_original,
                        visit_charge_price, warranty_status, l1_status, l2_status,
                        l1_approver_user_id, l2_approver_user_id,
                        overall_status, created_by_username
                    )
                    VALUES
                    (
                        :complaint_id, :km_travelled, :service_date, :resolution_notes,
                        :po_number, :po_attachment, :po_attachment_original,
                        :visit_charge_price, :warranty_status, :l1_status, :l2_status,
                        :l1_approver_user_id, :l2_approver_user_id,
                        :overall_status, :created_by_username
                    )
                    RETURNING id
                ");
                $stmt->bindValue(':complaint_id', $complaintId, PDO::PARAM_INT);
                $stmt->bindValue(':km_travelled', (float) $kmTravelled);
                $stmt->bindValue(':service_date', $serviceDate);
                $stmt->bindValue(':resolution_notes', $resolutionNotes !== '' ? $resolutionNotes : null);
                if ($poNumber !== '') {
                    $stmt->bindValue(':po_number', $poNumber);
                } else {
                    $stmt->bindValue(':po_number', null, PDO::PARAM_NULL);
                }
                if ($storedPo !== null) {
                    $stmt->bindValue(':po_attachment', $storedPo['stored']);
                    $stmt->bindValue(':po_attachment_original', $storedPo['original']);
                } else {
                    $stmt->bindValue(':po_attachment', null, PDO::PARAM_NULL);
                    $stmt->bindValue(':po_attachment_original', null, PDO::PARAM_NULL);
                }
                if ($visitCharge === null) {
                    $stmt->bindValue(':visit_charge_price', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':visit_charge_price', number_format((float) $visitCharge['price'], 2, '.', ''));
                }
                $stmt->bindValue(':warranty_status', $warrantyStatus !== '' ? $warrantyStatus : null);
                $stmt->bindValue(':l1_status', $stage['l1_status']);
                $stmt->bindValue(':l2_status', $stage['l2_status']);
                foc_claim_bind_nullable_user_id($stmt, ':l1_approver_user_id', $approvers['l1'] ?? null);
                foc_claim_bind_nullable_user_id($stmt, ':l2_approver_user_id', $approvers['l2'] ?? null);
                $stmt->bindValue(':overall_status', $stage['overall_status']);
                $stmt->bindValue(':created_by_username', $userName);
                $stmt->execute();

                $newClaimId = (int) $stmt->fetchColumn();

                $pendingReimbursement = $obconn->prepare("\n                    INSERT INTO service_claim_reimbursement_pending\n                    (service_claim_id, batch_code, cuno, area, claim_amount, dispute, dispute_remarks, invno, invdt)\n                    VALUES (:service_claim_id, :batch_code, :cuno, :area, :claim_amount, :dispute, :dispute_remarks, :invno, :invdt)\n                ");
                $pendingReimbursement->bindValue(':service_claim_id', $newClaimId, PDO::PARAM_INT);
                $pendingReimbursement->bindValue(':batch_code', $batchCode);
                $pendingReimbursement->bindValue(':cuno', $customerNumber !== '' ? $customerNumber : null);
                $pendingReimbursement->bindValue(':area', $customerArea !== '' ? $customerArea : null);
                $pendingReimbursement->bindValue(':claim_amount', (float) $claimAmount);
                $pendingReimbursement->bindValue(':dispute', $disputeFlag !== '' ? $disputeFlag : null);
                $pendingReimbursement->bindValue(':dispute_remarks', $disputeRemarks !== '' ? $disputeRemarks : null);
                $pendingReimbursement->bindValue(':invno', $claimInvoiceNo !== '' ? $claimInvoiceNo : null);
                $pendingReimbursement->bindValue(':invdt', $claimInvoiceDate !== '' ? $claimInvoiceDate : null);
                $pendingReimbursement->execute();

                if (($stage['overall_status'] ?? '') === 'Approved') {
                    $reimbursementError = service_claim_create_reimbursement_after_approval($obconn, $newClaimId);
                    if ($reimbursementError !== null) {
                        throw new PDOException($reimbursementError);
                    }
                }

                if (($stage['notify_level'] ?? '') === 'l2') {
                    warranty_claims_notify_user(
                        $obconn,
                        $approvers['l2'],
                        'service-claims',
                        'New Service Claim Pending L2 Approval',
                        'Service claim #' . $newClaimId . ' for call ticket #' . $complaintId . ' needs Business Head approval.',
                        $newClaimId
                    );
                    $_SESSION['success_message'] = 'Service claim #' . $newClaimId . ' for call ticket #' . $complaintId . ' submitted successfully. Pending L2 (Business Head) approval.';
                } elseif (($stage['notify_level'] ?? '') === 'l1') {
                    warranty_claims_notify_user(
                        $obconn,
                        $approvers['l1'],
                        'service-claims',
                        'New Service Claim Pending L1 Approval',
                        'Service claim #' . $newClaimId . ' for call ticket #' . $complaintId . ' needs Lock-in Engineer approval.',
                        $newClaimId
                    );
                    $_SESSION['success_message'] = 'Service claim #' . $newClaimId . ' for call ticket #' . $complaintId . ' submitted successfully. Pending L1 (Lock-in Engineer) approval.';
                } else {
                    if (($stage['overall_status'] ?? '') === 'Approved') {
                        service_claim_notify_invoice_pending($obconn, $newClaimId);
                    }
                    $_SESSION['success_message'] = 'Service claim #' . $newClaimId . ' for call ticket #' . $complaintId . ' submitted successfully.';
                }
                header('Location: service_claims.php');
                exit;
            } catch (PDOException $e) {
                if ($storedPo !== null) {
                    service_claim_po_delete_file($storedPo['stored'] ?? '');
                }
                $error_message = 'Failed to submit call closure. Please try again.';
            } catch (RuntimeException $e) {
                if ($storedPo !== null) {
                    service_claim_po_delete_file($storedPo['stored'] ?? '');
                }
                $field_errors['po_attachment'] = $e->getMessage();
                $error_message = $field_errors['po_attachment'];
            }
            }
        }
    }
}

// --- Handle CCS warranty marking (Process 2, steps 3-4) ----------------------
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

    $requesterUserId = warranty_claims_user_id_by_username(
        $obconn,
        (string) ($claim['created_by_username'] ?? '')
    );
    $approvers = foc_claim_resolve_approver_ids($obconn, $requesterUserId);
    if ($approvers['error'] !== null) {
        $_SESSION['error_message'] = $approvers['error'];
        header('Location: service_claims.php');
        exit;
    }
    $stage = foc_claim_initial_stage($approvers);

    $update = $obconn->prepare("
        UPDATE service_claims
        SET ccs_warranty_claim = :warranty_claim, ccs_remarks = :remarks,
            ccs_marked_by_username = :by, ccs_marked_at = CURRENT_TIMESTAMP,
            l1_status = :l1_status, l2_status = :l2_status,
            l1_approver_user_id = :l1_approver_user_id,
            l2_approver_user_id = :l2_approver_user_id,
            overall_status = :overall_status, updated_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $update->bindValue(':warranty_claim', $warrantyClaim);
    $update->bindValue(':remarks', $ccsRemarks !== '' ? $ccsRemarks : null);
    $update->bindValue(':by', $userName);
    $update->bindValue(':l1_status', $stage['l1_status']);
    $update->bindValue(':l2_status', $stage['l2_status']);
    foc_claim_bind_nullable_user_id($update, ':l1_approver_user_id', $approvers['l1'] ?? null);
    foc_claim_bind_nullable_user_id($update, ':l2_approver_user_id', $approvers['l2'] ?? null);
    $update->bindValue(':overall_status', $stage['overall_status']);
    $update->bindValue(':id', $claimId, PDO::PARAM_INT);
    $update->execute();

    if (($stage['notify_level'] ?? '') === 'l2') {
        warranty_claims_notify_user(
            $obconn,
            $approvers['l2'],
            'service-claims',
            'Service Claim Pending L2 Approval',
            'Service claim #' . $claimId . ' has been marked "' . $warrantyClaim . '" by CCS and needs Business Head approval.',
            $claimId
        );
        $_SESSION['success_message'] = 'Warranty eligibility recorded. Claim moved to L2 (Business Head) approval.';
    } elseif (($stage['notify_level'] ?? '') === 'l1') {
        warranty_claims_notify_user(
            $obconn,
            $approvers['l1'],
            'service-claims',
            'Service Claim Pending L1 Approval',
            'Service claim #' . $claimId . ' has been marked "' . $warrantyClaim . '" by CCS and needs Lock-in Engineer approval.',
            $claimId
        );
        $_SESSION['success_message'] = 'Warranty eligibility recorded. Claim moved to L1 (Lock-in Engineer) approval.';
    } else {
        if (($stage['overall_status'] ?? '') === 'Approved') {
            service_claim_notify_invoice_pending($obconn, $claimId);
        }
        $_SESSION['success_message'] = 'Warranty eligibility recorded.';
    }
    header('Location: service_claims.php');
    exit;
}

// --- Handle L1 (Lock-in Engineer) decision (Process 2, step 5-6) -------------
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

    if ($claim === false || !foc_claim_is_assigned_to_current_user($obconn, $claim, 'l1')) {
        header('Location: access_denied.php');
        exit;
    }

    $applyError = service_claim_apply_decision($obconn, $claimId, 'l1', $decision, $remarks, $userName);
    if ($applyError !== null) {
        $_SESSION['error_message'] = $applyError;
        header('Location: ' . $redirectTo);
        exit;
    }

    $_SESSION['success_message'] = 'Service claim #' . $claimId . ' has been ' . strtolower($decision) . ' at L1.';
    header('Location: ' . $redirectTo);
    exit;
}

// --- Handle Dealer raising the invoice (Process 2, step 6) -------------------
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

    if ($claim === false || !in_array((string) ($claim['overall_status'] ?? ''), ['Approved', 'Approved - Pending Invoice'], true)) {
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

// --- Handle Finance settlement (Process 2, step 7) ---------------------------
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

// --- Fetch existing claims for the datatable ---------------------------------
$claims = [];
try {
    $listScope = service_claims_list_scope($obconn);
    $claimStmt = $obconn->prepare("
        SELECT
            sc.*, c.fab_number, cm.customer_name,
            COALESCE(NULLIF(TRIM(um.name), ''), NULLIF(TRIM(sc.created_by_username), ''), '-') AS created_by_name
        FROM service_claims sc
        INNER JOIN complaints c ON c.id = sc.complaint_id
        LEFT JOIN customer_masters cm
            ON cm.id = c.customer_id
           AND cm.deleted_at IS NULL
        LEFT JOIN user_master um
            ON LOWER(TRIM(um.username)) = LOWER(TRIM(sc.created_by_username))
           AND um.deleted_at IS NULL
        WHERE {$listScope['where']}
        ORDER BY sc.created_at DESC, sc.id DESC
    ");
    foreach ($listScope['params'] as $key => $value) {
        $claimStmt->bindValue($key, $value);
    }
    $claimStmt->execute();
    $claims = $claimStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table may not exist yet; silently continue
}

$installedBaseIdByFab = [];

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
                <?php if ($canCreateClaim): ?>
                <button class="new-order-btn btn-complaint-primary" id="openClaimForm" type="button">
                    <i class="bi bi-plus-lg"></i> New Call Closure
                </button>
                <?php endif; ?>
                <button class="close-form-btn cancel-btn" id="closeClaimForm" type="button">
                    <i class="bi bi-x-lg"></i> Cancel
                </button>
            </div>
        </div>

        <!-- -- Call Closure Entry Form -------------------------------------- -->
        <div class="complaint-form-card" id="claimFormCard" style="display:none;">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <div>
                        <h2 class="complaint-form-header__title">New Warranty Service Claim</h2>
                        <p class="complaint-form-header__subtitle">
                            Close the call and submit the service visit details for warranty claim review.
                        </p>
                    </div>
                </div>
            </div>

            <form method="POST" id="serviceClaimForm" enctype="multipart/form-data" novalidate>
                <div class="complaint-form-body">

                    <!-- Section 1   Call Ticket -->
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
                                <select class="form-control<?= isset($field_errors['complaint_id']) ? ' is-invalid' : '' ?>" id="complaintId" name="complaint_id">
                                    <option value="">-- Select Call Ticket --</option>
                                    <?php foreach ($recentComplaints as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>"
                                        <?= (((int) ($_POST['complaint_id'] ?? 0)) === (int) $c['id']) ? 'selected' : '' ?>>
                                        #<?= (int) $c['id'] ?> - <?= htmlspecialchars($c['fab_number']) ?> (<?= htmlspecialchars($c['customer_name']) ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="text-danger validation-msg" data-field="complaint_id"><?= htmlspecialchars($field_errors['complaint_id'] ?? '') ?></div>
                            </div>
                            <div class="col-md-2 form-group d-flex align-items-end mt-5">
                                <?php
                                    $selectedComplaintId = (int) ($_POST['complaint_id'] ?? 0);
                                    $viewTicketHref = $selectedComplaintId > 0
                                        ? 'complaint_details.php?id=' . rawurlencode(base64_encode((string) $selectedComplaintId))
                                        : '#';
                                ?>
                                <a href="<?= htmlspecialchars($viewTicketHref, ENT_QUOTES, 'UTF-8') ?>" id="viewTicketLink" target="_blank" rel="noopener"
                                    class="btn btn-outline-secondary w-100<?= $selectedComplaintId > 0 ? '' : ' disabled' ?>">
                                    <i class="bi bi-box-arrow-up-right"></i> View Ticket Details
                                </a>
                            </div>
                        </div>
                    </section>

                    <!-- Section 2   Reimbursement Claim Details -->
                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">2</span>
                            <div>
                                <h3 class="complaint-form-section__title">Reimbursement Claim Details</h3>
                                <p class="complaint-form-section__hint">These details are sent to CCS only after the claim is approved.</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4 form-group">
                                <label class="form-label" for="claimAmount">Claim Amount <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" class="form-control<?= isset($field_errors['claim_amount']) ? ' is-invalid' : '' ?>" id="claimAmount" name="claim_amount"
                                    value="<?= htmlspecialchars($_POST['claim_amount'] ?? '') ?>" required>
                                <div class="text-danger validation-msg" data-field="claim_amount"><?= htmlspecialchars($field_errors['claim_amount'] ?? '') ?></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label" for="claimInvoiceNumber">Invoice Number</label>
                                <input type="text" class="form-control" id="claimInvoiceNumber" name="claim_invoice_number"
                                    value="<?= htmlspecialchars($_POST['claim_invoice_number'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label" for="claimInvoiceDate">Invoice Date</label>
                                <input type="date" class="form-control" id="claimInvoiceDate" name="claim_invoice_date"
                                    value="<?= htmlspecialchars($_POST['claim_invoice_date'] ?? '') ?>">
                            </div>
                        </div>
                    </section>

                    <!-- Section 3   Call Closure Details -->
                    <section class="complaint-form-section">
                        <div class="complaint-form-section__head">
                            <span class="complaint-form-section__badge">3</span>
                            <div>
                                <h3 class="complaint-form-section__title">Call Closure Details</h3>
                                <p class="complaint-form-section__hint">Distance travelled is mandatory to close the call. Invoice and attachment are optional.</p>
                            </div>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-4 form-group">
                                <label class="form-label" for="kmTravelled">
                                    <i class="bi bi-signpost-split"></i> Distance Travelled (KMs) <span class="text-danger">*</span>
                                </label>
                                <input type="number" step="0.1" min="0.1" class="form-control<?= isset($field_errors['km_travelled']) ? ' is-invalid' : '' ?>" id="kmTravelled" name="km_travelled"
                                    placeholder="Auto from Service Log"
                                    value="<?= htmlspecialchars($_POST['km_travelled'] ?? '') ?>">
                                <div class="form-text" id="kmTravelledHint">Auto-filled from the Service Log for this Fab Number.</div>
                                <div class="text-danger validation-msg" data-field="km_travelled"><?= htmlspecialchars($field_errors['km_travelled'] ?? '') ?></div>
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
                                <input type="date" class="form-control<?= isset($field_errors['service_date']) ? ' is-invalid' : '' ?>" id="serviceDate" name="service_date"
                                    value="<?= htmlspecialchars($_POST['service_date'] ?? '') ?>"
                                    max="<?= date('Y-m-d') ?>">
                                <div class="text-danger validation-msg" data-field="service_date"><?= htmlspecialchars($field_errors['service_date'] ?? '') ?></div>
                            </div>
                            <div class="col-md-4 form-group">
                                <label class="form-label" for="poNumber">
                                    <i class="bi bi-receipt"></i> Invoice
                                </label>
                                <input type="text" class="form-control<?= isset($field_errors['po_number']) ? ' is-invalid' : '' ?>" id="poNumber" name="po_number"
                                    maxlength="100" placeholder="Enter Invoice"
                                    value="<?= htmlspecialchars($_POST['po_number'] ?? '') ?>">
                                <div class="text-danger validation-msg" data-field="po_number"><?= htmlspecialchars($field_errors['po_number'] ?? '') ?></div>
                            </div>
                            <div class="col-md-8 form-group">
                                <label class="form-label" for="poAttachment">
                                    <i class="bi bi-paperclip"></i> Attachment
                                </label>
                                <input type="file" class="form-control<?= isset($field_errors['po_attachment']) ? ' is-invalid' : '' ?>" id="poAttachment" name="po_attachment"
                                    accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                                <div class="form-text">PDF, JPG, PNG, DOC, DOCX. Maximum 2 MB.</div>
                                <div class="text-danger validation-msg" data-field="po_attachment"><?= htmlspecialchars($field_errors['po_attachment'] ?? '') ?></div>
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

                <div class="complaint-form-actions">
                    <button type="button" class="cancel-btn" id="cancelClaimForm">Cancel</button>
                    <button type="submit" name="submit_service_claim" class="submit-btn btn-complaint-primary">
                        <i class="bi bi-send"></i> Submit Call Closure
                    </button>
                </div>
            </form>
        </div>
        <!-- -- End Form ----------------------------------------------------- -->

        <!-- -- Claims List -------------------------------------------------- -->
        <div class="complaint-form-card show" id="claimTableCard">
            <div class="complaint-form-header">
                <div class="complaint-form-header__main">
                    <div class="complaint-form-header__icon">
                        <i class="bi bi-clipboard-check"></i>
                    </div>
                    <div>
                        <h2 class="complaint-form-header__title">Warranty Service Claims</h2>
                        <p class="complaint-form-header__subtitle">
                            Track call closures, L1 / L2 approval and visit-charge status.
                        </p>
                    </div>
                </div>
            </div>
            <div class="complaint-form-body">
                <div class="table-responsive">
                <table id="serviceClaimsTable" class="table table-hover booking-table w-100">
                    <thead>
                        <tr>
                            <th width="10%">ID</th>
                            <th width="16%">Call Ticket</th>
                            <th width="12%">Fab Number</th>
                            <th width="14%">Customer</th>
                            <th width="12%">KM</th>
                            <th width="12%">Service Date</th>
                            <th width="12%">Invoice</th>
                            <th width="10%">Attachment</th>
                            <th width="10%">Warranty</th>
                            <th width="10%">Lock-in Engineer</th>
                            <th width="10%">Business Head</th>
                            <th width="10%">Settlement</th>
                            <th width="14%">Overall Status</th>
                            <?php if ($canSeeSubmittedBy) { ?>
                            <th width="10%">Submitted By</th>
                            <?php } ?>
                            <th width="8%">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $serviceClaimAmcLookup = amc_coverage_lookup(
                            $obconn,
                            [],
                            array_map(static fn ($claimRow) => (string) ($claimRow['fab_number'] ?? ''), $claims)
                        );
                        $serviceClaimCommissioningLookup = installed_base_commissioning_lookup(
                            $obconn,
                            [],
                            array_map(static fn ($claimRow) => (string) ($claimRow['fab_number'] ?? ''), $claims)
                        );
                        foreach ($claims as $row): ?>
                        <?php
                            $claimId = (int) $row['id'];
                            $complaintId = (int) $row['complaint_id'];
                            $encodedClaimId = rawurlencode(base64_encode((string) $claimId));
                            $encodedComplaintId = rawurlencode(base64_encode((string) $complaintId));
                            $serviceDate = trim((string) ($row['service_date'] ?? ''));
                            $serviceDateLabel = $serviceDate !== '' ? date('d M Y', strtotime($serviceDate)) : '-';
                            $poNumberLabel = trim((string) ($row['po_number'] ?? ''));
                            $poAttachmentHtml = service_claim_po_attachment_html(
                                (string) ($row['po_attachment'] ?? ''),
                                (string) ($row['po_attachment_original'] ?? '')
                            );
                            $kmLabel = distance_wise_price_format_number($row['km_travelled'] ?? '');
                            $priceValue = $row['visit_charge_price'] ?? '';
                            $priceLabel = ($priceValue === null || $priceValue === '')
                                ? ''
                                : distance_wise_price_format_number($priceValue);
                            $serviceClaimCoverage = amc_coverage_resolve($serviceClaimAmcLookup, 0, (string) ($row['fab_number'] ?? ''));
                            $serviceClaimCommissioningDate = installed_base_commissioning_resolve(
                                $serviceClaimCommissioningLookup,
                                0,
                                (string) ($row['fab_number'] ?? '')
                            );
                            $warrantyStatus = service_claim_warranty_status_label($row, $serviceClaimCommissioningDate);
                            $overallStatus = service_claim_overall_status_label($row);
                        ?>
                        <tr>
                            <td data-order="<?= $claimId ?>">#<?= $claimId ?></td>
                            <td>
                                <a href="complaint_details.php?id=<?= htmlspecialchars($encodedComplaintId, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="text-primary fw-semibold text-decoration-none">
                                    #<?= $complaintId ?>
                                </a>
                            </td>
                            <td><?= amc_with_coverage_html(installed_base_fab_link_html($obconn, (string) ($row['fab_number'] ?? ''), $installedBaseIdByFab), $serviceClaimCoverage, $serviceClaimCommissioningDate) ?></td>
                            <td><?= htmlspecialchars((string) ($row['customer_name'] ?? '-')) ?></td>
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($kmLabel === '-' ? '' : $kmLabel) ?><?= $kmLabel !== '-' ? ' KM' : '-' ?></div>
                                <?php /* if ($priceLabel !== ''): ?>
                                <div class="text-muted small"><?=  '&#8377;'.htmlspecialchars($priceLabel) ?></div>
                                <?php endif; */ ?>
                            </td>
                            <td><?= htmlspecialchars($serviceDateLabel) ?></td>
                            <td>
                                <?php if ($poNumberLabel !== ''): ?>
                                    <?= htmlspecialchars($poNumberLabel) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= $poAttachmentHtml !== '' ? $poAttachmentHtml : '-' ?>
                            </td>
                            <td>
                                <span class="status-badge border border-dark">
                                    <?= htmlspecialchars($warrantyStatus) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge border border-dark">
                                    <?= htmlspecialchars((string) ($row['l1_status'] ?? '-')) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge border border-dark">
                                    <?= htmlspecialchars((string) ($row['l2_status'] ?? '-')) ?>
                                </span>
                            </td>
                            <td>
                               -
                            </td>
                            <td>
                                <span class="status-badge border border-dark">
                                    <?= htmlspecialchars($overallStatus !== '' ? $overallStatus : '-') ?>
                                </span>
                            </td>
                            <?php if ($canSeeSubmittedBy) { ?>
                            <td><?= htmlspecialchars((string) ($row['created_by_name'] ?? $row['created_by_username'] ?? '-')) ?></td>
                            <?php } ?>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="service_claim_details.php?id=<?= htmlspecialchars($encodedClaimId, ENT_QUOTES, 'UTF-8') ?>"
                                        class="btn btn-sm btn-outline-dark" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <?php if ($canDeleteClaim): ?>
                                    <a href="delete_service_claim.php?id=<?= htmlspecialchars($encodedClaimId, ENT_QUOTES, 'UTF-8') ?>"
                                        class="btn btn-sm btn-outline-dark"
                                        onclick="return confirm('Delete this service claim?');" title="Delete">
                                        <i class="bi bi-trash"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <!-- -- End Claims List --------------------------------------------- -->

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
        if (closeBtn) closeBtn.classList.add('show');
        formCard.scrollIntoView({ behavior: 'smooth' });
    }

    function hideForm() {
        if (!formCard) return;
        formCard.style.display = 'none';
        tableCard.style.display = 'block';
        if (openBtn) openBtn.style.display  = '';
        if (closeBtn) closeBtn.classList.remove('show');
    }

    if (openBtn) openBtn.addEventListener('click', showForm);
    if (closeBtn) closeBtn.addEventListener('click', hideForm);
    if (cancelBtn) cancelBtn.addEventListener('click', hideForm);

    function complaintDetailsUrl(id) {
        return 'complaint_details.php?id=' + encodeURIComponent(btoa(String(id)));
    }

    function syncViewTicketLink() {
        if (!viewTicketLink || !complaintSelect) {
            return;
        }
        const id = String(complaintSelect.value || '').trim();
        if (id) {
            viewTicketLink.href = complaintDetailsUrl(id);
            viewTicketLink.classList.remove('disabled');
            viewTicketLink.setAttribute('aria-disabled', 'false');
        } else {
            viewTicketLink.href = '#';
            viewTicketLink.classList.add('disabled');
            viewTicketLink.setAttribute('aria-disabled', 'true');
        }
    }

    if (complaintSelect && viewTicketLink) {
        syncViewTicketLink();
        complaintSelect.addEventListener('change', syncViewTicketLink);
        if (typeof $ !== 'undefined') {
            $(complaintSelect).on('change select2:select select2:clear', syncViewTicketLink);
        }
        viewTicketLink.addEventListener('click', function (e) {
            const id = String(complaintSelect.value || '').trim();
            if (!id) {
                e.preventDefault();
                return;
            }
            this.href = complaintDetailsUrl(id);
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
            pageLength: 10,
            columnDefs: [
                { orderable: false, targets: -1 }
            ],
            language: { emptyTable: 'No warranty service claims submitted yet.' }
        });
    }

    const claimForm = document.getElementById('serviceClaimForm');
    const claimAmountInput = document.getElementById('claimAmount');
    const kmInput = document.getElementById('kmTravelled');
    const serviceDateInput = document.getElementById('serviceDate');
    const poNumberInput = document.getElementById('poNumber');
    const poAttachmentInput = document.getElementById('poAttachment');
    const poAllowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
    const poMaxFileSize = 2 * 1024 * 1024;

    function setFieldError(field, message) {
        const msg = document.querySelector('.validation-msg[data-field="' + field + '"]');
        if (msg) {
            msg.textContent = message || '';
        }
    }

    function clearFieldError(field, input) {
        setFieldError(field, '');
        if (input) {
            input.classList.remove('is-invalid');
        }
    }

    if (complaintSelect) {
        complaintSelect.addEventListener('change', function () {
            clearFieldError('complaint_id', complaintSelect);
        });
        if (typeof $ !== 'undefined') {
            $(complaintSelect).on('change select2:select select2:clear', function () {
                clearFieldError('complaint_id', complaintSelect);
            });
        }
    }
    if (claimAmountInput) {
        claimAmountInput.addEventListener('input', function () {
            clearFieldError('claim_amount', claimAmountInput);
        });
        claimAmountInput.addEventListener('change', function () {
            clearFieldError('claim_amount', claimAmountInput);
        });
    }
    if (kmInput) {
        kmInput.addEventListener('input', function () {
            clearFieldError('km_travelled', kmInput);
        });
        kmInput.addEventListener('change', function () {
            clearFieldError('km_travelled', kmInput);
        });
    }
    if (serviceDateInput) {
        serviceDateInput.addEventListener('change', function () {
            clearFieldError('service_date', serviceDateInput);
        });
        serviceDateInput.addEventListener('input', function () {
            clearFieldError('service_date', serviceDateInput);
        });
    }
    if (poNumberInput) {
        poNumberInput.addEventListener('input', function () {
            clearFieldError('po_number', poNumberInput);
        });
    }
    if (poAttachmentInput) {
        poAttachmentInput.addEventListener('change', function () {
            clearFieldError('po_attachment', poAttachmentInput);
        });
    }

    if (claimForm) {
        claimForm.addEventListener('submit', function (e) {
            let blocked = false;
            let firstInvalid = null;

            if (complaintSelect && String(complaintSelect.value || '').trim() === '') {
                e.preventDefault();
                blocked = true;
                setFieldError('complaint_id', 'Please select a Call Ticket Number.');
                complaintSelect.classList.add('is-invalid');
                firstInvalid = firstInvalid || complaintSelect;
            }

            const claimAmountValue = claimAmountInput ? String(claimAmountInput.value || '').trim() : '';
            const claimAmountNumber = parseFloat(claimAmountValue);
            if (claimAmountValue === '' || isNaN(claimAmountNumber) || !isFinite(claimAmountNumber) || claimAmountNumber < 0) {
                e.preventDefault();
                blocked = true;
                setFieldError('claim_amount', 'Claim Amount is required and must be a valid non-negative amount.');
                if (claimAmountInput) {
                    claimAmountInput.classList.add('is-invalid');
                    firstInvalid = firstInvalid || claimAmountInput;
                }
            }

            const kmValue = kmInput ? String(kmInput.value || '').trim() : '';
            const kmNumber = parseFloat(kmValue);
            if (!kmValue || isNaN(kmNumber) || kmNumber <= 0) {
                e.preventDefault();
                blocked = true;
                setFieldError('km_travelled', 'Distance Travelled (KMs) is required and must be greater than zero.');
                if (kmInput) {
                    kmInput.classList.add('is-invalid');
                    firstInvalid = firstInvalid || kmInput;
                }
            }

            if (serviceDateInput && String(serviceDateInput.value || '').trim() === '') {
                e.preventDefault();
                blocked = true;
                setFieldError('service_date', 'Service Date is required.');
                serviceDateInput.classList.add('is-invalid');
                firstInvalid = firstInvalid || serviceDateInput;
            }

            const poValue = poNumberInput ? String(poNumberInput.value || '').trim() : '';
            if (poValue.length > 100) {
                e.preventDefault();
                blocked = true;
                setFieldError('po_number', 'Invoice cannot exceed 100 characters.');
                if (poNumberInput) {
                    poNumberInput.classList.add('is-invalid');
                    firstInvalid = firstInvalid || poNumberInput;
                }
            }

            const poFile = poAttachmentInput && poAttachmentInput.files && poAttachmentInput.files[0]
                ? poAttachmentInput.files[0]
                : null;
            if (poFile) {
                const poName = String(poFile.name || '');
                const poExt = poName.includes('.') ? poName.split('.').pop().toLowerCase() : '';
                if (poAllowedExtensions.indexOf(poExt) === -1) {
                    e.preventDefault();
                    blocked = true;
                    setFieldError('po_attachment', 'Invalid attachment type. Allowed: PDF, JPG, PNG, DOC, DOCX.');
                    poAttachmentInput.classList.add('is-invalid');
                    firstInvalid = firstInvalid || poAttachmentInput;
                } else if (poFile.size > poMaxFileSize) {
                    e.preventDefault();
                    blocked = true;
                    setFieldError('po_attachment', 'Attachment must be 2 MB or smaller.');
                    poAttachmentInput.classList.add('is-invalid');
                    firstInvalid = firstInvalid || poAttachmentInput;
                }
            }

            if (blocked && firstInvalid && typeof firstInvalid.focus === 'function') {
                firstInvalid.focus();
            }
        });
    }
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

    const kmHint = document.getElementById('kmTravelledHint');
    const hasPostedKm = !!(kmInput && String(kmInput.value || '').trim() !== '');

    function setKmTravelledLocked(locked) {
        if (!kmInput) {
            return;
        }
        kmInput.readOnly = !!locked;
        kmInput.style.backgroundColor = locked ? '#f8f9fa' : '';
        if (kmHint) {
            kmHint.textContent = locked
                ? 'Auto-filled from the Service Log for this Fab Number.'
                : 'Select a call ticket to auto-fill from the Service Log, or enter KM manually.';
        }
    }

    function applyServiceClaimDistance(data) {
        if (!kmInput) {
            return;
        }
        const km = data && data.found ? String(data.km_travelled || '').trim() : '';
        if (km !== '' && !isNaN(parseFloat(km)) && parseFloat(km) > 0) {
            kmInput.value = km;
            setKmTravelledLocked(true);
            clearFieldError('km_travelled', kmInput);
            updateVisitChargePrice();
            return;
        }
        kmInput.value = '';
        setKmTravelledLocked(false);
        updateVisitChargePrice();
    }

    function loadServiceClaimDistanceFromServiceLog(complaintId, overwrite) {
        const id = String(complaintId || '').trim();
        if (!overwrite && kmInput && String(kmInput.value || '').trim() !== '') {
            setKmTravelledLocked(true);
            updateVisitChargePrice();
            return;
        }
        if (id === '' || !/^\d+$/.test(id)) {
            applyServiceClaimDistance(null);
            return;
        }
        fetch('api/service_claim_distance_prefill.php?complaint_id=' + encodeURIComponent(id), {
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (response) {
                return response.json().then(function (payload) {
                    return { ok: response.ok, payload: payload };
                });
            })
            .then(function (result) {
                if (!result.ok || !result.payload || result.payload.success === false) {
                    applyServiceClaimDistance(null);
                    return;
                }
                applyServiceClaimDistance(result.payload);
            })
            .catch(function () {
                applyServiceClaimDistance(null);
            });
    }

    if (complaintSelect) {
        const onComplaintChange = function () {
            loadServiceClaimDistanceFromServiceLog(complaintSelect.value, true);
        };
        complaintSelect.addEventListener('change', onComplaintChange);
        if (typeof $ !== 'undefined') {
            $(complaintSelect).on('change select2:select select2:clear', onComplaintChange);
        }
        if (String(complaintSelect.value || '').trim() !== '') {
            loadServiceClaimDistanceFromServiceLog(complaintSelect.value, !hasPostedKm);
        } else {
            setKmTravelledLocked(false);
        }
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